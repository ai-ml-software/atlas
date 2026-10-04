"""Bounded PDF extraction with local English/Arabic OCR and NDJSON progress.

Usage:
  publisher_extract.py FILE [--progress]   extract text with per-page provenance
  publisher_extract.py --health            report dependency status as JSON

Tesseract and its language data come from ALTUS_TESSERACT / ALTUS_TESSDATA, which the
PHP worker sets from application/config/ha_publisher.php. Without ALTUS_TESSERACT the
executable is looked up on PATH.
"""
import json, os, re, shutil, subprocess, sys, tempfile, unicodedata

MAX_PAGES = 150
MAX_CHARS = 120000
MIN_PAGE_CHARS = 30


def emit(value):
    print(json.dumps(value, ensure_ascii=True), flush=True)


PRESENTATION = re.compile('[\uFB50-\uFDFF\uFE70-\uFEFF]+')


def normalize(text):
    """Maps Arabic presentation forms (common in shaped PDF text layers) to base letters."""
    return PRESENTATION.sub(lambda m: unicodedata.normalize('NFKC', m.group(0)), text)


def load_fitz():
    try:
        import pymupdf as fitz
    except ImportError:
        import fitz
    return fitz


def tesseract_path():
    configured = os.environ.get('ALTUS_TESSERACT')
    if configured:
        return configured
    return shutil.which('tesseract')


def tessdata_dir():
    folder = os.environ.get('ALTUS_TESSDATA')
    return folder if folder and os.path.isdir(folder) else None


def ocr(image, page_number, tess):
    command = [tess, image, 'stdout', '-l', 'eng+ara', '--psm', '3']
    data = tessdata_dir()
    if data:
        command += ['--tessdata-dir', data]
    try:
        run = subprocess.run(command, capture_output=True, timeout=60, encoding='utf-8', errors='replace')
    except FileNotFoundError as exc:
        raise ValueError('Tesseract OCR was not found at "%s". Install Tesseract and set tesseract in config/ha_publisher.php (or ALTUS_TESSERACT).' % tess) from exc
    except subprocess.TimeoutExpired as exc:
        raise ValueError('OCR timed out on page %d. Split the document or improve scan quality.' % page_number) from exc
    if run.returncode:
        raise ValueError('OCR failed on page %d. Verify eng and ara language data in the configured tessdata folder: %s' % (page_number, run.stderr[:500]))
    return run.stdout


def extract(path, progress=False):
    from pypdf import PdfReader
    from pypdf.errors import DependencyError, PdfReadError
    try:
        reader = PdfReader(path)
        if reader.is_encrypted:
            raise ValueError('Password protected PDFs are not supported. Export an unlocked copy.')
        count = len(reader.pages)
    except DependencyError as exc:
        raise ValueError('Password protected PDFs are not supported. Export an unlocked copy.') from exc
    except (PdfReadError, OSError, KeyError, TypeError) as exc:
        raise ValueError('The PDF is damaged or not readable. Re-export it and upload again.') from exc
    if not 1 <= count <= MAX_PAGES:
        raise ValueError('Use a PDF with 1-%d pages.' % MAX_PAGES)
    pages, provenance, total, rendered = [], [], 0, None
    tess = tesseract_path()
    try:
        for index, page in enumerate(reader.pages):
            try:
                raw = page.extract_text() or ''
                shaped = bool(PRESENTATION.search(raw))
                text = normalize(raw)
            except Exception:
                text, shaped = '', False
            method = 'text'
            if len(text.strip()) < MIN_PAGE_CHARS:
                if not tess:
                    raise ValueError('Scanned page %d needs Tesseract OCR. Install Tesseract with eng and ara language data and set tesseract in config/ha_publisher.php.' % (index + 1))
                fitz = load_fitz()
                if rendered is None:
                    rendered = fitz.open(path)
                p = rendered[index]
                if p.rect.width * p.rect.height > 4000000:
                    raise ValueError('PDF page dimensions exceed the rendering limit.')
                with tempfile.TemporaryDirectory(prefix='altus-ocr-') as folder:
                    image = os.path.join(folder, 'page.png')
                    p.get_pixmap(matrix=fitz.Matrix(2, 2)).save(image)
                    text, method = ocr(image, index + 1, tess), 'ocr'
            marked = '[Page %d]\n%s' % (index + 1, text.strip())
            total += len(marked) + 2
            if total > MAX_CHARS:
                raise ValueError('Split the document into smaller files (120,000 characters maximum).')
            pages.append(marked)
            chars = len(text.strip())
            provenance.append({'page': index + 1, 'method': method, 'characters': chars,
                               'review_required': method == 'ocr' or shaped or chars < MIN_PAGE_CHARS})
            if progress:
                emit({'progress': round((index + 1) * 100 / count), 'page': provenance[-1]})
        if sum(p['characters'] for p in provenance) < MIN_PAGE_CHARS:
            raise ValueError('The document contains too little readable text. Review the scan quality.')
        return {'ok': True, 'text': '\n\n'.join(pages), 'pages': len(pages), 'provenance': provenance}
    finally:
        if rendered is not None:
            rendered.close()


def health():
    report = {'ok': True, 'python': sys.version.split()[0], 'executable': sys.executable}
    try:
        import pypdf
        report['pypdf'] = pypdf.__version__
    except Exception as exc:
        report['pypdf'] = None
        report['pypdf_error'] = str(exc)
    try:
        fitz = load_fitz()
        report['pymupdf'] = getattr(fitz, 'VersionBind', None) or getattr(fitz, '__version__', 'installed')
    except Exception as exc:
        report['pymupdf'] = None
        report['pymupdf_error'] = str(exc)
    tess = tesseract_path()
    report['tesseract'] = tess
    report['tesseract_version'] = None
    report['languages'] = []
    if tess:
        try:
            run = subprocess.run([tess, '--version'], capture_output=True, timeout=15, encoding='utf-8', errors='replace')
            report['tesseract_version'] = (run.stdout or run.stderr).splitlines()[0] if (run.stdout or run.stderr) else None
            command = [tess, '--list-langs']
            if tessdata_dir():
                command += ['--tessdata-dir', tessdata_dir()]
            run = subprocess.run(command, capture_output=True, timeout=15, encoding='utf-8', errors='replace')
            report['languages'] = [line.strip() for line in run.stdout.splitlines()[1:] if line.strip()]
        except (OSError, subprocess.TimeoutExpired) as exc:
            report['tesseract_error'] = str(exc)
    report['tessdata'] = tessdata_dir()
    return report


if __name__ == '__main__':
    if sys.argv[1:2] == ['--health']:
        emit(health())
        sys.exit(0)
    try:
        if len(sys.argv) < 2:
            raise ValueError('Pass a PDF path.')
        emit(extract(sys.argv[1], '--progress' in sys.argv[2:]))
    except ImportError as exc:
        emit({'ok': False, 'error': 'Python dependency missing (%s). Run: python -m pip install -r tools/publisher-requirements.txt' % exc.name})
        sys.exit(1)
    except Exception as exc:
        emit({'ok': False, 'error': str(exc)})
        sys.exit(1)
