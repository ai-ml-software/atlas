import test from 'node:test';
import assert from 'node:assert/strict';
import { decodeJwt, decodeProtectedHeader } from 'jose';
import { allowedScopes, signer, MAX_DELEGATED_TTL } from '../src/security.js';
test('delegated credentials carry a key id, are capped at 60s and survive key rotation',async()=>{
  const issuer='http://127.0.0.1:3100',oldKey='o'.repeat(48),newKey='n'.repeat(48);
  const before=signer({kid:'k1',secret:oldKey},issuer); const issued=await before.sign('altus-native-publisher',{sub:'7'},3600);
  assert.equal(decodeProtectedHeader(issued).kid,'k1'); const c=decodeJwt(issued); assert.ok((c.exp??0)-(c.iat??0)<=MAX_DELEGATED_TTL);
  const login=decodeJwt(await before.sign('altus-login',{},3600)); assert.equal((login.exp??0)-(login.iat??0),300);
  const rotated=signer({kid:'k2',secret:newKey,previous:{kid:'k1',secret:oldKey}},issuer);
  assert.equal((await rotated.verify(issued,'altus-native-publisher')).sub,'7','previous key still verifies');
  assert.equal(decodeProtectedHeader(await rotated.sign('altus-native-publisher',{})).kid,'k2','current key signs');
  await assert.rejects(()=>signer({kid:'k2',secret:newKey},issuer).verify(issued,'altus-native-publisher'),'retired key rejected');
  const forged=await signer({kid:'k2',secret:oldKey},issuer).sign('altus-native-publisher',{});
  await assert.rejects(()=>rotated.verify(forged,'altus-native-publisher'),'kid must match its own secret');
});
test('OAuth scopes never add content, publication or administration to a learner',()=>{
  assert.deepEqual(allowedScopes([],false),['altus.read']);
  assert.deepEqual(allowedScopes(['courses.create'],false),['altus.read','altus.course.write']);
  assert.ok(!allowedScopes(['cms_pages.update'],true).includes('altus.admin'));
});
test('delegation signatures reject wrong audience, issuer, key and expiry',async()=>{
  const jwt=signer('a'.repeat(48),'http://127.0.0.1:3100');
  const token=await jwt.sign('altus-native-publisher',{sub:'42'});
  assert.equal((await jwt.verify(token,'altus-native-publisher')).sub,'42');
  await assert.rejects(()=>jwt.verify(token,'other-resource'));
  await assert.rejects(()=>signer('b'.repeat(48),'http://127.0.0.1:3100').verify(token,'altus-native-publisher'));
  await assert.rejects(()=>signer('a'.repeat(48),'http://other').verify(token,'altus-native-publisher'));
  const awaitToken=await jwt.sign('altus-native-publisher',{},-1);
  await assert.rejects(()=>jwt.verify(awaitToken,'altus-native-publisher'));
});
