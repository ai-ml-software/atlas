"""OCR all canonical PDF pages, retaining image coordinates and unreviewed text.

Never sets review flags. Tesseract and ara/eng models must be installed explicitly.
"""
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path
import argparse
import hashlib
import json
import os
import subprocess
import tempfile
import pymupdf

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / 'application/seeds/library_support'


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def process(source, page, args):
    folder = ROOT / 'backups/library-review/pages' / source['course_code']
    folder.mkdir(parents=True, exist_ok=True)
    output = folder / f'{page:03d}'
    cache = output.with_suffix('.json')
    if cache.exists():
        prior = json.loads(cache.read_text(encoding='utf-8'))
        if prior['source_sha256'] == source['sha256'] and prior.get('dpi') == args.dpi and prior.get('model_sha256') == args.model_sha256:
            return source['sha256'], page, prior
    with pymupdf.open(ROOT / source['path']) as doc:
        pix = doc[page-1].get_pixmap(matrix=pymupdf.Matrix(args.dpi/72,args.dpi/72))
        pix.save(str(output.with_suffix('.png')))
    env = dict(os.environ, OMP_THREAD_LIMIT='1')
    cmd = [args.tesseract,str(output.with_suffix('.png')),str(output),
           '--tessdata-dir',args.models,'-l','ara+eng','--psm','11','txt','tsv']
    result = subprocess.run(cmd, capture_output=True, env=env, timeout=180)
    if result.returncode:
        raise RuntimeError(f"OCR failed: {source['filename']} page {page}: {result.stderr.decode(errors='replace')[-300:]}")
    text = output.with_suffix('.txt').read_text(encoding='utf-8')
    item = {'source_sha256':source['sha256'],'page':page,'dpi':args.dpi,'method':'tesseract-ara-eng-psm11',
            'review_status':'pending','ocr_text':text,'image_sha256':digest(output.with_suffix('.png')),'model_sha256':args.model_sha256,
            'coordinates_tsv':output.with_suffix('.tsv').relative_to(ROOT).as_posix()}
    cache.write_text(json.dumps(item,ensure_ascii=False,indent=2),encoding='utf-8')
    return source['sha256'], page, item


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--tesseract',default='tesseract')
    parser.add_argument('--models',required=True)
    parser.add_argument('--dpi',type=int,default=200)
    parser.add_argument('--workers',type=int,default=4)
    args = parser.parse_args()
    args.model_sha256={lang:digest(Path(args.models)/f'{lang}.traineddata') for lang in ('ara','eng')}
    manifest = json.loads((DATA/'manifest.json').read_text(encoding='utf-8'))
    sources = [s for s in manifest['sources'] if not s['duplicate_of']]
    package = {'version':1,'review_status':'pending','sources':{},
               'model_sha256':args.model_sha256}
    total = sum(s['page_count'] for s in sources); completed=0
    with ThreadPoolExecutor(max_workers=max(1,min(8,args.workers))) as pool:
        futures = [pool.submit(process,s,p,args) for s in sources for p in range(1,s['page_count']+1)]
        for future in as_completed(futures):
            sha,page,item = future.result()
            package['sources'].setdefault(sha,{'pages':{}})['pages'][str(page)] = item
            completed += 1
            if completed % 20 == 0: print(f'OCR {completed}/{total} canonical pages',flush=True)
    package['accounted_files']=manifest['file_count']; package['accounted_pages']=manifest['page_count']
    temp=DATA/'ocr_drafts.json.tmp'
    temp.write_text(json.dumps(package,ensure_ascii=False,indent=2),encoding='utf-8'); temp.replace(DATA/'ocr_drafts.json')
    print(f'OCR complete: {completed} canonical pages; {manifest["page_count"]} source pages accounted; no approvals',flush=True)


if __name__ == '__main__': main()
