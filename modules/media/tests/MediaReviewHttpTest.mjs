import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {mkdtemp,mkdir,writeFile,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,dirname} from 'node:path';
import {createHash} from 'node:crypto';
const php=code=>execFileSync('php',['-r',code],{encoding:'utf8'}).trim();
const snapshot=()=>JSON.parse(execFileSync('php',['modules/media/tests/MediaReviewReadSnapshot.php'],{encoding:'utf8'}));
const before=snapshot(),id='42adffaa-3344-47cf-9bf5-6831c969cbd5';
const files=JSON.parse(php(`require 'api/_lib/db.php';$s=mxmed_pdo()->prepare('SELECT * FROM media_review_files WHERE submission_id=?');$s->execute(['${id}']);echo json_encode($s->fetchAll());`));
const review=files.find(f=>f.role==='REVIEW'),source=files.find(f=>f.role==='SOURCE');
const sessions={},servers=[];let root;
const createSession=kind=>php(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=${kind==='physician'?"['user_id'=>'mr2_physician','doctor_id'=>'1','role'=>'administrator','capabilities'=>['media_review_read']]":`['media_review_dev_operator'=>['account_id'=>'mr2_local_operator','expires_at'=>time()+${kind==='expired'?'-1':'300'},'session_status'=>'${kind==='revoked'?'revoked':kind==='invalid'?'invalid':'active'}','account_active'=>true,'review_read_granted'=>${kind==='missing'?'false':'true'}]]`};echo session_id();session_write_close();`);
async function server(port,overrides={}){
 const env={...process.env,APP_ENV:'local',MXMED_ENV:'local',MXMED_ENVIRONMENT:'local',ENVIRONMENT:'local',MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED:'1',...overrides};
 const child=spawn('php',['-S',`127.0.0.1:${port}`,'-t','.'],{env,stdio:['ignore','ignore','pipe']});servers.push(child);let errors='';child.stderr.on('data',d=>errors+=d);
 await new Promise((resolve,reject)=>{let tries=0;const tick=setInterval(async()=>{if(child.exitCode!==null||++tries>100){clearInterval(tick);reject(new Error('QA server failed '+errors));return;}try{await fetch(`http://127.0.0.1:${port}/api/internal/media-review/submission.php`);clearInterval(tick);resolve();}catch{}},50);});
 return `http://127.0.0.1:${port}`;
}
async function request(base,route,session=null,suffix=`submission_id=${id}`,method='GET',extra={}){
 const r=await fetch(`${base}/api/internal/media-review/${route}.php?${suffix}`,{method,headers:{...(session?{Cookie:'PHPSESSID='+session}:{}),...extra}});
 const bytes=Buffer.from(await r.arrayBuffer());
 assert.match(r.headers.get('cache-control'),/private, no-store/);assert.equal(r.headers.get('x-content-type-options'),'nosniff');assert.equal(r.headers.get('access-control-allow-origin'),null);
 return {status:r.status,headers:r.headers,bytes};
}
function denied(r,status=403){assert.equal(r.status,status,r.bytes.toString());assert.ok(!r.headers.get('content-type').startsWith('image/'));assert.ok(!r.bytes.subarray(0,4).equals(Buffer.from('RIFF')));}
try{
 for(const kind of ['good','physician','missing','expired','revoked','invalid'])sessions[kind]=createSession(kind);
 const base=await server(8098);
 for(const route of ['submission','review-image']){
  for(const kind of [null,'physician','missing','expired','revoked','invalid']){
   const valid=await request(base,route,kind?sessions[kind]:null);denied(valid);
   const unknown=await request(base,route,kind?sessions[kind]:null,'submission_id=11111111-1111-4111-8111-111111111111');denied(unknown);assert.deepEqual(valid.bytes,unknown.bytes);
  }
  denied(await request(base,route,null,`submission_id=${id}&capability=media_review_read`,'GET',{'X-User-Id':'mr2_local_operator','X-Role':'internal_operator','X-Capability':'media_review_read'}));
  denied(await request(base,route,sessions.good,'submission_id=11111111-1111-4111-8111-111111111111'),404);
  for(const key of ['role=SOURCE','storage_key='+encodeURIComponent(source.storage_key),'path='+encodeURIComponent(source.storage_key)])denied(await request(base,route,sessions.good,`submission_id=${id}&${key}`),400);
  for(const method of ['POST','DELETE','PUT'])denied(await request(base,route,sessions.good,`submission_id=${id}`,method),405);
 }
 const metadata=await request(base,'submission',sessions.good);assert.equal(metadata.status,200);const text=metadata.bytes.toString(),data=JSON.parse(text).data;
 assert.equal(data.review.role,'REVIEW');assert.equal(data.review_status,'PENDING_REVIEW');assert.equal(data.technical_status,'READY');
 for(const secret of ['storage_key','public_url','/source/','SOURCE','checksum',source.checksum_sha256,source.storage_key,review.storage_key,process.env.HOME])assert.ok(!text.includes(secret),'metadata leak');
 const binary=await request(base,'review-image',sessions.good);assert.equal(binary.status,200);assert.equal(binary.headers.get('content-type'),'image/webp');assert.equal(+binary.headers.get('content-length'),binary.bytes.length);assert.equal(createHash('sha256').update(binary.bytes).digest('hex'),review.checksum_sha256);
 for(const file of files)for(const key of [file.file_id,file.storage_key,encodeURIComponent(file.storage_key)])assert.equal((await fetch(base+'/api/media/index.php/public/'+key)).status,404);
 assert.equal((await fetch(base+'/api/media/index.php/public/'+id)).status,404);
 const publicHtml=await (await fetch(base+'/profiles/doctor.php?doctor_id=1&mxmed_plan=standard')).text();assert.ok(publicHtml.includes(before.photo));assert.ok(!publicHtml.includes(id));
 // Separate disposable private root: never mutate the retained candidate files.
 root=await mkdtemp(join(tmpdir(),'mr2-private-fault-'));const faultBase=await server(8099,{MXMED_PRIVATE_MEDIA_ROOT:root});
 denied(await request(faultBase,'review-image',sessions.good),500);
 const fakePath=join(root,review.storage_key);await mkdir(dirname(fakePath),{recursive:true,mode:0o700});await writeFile(fakePath,Buffer.alloc(+review.byte_size,120),{mode:0o600});
 denied(await request(faultBase,'review-image',sessions.good),500);
 const prod=await server(8100,{APP_ENV:'production'});denied(await request(prod,'review-image',sessions.good));
 const disabled=await server(8101,{MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED:''});denied(await request(disabled,'submission',sessions.good));
 assert.deepEqual(snapshot(),before,'Public/private rows or files changed');
 console.log('PASS: real HTTP trusted reviewer metadata/exact REVIEW; anonymous/physician/no-capability/expired/revoked/invalid/header denial; anti-enumeration; SOURCE manipulation denied; missing/tampered zero image bytes; production/default deny; exact public and candidate snapshots unchanged');
 console.log('PUBLIC_BASELINE='+JSON.stringify({photo:before.photo,counts:before.counts}));
}finally{
 for(const child of servers)child.kill();
 for(const sid of Object.values(sessions))php(`session_id('${sid}');session_start();session_destroy();`);
 if(root)await rm(root,{recursive:true,force:true});
}
