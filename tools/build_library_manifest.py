"""Inventory every source PDF without asserting that extraction is a review.

Run: python tools/build_library_manifest.py [--render] [--refresh-languages]
Rendered contact sheets and extracted text are review aids, not approval records.
"""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
import csv
import hashlib
import io
import json
from pathlib import Path
import re
import urllib.request

import pymupdf

ROOT = Path(__file__).resolve().parents[1]
PDF_DIR = ROOT / 'pdf-20260925T132712Z-1-001/pdf'
DATA = ROOT / 'application/seeds/library_support'
ISO_URL = 'https://iso639-3.sil.org/sites/iso639-3/files/downloads/iso-639-3.tab'


def digest(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def write_json(path: Path, value) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def slug(name: str) -> str:
    return re.sub(r' \(\d+\)$', '', name.replace('-Dyafa', '')).lower()


def build(render=False):
    DATA.mkdir(parents=True, exist_ok=True)
    prior_file = DATA / 'manifest.json'
    prior = json.loads(prior_file.read_text(encoding='utf-8')) if prior_file.exists() else {}
    old = {x['sha256']: x for x in prior.get('sources', [])}
    sources, visuals = [], {}
    for path in sorted(PDF_DIR.glob('*.pdf')):
        doc = pymupdf.open(path)
        sha = digest(path.read_bytes())
        visual = digest(b''.join(bytes.fromhex(digest(p.get_pixmap(matrix=pymupdf.Matrix(1, 1)).samples)) for p in doc))
        duplicate = visuals.get(visual)
        visuals.setdefault(visual, sha)
        pages = []
        for i, page in enumerate(doc):
            text = page.get_text(sort=True).strip()
            pages.append({'page': i + 1, 'key': f'{sha}:p{i+1}', 'kind': 'cover' if i == 0 else ('closing' if i == len(doc)-1 else 'instruction'),
                          'extracted_text': text, 'text_sha256': digest(text.encode()),
                          'needs_visual_review': True, 'contains_images': bool(page.get_images()),
                          'review_status': 'pending', 'points': []})
        entry = {'filename': path.name, 'path': path.relative_to(ROOT).as_posix(), 'course_code': 'dy-' + slug(path.stem),
                 'sha256': sha, 'visual_sha256': visual, 'page_count': len(doc), 'duplicate_of': duplicate, 'pages': pages}
        if sha in old:
            # A rebuild never loses completed reviews for the same file and page text.
            for p, prev in zip(pages, old[sha]['pages']):
                if p['text_sha256'] == prev.get('text_sha256'):
                    p.update({k: v for k, v in prev.items() if k not in ('extracted_text', 'text_sha256', 'key', 'page')})
        sources.append(entry)
        if render:
            from PIL import Image, ImageDraw
            tiles = []
            for i, page in enumerate(doc):
                pix = page.get_pixmap(matrix=pymupdf.Matrix(1.1, 1.1))
                im = Image.frombytes('RGB', [pix.width, pix.height], pix.samples)
                im.thumbnail((700, 440))
                tile = Image.new('RGB', (720, 475), 'white')
                tile.paste(im, (10, 25)); ImageDraw.Draw(tile).text((10, 5), f'Page {i+1}', fill='black')
                tiles.append(tile)
            sheet = Image.new('RGB', (2160, 475 * ((len(tiles)+2)//3)), '#ddd')
            for i, tile in enumerate(tiles): sheet.paste(tile, ((i % 3)*720, (i//3)*475))
            dest = ROOT / 'backups/library-review' / (slug(path.stem) + '.jpg')
            dest.parent.mkdir(parents=True, exist_ok=True); sheet.save(dest, quality=94)
    manifest = {'version': 1, 'source_directory': PDF_DIR.relative_to(ROOT).as_posix(),
                'file_count': len(sources), 'page_count': sum(x['page_count'] for x in sources),
                'course_count': len(set(x['course_code'] for x in sources)), 'sources': sources}
    write_json(prior_file, manifest)
    print(json.dumps({k: manifest[k] for k in ('file_count', 'page_count', 'course_count')}))


def languages():
    req = urllib.request.Request(ISO_URL, headers={'User-Agent': 'AtlasCourseInventory/1.0'})
    raw = urllib.request.urlopen(req, timeout=45).read()
    rows = list(csv.DictReader(io.StringIO(raw.decode('utf-8')), delimiter='\t'))
    out = []
    for row in rows:
        if row['Scope'] != 'I' or row['Language_Type'] != 'L': continue
        code = row['Part1'] or row['Id']
        # This name-based indication is explicit; reviewers can correct modality.
        signed = bool(re.search(r'\b(sign language|signed|international sign|signes|gebarentaal)\b', row['Ref_Name'], re.I))
        out.append({'locale': code, 'iso6393': row['Id'], 'name': row['Ref_Name'], 'modality': 'signed' if signed else 'spoken',
                    'modality_source': 'reference-name; review required', 'status': 'pending'})
    write_json(DATA / 'languages.json', {'version': 2, 'source': ISO_URL, 'source_sha256': digest(raw),
                                       'modality_sources': ['reference-name; human review required','https://www.sign-lang.uni-hamburg.de/lrec/language/ils.html','https://www.sign-lang.uni-hamburg.de/lrec/language/sfb.html','https://www.sign-lang.uni-hamburg.de/lrec/language/vgt.html'],
                                       'retrieved_on': datetime.now(timezone.utc).date().isoformat(), 'languages': out})
    (DATA / 'iso-639-3.tab').write_bytes(raw)
    print(f'Living individual languages: {len(out)}; signed indications: {sum(x["modality"] == "signed" for x in out)}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--render', action='store_true')
    parser.add_argument('--refresh-languages', action='store_true')
    args = parser.parse_args()
    build(args.render)
    if args.refresh_languages: languages()
