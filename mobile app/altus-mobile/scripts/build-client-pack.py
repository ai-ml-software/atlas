"""Generate a branded, complete client PDF, screen contact sheet and narrated MP4.
Only uses real captured app screens. Coverage and implementation status are explicit.
Requires capture-and-test.cjs first; PyMuPDF, Pillow, imageio-ffmpeg, Windows speech.
"""
from pathlib import Path
import json, html, subprocess, sys, textwrap, wave, math
import pymupdf as fitz
from PIL import Image, ImageDraw, ImageFont
import imageio_ffmpeg

ROOT=Path(__file__).resolve().parents[3]
APP=Path(__file__).resolve().parents[1]
OUT=ROOT/'mobile app/client-deliverables'
CAP=OUT/'screens'
CREAM='#F7F5F1'; COPPER='#C45B2F'; CHARCOAL='#2A2F35'; SLATE='#5B6775'; SAND='#D9C6A3'; GREEN='#2E7D5A'
FONTS=APP/'node_modules/@expo-google-fonts'
font_inter=FONTS/'inter/400Regular/Inter_400Regular.ttf'
font_display=FONTS/'fraunces/400Regular/Fraunces_400Regular.ttf'
font_ar=FONTS/'ibm-plex-sans-arabic/400Regular/IBMPlexSansArabic_400Regular.ttf'
manifest=json.loads((OUT/'capture-manifest.json').read_text(encoding='utf8'))
screens=json.loads((OUT/'screens.json').read_text(encoding='utf8'))
lookup={(c['id'],c['locale']):c for c in manifest['captures']}
report=fitz.Document()
W,H=842,595
def rgb(hex):return tuple(int(hex.lstrip('#')[i:i+2],16)/255 for i in (0,2,4))
def page(label):
 p=report.new_page(width=W,height=H);p.draw_rect(p.rect,color=None,fill=rgb(CREAM));p.insert_font(fontname='Altus',fontfile=str(font_inter));p.insert_font(fontname='Display',fontfile=str(font_display));p.insert_font(fontname='Arabic',fontfile=str(font_ar));p.insert_image(fitz.Rect(40,22,156,60),filename=str(APP/'assets/altus-logo-horizontal.png'),keep_proportion=True);p.insert_text((W-255,44),label.upper(),fontsize=9,fontname='Altus',color=rgb(SLATE));p.draw_line((40,H-33),(W-40,H-33),color=rgb(SAND),width=.5);p.insert_text((40,H-16),'ALTUS KNOWLEDGE & PERFORMANCE  /  PRODUCT PREVIEW  /  2 OCTOBER 2026',fontname='Altus',fontsize=7,color=rgb(SLATE));p.insert_text((W-60,H-16),str(len(report)),fontname='Altus',fontsize=8,color=rgb(SLATE));return p
def text(p,box,value,size=14,display=False,color=CHARCOAL):
 rect=fitz.Rect(*box)
 while size>=8:
  result=p.insert_textbox(rect,value,fontsize=size,fontname='Display' if display else 'Altus',lineheight=1.35,color=rgb(color))
  if result>=0:return
  size-=.5
 raise RuntimeError(f'Text overflow: {value[:80]}')
def shot(p,sid,locale,rect):p.insert_image(fitz.Rect(*rect),filename=str(CAP/lookup[(sid,locale)]['file']),keep_proportion=True)

