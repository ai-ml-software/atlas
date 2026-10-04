import { SignJWT, jwtVerify, decodeProtectedHeader } from 'jose';
import { createHash } from 'node:crypto';
export const digest=(value:string)=>createHash('sha256').update(value).digest('hex');
/** Delegated native credentials never live longer than this (seconds). */
export const MAX_DELEGATED_TTL=60;
export function allowedScopes(permissions: string[], system: boolean) {
  const scopes=['altus.read'];
  if (permissions.some(p=>['cms_pages.update','articles.update','knowledge.create','programs.update','learning_paths.update'].includes(p))) scopes.push('altus.content.write');
  if (permissions.some(p=>['courses.create','courses.update'].includes(p))) scopes.push('altus.course.write');
  if (permissions.some(p=>['cms_pages.update','courses.update','lessons.update'].includes(p))) scopes.push('altus.media.write');
  if (permissions.some(p=>['cms_pages.publish','courses.publish','articles.publish'].includes(p))) scopes.push('altus.publish');
  if (system && permissions.includes('system.manage')) scopes.push('altus.admin');
  return scopes;
}
export type KeyRing={kid:string,secret:string,previous?:{kid:string,secret:string}};
/**
 * HS256 signer with key ids. The current key signs; current and previous keys
 * verify, so ALTUS_MCP_SECRET can be rotated without downtime.
 */
export function signer(keys:string|KeyRing,issuer:string) {
  const ring:KeyRing=typeof keys==='string'?{kid:'current',secret:keys}:keys;
  const enc=new TextEncoder(), current=enc.encode(ring.secret);
  const verifyKeys=new Map<string,Uint8Array>([[ring.kid,current]]);
  if (ring.previous && ring.previous.secret.length>=32) verifyKeys.set(ring.previous.kid,enc.encode(ring.previous.secret));
  return {
    kid: ring.kid,
    /** ttl is capped at 60s except for the browser consent binding (audience altus-login, ≤300s). */
    sign: (aud:string,payload:Record<string,unknown>,ttl=MAX_DELEGATED_TTL)=>{
      const cap=aud==='altus-login'?300:MAX_DELEGATED_TTL; const life=Math.min(ttl,cap);
      return new SignJWT(payload).setProtectedHeader({alg:'HS256',typ:'JWT',kid:ring.kid}).setIssuer(issuer).setAudience(aud).setIssuedAt().setExpirationTime(Math.floor(Date.now()/1000)+life).sign(current);
    },
    verify: async (token:string,aud:string)=>{
      const kid=decodeProtectedHeader(token).kid; const key=kid===undefined?current:verifyKeys.get(String(kid));
      if (!key) throw new Error('Unknown signing key id.');
      return (await jwtVerify(token,key,{issuer,audience:aud,algorithms:['HS256']})).payload;
    },
  };
}
