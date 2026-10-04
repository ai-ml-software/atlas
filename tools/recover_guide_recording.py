"""Recover chapter timestamps from the captions in an interrupted real screen recording."""
import json, re, sys, subprocess, wave, unicodedata, difflib
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
import imageio_ffmpeg

root=Path(__file__).resolve().parents[1]
work=root/'application/logs/guide-recordings'
source=max(work.glob('*.webm'), key=lambda item:item.stat().st_mtime)
samples=work/'recovery-samples';samples.mkdir(exist_ok=True)
ffmpeg=imageio_ffmpeg.get_ffmpeg_exe()
subprocess.run([ffmpeg,'-y','-loglevel','error','-i',str(source),'-vf','fps=1/3,crop=1280:160:0:640','-frames:v','500',str(samples/'frame-%04d.png')],check=True)
chapters=json.loads((work/'admin-speech.json').read_text(encoding='utf-8'))[:45]
def normal(text):return re.sub('[^a-z0-9]+',' ',unicodedata.normalize('NFKD',text).lower()).strip()
titles=[normal(chapter['title']) for chapter in chapters]
def read(item):
    result=subprocess.run(['C:/Program Files/Tesseract-OCR/tesseract.exe',str(item),'stdout','--psm','6','-l','eng'],capture_output=True,text=True)
    for line in result.stdout.splitlines():
        if 'GUIDE' in line.upper():
            candidate=re.split(r'GUIDE\s*[·.:|—-]*',line,flags=re.I)[-1]
            scores=[difflib.SequenceMatcher(None,normal(candidate),title).ratio() for title in titles]
            index=max(range(len(scores)),key=scores.__getitem__)
            if scores[index]>.68:return (int(item.stem.split('-')[-1])-1)*3,index
    return None
with ThreadPoolExecutor(max_workers=4) as pool: found=sorted(value for value in pool.map(read,sorted(samples.glob('frame-*.png'))) if value is not None)
starts={}
for seconds,index in found:
    if index not in starts:starts[index]=seconds
previous=0
for index,chapter in enumerate(chapters):
    with wave.open(chapter['file'],'rb') as audio:duration=audio.getnframes()/audio.getframerate()
    chapter['duration']=duration
    chapter['start']=max(previous,starts.get(index,previous))
    chapter['end']=chapter['start']+duration+1
    chapter['controls']=[]
    previous=chapter['end']
manifest={'role':'admin','created':'2026-10-03','base':'http://127.0.0.1:8099','raw':str(source),'parts':[{'file':str(source),'duration':previous}],'chapters':chapters,'errors':[],
          'recording':'Actual browser recording; interrupted after 45 completed chapters. Caption-based timestamp recovery (three-second sampling); remaining chapters are separately recorded and concatenated.'}
(root/'docs/guides/admin-chapters.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
print(f'Recovered {len(starts)}/45 caption timestamps; preserved {previous:.1f} seconds. Raw recording remains unchanged.')
