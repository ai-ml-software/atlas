import express from 'express';
import Provider from 'oidc-provider';
import mysql from 'mysql2/promise';
import { McpServer } from '@modelcontextprotocol/server';
import { NodeStreamableHTTPServerTransport, hostHeaderValidation } from '@modelcontextprotocol/node';
import * as z from 'zod/v4';
import { adapter } from './adapter.js';
import { allowedScopes, digest, signer } from './security.js';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';

const issuer=(process.env.ALTUS_MCP_URL??'http://127.0.0.1:3100').replace(/\/$/,'');
const resource=issuer+'/mcp', secret=process.env.ALTUS_MCP_SECRET??'';
if (secret.length<32) throw new Error('Set ALTUS_MCP_SECRET to a random secret of at least 32 characters.');
const native=(process.env.ALTUS_NATIVE_URL??'http://localhost/atlas/atlas').replace(/\/$/,'');
if (process.env.NODE_ENV==='production' && (!issuer.startsWith('https://') || !native.startsWith('https://'))) throw new Error('Production gateway and native URLs require HTTPS.');
const pool=mysql.createPool({host:process.env.ALTUS_DB_HOST??'127.0.0.1',user:process.env.ALTUS_DB_USER??'root',password:process.env.ALTUS_DB_PASSWORD??'',database:process.env.ALTUS_DB_NAME??'atlas_merged',connectionLimit:5});
const enabled=process.env.ALTUS_MCP_ENABLED==='1';
const previous=process.env.ALTUS_MCP_SECRET_PREVIOUS??'';
const jwt=signer({kid:process.env.ALTUS_MCP_KEY_ID??'current',secret,previous:previous.length>=32?{kid:process.env.ALTUS_MCP_KEY_ID_PREVIOUS??'previous',secret:previous}:undefined},issuer);
const scopes=['altus.read','altus.content.write','altus.course.write','altus.media.write','altus.publish','altus.admin'];
const clients=JSON.parse(process.env.ALTUS_OAUTH_CLIENTS??'[]');
const jwks=JSON.parse(readFileSync(process.env.ALTUS_OAUTH_JWKS_FILE??'private-jwks.json','utf8'));
const provider=new Provider(issuer+'/oauth',{
  adapter:adapter(pool),clients,jwks,
  cookies:{keys:(process.env.ALTUS_COOKIE_KEYS??digest(secret+'oauth-cookie')).split(',')},
  scopes:['openid','offline_access',...scopes],
  pkce:{required:()=>true},
  clientDefaults:{grant_types:['authorization_code','refresh_token'],response_types:['code'],token_endpoint_auth_method:'none'},
  features:{devInteractions:{enabled:false},revocation:{enabled:true},introspection:{enabled:true},resourceIndicators:{enabled:true,defaultResource:()=>resource,useGrantedResource:()=>true,getResourceServerInfo:async (_ctx,indicator)=>{if(indicator!==resource) throw new Error('Invalid resource audience.');return {scope:scopes.join(' '),audience:resource,accessTokenFormat:'opaque'};}}},
  interactions:{url:(_ctx,interaction)=>issuer+'/interaction/'+interaction.uid},
  ttl:{AccessToken:300,AuthorizationCode:60,RefreshToken:86400,Grant:86400,Session:86400,Interaction:600},
  rotateRefreshToken:()=>true,
  findAccount:async (_ctx,id)=>{
    const [rows]=await pool.execute<mysql.RowDataPacket[]>('SELECT id,status FROM users WHERE id=?',[Number(id)]);
    const [profiles]=await pool.execute<mysql.RowDataPacket[]>('SELECT status FROM ha_profile WHERE user_id=?',[Number(id)]);
    if(!rows[0] || Number(rows[0].status)!==1 || (profiles[0] && profiles[0].status!=='active')) return undefined;
    return {accountId:id,claims:async()=>({sub:id})};
  },
});
provider.proxy=process.env.ALTUS_TRUST_PROXY==='1';
const app=express(); app.disable('x-powered-by'); if(provider.proxy) app.set('trust proxy',1);
const hostGuard=hostHeaderValidation([new URL(issuer).hostname]);
app.use((req,res,next)=>{if(hostGuard(req,res))next();});
app.get('/health',(_req,res)=>res.json({status:enabled?'ready':'disabled',service:'ALTUS publisher MCP',mcp_enabled:enabled,key_id:jwt.kid}));
const metadata={resource,authorization_servers:[issuer+'/oauth'],scopes_supported:scopes,bearer_methods_supported:['header'],resource_name:'ALTUS Publishing'};
app.get(['/.well-known/oauth-protected-resource','/.well-known/oauth-protected-resource/mcp'],(_req,res)=>res.json(metadata));
app.get('/.well-known/oauth-authorization-server/oauth',async (_req,res)=>{const r=await fetch(issuer+'/oauth/.well-known/openid-configuration');res.json(await r.json());});
app.get('/interaction/:uid',async(req,res,next)=>{
  try { if(!enabled) throw new Error('ALTUS MCP is switched off.'); const details=await provider.interactionDetails(req,res); if(details.uid!==req.params.uid) throw new Error('Interaction mismatch.');
    const binding=await jwt.sign('altus-login',{uid:details.uid,client_id:details.params.client_id,scope:details.params.scope??'altus.read',params_hash:digest(JSON.stringify(details.params))},300);
    res.redirect(native+'/hkp/cms/mcp_login?binding='+encodeURIComponent(binding));
  } catch(e){next(e);}
});
app.get('/interaction/:uid/decline',async(req,res,next)=>{try{const d=await provider.interactionDetails(req,res);if(d.uid!==req.params.uid)throw new Error('Interaction mismatch.');await provider.interactionFinished(req,res,{error:'access_denied',error_description:'The ALTUS user declined access.'},{mergeWithLastSubmission:false});}catch(e){next(e);}});
app.get('/interaction/:uid/complete',async(req,res,next)=>{
  try { const details=await provider.interactionDetails(req,res),binding=String(req.query.binding??''),claims=await jwt.verify(binding,'altus-login');
    if(details.uid!==req.params.uid || claims.uid!==details.uid || claims.params_hash!==digest(JSON.stringify(details.params))) throw new Error('Interaction binding mismatch.');
    const body=JSON.stringify({code:String(req.query.code??''),binding});
    const credential=await jwt.sign('altus-login-exchange',{body_hash:digest(body)});
    const r=await fetch(native+'/api/publisher/v1/bridge',{method:'POST',headers:{Authorization:'Bearer '+credential,'Content-Type':'application/json'},body,signal:AbortSignal.timeout(15000)});
    const result=await r.json() as any;if(!result.success)throw new Error(result.error?.message??'ALTUS login exchange failed.');
    const accountId=String(result.data.user_id),allowed=allowedScopes(result.data.permissions??[],(result.data.permissions??[]).includes('system.manage'));
    const requested=String(details.params.scope??'altus.read').split(' ').filter(Boolean);
    if(requested.some(s=>!['openid','offline_access',...allowed].includes(s))) throw new Error('Requested scopes exceed your current ALTUS permissions.');
    const grant=new provider.Grant({accountId,clientId:String(details.params.client_id)});
    grant.addOIDCScope(requested.join(' '));
    grant.addResourceScope(resource,requested.filter(s=>scopes.includes(s)).join(' '));
    const grantId=await grant.save();
    await provider.interactionFinished(req,res,{login:{accountId},consent:{grantId}},{mergeWithLastSubmission:false});
  } catch(e){next(e);}
});
app.use('/oauth',provider.callback());
app.use('/mcp',express.json({limit:'22mb'}));
const counters=new Map<string,{count:number,until:number}>();
const type=z.enum(['page','publisher','courses','articles','topics','programs','paths','site','navigation']);
const tools=[
  ['altus_health','health',z.object({}),true],
  ['altus_list_objects','list',z.object({type,query:z.string().max(100).optional(),page:z.number().int().positive().optional()}),true],
  ['altus_get_object','get',z.object({type,id:z.number().int().nonnegative()}),true],
  ['altus_create_draft','create',z.object({type:z.enum(['courses','articles','topics','programs','paths']),payload:z.record(z.string(),z.unknown()),request_key:z.string().min(8).max(100)}),false],
  ['altus_save_draft','save',z.object({type,id:z.number().int().nonnegative(),version:z.number().int().nonnegative(),base_hash:z.string().optional(),payload:z.record(z.string(),z.unknown()),request_key:z.string().min(8).max(100)}),false],
  ['altus_list_media','media_list',z.object({query:z.string().max(100).optional()}),true],
  ['altus_upload_media','media',z.object({name:z.string().max(255),base64:z.string().max(7*1024*1024),alt_en:z.string().max(255).optional(),alt_ar:z.string().max(255).optional(),request_key:z.string().min(8).max(100)}),false],
  ['altus_upload_document','upload_document',z.object({name:z.string().max(255),base64:z.string().max(21*1024*1024),target:z.enum(['course','page','article','sop','topic']),locale:z.enum(['en','ar']),request_key:z.string().min(8).max(100)}),false],
  ['altus_source_document','source',z.object({source:z.string().min(30).max(120000),name:z.string().max(255),target:z.enum(['course','page','article','sop','topic']),locale:z.enum(['en','ar']),request_key:z.string().min(8).max(100)}),false],
  ['altus_generate_draft','generate',z.object({id:z.number().int().positive(),version:z.number().int().positive(),provider:z.string(),model:z.string(),brief:z.string().max(2000).optional(),selection:z.string().regex(/^(sections|modules|quiz|lesson):\d+(?::\d+)?$/).optional(),request_key:z.string().min(8).max(100)}),false],
  ['altus_translate_draft','translate',z.object({id:z.number().int().positive(),version:z.number().int().positive(),locale:z.enum(['en','ar']),provider:z.string(),model:z.string(),request_key:z.string().min(8).max(100)}),false],
  ['altus_correct_source','source_correction',z.object({id:z.number().int().positive(),version:z.number().int().positive(),source:z.string().min(30).max(120000),request_key:z.string().min(8).max(100)}),false],
  ['altus_approval_status','approval_status',z.object({id:z.number().int().positive()}),true],
  ['altus_validate_package','validate',z.object({target:z.enum(['course','page','article','sop','topic']),payload:z.record(z.string(),z.unknown())}),true],
  ['altus_import_package','import',z.object({id:z.number().int().positive(),version:z.number().int().positive(),request_key:z.string().min(8).max(100)}),false],
  ['altus_document_status','job_status',z.object({id:z.number().int().positive()}),true],
  ['altus_document_control','job_control',z.object({id:z.number().int().positive(),operation:z.enum(['cancel','retry']),request_key:z.string().min(8).max(100)}),false],
  ['altus_request_publish','request_publish',z.object({type,id:z.number().int().nonnegative(),operation:z.enum(['publish','archive']),request_key:z.string().min(8).max(100)}),false],
  ['altus_publish_approved','publish',z.object({approval_id:z.number().int().positive(),type:type.optional(),id:z.number().int().nonnegative().optional(),operation:z.enum(['publish','archive']).optional(),version:z.number().int().nonnegative().optional(),request_key:z.string().min(8).max(100)}),false],
  ['altus_restore_archived','unarchive',z.object({type,id:z.number().int().positive(),request_key:z.string().min(8).max(100)}),false],
  ['altus_restore_revision','restore',z.object({type,id:z.number().int().nonnegative(),revision:z.number().int().positive(),version:z.number().int().nonnegative(),base_hash:z.string(),request_key:z.string().min(8).max(100)}),false],
] as const;
app.all('/mcp',async(req,res,next)=>{
  const challenge=`Bearer resource_metadata="${issuer}/.well-known/oauth-protected-resource/mcp", scope="altus.read"`;
  try {
    if(!enabled) return void res.status(503).json({error:'mcp_disabled',message:'ALTUS MCP is switched off (ALTUS_MCP_ENABLED).'});
    if(req.headers.origin && req.headers.origin!==issuer) return void res.status(403).json({error:'untrusted_origin'});
    const header=req.headers.authorization??''; if(!header.startsWith('Bearer ') || req.query.access_token) return void res.set('WWW-Authenticate',challenge).status(401).json({error:'invalid_token'});
    const token=await provider.AccessToken.find(header.slice(7)); if(!token || token.isExpired || token.aud!==resource || !token.accountId || !token.grantId) return void res.set('WWW-Authenticate',challenge).status(401).json({error:'invalid_token'});
    const grant=await provider.Grant.find(token.grantId); const [active]=await pool.execute<mysql.RowDataPacket[]>('SELECT u.id FROM users u LEFT JOIN ha_profile p ON p.user_id=u.id WHERE u.id=? AND u.status=1 AND (p.status IS NULL OR p.status=?)',[Number(token.accountId),'active']); const account=active[0];
    if(!grant || !account) return void res.set('WWW-Authenticate',challenge).status(401).json({error:'revoked_identity'});
    const key=token.accountId+':'+token.clientId,now=Date.now(),counter=counters.get(key); if(counter && counter.until>now && counter.count>=120) return void res.set('Retry-After','60').status(429).json({error:'rate_limited'});
    counters.set(key,{count:counter && counter.until>now?counter.count+1:1,until:counter && counter.until>now?counter.until:now+60000}); if(counters.size>10000) for(const [k,v] of counters) if(v.until<now)counters.delete(k);
    const server=new McpServer({name:'altus-publisher',version:'1.0.0'});
    for(const [name,action,schema,readOnly] of tools) server.registerTool(name,{description:`ALTUS ${action}. Uses your current permissions; writes stay private until human approval.`,inputSchema:schema,annotations:{readOnlyHint:readOnly,destructiveHint:action==='publish',idempotentHint:true,openWorldHint:false}},async(args:any)=>{
      const delegated=await jwt.sign('altus-native-publisher',{sub:token.accountId,client_id:token.clientId,scope:token.scope,source_id:token.jti});
      const {request_key,...input}=args; if(['generate','translate','source_correction','import','job_status','job_control'].includes(action))input.type='publisher';
      const requestId=randomUUID();
      const r=await fetch(native+'/api/publisher/v1/'+action,{method:'POST',headers:{Authorization:'Bearer '+delegated,'Content-Type':'application/json','Idempotency-Key':request_key??randomUUID(),'X-Request-Id':requestId},body:JSON.stringify(input),signal:AbortSignal.timeout(action==='generate'?120000:30000)});
      let result:any; try { result=await r.json(); } catch { result={success:false,data:null,error:{code:'native_unavailable',message:'ALTUS returned HTTP '+r.status+' without a JSON body.',details:{},retryable:r.status>=500},request_id:requestId}; }return {content:[{type:'text' as const,text:JSON.stringify(result)}],structuredContent:result,isError:!result.success};
    });
    const transport=new NodeStreamableHTTPServerTransport({sessionIdGenerator:undefined,enableJsonResponse:true});
    res.on('close',()=>{void transport.close();void server.close();}); await server.connect(transport); await transport.handleRequest(req,res,req.body);
  } catch(e){next(e);}
});
app.use((err:Error,_req:express.Request,res:express.Response,_next:express.NextFunction)=>{console.error(err.message);if(!res.headersSent)res.status(400).json({error:'request_failed',message:err.message});});
const port=Number(process.env.PORT??3100); const listener=app.listen(port,process.env.HOST??'127.0.0.1',()=>console.log(`ALTUS MCP listening on ${issuer}`));
for(const sig of ['SIGTERM','SIGINT'])process.on(sig,()=>listener.close(()=>{void pool.end();process.exit(0);}));
