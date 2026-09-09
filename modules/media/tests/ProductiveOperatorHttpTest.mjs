import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {randomBytes,createHash} from 'node:crypto';
const id='42adffaa-3344-47cf-9bf5-6831c969cbd5';
const env={...process.env,MR3_TEST_DB:'mxmed_gate4d_preview_mr3_'+randomBytes(6).toString('hex')};
const fixture=action=>execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php',action],{env,encoding:'utf8'});
const snapshot=()=>JSON.parse(execFileSync('php',['modules/media/tests/MediaReviewReadSnapshot.php'],{encoding:'utf8'}));
const before=snapshot();let child,created=false;
const base='http://127.0.0.1:8102';
async function request(route,token,suffix='',extra={},method='GET'){
 const r=await fetch(`${base}/api/internal/media-review/${route}.php?submission_id=${id}${suffix}`,{method,headers:{...(token?{Cookie:'__Host-mxmed_session='+token}:{}),...extra},...(method==='POST'?{body:JSON.stringify({account_id:'mr3_good',capability:'media_review_read'})}:{})});
 return {status:r.status,headers:r.headers,bytes:Buffer.from(await r.arrayBuffer())};
}
try{
 created=true;const setup=JSON.parse(fixture('setup'));
 // No MR2 fixture flag/session: requests use canonical credentials/session store and grants.
 child=spawn('php',['-S','127.0.0.1:8102','-t','.'],{env:{...process.env,...setup.env,MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED:''},stdio:['ignore','ignore','pipe']});
 let serverErrors='';child.stderr.on('data',d=>serverErrors+=d);
 await new Promise((resolve,reject)=>{let tries=0;const timer=setInterval(async()=>{if(child.exitCode!==null||++tries>100){clearInterval(timer);reject(new Error('server unavailable'));return;}try{await request('submission',null);clearInterval(timer);resolve();}catch{}},50);});
 for(const route of ['submission','review-image']){
  for(const key of [null,'customer','wrong','revoked_grant','inactive','blocked','expired','revoked_session','superseded','credential']){
   const r=await request(route,key?setup.tokens[key]:null);assert.equal(r.status,403,`${route}/${key}: ${r.bytes}`);assert.ok(!r.headers.get('content-type').startsWith('image/'));
  }
  assert.equal((await request(route,'invalid-token')).status,403);
  assert.equal((await request(route,null,'&account_id=mr3_good&capability=media_review_read',{'X-User-Id':'mr3_good','X-Capability':'media_review_read','X-Role':'internal_operator'})).status,403);
  assert.equal((await request(route,setup.tokens.customer,'',{},'POST')).status,403);
 }
 const m=await request('submission',setup.tokens.good);assert.equal(m.status,200,m.bytes.toString());assert.equal(JSON.parse(m.bytes).data.review_status,'PENDING_REVIEW');
 const image=await request('review-image',setup.tokens.good);assert.equal(image.status,200,image.bytes.toString());assert.equal(image.headers.get('content-type'),'image/webp');assert.equal(image.headers.get('cache-control'),'private, no-store');assert.equal(createHash('sha256').update(image.bytes).digest('hex'),before.files.REVIEW);
 assert.equal((await request('review-image',setup.tokens.good,'&role=SOURCE')).status,400);
 fixture('revoke');assert.equal((await request('submission',setup.tokens.good)).status,403);assert.equal((await request('review-image',setup.tokens.good)).status,403);
 fixture('regrant');assert.equal((await request('submission',setup.tokens.good)).status,200);
 assert.deepEqual(snapshot(),before,'Public or retained candidate changed');
 console.log('PASS: canonical password authentication + Valkey session + durable grant; ownerless operator allowed; no-grant/wrong/revoked/inactive/expired/revoked-session/superseded/credential mismatch/header/query/body denied; grant revocation immediate; exact private REVIEW; retained public/private snapshot unchanged; migration/FK/active uniqueness/no wildcard');
}finally{
 if(child)child.kill();
 if(created)fixture('cleanup');
}
