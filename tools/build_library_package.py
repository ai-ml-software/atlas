"""Build explicit workflow/source delivery and a separate private reviewer bundle.

Never packages machine-specific config, SQL, active language configuration,
unrelated draft seeds or generated learner/test uploads.
"""
from pathlib import Path
import argparse
import hashlib
import json
import subprocess
import zipfile
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
SUPPORT = ROOT / 'application/seeds/library_support'


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def inventory(files, kind):
    return {
        'version': 1,
        'kind': kind,
        'generated_at': datetime.now(timezone.utc).isoformat(),
        'baseline_commit': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
        'status': 'Implementation delivered; source, video and language review incomplete; no revised release approved',
        'files': [{'path': name, 'sha256': sha(path), 'bytes': path.stat().st_size} for name, path in sorted(files.items())],
    }


def archive(output, files, kind):
    output = Path(output).resolve()
    if output.exists():
        raise RuntimeError('Choose a new output path; existing packages are never overwritten: ' + str(output))
    output.parent.mkdir(parents=True, exist_ok=True)
    package = inventory(files, kind)
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as z:
        for name, path in sorted(files.items()):
            z.write(path, name)
        z.writestr('PACKAGE-MANIFEST.json', json.dumps(package, ensure_ascii=False, indent=2))
    # Verify archive integrity and all manifest hashes before delivery.
    with zipfile.ZipFile(output) as z:
        bad = z.testzip()
        if bad:
            raise RuntimeError('Archive integrity failed: ' + bad)
        for entry in package['files']:
            if hashlib.sha256(z.read(entry['path'])).hexdigest() != entry['sha256']:
                raise RuntimeError('Packaged checksum mismatch: ' + entry['path'])
    checksum = sha(output)
    output.with_suffix(output.suffix + '.sha256').write_text(checksum + '  ' + output.name + '\n', encoding='utf-8')
    print(json.dumps({'package': str(output), 'files': len(files), 'bytes': output.stat().st_size, 'sha256': checksum}))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', required=True)
    parser.add_argument('--reports', default='backups/library-review')
    parser.add_argument('--review-output')
    args = parser.parse_args()
    listed = json.loads((ROOT / 'tools/library_package_files.json').read_text(encoding='utf-8'))
    manifest = json.loads((SUPPORT / 'manifest.json').read_text(encoding='utf-8'))
    if manifest['file_count'] != 41 or manifest['page_count'] != 368 or manifest['course_count'] != 40:
        raise RuntimeError('Unexpected source inventory; review the delivery scope explicitly')
    files = {name: ROOT / name for name in listed['files']}
    for name in ('manifest.json', 'languages.json', 'iso-639-3.tab', 'ocr_drafts.json'):
        path = SUPPORT / name
        files[path.relative_to(ROOT).as_posix()] = path
    for source in manifest['sources']:
        path = ROOT / source['path']
        if not path.is_file() or sha(path) != source['sha256']:
            raise RuntimeError('Source checksum failed: ' + source['filename'])
        files[source['path']] = path
        course = 'application/seeds/library/' + source['course_code'][3:] + '.json'
        files[course] = ROOT / course
    for name, path in files.items():
        if not path.is_file():
            raise RuntimeError('Missing package file: ' + name)
        if name.endswith('.sql') or name.startswith('application/config/database') or path.name.startswith('.env'):
            raise RuntimeError('Private file refused: ' + name)
    reports = Path(args.reports).resolve()
    for name in ('coverage-en.json', 'coverage-ar.json', 'browser-smoke.json', 'validation.json'):
        path = reports / name
        if path.is_file():
            files['delivery-reports/' + name] = path
    archive(args.output, files, 'workflow-and-original-sources')
    if args.review_output:
        review = {}
        for name in ('source-review-template.json', 'translations-ar.json', 'video-candidates.json', 'coverage-en.json', 'coverage-ar.json', 'browser-smoke.json', 'validation.json'):
            path = reports / name
            if not path.is_file():
                raise RuntimeError('Missing reviewer file: ' + name)
            review['review/' + name] = path
        for path in reports.glob('*.jpg'):
            review['backups/library-review/' + path.name] = path
        for path in (reports / 'pages').rglob('*'):
            if path.is_file() and path.suffix.lower() in ('.png', '.txt', '.tsv', '.json'):
                review['backups/library-review/pages/' + path.relative_to(reports / 'pages').as_posix()] = path
        for name in ('library-workflow.md', 'library-review.md', 'library-deployment.md'):
            review['docs/' + name] = ROOT / 'docs' / name
        archive(args.review_output, review, 'private-unreviewed-editorial-material-do-not-deploy-to-web-root')


if __name__ == '__main__':
    main()
