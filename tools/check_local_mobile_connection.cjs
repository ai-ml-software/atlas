/* Reads the ignored local app-only configuration; never prints its key. */
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const env=Object.fromEntries(fs.readFileSync(path.join(root,'mobile app/altus-mobile/.env'),'utf8').split(/\r?\n/).filter(line=>/^EXPO_PUBLIC_[A-Z_]+=/.test(line)).map(line=>{const index=line.indexOf('=');return [line.slice(0,index),line.slice(index+1)];}));
(async()=>{
 const url=env.EXPO_PUBLIC_PLATFORM_URL,key=env.EXPO_PUBLIC_ALTUS_APP_KEY;
 assert(['localhost','127.0.0.1','10.0.2.2'].includes(new URL(url).hostname),'This local check refuses remote backends.');
 assert(/^altm_[a-f0-9]{12}_[A-Za-z0-9]{40}$/.test(key));
 const config=await fetch(url+'/api/v1/mobile/config',{headers:{'X-App-Key':key},signal:AbortSignal.timeout(10000)});
 assert.equal(config.status,200);const body=await config.json();assert.equal(body.success,true);
 const identity=await fetch(url+'/mobile_api/me',{headers:{Authorization:'Bearer '+key},signal:AbortSignal.timeout(10000)});
 assert.equal(identity.status,401,'An app configuration key must never authenticate a person.');
 for(const file of ['docs/ALTUS-User-Guides.zip','mobile%20app/client-deliverables/android/ALTUS-1.0.1-release.apk'])assert.equal((await fetch(url+'/'+file,{method:'HEAD'})).status,200);
 console.log(`Local ${url}: configuration 200 (v${body.data.version}), configuration key denied personal identity (401), APK and guide pack downloads 200.`);
})().catch(error=>{console.error(error.message);process.exitCode=1;});