p=page('People. Knowledge. Performance.')
text(p,(40,100,430,330),'A stronger hospitality\ntomorrow.\nIn your hands.',43,True)
text(p,(40,343,408,429),'A distinctive mobile experience for the people behind exceptional service. Learn with purpose. Apply approved knowledge. Make improvement visible.',16,color=SLATE)
text(p,(40,465,415,530),'CLIENT PRESENTATION · WORKING PRODUCT PREVIEW\nNative mobile code + isolated API checks + actual app captures\nDemonstration figures are illustrative; release work remains.',10,color=COPPER)
shot(p,'home','en',(482,90,680,519));shot(p,'home','ar',(647,124,816,489))
p=page('The business case')
text(p,(40,93,730,170),'Knowledge → Application → Performance',35,True)
cards=[('For employees','A clear next step, focused learning and readable operational standards.','Reduce the effort of finding the right guidance during a shift.'),('For supervisors','Visible learning, readiness and capability gaps.','Turn a training conversation into a specific next action.'),('For management','Property context, team development and scoped reporting.','Connect learning activity to operational follow-through.'),('For ALTUS','An expandable mobile product around existing domain governance.','Support a consistent platform while respecting property identity.')]
for i,(title,body,benefit) in enumerate(cards):
 x=40+(i%2)*394;y=194+(i//2)*158
 p.draw_rect(fitz.Rect(x,y,x+372,y+140),color=rgb(SAND),fill=(1,1,1),width=.5)
 text(p,(x+16,y+14,x+350,y+43),title,17,True)
 text(p,(x+16,y+49,x+350,y+94),body,12,color=SLATE)
 text(p,(x+16,y+99,x+350,y+132),benefit,10,color=COPPER)
p=page('The ALTUS difference')
text(p,(40,92,505,169),'Designed for hospitality.\nBuilt around the next step.',32,True)
text(p,(40,191,451,426),'01   People first\nA calm mobile interface with clear reading, useful touch targets and focused actions.\n\n02   Trusted knowledge\nApproved SOPs, version metadata and source-led operational guidance.\n\n03   Evidence of progress\nLearning and capability are connected without treating course completion as proof of readiness.',15,color=SLATE)
shot(p,'sop','en',(535,98,731,522))
p=page('English / Arabic by design')
text(p,(40,91,760,143),'One experience. Two reading directions.',33,True)
shot(p,'knowledge','en',(57,159,224,521));shot(p,'knowledge','ar',(247,159,414,521))
text(p,(463,180,782,307),'English and Arabic interface catalogues. Arabic typography and mirrored navigation. Switch language without signing out. Localized dates and numbers.',17)
text(p,(463,327,786,497),'Global coverage is described precisely:\n186 selectable language entries, including all ISO 639-1 languages and platform additions.\n250 country/region entries; every ISO 3166-1 country is present.\nOther languages currently display an explicit English fallback. Country coverage is not a claim of translation into every language or dialect.',12,color=SLATE)
p=page('Implementation status')
text(p,(40,90,790,140),'A reviewable product. A clear release path.',31,True)
text(p,(40,163,415,344),'BUILT AND VERIFIED HERE\n\nNative React Native/Expo code and 110 registered routes.\nEnglish/Arabic browser captures and responsive checks.\nLocal demonstration journeys and persistence.\nAdditive API: identity, courses, plans, lessons, server-graded assessments, SOPs, acknowledgment, search, governed AI with sources, competency, readiness, actions, certificates, people, gaps, KPIs, notifications and assignments.\n20 isolated HTTP authorization and assessment checks.',12)
text(p,(450,163,795,403),'REQUIRED BEFORE A PRODUCTION SALE / RELEASE\n\nNative iOS and Android device validation.\nNormal mobile password/session/MFA integration preserving account policy; current live connection uses scoped personal keys.\nDevice validation of media playback and all assessment types; private media delivery.\nEncrypted account-bound offline media and idempotent progress synchronization.\nPush token registration and delivery.\nLive support tickets, discussions, event attendance, property-brand publishing and content approval actions.\nProduction endpoint rollout, provider configuration and store signing.',12,color=SLATE)
text(p,(40,443,790,523),'This presentation shows a working product preview with demonstration content. It must not be described as a fully released or fully translated application. Existing website capabilities are not automatically complete native mobile capabilities.',13,color=COPPER)

for idx in range(0,len(screens),2):
 pair=screens[idx:idx+2];p=page('Complete screen tour')
 for j,s in enumerate(pair):
  x=40+j*395
  text(p,(x,79,x+363,101),f"{idx+j+1:03d}  /  {s['module'].upper()}",9,color=COPPER)
  text(p,(x,108,x+364,162),s['title'],19,True)
  shot(p,s['id'],'en',(x+4,179,x+140,473));shot(p,s['id'],'ar',(x+154,179,x+290,473))
  text(p,(x,488,x+365,530),s['benefit'],10,color=SLATE)
  p.insert_text((x+13,481),'ENGLISH',fontname='Altus',fontsize=7,color=rgb(SLATE));p.insert_text((x+163,481),'ARABIC / RTL',fontname='Altus',fontsize=7,color=rgb(SLATE))

p=page('Complete language and country coverage')
text(p,(40,91,790,143),'Global by architecture. Honest about coverage.',31,True)
text(p,(40,163,430,333),'The selector includes all ISO 639-1 language codes plus Filipino and Central Kurdish from the platform. That standard covers widely used languages; it does not enumerate every living language, dialect or script variant.\n\nEnglish and Arabic UI translations are implemented. Additional language packs require translation, review, content availability and script/device QA.',13,color=SLATE)
text(p,(463,163,793,335),'All 249 ISO 3166-1 country/territory codes are present, with the platform’s additional region entry. Countries carry dialing code, currency and a default time zone.\n\nA country does not map to one language. Property time zones and language preference are independent.',13,color=SLATE)
text(p,(40,365,790,475),'The complete machine-readable registries accompany this package as languages.json and countries.json. No registered country or selectable language was omitted from the delivered catalog.',16)

langs=json.loads((APP/'src/i18n/languages.json').read_text(encoding='utf8'));countries=json.loads((APP/'src/i18n/countries.json').read_text(encoding='utf8'))
for label,items,size in [('Language catalog',langs,75),('Country / region catalog',countries,75)]:
 for start in range(0,len(items),size):
  p=page(label);text(p,(40,84,790,128),f'{label} · {start+1}–{min(start+size,len(items))} of {len(items)}',25,True)
  chunk=items[start:start+size]
  for col in range(3):
   values=chunk[col*25:(col+1)*25]
   for row,item in enumerate(values):
    x=40+col*260;y=150+row*15.3
    value=f"{item['code'].upper():<4}  {item['name']}"
    p.insert_text((x,y),value[:43],fontname='Altus',fontsize=8,color=rgb(SLATE))
p=page('Rollout & adoption')
text(p,(40,95,790,153),'Start with one property. Grow with evidence.',31,True)
text(p,(40,189,784,421),'1. Validate the foundation\nConfirm the production API, identity policy, role matrix and source ownership. Test native devices and required integrations.\n\n2. Pilot with a real team\nLaunch one department with approved content, a supervisor and a defined support owner. Track access, completion, understanding and practical follow-through.\n\n3. Review operational value\nCompare agreed evidence before and after the pilot. Refine the learning plan and the property’s standards.\n\n4. Expand deliberately\nAdd departments, properties and reviewed language packs with a consistent governance process.',15,color=SLATE)
text(p,(40,466,790,519),'A commercial offer should distinguish the delivered preview, the implementation scope, provider costs and final native release acceptance. No invented ROI or accreditation claim is included.',12,color=COPPER)

pdf=OUT/'ALTUS-Client-Presentation.pdf';report.save(pdf,garbage=4,deflate=True);print('PDF:',len(report),'pages',flush=True)
# Render a large overview that lets the client review every screen in one image.
thumb_w,thumb_h=156,338;gap=25;cols=10;rows=math.ceil(len(screens)/cols)
board=Image.new('RGB',(cols*(thumb_w+gap)+gap,rows*(thumb_h+68)+135),CREAM);draw=ImageDraw.Draw(board);bodyfont=ImageFont.truetype(str(font_inter),13);headfont=ImageFont.truetype(str(font_display),39)
draw.text((gap,25),'ALTUS Gulf  /  Complete mobile screen collection',font=headfont,fill=CHARCOAL)
draw.text((gap,82),'Actual Expo captures · working demonstration · English and Arabic catalogues accompany the presentation',font=bodyfont,fill=SLATE)
for i,s in enumerate(screens):
 x=gap+(i%cols)*(thumb_w+gap);y=130+(i//cols)*(thumb_h+68);im=Image.open(CAP/lookup[(s['id'],'en')]['file']).convert('RGB').resize((thumb_w,thumb_h),Image.Resampling.LANCZOS);board.paste(im,(x,y));draw.text((x,y+thumb_h+8),f"{i+1:03d}  {s['id']}",font=bodyfont,fill=CHARCOAL)
board.save(OUT/'ALTUS-All-Screens.jpg',quality=90);print('Contact sheet saved',flush=True)
for name in ['languages.json','countries.json']:(OUT/name).write_bytes((APP/'src/i18n'/name).read_bytes())
if '--pdf-only' in sys.argv:sys.exit(0)
# Each narrated video chapter shows an actual screen next to the business benefit.
frames=OUT/'video-frames';frames.mkdir(exist_ok=True);narration=[]
font_head=ImageFont.truetype(str(font_display),62);font_body=ImageFont.truetype(str(font_inter),29);font_small=ImageFont.truetype(str(font_inter),21)
for i,s in enumerate(screens):
 canvas=Image.new('RGB',(1920,1080),CREAM);d=ImageDraw.Draw(canvas)
 d.text((120,90),'ALTUS GULF',font=font_body,fill=CHARCOAL);d.text((120,145),'KNOWLEDGE & PERFORMANCE',font=font_small,fill=COPPER)
 d.text((120,262),f"{i+1:03d}  /  {s['module'].upper()}",font=font_small,fill=COPPER)
 title='\n'.join(textwrap.wrap(s['title'],width=27));d.multiline_text((120,322),title,font=font_head,fill=CHARCOAL,spacing=17)
 benefit='\n'.join(textwrap.wrap(s['benefit'],width=48));d.multiline_text((120,610),benefit,font=font_body,fill=SLATE,spacing=13)
 d.text((120,925),'WORKING DEMONSTRATION · SAMPLE CONTENT',font=font_small,fill=COPPER)
 d.text((120,965),'Native iOS / Android validation and release integrations remain.',font=font_small,fill=SLATE)
 im=Image.open(CAP/lookup[(s['id'],'en')]['file']).convert('RGB').resize((430,930),Image.Resampling.LANCZOS);canvas.paste(im,(1200,75))
 arim=Image.open(CAP/lookup[(s['id'],'ar')]['file']).convert('RGB').resize((255,552),Image.Resampling.LANCZOS);canvas.paste(arim,(1650,270))
 frame=frames/f'{i:03d}.png';canvas.save(frame)
 prefix='Welcome to the Altus Gulf Knowledge and Performance mobile product preview. All figures and learning content shown are illustrative. This is a working demonstration, with production integrations and native device validation still required. ' if i==0 else ''
 suffix=' The language selector contains 186 entries and all ISO 639-1 language codes. The interface is translated into English and Arabic. Other languages display an English fallback. The country catalog contains 250 entries with every ISO 3166-1 country represented.' if s['id']=='language' else ''
 narration.append({'file':str(frames/f'{i:03d}.wav'),'text':prefix+s['title']+'. '+s['benefit']+suffix})
(OUT/'narration.json').write_text(json.dumps(narration,ensure_ascii=False),encoding='utf8')
ps=APP/'scripts/narrate.ps1'
subprocess.run(['powershell','-NoProfile','-ExecutionPolicy','Bypass','-File',str(ps),'-Manifest',str(OUT/'narration.json')],check=True)
ffmpeg=imageio_ffmpeg.get_ffmpeg_exe();segments=[];srt=[];elapsed=0
for i,s in enumerate(screens):
 wavfile=frames/f'{i:03d}.wav'
 with wave.open(str(wavfile)) as wav:duration=wav.getnframes()/wav.getframerate()
 duration=max(duration+.6,4)
 seg=frames/f'{i:03d}.mp4'
 subprocess.run([ffmpeg,'-y','-loglevel','error','-loop','1','-i',str(frames/f'{i:03d}.png'),'-i',str(wavfile),'-t',str(duration),'-r','15','-c:v','libx264','-preset','ultrafast','-crf','25','-pix_fmt','yuv420p','-c:a','aac','-b:a','96k','-af','apad','-movflags','+faststart',str(seg)],check=True)
 segments.append(seg)
 def stamp(v):
  ms=round(v*1000);return f'{ms//3600000:02d}:{ms//60000%60:02d}:{ms//1000%60:02d},{ms%1000:03d}'
 srt.append(f"{i+1}\n{stamp(elapsed)} --> {stamp(elapsed+duration)}\n{s['title']}\n{s['benefit']}\n")
 elapsed+=duration
 if i%10==0:print(f'Video chapters {i+1}/{len(screens)}',flush=True)
concat=frames/'concat.txt';concat.write_text('\n'.join("file '"+p.as_posix()+"'" for p in segments),encoding='utf8')
subprocess.run([ffmpeg,'-y','-loglevel','error','-f','concat','-safe','0','-i',str(concat),'-c','copy','-movflags','+faststart',str(OUT/'ALTUS-Client-Walkthrough.mp4')],check=True)
(OUT/'ALTUS-Client-Walkthrough.srt').write_text('\n'.join(srt),encoding='utf8')
print('Video duration:',round(elapsed),'seconds',flush=True)
