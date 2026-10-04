/* Actual browser recordings against the isolated local test database; no production mutations. */
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright');
const ROOT = path.resolve(__dirname, '../..');
const OUT = path.join(ROOT, 'docs/guides');
const WORK = path.join(ROOT, 'application/logs/guide-recordings');
const BASE = 'http://127.0.0.1:8099';
fs.mkdirSync(OUT, { recursive: true }); fs.mkdirSync(WORK, { recursive: true });
const explanations = [
 [/admin\/mobile/, 'Set the platform address, branding, feature switches, language, support, update rules and maintenance. Generate an app configuration key, then enter it in mobile Server setup and test the connection. Rotate or revoke keys here.'],
 [/cms\/publisher/, 'Upload a PDF, choose the output type and follow extraction progress. Review source text and generated content, reorder sections, and save a private draft. Configure an AI provider first. SOP output must pass its normal review and approval process.'],
 [/cms\/integrations/, 'Connections lists OAuth clients and grants. Approvals contains publication requests. Health tests the gateway and dependencies. Audit lists actions. Archive lets permitted administrators restore supported content. Each MCP publication requires a current, single-use administrator approval.'],
 [/cms\/navigation/, 'Choose a navigation location and language. Edit labels and internal destinations, add items and arrange their order. Check the footer groups and save the navigation changes.'],
 [/cms\/theme/, 'Edit approved brand colours, fonts, spacing, header and footer settings. Save a draft, preview the real website and publish explicitly when the result is ready.'],
 [/cms\/catalogue/, 'Use search and filters to find the record. Open its editor, update English and Arabic content and relationships, and save a draft. Preview before publishing. Archive or restore only when your role permits it.'],
 [/cms\/modules/, 'Create or open a course. Edit its sections, lessons, media and assessments. Check learner-facing content and language coverage. Saving draft content does not publish it; use the normal publication controls.'],
 [/cms(?:\?|$)/, 'Find a website page, open its live editor and click supported text or images on the actual frontend. Add, duplicate, order or hide sections. Save a private draft, preview responsive layouts, then publish explicitly.'],
 [/studio\/revisions/, 'Find a saved revision by object and date. Review the recorded change and restore an earlier version through the protected native service. Version checks prevent overwriting a newer edit.'],
 [/admin\/users/, 'Search users and open a profile to edit permitted account fields and tenant grants. Keep users in their correct organisation, property and role. Deactivate access through the supported controls rather than removing learning history.'],
 [/admin\/crud/, 'Search and filter this structured catalogue. Use Add for a new record and Edit for an existing record. Required fields and tenant relationships are validated on save. Deletion is refused when related records still depend on the item.'],
 [/admin\/content/, 'Review knowledge drafts and their source evidence. Submit changes for review, request corrections or approve with the required role. Published SOP versions and acknowledgements remain governed by the existing workflow.'],
 [/admin\/assessments/, 'Create an assessment, set its scoring and attempts, and edit questions and approved answers. Review grading and practical assessment requirements before releasing it to learners.'],
 [/admin\/competencies/, 'Manage competency definitions and evidence requirements. Link them to learning and practical criteria. Course completion alone does not prove operational readiness.'],
 [/admin\/rules/, 'Manage readiness and certification rules. Review the required learning, assessments and practical evidence before issuing or revoking a certificate. Existing certificate verification remains available.'],
 [/admin\/ai/, 'Choose enabled providers and models, set governance and inspect coverage. Enter provider credentials only in their secured settings. Generated learning content stays in review; AI does not approve SOPs.'],
 [/admin\/system/, 'Review service health and platform settings. Run scheduled jobs when permitted and check the learning progress diagnostics. The dedicated Mobile app settings link opens app configuration and key management.'],
 [/admin\/audit/, 'Filter the audit history by activity and object. Use it to trace who changed access, content or settings. Audit records support operational review and are not editable learning content.'],
 [/admin\/imports/, 'Select the supported import format, upload your source and review validation errors before confirming. Keep a backup before bulk imports and verify relationships after the import completes.'],
 [/admin\/library/, 'Review the source library, supported languages and release coverage. Missing or unreviewed translations must be completed and signed off before a language is advertised as ready.'],
 [/admin\/curriculum/, 'Review the structured curriculum and its learning relationships. Update permitted definitions and check downstream courses, competency requirements and language coverage before publication.'],
 [/admin\/corporate/, 'Manage the corporate website records and approved public content. Edits bind to native records. Preview the real homepage through the live CMS and publish only reviewed changes.'],
 [/admin\/engagements/, 'Create or open an engagement, review its stages and update assigned tasks with evidence. Keep organisation and property context correct so stakeholders see only their own work.'],
 [/admin\/frameworks/, 'Manage advisory frameworks and their stages. Use the published structure consistently across engagements and review changes before they affect active work.'],
 [/team\/assign/, 'Choose visible team members, a learning object and due date. Review the assignment before submitting. Existing assignments and learner progress stay attached to the same accounts.'],
 [/assess\/queue/, 'Open an assigned practical assessment, inspect evidence and score the rubric criteria. Critical criteria can cap the outcome. Submit only observations supported by evidence.'],
 [/team\/reports/, 'Choose the report and filters for your permitted property context. Review learning, compliance and capability results, then export the supported spreadsheet or report if your role allows it.'],
 [/team\/people/, 'Search the team within your permitted scope. Open a learner to inspect their learning and capability record. Organisation and property boundaries are enforced by the server.'],
 [/team\/gaps/, 'Review capability gaps and their severity. Open the relevant learner or requirement, then plan an assignment, practical assessment or action that addresses the gap.'],
 [/team\/actions/, 'Review submitted action plans, inspect the evidence and provide the permitted review outcome. Follow up through the approved workflow rather than marking unsupported work complete.'],
 [/team\/readiness|team\/opening/, 'Review readiness requirements and supporting evidence. Investigate missing learning, practical criteria and operational actions before confirming readiness for a role or opening.'],
 [/team\/certifications/, 'Review certificates for visible team members. Check issuance, expiry and revocation. Export only within your permitted scope and verify certificates through their public verification link.'],
 [/team\/cohorts/, 'Create or open a cohort, select its permitted members and manage its learning assignments. Cohorts organise delivery while preserving each learner’s individual progress.'],
 [/team\/audits/, 'Open a quality audit, review its checklist and enter evidence-based findings. Link follow-up actions to the responsible people and track their completion.'],
 [/team\/kpis/, 'Review property KPI scorecards and reporting periods. Check whether higher or lower values represent improvement, and record evidence before interpreting performance.'],
 [/team\/branding/, 'Update the permitted property branding and settings. Preview the result in its tenant context. Property changes do not grant control over global mobile configuration.'],
 [/learn\/paths/, 'Open an assigned learning path, check the required sequence and due dates, and continue the next available course. Locked items show their prerequisites.'],
 [/learn(?:\?|$)/, 'Use the learning list to find assigned, active or completed courses. Open a course, continue its next available lesson and follow any prerequisites. Progress is stored against your own account.'],
 [/knowledge/, 'Search approved SOPs and knowledge in your permitted organisation and language. Open the document, check its version and read the procedure. Acknowledge only after you have read and understood it.'],
 [/\/assess(?:\?|$)/, 'Open an available assessment, read its instructions and submit your answers. Attempts and passing requirements are enforced on the server. Review the outcome before retrying if another attempt is allowed.'],
 [/\/competencies/, 'Review your competency record, the required evidence and gaps. Arrange any practical assessment with an authorised assessor. A completed course is one part of the evidence.'],
 [/\/actions(?:\?|$)/, 'Create or update an action plan with an owner, due date and evidence. Submit it for review when ready, then follow the review comments and update the result.'],
 [/\/certificates/, 'Review issued certificates and expiry dates. Open or download the permitted certificate and use its verification code to confirm its status. A revoked certificate no longer verifies as valid.'],
 [/\/assistant/, 'Ask a focused question about approved operational knowledge. Review the cited sources. The assistant reports insufficient coverage when the approved library cannot support an answer.'],
 [/account_security/, 'Manage your authenticator and recovery codes. Create a personal mobile API key using your current password and a second-factor code when enabled. Copy the key once into mobile Sign in. Revoke it here when no longer needed.'],
 [/\/notifications/, 'Read your account notifications and follow their linked actions. Mark the items read through the supported controls. Mobile push delivery is a separate integration and is not demonstrated in this recording.'],
 [/\/profile/, 'Review your account information, language and accessible property context. Change only the fields permitted by your role and keep your security settings current.'],
 [/\/team(?:\?|$)/, 'Use the team dashboard to prioritise learning, capability gaps and operational actions for visible staff. Figures come from the current tenant data; choose the property context before making decisions.'],
 [/\/admin(?:\?|$)/, 'Use the portfolio dashboard and search the sidebar to find your tool. Website Pages, Courses, AI Publisher and Menus stay prominent. Other groups expand around the active page and show only permitted destinations.'],
 [/\/hkp(?:\?|$)/, 'Your dashboard shows the next learning step, current progress and permitted work. Use the sidebar to open learning, approved knowledge, assessments and certificates. Switch language without changing your account permissions.'],
];
function explain(url, label) { return explanations.find(([pattern]) => pattern.test(url))?.[1] || `${label}: review the records and visible actions in your current tenant context. Use the search, filters and forms shown here. Required fields are validated and every change still requires your current permission.`; }
function wavDuration(file) { const data = fs.readFileSync(file); let offset=12, rate=0, size=0; while(offset+8<data.length){ const id=data.toString('ascii',offset,offset+4), length=data.readUInt32LE(offset+4); if(id==='fmt ') rate=data.readUInt32LE(offset+16); if(id==='data')size=length; offset+=8+length+(length%2); } return size/rate; }
async function healthy(page, url) {
  const response = await page.goto(url, {waitUntil:'domcontentloaded',timeout:180000});
  if(response.status() !== 200) throw new Error(`${url} returned ${response.status()}`);
  if(/A PHP Error was encountered|Fatal error|A Database Error Occurred/.test(await page.locator('body').innerText())) throw new Error(`Rendering error at ${url}`);
}
async function caption(page, chapter) {
  await page.evaluate(({title,text}) => {
    document.getElementById('altus-training-caption')?.remove();
    const box=document.createElement('aside'); box.id='altus-training-caption';
    Object.assign(box.style,{position:'fixed',left:'0',right:'0',bottom:'0',zIndex:'2147483647',background:'#1c2227',color:'#fff',padding:'14px 26px',font:'15px/1.5 Arial',borderTop:'3px solid #c45b2f',boxShadow:'0 -2px 12px #0002'});
    const heading=document.createElement('strong'); heading.textContent='ALTUS GUIDE · '+title; heading.style.color='#e6ae88';
    const body=document.createElement('div');body.textContent=text; box.append(heading,body);document.body.append(box);
  },chapter);
}
async function recordRole(browser, role) {
  const manifestFile=path.join(OUT,`${role}-chapters.json`);
  const previous=fs.existsSync(manifestFile)?JSON.parse(fs.readFileSync(manifestFile,'utf8')):null;
  const offset=previous?.chapters.at(-1)?.end||0;
  const state=path.join(ROOT,`e2e/.auth/isolated-${role}.json`);
  const inspect=await browser.newContext({storageState:state}); const probe=await inspect.newPage();
  await healthy(probe,BASE+'/hkp?lang=en');
  const menu=await probe.locator('.hkp-nav a[href]').evaluateAll(items => items.map(a=>({title:a.textContent.trim(),url:a.href})).filter(a=>a.url.includes('/hkp')));
  const unique=[...new Map(menu.map(item=>[item.url.replace(/\?.*$/,''),item])).values()];
  const extra=[{title:'Account security and personal mobile key',url:BASE+'/account_security'},{title:'Notifications',url:BASE+'/hkp/notifications'},{title:'Profile and preferences',url:BASE+'/hkp/profile'}];
  const allChapters=unique.concat(extra).map((item,index)=>({...item,text:explain(item.url,item.title),file:path.join(WORK,`${role}-${index}.wav`)}));
  const chapters=allChapters.slice(previous?.chapters.length||0);
  if(!chapters.length){await inspect.close();const assembled=spawnSync('C:/Python313/python.exe',[path.join(ROOT,'tools/assemble_guide_video.py'),manifestFile],{encoding:'utf8'});if(assembled.status!==0)throw new Error(assembled.stderr||assembled.stdout);console.log(assembled.stdout.trim());return;}
  const narration=path.join(WORK,`${role}-speech.json`); fs.writeFileSync(narration,JSON.stringify(chapters));
  const speech=spawnSync('powershell.exe',['-NoProfile','-ExecutionPolicy','Bypass','-File',path.join(ROOT,'mobile app/altus-mobile/scripts/narrate.ps1'),'-Manifest',narration],{encoding:'utf8'});
  if(speech.status !== 0) throw new Error(speech.stderr || 'Narration failed');
  for(const chapter of chapters)chapter.duration=wavDuration(chapter.file);
  await inspect.close();
  const context=await browser.newContext({storageState:state,viewport:{width:1280,height:800},recordVideo:{dir:WORK,size:{width:1280,height:800}}});
  const page=await context.newPage(); const start=Date.now(); const video=page.video(); const raw=await video.path(); const errors=[];
  page.on('pageerror',e=>errors.push(e.message)); page.on('dialog',d=>d.accept());
  for(let index=0;index<chapters.length;index++) {
    const chapter=chapters[index]; await healthy(page,chapter.url); await page.waitForTimeout(350);
    chapter.start=offset+(Date.now()-start)/1000; await caption(page,chapter);
    chapter.controls=await page.locator('main label, main button, main select option, main h2, main h3, .hkp-main label, .hkp-main button, .hkp-main h2').allTextContents();
    chapter.controls=[...new Set(chapter.controls.map(value=>value.trim()).filter(Boolean))];
    await page.mouse.move(1000,350,{steps:8});
    await page.waitForTimeout(Math.max(1200,(chapter.duration/2)*1000));
    await page.mouse.wheel(0,360); await page.waitForTimeout(Math.max(1200,(chapter.duration/2)*1000));
    chapter.end=offset+(Date.now()-start)/1000;
    fs.writeFileSync(manifestFile,JSON.stringify({role,created:new Date().toISOString(),base:BASE,raw,
      recording:'Actual browser screen recording on isolated seeded accounts; English synthetic narration. No real personal data or plaintext keys recorded.',
      parts:[...(previous?.parts||[]),{file:raw,duration:chapter.end-offset}],chapters:[...(previous?.chapters||[]),...chapters.slice(0,index+1)],errors},null,2));
    console.log(`${role}: ${index+1}/${chapters.length} ${chapter.title}`);
  }
  await context.close();
  if(errors.length)throw new Error(errors.join('\n'));
  const assembled=spawnSync('C:/Python313/python.exe',[path.join(ROOT,'tools/assemble_guide_video.py'),path.join(OUT,`${role}-chapters.json`)],{encoding:'utf8',maxBuffer:1024*1024});
  if(assembled.status!==0)throw new Error(assembled.stderr || assembled.stdout); console.log(assembled.stdout.trim());
}
(async()=>{ const browser=await chromium.launch({channel:'msedge',headless:true}); try {for(const role of ['admin','learner','instructor']) await recordRole(browser,role);}finally{await browser.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
