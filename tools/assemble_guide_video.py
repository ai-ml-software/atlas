"""Mix timestamped walkthrough narration over actual Playwright screen recordings."""
import json, sys, wave, subprocess
from pathlib import Path
import imageio_ffmpeg

manifest=Path(sys.argv[1]).resolve()
data=json.loads(manifest.read_text(encoding='utf-8'))
chapters=data['chapters']
audio=Path(chapters[0]['file']).parent/f"{data['role']}-mixed.wav"
with wave.open(chapters[0]['file'],'rb') as source:
    channels,width,rate=source.getnchannels(),source.getsampwidth(),source.getframerate()
bytes_per_second=channels*width*rate
with wave.open(str(audio),'wb') as output:
    output.setnchannels(channels);output.setsampwidth(width);output.setframerate(rate)
    cursor=0
    for chapter in chapters:
        start=int(chapter['start']*rate)*channels*width
        if start>cursor:output.writeframes(b'\0'*(start-cursor));cursor=start
        with wave.open(chapter['file'],'rb') as source:
            frames=source.readframes(source.getnframes())
        output.writeframes(frames);cursor+=len(frames)
    end=int(chapters[-1]['end']*rate)*channels*width
    if end>cursor:output.writeframes(b'\0'*(end-cursor))
role_name='Student' if data['role']=='learner' else data['role'].title()
target=manifest.parent/f"ALTUS-{role_name}-Guide.mp4"
parts=data.get('parts') or [{'file':data['raw'],'duration':chapters[-1]['end']}]
ffmpeg=imageio_ffmpeg.get_ffmpeg_exe()
clips=[]
for index,part in enumerate(parts):
    clip=Path(part['file']).with_name(f"{data['role']}-part-{index}.mp4")
    if not clip.exists():
        subprocess.run([ffmpeg,'-y','-loglevel','error','-i',part['file'],'-t',str(part['duration']),'-an','-c:v','libx264','-preset','fast','-crf','24','-pix_fmt','yuv420p','-r','25','-threads','2',str(clip)],check=True)
    clips.append(clip)
concat=audio.with_suffix('.concat.txt')
concat.write_text('\n'.join("file '"+str(clip).replace('\\','/')+"'" for clip in clips),encoding='utf-8')
subprocess.run([ffmpeg,'-y','-loglevel','error','-f','concat','-safe','0','-i',str(concat),'-i',str(audio),'-map','0:v:0','-map','1:a:0','-c:v','copy','-c:a','aac','-b:a','96k','-t',str(chapters[-1]['end']),'-movflags','+faststart',str(target)],check=True)
def timestamp(seconds):
    millis=round(seconds*1000);return f'{millis//3600000:02}:{millis//60000%60:02}:{millis//1000%60:02}.{millis%1000:03}'
vtt=['WEBVTT','']
for chapter in chapters:
    vtt.extend([f"{timestamp(chapter['start'])} --> {timestamp(chapter['end'])}",chapter['title']+' — '+chapter['text'],''])
manifest.with_suffix('.vtt').write_text('\n'.join(vtt),encoding='utf-8')
print(f"Created {target.name}: {len(chapters)} chapters, {chapters[-1]['end']:.1f} seconds")
