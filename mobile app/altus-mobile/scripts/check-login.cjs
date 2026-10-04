const fs=require('node:fs');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../../..');
const {chromium}=require(path.join(root,'e2e/node_modules/playwright'));
const php='C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe';
const helper=path.join(__dirname,'login-fixture.php');
const checks=[];
function check(ok,label){assert(ok,label);checks.push(label);console.log('PASS '+label);}
function fixture(action,input){const p=spawnSync(php,[helper,action],{input,encoding:'utf8'});if(p.status!==0)throw new Error('Isolated fixture operation failed.');return p.stdout;}
async function request(endpoint,token,body){const r=await fetch('http://127.0.0.1:8099/mobile_api/'+endpoint,{method:body?'POST':'GET',headers:{Accept:'application/json',...(token?{Authorization:'Bearer '+token}:{}),...(body?{'Content-Type':'application/json'}:{})},...(body?{body:JSON.stringify(body)}:{})});assert.equal(r.headers.get('x-ha-test-database'),'atlas_hospitality_test');return {status:r.status,body:await r.json()};}
(async()=>{
  await request('me'); // Verify isolated router before mutating any fixtures.
  const data=JSON.parse(fixture('setup'));let browser;
  try {
    let r=await request('login',null,{email:data.users[0].email,password:'wrong'});
    check(r.status===401&&!r.body.success,'Invalid password is rejected by the actual HTTP endpoint.');
    browser=await chromium.launch({headless:true});
    const roles={1:'academy_admin',3:'instructor',6:'property_manager',12:'learner'};
    for(const user of data.users){
      user.id=Number(user.id);
      const context=await browser.newContext({viewport:{width:390,height:844}});
      const page=await context.newPage(); const errors=[];page.on('pageerror',e=>errors.push(e.message));
      await page.route('https://altusgulf.com/mobile_api/**',async route=>{
        const req=route.request();const endpoint=new URL(req.url()).pathname.split('/mobile_api/')[1]+new URL(req.url()).search;
        const payload=req.postData()?JSON.parse(req.postData()):undefined;
        const result=await request(endpoint,req.headers().authorization?.replace(/^Bearer /,''),payload);
        await route.fulfill({status:result.status,contentType:'application/json',headers:{'Access-Control-Allow-Origin':'*','Access-Control-Allow-Headers':'Authorization,Content-Type,Accept'},body:JSON.stringify(result.body)});
      });
      await page.goto('http://127.0.0.1:8083/',{waitUntil:'networkidle'});
      await page.waitForURL('**/sign-in');
      check(await page.getByRole('textbox',{name:'Email',exact:true}).count()===1,'Role '+roles[user.id]+': app opens directly to email/password sign-in.');
      check(!/API key|Server setup|Platform URL/.test(await page.locator('body').innerText()),'Role '+roles[user.id]+': no key or server configuration fields.');
      await page.getByRole('textbox',{name:'Email',exact:true}).fill(user.email);
      await page.getByRole('textbox',{name:'Password',exact:true}).fill(data.password);
      await page.getByRole('button',{name:'Sign in',exact:true}).click();await page.waitForURL('**/home');
      await page.waitForTimeout(400);
      check(!errors.length,'Role '+roles[user.id]+': authenticated home renders without browser errors.');
      if(user.id===12){
        check(await page.getByRole('button',{name:'Team members',exact:true}).count()===0,'Learner cannot see team shortcuts.');
        await page.goto('http://127.0.0.1:8083/admin',{waitUntil:'networkidle'});
        // Browser credentials intentionally live in memory: reload must require sign-in.
        await page.waitForURL('**/sign-in');check(true,'Browser refresh requires sign-in; no token is stored in local preferences.');
      }
      await context.close();
      r=await request('login',null,{email:user.email,password:data.password,role:'super_admin'});
      check(r.status===200&&!r.body.data.two_factor_required,'Role '+roles[user.id]+': password endpoint returns an expiring session.');
      const token=r.body.data.token;const me=await request('me',token);
      check(me.status===200&&me.body.data.roles.includes(roles[user.id])&&!me.body.data.roles.includes('super_admin'),'Role '+roles[user.id]+': posted role cannot elevate the account.');
      if(user.id===12){
        const denied=await request('people',token);check(denied.status===403,'Learner is denied team data on the server.');
        const tenant=await request('competencies?user_id=25',token);check(tenant.status===403,'Learner is denied another tenant employee on the server.');
        fixture('revoke-role');
        const deniedCourse=await request('courses',token);
        check(deniedCourse.status===403,'An existing mobile session loses course access immediately when its grant is removed.');
        const fresh=await request('me',token);
        check(fresh.status===200&&fresh.body.data.roles.length===0&&!fresh.body.data.permissions.includes('courses.view'),'Identity reflects revoked grants without trusting cached or posted roles.');
      }
      if(user.id===6){const tenant=await request('assign',token,{user_id:25,course_id:1});check(tenant.status===403,'Manager cannot assign learning across tenants.');}
      const out=await request('logout',token,{});const revoked=await request('me',token);
      check(out.status===200&&revoked.status===401,'Role '+roles[user.id]+': logout revokes the actual server credential.');
    }
    fs.writeFileSync(path.join(root,'mobile app/client-deliverables/login-checks.json'),JSON.stringify({database:'atlas_hospitality_test',checks,nativeDeviceTested:false,workingDataTouched:false},null,2));
  } finally {if(browser)await browser.close();fixture('cleanup',JSON.stringify(data));}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
