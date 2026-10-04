import {generateKeyPair,exportJWK} from 'jose';
import {writeFile} from 'node:fs/promises';
const {privateKey}=await generateKeyPair('RS256',{extractable:true});
const jwk=await exportJWK(privateKey);jwk.use='sig';jwk.alg='RS256';jwk.kid='altus-oauth-1';
await writeFile('private-jwks.json',JSON.stringify({keys:[jwk]}),{flag:'wx',mode:0o600});
console.log('Created private-jwks.json. Keep this signing key private and persistent.');
