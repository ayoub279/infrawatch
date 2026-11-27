import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
const base = process.env.SMOKE_URL || 'http://localhost:8080';
const env = Object.fromEntries(readFileSync(new URL('../.env',import.meta.url),'utf8').split(/\r?\n/).filter(l=>l && !l.startsWith('#')).map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];}));
const password = env.DEMO_PASSWORD;
assert.ok(password,'Seed demo accounts first');
function client() {
 const cookies = new Map();
 return async (path,method='GET',body,expected=200) => {
  const headers = {Accept:'application/json',Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')};
  if(body) headers['Content-Type']='application/json';
  if(cookies.has('XSRF-TOKEN')) headers['X-XSRF-TOKEN']=decodeURIComponent(cookies.get('XSRF-TOKEN'));
  const r=await fetch(base+'/api'+path,{method,headers,body:body?JSON.stringify(body):undefined,redirect:'manual'});
  for(const cookie of r.headers.getSetCookie()){const pair=cookie.split(';')[0];const i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}
  // Do not echo response bodies: an unexpected error could contain sensitive context.
  assert.equal(r.status,expected,method+' '+path+' returned '+r.status);
  return r.status===204?null:r.json();
 };
}
const admin=client(),viewer=client(),guest=client();
await guest('/services','GET',null,401);
assert.equal((await guest('/health')).status,'ok');
await admin('/csrf');await admin('/login','POST',{email:'admin@infrawatch.test',password});
await viewer('/csrf');await viewer('/login','POST',{email:'viewer@infrawatch.test',password});
const payload={name:'CI smoke canary',url:'https://example.com/',enabled:true};
const service=await admin('/services','POST',payload,201);
await admin('/services/'+service.id,'PUT',{...payload,name:'CI verified canary'},204);
await viewer('/services','POST',payload,403);
await viewer('/audit','GET',null,403);
try {
 for(let pass=0;pass<2;pass++) execFileSync('docker',['compose','exec','-T','worker','php','artisan','monitor:check'],{stdio:'pipe'});
 const checks=await viewer('/services/'+service.id+'/checks');
 assert.ok(checks.length>=2,'Monitoring must persist actual results');
 assert.ok(Array.isArray(checks[0].dns_addresses),'DNS result must be exposed as an array');
 const active=(await viewer('/incidents')).filter(i=>i.service_id===service.id && i.status!=='resolved');
 assert.ok(active.length<=1,'No duplicate open incidents');
 if(!checks[0].available) {
  assert.equal(active.length,1,'Failure must open an incident');
  await viewer('/incidents/'+active[0].id,'PATCH',{action:'acknowledge'},204);
  await viewer('/incidents/'+active[0].id,'PATCH',{action:'resolve'},204);
 }
 await viewer('/logout','POST',{},204);
 await viewer('/services','GET',null,401);
} finally {
 await admin('/services/'+service.id,'DELETE',null,204);
}
console.log('HTTP smoke passed: sessions, roles, CRUD, real checks and incident invariant.');
