/**
 * Live end-to-end contract: official MCP SDK client over Streamable HTTP →
 * this gateway (oidc-provider OAuth with PKCE) → real ALTUS native API (PHP
 * built-in server on the atlas_hospitality_test database, see e2e/support/router.php).
 *
 * Opt-in: ALTUS_INTEGRATION_TEST=1 npm test
 * Needs: MySQL on 127.0.0.1 with a seeded atlas_hospitality_test database
 * (php index.php ha_test prepare_browser), PHP (ALTUS_TEST_PHP), Playwright
 * Chromium from e2e/ (npm --prefix ../e2e ci), and `npm run build` first.
 * The test never rebuilds the database; it only adds OAuth/approval rows and
 * page drafts, and restores user status flags it changes.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { mkdtemp,writeFile,rm } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { randomBytes,createHash,createCipheriv,createHmac } from 'node:crypto';
import { generateKeyPair,exportJWK } from 'jose';
import { Client,StreamableHTTPClientTransport } from '@modelcontextprotocol/client';
import mysql from 'mysql2/promise';

const DB='atlas_hospitality_test';
const ADMIN='admin@hospitalityacademy.sa',REVIEWER='academy.admin@hospitalityacademy.sa',TENANT='org.admin@dyafagroup.sa',LEARNER='omar.learner@dyafagroup.sa',PASSWORD='Academy#2026';

test('MCP SDK client: OAuth PKCE, scopes, revocation, tenant isolation, approvals, idempotency',{skip:process.env.ALTUS_INTEGRATION_TEST!=='1' && process.env.npm_lifecycle_event!=='test:integration',timeout:300000},async(t)=>{
  const root=path.resolve('..'),folder=await mkdtemp(path.join(os.tmpdir(),'altus-mcp-'));
  const php=process.env.ALTUS_TEST_PHP??'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe';
  const issuer='http://127.0.0.1:3111',native='http://127.0.0.1:9088',callback='http://127.0.0.1:3211/callback',resource=issuer+'/mcp';
  const run=randomBytes(6).toString('hex'),secret=randomBytes(48).toString('hex'),appKey=randomBytes(32);
  const {privateKey}=await generateKeyPair('RS256',{extractable:true});const jwk=await exportJWK(privateKey);Object.assign(jwk,{kid:'test',alg:'RS256',use:'sig'});const keyfile=path.join(folder,'jwks.json');await writeFile(keyfile,JSON.stringify({keys:[jwk]}));
  const env={...process.env,HA_APP_KEY:appKey.toString('base64'),ALTUS_MCP_ENABLED:'1',ALTUS_MCP_URL:issuer,ALTUS_MCP_SECRET:secret,ALTUS_MCP_KEY_ID:'it-'+run,ALTUS_NATIVE_URL:native,PORT:'3111',ALTUS_DB_NAME:DB,ALTUS_OAUTH_JWKS_FILE:keyfile,ALTUS_OAUTH_CLIENTS:JSON.stringify([{client_id:'contract-client',redirect_uris:[callback],grant_types:['authorization_code','refresh_token'],response_types:['code'],token_endpoint_auth_method:'none'}])};
  let logs='';
  const phpServer=spawn(php,['-S','127.0.0.1:9088','-t','.','e2e/support/router.php'],{cwd:root,env,windowsHide:true});
  const gateway=spawn(process.execPath,['dist/server.js'],{env,windowsHide:true});gateway.stdout.on('data',d=>{logs+=d;});gateway.stderr.on('data',d=>{logs+=d;});
  const callbackServer=createServer((_req,res)=>res.end('Connection approved'));await new Promise<void>(resolve=>callbackServer.listen(3211,'127.0.0.1',resolve));
  const require=createRequire(path.join(root,'e2e/package.json'));const {chromium}=require('@playwright/test');const browser=await chromium.launch();
  const db=await mysql.createConnection({host:'127.0.0.1',user:'root',database:DB});
  const uid=async(email:string)=>Number(((await db.execute<mysql.RowDataPacket[]>('SELECT id FROM users WHERE email=?',[email]))[0])[0].id);
  try {
    await db.execute("UPDATE users SET sessions='[]', status=1 WHERE email IN (?,?,?,?)",[ADMIN,REVIEWER,TENANT,LEARNER]);
    for(let i=0;i<60;i++){try{if((await fetch(issuer+'/health')).ok && (await fetch(native+'/login')).ok)break;}catch{}await new Promise(r=>setTimeout(r,300));}
    const health=await (await fetch(issuer+'/health')).json() as any;assert.equal(health.status,'ready',logs);assert.equal(health.key_id,'it-'+run);
    const metadata=await (await fetch(issuer+'/.well-known/oauth-protected-resource/mcp')).json() as any;assert.equal(metadata.resource,resource);
    const as=await (await fetch(issuer+'/oauth/.well-known/openid-configuration')).json() as any;assert.ok(as.code_challenge_methods_supported.includes('S256'));
    const denied=await fetch(resource,{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});assert.equal(denied.status,401);assert.match(denied.headers.get('www-authenticate')??'',/resource_metadata/);

    async function login(email:string){const ctx=await browser.newContext();const page=await ctx.newPage();await page.goto(native+'/login');await page.locator('#login-form #email').fill(email);await page.locator('#login-form #password').fill(PASSWORD);await page.locator('#login-form button[type=submit]').click();await page.waitForLoadState('networkidle');return page;}
    /** Full authorization-code + PKCE flow through the ALTUS login/consent bridge. */
    async function authorize(page:any,scope:string){
      const verifier=randomBytes(48).toString('base64url'),state=randomBytes(24).toString('hex'),challenge=createHash('sha256').update(verifier).digest('base64url');
      const params=new URLSearchParams({client_id:'contract-client',redirect_uri:callback,response_type:'code',prompt:'consent',scope,resource,code_challenge:challenge,code_challenge_method:'S256',state});
      await page.goto(issuer+'/oauth/auth?'+params);await page.getByRole('button',{name:'Allow connection'}).click();
      try{await page.waitForURL(callback+'?**',{timeout:8000});}catch{return {error:(await page.locator('body').innerText()).slice(0,500)};}
      const back=new URL(page.url());assert.equal(back.searchParams.get('state'),state);const code=back.searchParams.get('code')!;
      const redeem=(extra:Record<string,string>={})=>fetch(issuer+'/oauth/token',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({grant_type:'authorization_code',client_id:'contract-client',redirect_uri:callback,code,code_verifier:verifier,resource,...extra})});
      assert.equal((await redeem({code_verifier:'wrong'.repeat(12)})).status,400,'wrong PKCE verifier rejected');
      const r=await redeem();const tokens=await r.json() as any;assert.equal(r.status,200,JSON.stringify(tokens));return {tokens,redeem};
    }
    async function connect(access:string){const c=new Client({name:'altus-contract-test',version:'1.0.0'});await c.connect(new StreamableHTTPClientTransport(new URL(resource),{requestInit:{headers:{Authorization:'Bearer '+access}}}));return c;}
    async function raw(c:Client,name:string,args:Record<string,unknown>){const r=await c.callTool({name,arguments:args});return JSON.parse((r.content as any)[0].text);}
    async function call(c:Client,name:string,args:Record<string,unknown>){const p=await raw(c,name,args);assert.equal(p.success,true,name+': '+JSON.stringify(p));return p.data;}

    const adminPage=await login(ADMIN),reviewerPage=await login(REVIEWER);
    const full=await authorize(adminPage,'openid offline_access altus.read altus.content.write altus.course.write altus.media.write altus.publish');assert.ok('tokens' in full);
    const {tokens}=full as any;assert.ok(tokens.refresh_token);
    const client=await connect(tokens.access_token);
    const listed=await client.listTools();assert.ok(listed.tools.some(x=>x.name==='altus_save_draft'));assert.ok(listed.tools.some(x=>x.name==='altus_restore_archived'));

    await t.test('disabled gateway refuses MCP and advertises disabled health',async()=>{
      const off=spawn(process.execPath,['dist/server.js'],{env:{...env,PORT:'3112',ALTUS_MCP_URL:'http://127.0.0.1:3112',ALTUS_MCP_ENABLED:'0'},windowsHide:true,stdio:'ignore'});
      try {
        let ready=false;for(let i=0;i<60;i++){try{const h=await fetch('http://127.0.0.1:3112/health');if(h.ok){assert.equal((await h.json() as any).status,'disabled');ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,100));}
        assert.ok(ready);const denied=await fetch('http://127.0.0.1:3112/mcp',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});assert.equal(denied.status,503);assert.equal((await denied.json() as any).error,'mcp_disabled');
      } finally {off.kill();}
    });

    const pages=await call(client,'altus_list_objects',{type:'page'});// A page other suites rarely edit; any stale draft left by an earlier run is discarded (test database only).
    const about=Number(pages.find((p:any)=>p.code==='terms').id);await db.execute('DELETE FROM ha_website_draft WHERE page_id=?',[about]);
    async function draft(title:string,key:string){const o=await call(client,'altus_get_object',{type:'page',id:about});o.state.payload.tr.en.title=title;return {o,args:{type:'page',id:about,version:o.version,base_hash:o.state.base_hash,payload:o.state.payload,request_key:key}};}
    async function approve(id:number){await reviewerPage.goto(native+'/hkp/cms/integrations?approval='+id);await reviewerPage.getByRole('button',{name:'Approve this version'}).click();await reviewerPage.waitForLoadState('networkidle');const [r]=await db.execute<mysql.RowDataPacket[]>('SELECT status FROM ha_publisher_approval WHERE id=?',[id]);assert.equal(r[0].status,'approved');}

    await t.test('catalogue CRUD creates private drafts and archives/restores without deletion',async()=>{
      const payload={title_en:'MCP CRUD '+run,title_ar:'MCP CRUD AR '+run,slug_en:'mcp-crud-'+run,slug_ar:'mcp-crud-ar-'+run,body_en:'<p>Source facts.</p>',body_ar:'<p>Source facts.</p>',status:'published'};
      const created=await call(client,'altus_create_draft',{type:'articles',payload,request_key:'create-'+run});assert.equal(created.status,'draft');assert.equal(created.version,1);
      const nativeRow=async()=>((await db.execute<mysql.RowDataPacket[]>('SELECT status FROM ha_article WHERE id=?',[created.object_id]))[0])[0];assert.equal((await nativeRow()).status,'draft');
      const request=await call(client,'altus_request_publish',{type:'articles',id:created.object_id,operation:'publish',request_key:'crud-pubreq-'+run});await approve(request.approval_id);await call(client,'altus_publish_approved',{approval_id:request.approval_id,request_key:'crud-pub-'+run});assert.equal((await nativeRow()).status,'published');
      const archive=await call(client,'altus_request_publish',{type:'articles',id:created.object_id,operation:'archive',request_key:'crud-archreq-'+run});await approve(archive.approval_id);const archived=await call(client,'altus_publish_approved',{approval_id:archive.approval_id,request_key:'crud-archive-'+run});assert.equal(archived.status,'archived');assert.equal((await nativeRow()).status,'archived');
      await call(client,'altus_restore_archived',{type:'articles',id:created.object_id,request_key:'crud-restore-'+run});assert.equal((await nativeRow()).status,'draft');
    });

    await t.test('drafts are versioned and idempotent; stale versions conflict',async()=>{
      const {o,args}=await draft('MCP contract headline '+run,'save-'+run);
      const saved=await call(client,'altus_save_draft',args);assert.equal(saved.version,o.version+1);assert.ok(saved.audit_reference.audit_id>0);
      const replay=await call(client,'altus_save_draft',args);assert.equal(replay.audit_reference.audit_id,saved.audit_reference.audit_id,'replayed, not re-executed');
      const stale=await raw(client,'altus_save_draft',{...args,request_key:'stale-'+run});assert.equal(stale.error.code,'conflict');assert.equal(stale.error.details.current_version,saved.version);
      const mismatch=await raw(client,'altus_save_draft',{...args,payload:{...args.payload,extra:1}});assert.equal(mismatch.error.code,'idempotency_conflict');
    });
    await t.test('self-approval is blocked; second administrator approves; publication is single-use',async()=>{
      const req=await call(client,'altus_request_publish',{type:'page',id:about,operation:'publish',request_key:'req-'+run});assert.match(req.expires_at,/Z$/);
      await adminPage.goto(native+'/hkp/cms/integrations?approval='+req.approval_id);assert.equal(await adminPage.getByRole('button',{name:'Approve this version'}).count(),0,'requester sees no approve button');
      await approve(req.approval_id);
      const pub=await call(client,'altus_publish_approved',{approval_id:req.approval_id,request_key:'pub-'+run});assert.equal(pub.version,0);
      const dup=await call(client,'altus_publish_approved',{approval_id:req.approval_id,request_key:'pub-'+run});assert.equal(dup.audit_reference.audit_id,pub.audit_reference.audit_id,'duplicate publication replays');
      const again=await raw(client,'altus_publish_approved',{approval_id:req.approval_id,request_key:'pub2-'+run});assert.equal(again.error.code,'approval_consumed');
    });
    await t.test('changed content invalidates an approval',async()=>{
      await call(client,'altus_save_draft',(await draft('Before approval '+run,'s2-'+run)).args);
      const req=await call(client,'altus_request_publish',{type:'page',id:about,operation:'publish',request_key:'req2-'+run});await approve(req.approval_id);
      await call(client,'altus_save_draft',(await draft('Edited after approval '+run,'s3-'+run)).args);
      const r=await raw(client,'altus_publish_approved',{approval_id:req.approval_id,request_key:'pub3-'+run});assert.equal(r.error.code,'conflict');assert.equal(r.error.details.approved_version,req.version);
    });
    await t.test('expired approvals cannot publish',async()=>{
      const req=await call(client,'altus_request_publish',{type:'page',id:about,operation:'publish',request_key:'req3-'+run});await approve(req.approval_id);
      await db.execute('UPDATE ha_publisher_approval SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=?',[req.approval_id]);
      const r=await raw(client,'altus_publish_approved',{approval_id:req.approval_id,request_key:'pub4-'+run});assert.equal(r.error.code,'approval_expired');
      assert.equal((await call(client,'altus_approval_status',{id:req.approval_id})).status,'expired');
    });
    await t.test('scope restriction: read-only grant cannot write; learner cannot obtain write scopes',async()=>{
      const ro=await authorize(adminPage,'openid altus.read') as any;const c=await connect(ro.tokens.access_token);
      const r=await raw(c,'altus_save_draft',(await draft('Read-only attempt','ro-'+run)).args);assert.equal(r.error.code,'insufficient_scope');await c.close();
      const learner=await login(LEARNER);const attempt=await authorize(learner,'openid altus.read altus.content.write') as any;assert.ok(attempt.error,'scopes beyond permissions are refused');
    });
    await t.test('tenant isolation and revoked user',async()=>{
      const tenantPage=await login(TENANT);const tk=await authorize(tenantPage,'openid altus.read') as any;const c=await connect(tk.tokens.access_token);
      const r=await raw(c,'altus_get_object',{type:'page',id:about});assert.equal(r.success,false);assert.equal(r.error.code,'forbidden');
      const tenant=await uid(TENANT);await db.execute('UPDATE users SET status=0 WHERE id=?',[tenant]);
      try{const blocked=await fetch(resource,{method:'POST',headers:{Authorization:'Bearer '+tk.tokens.access_token,'Content-Type':'application/json',Accept:'application/json, text/event-stream'},body:JSON.stringify({jsonrpc:'2.0',id:1,method:'tools/list'})});assert.equal(blocked.status,401);}
      finally{await db.execute('UPDATE users SET status=1 WHERE id=?',[tenant]);}
      await c.close().catch(()=>{});
    });
    await t.test('refresh rotation and grant revocation',async()=>{
      const refresh=await fetch(issuer+'/oauth/token',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({grant_type:'refresh_token',client_id:'contract-client',refresh_token:tokens.refresh_token,resource})});const rotated=await refresh.json() as any;assert.equal(refresh.status,200,JSON.stringify(rotated));assert.notEqual(rotated.refresh_token,tokens.refresh_token);
      await client.close();
      const [rows]=await db.execute<mysql.RowDataPacket[]>("SELECT grant_id FROM ha_oauth_store WHERE model='AccessToken' AND id=?",[rotated.access_token]);
      const grant=rows[0]?.grant_id;assert.ok(grant);
      await reviewerPage.goto(native+'/hkp/cms/integrations?tab=connections');
      await db.execute('DELETE FROM ha_oauth_store WHERE grant_id=? OR (model=? AND id=?)',[grant,'Grant',grant]);
      const revoked=await fetch(resource,{method:'POST',headers:{Authorization:'Bearer '+rotated.access_token,'Content-Type':'application/json'},body:'{}'});assert.equal(revoked.status,401);
    });
    await t.test('two-factor ALTUS login precedes OAuth consent and MCP access',async()=>{
      const account=await uid(TENANT),[old]=await db.execute<mysql.RowDataPacket[]>('SELECT * FROM ha_user_2fa WHERE user_id=?',[account]);
      // Isolated fixture, encrypted with a key confined to this PHP test process.
      const nonce=randomBytes(12),cipher=createCipheriv('aes-256-gcm',appKey,nonce),encrypted=Buffer.concat([cipher.update('JBSWY3DPEHPK3PXP','utf8'),cipher.final()]);
      const stored='v1:'+Buffer.concat([nonce,cipher.getAuthTag(),encrypted]).toString('base64');
      await db.execute('REPLACE INTO ha_user_2fa (user_id,secret_cipher,confirmed_at,last_step,recovery_hashes,created_at,updated_at) VALUES (?,?,UTC_TIMESTAMP(),NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())',[account,stored]);
      try {
        const page=await login(TENANT);assert.match(page.url(),/login\/two_factor/);assert.equal(await page.locator('#ha-2fa-code').count(),1);
        const counter=Buffer.alloc(8);counter.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));
        const hash=createHmac('sha1',Buffer.from('48656c6c6f21deadbeef','hex')).update(counter).digest(),offset=hash[19]&15,code=String((hash.readUInt32BE(offset)&0x7fffffff)%1000000).padStart(6,'0');
        await page.locator('#ha-2fa-code').fill(code);await page.locator('form:has(#ha-2fa-code) button[type=submit]').click();await page.waitForLoadState('networkidle');assert.doesNotMatch(page.url(),/login\/two_factor/);
        const authorized=await authorize(page,'openid altus.read') as any;assert.ok(authorized.tokens);const connected=await connect(authorized.tokens.access_token);assert.equal((await call(connected,'altus_health',{})).native,'ready');await connected.close();
        const [audit]=await db.execute<mysql.RowDataPacket[]>("SELECT id FROM ha_audit_log WHERE action='auth.2fa_passed' AND entity_id=? ORDER BY id DESC LIMIT 1",[account]);assert.ok(audit[0]);
      } finally {
        await db.execute('DELETE FROM ha_user_2fa WHERE user_id=?',[account]);
        if(old[0]) {const row=old[0],columns=Object.keys(row);await db.execute('INSERT INTO ha_user_2fa ('+columns.join(',')+') VALUES ('+columns.map(()=>'?').join(',')+')',columns.map(k=>row[k]));}
      }
    });
  } finally {await browser.close();await db.end();callbackServer.close();gateway.kill();phpServer.kill();await rm(folder,{recursive:true,force:true});}
});
