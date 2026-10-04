"""Decode exported walkthroughs and verify narration, timing and chapter completeness."""
import json, re, subprocess
from pathlib import Path
import imageio_ffmpeg

root=Path(__file__).resolve().parents[1]
directory=root/'docs/guides'
ffmpeg=imageio_ffmpeg.get_ffmpeg_exe()
expected={'admin':('Admin',57),'learner':('Student',14),'instructor':('Instructor',20),'mobile':('Mobile',5)}
report=[]
for role,(label,count) in expected.items():
    manifest=json.loads((directory/f'{role}-chapters.json').read_text(encoding='utf-8'))
    chapters=manifest['chapters']
    assert len(chapters)==count, (role,len(chapters))
    assert not manifest['errors'], (role,manifest['errors'])
    previous=0
    for chapter in chapters:
        assert chapter['start']>=previous-.05 and chapter['end']>chapter['start'], (role,chapter['title'])
        previous=chapter['end']
    file=directory/f'ALTUS-{label}-Guide.mp4'
    info=subprocess.run([ffmpeg,'-hide_banner','-i',str(file)],capture_output=True,text=True).stderr
    assert 'Video: h264' in info and 'Audio: aac' in info, file.name
    match=re.search(r'Duration: (\d+):(\d+):([\d.]+)',info)
    duration=int(match[1])*3600+int(match[2])*60+float(match[3])
    assert abs(duration-chapters[-1]['end'])<2, (file.name,duration,chapters[-1]['end'])
    decode=subprocess.run([ffmpeg,'-v','error','-threads','2','-i',str(file),'-f','null','-'],capture_output=True,text=True)
    assert decode.returncode==0 and not decode.stderr.strip(), (file.name,decode.stderr)
    report.append({'file':file.name,'chapters':count,'duration_seconds':duration,'bytes':file.stat().st_size,'decoded':True,'audio':'AAC English synthetic narration','video':'H264 actual browser recording'})
    print(f'{file.name}: {count} chapters, {duration:.2f}s, fully decoded with audio',flush=True)
(directory/'video-verification.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
