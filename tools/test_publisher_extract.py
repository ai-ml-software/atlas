"""Real extraction regression over the committed fixtures (see tools/publisher_fixtures.py):
embedded, scanned, mixed, Arabic, malformed, encrypted and missing OCR."""
import importlib.util
import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
import fitz

HERE = Path(__file__).resolve().parent
FIXTURES = HERE.parent / 'application' / 'tests' / 'fixtures' / 'publisher'
TESSDATA = HERE.parent / 'application' / 'storage' / 'private' / 'ocr' / 'tessdata'
TESSERACT = os.environ.get('ALTUS_TESSERACT') or ('C:/Program Files/Tesseract-OCR/tesseract.exe' if os.path.isfile('C:/Program Files/Tesseract-OCR/tesseract.exe') else None)
OCR = {'ALTUS_TESSERACT': TESSERACT or '', 'ALTUS_TESSDATA': str(TESSDATA)}

spec = importlib.util.spec_from_file_location('extractor', HERE / 'publisher_extract.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
needs_ocr = unittest.skipUnless(TESSERACT and (TESSDATA / 'ara.traineddata').is_file(), 'Tesseract executable or eng/ara tessdata unavailable')


def fixture(name):
    return str(FIXTURES / name)


class PdfExtraction(unittest.TestCase):
    def setUp(self):
        self.folder = tempfile.TemporaryDirectory(prefix='altus-pdf-test-')

    def tearDown(self):
        self.folder.cleanup()

    def test_fixtures_exist(self):
        for name in ('text.pdf', 'scanned.pdf', 'mixed.pdf', 'arabic.pdf', 'arabic-scanned.pdf', 'malformed.pdf'):
            self.assertTrue((FIXTURES / name).is_file(), name + ' missing: run tools/publisher_fixtures.py')

    def test_embedded_text_has_page_markers_and_provenance(self):
        result = module.extract(fixture('text.pdf'))
        self.assertIn('[Page 1]\nAlways wear PPE', result['text'])
        self.assertIn('[Page 2]\nReport every chemical spill', result['text'])
        self.assertEqual([p['method'] for p in result['provenance']], ['text', 'text'])
        self.assertEqual([p['page'] for p in result['provenance']], [1, 2])
        self.assertFalse(any(p['review_required'] for p in result['provenance']))
        self.assertTrue(all(p['characters'] > 30 for p in result['provenance']))

    @needs_ocr
    def test_scanned_page_uses_ocr_and_requires_review(self):
        with patch.dict(os.environ, OCR):
            result = module.extract(fixture('scanned.pdf'))
        self.assertIn('PPE', result['text'])
        self.assertEqual(result['provenance'][0]['method'], 'ocr')
        self.assertTrue(result['provenance'][0]['review_required'])

    @needs_ocr
    def test_mixed_document_reports_method_per_page(self):
        with patch.dict(os.environ, OCR):
            result = module.extract(fixture('mixed.pdf'))
        self.assertEqual([p['method'] for p in result['provenance']], ['text', 'ocr'])
        self.assertIn('[Page 2]', result['text'])

    def test_embedded_arabic_is_normalised_and_flagged_for_review(self):
        result = module.extract(fixture('arabic.pdf'))
        body = result['text'].split('\n', 1)[1]
        self.assertFalse(any('\uFB50' <= ch <= '\uFEFF' for ch in body), 'presentation forms must be normalised')
        self.assertGreater(sum('\u0600' <= ch <= '\u06FF' for ch in body), 30)
        self.assertTrue(result['provenance'][0]['review_required'])

    @needs_ocr
    def test_scanned_arabic_uses_ara_language_data(self):
        with patch.dict(os.environ, OCR):
            result = module.extract(fixture('arabic-scanned.pdf'))
        self.assertIn('الفندق', result['text'])
        self.assertEqual(result['provenance'][0]['method'], 'ocr')

    def test_malformed_pdf_is_actionable(self):
        with self.assertRaisesRegex(ValueError, 'damaged or not readable'):
            module.extract(fixture('malformed.pdf'))

    def test_missing_ocr_dependency_is_actionable(self):
        with patch.dict(os.environ, {'ALTUS_TESSERACT': 'nonexistent-altus-tesseract'}):
            with self.assertRaisesRegex(ValueError, 'Tesseract OCR was not found'):
                module.extract(fixture('scanned.pdf'))

    def test_missing_ocr_on_path_is_actionable(self):
        with patch.dict(os.environ, {'ALTUS_TESSERACT': ''}), patch.object(module.shutil, 'which', return_value=None):
            with self.assertRaisesRegex(ValueError, 'needs Tesseract OCR'):
                module.extract(fixture('scanned.pdf'))

    def test_encrypted_pdf_is_refused(self):
        source = fitz.open(fixture('text.pdf')); target = os.path.join(self.folder.name, 'locked.pdf')
        source.save(target, encryption=fitz.PDF_ENCRYPT_AES_256, owner_pw='owner', user_pw='secret'); source.close()
        with self.assertRaisesRegex(ValueError, 'Password protected'):
            module.extract(target)

    def test_cli_progress_and_health_are_ndjson(self):
        run = subprocess.run([sys.executable, str(HERE / 'publisher_extract.py'), fixture('text.pdf'), '--progress'], capture_output=True, text=True, timeout=60)
        lines = [json.loads(line) for line in run.stdout.splitlines() if line.strip()]
        self.assertEqual(lines[-1]['ok'], True)
        self.assertEqual([l['progress'] for l in lines[:-1]], [50, 100])
        health = subprocess.run([sys.executable, str(HERE / 'publisher_extract.py'), '--health'], capture_output=True, text=True, timeout=60, env={**os.environ, **OCR})
        report = json.loads(health.stdout.strip().splitlines()[-1])
        self.assertTrue(report['ok']); self.assertTrue(report['pypdf'])
        if TESSERACT:
            self.assertIn('ara', report['languages']); self.assertIn('eng', report['languages'])


if __name__ == '__main__':
    unittest.main()
