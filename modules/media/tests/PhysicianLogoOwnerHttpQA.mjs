import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFile,rm} from 'node:fs/promises';
export async function runLogoOwnerQA({base,php,sql,candidate,request,options,post,env}) {
 const a=candidate(),b=candidate();const sessions=[];
 const session=(doctor,extra='')=>{const id=php(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'mr7_synthetic_physician','doctor_id'=>'${doctor}'];${extra}echo session_id();session_write_close();`);sessions.push(id);return id;};
 const owner=session(a.doctor),other=session(b.doctor),conflict=session(a.doctor,`$_SESSION['active_doctor_id']='${b.doctor}';`),dev=session(a.doctor,"$_SESSION['subscriptions_dev_session_fixture']=true;");
 const call=(sid,method='GET',csrf='',body,query='',extra={})=>fetch(base+'/api/media/physician-logo-review-candidate.php'+query,{method,body,headers:{...(sid?{Cookie:'PHPSESSID='+sid}:{}),...(csrf?{'X-Physician-Logo-Candidate-CSRF':csrf}:{}),...extra}});
 const source=JSON.parse(execFileSync('php',['modules/media/tests/MediaReviewInterventionFixture.php','modern'],{env,encoding:'utf8'}));
 const form=async(forge=false)=>{const f=new FormData();f.append('image',new Blob([await readFile(source.tmp_name)],{type:source.type}),source.name);if(forge)f.append('doctor_id',b.doctor);return f;};
 const state=()=>sql("echo json_encode([$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll(),$p->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll()]);");
 try {
  const before=state();
  for(const sid of ['',conflict,dev])assert.equal((await call(sid)).status,401);
  assert.equal((await call('','GET','',undefined,'',{'X-Doctor-Id':a.doctor})).status,401);
  const first=await (await call(owner)).json(),token=first.data.csrf_token;
  assert.equal(first.data.candidate.submission_id,a.id);
  assert.equal((await call(owner,'POST','',await form())).status,403);
  assert.equal((await call(owner,'DELETE','forged')).status,403);
  assert.equal((await call(owner,'POST',token,await form(true))).status,400);
  assert.equal((await call(owner,'GET','',undefined,'?doctor_id='+b.doctor)).status,400);
  const upload=await call(owner,'POST',token,await form(),'',{'X-Doctor-Id':b.doctor});
  assert.equal(upload.status,200);const data=(await upload.json()).data.candidate;
  assert.notEqual(data.submission_id,a.id);assert.equal(data.technical_status,'READY');assert.equal(data.review_status,'PENDING_REVIEW');
  assert.ok(!/storage_key|private\/|public_url/.test(JSON.stringify(data)));assert.equal(state(),before);
  assert.equal((await (await call(other)).json()).data.candidate.submission_id,b.id,'cross-owner unchanged');
  for(const file of JSON.parse(sql(`echo json_encode($p->query("SELECT file_id,storage_key FROM media_review_files WHERE submission_id='${data.submission_id}'")->fetchAll());`))){for(const key of [file.file_id,file.storage_key,encodeURIComponent(file.storage_key)])assert.equal((await fetch(base+'/api/media/index.php/public/'+key)).status,404);}
  assert.equal((await fetch(base+'/api/media/index.php/public/'+data.submission_id)).status,404);
  for(const route of ['pending.php','review-image.php?submission_id='+data.submission_id,'source-download.php?submission_id='+data.submission_id,'approval-options.php'])assert.equal((await fetch(base+'/api/internal/media-review/'+route,{headers:{Cookie:'PHPSESSID='+owner,'X-Capability':'media_review_approve'}})).status,403);
  for(const kind of ['','invalid','customer','wrong','revoked_grant','inactive','blocked','expired','revoked_session','superseded','credential'])assert.equal((await post(data.submission_id,kind,'forged')).status,403,'approval authority '+kind);
  const opts=await options();assert.equal((await post(data.submission_id,'good','forged')).status,403);assert.equal((await post(data.submission_id,'good',opts.csrf,{doctor_id:b.doctor})).status,400);
  const photo=JSON.parse(execFileSync('php',['modules/media/tests/ProfilePhotoApprovalFixture.php','candidate'],{env,encoding:'utf8'}));
  assert.equal((await post(photo.id,'good',opts.csrf)).status,409,'logo endpoint rejects photo');
  let purposes=new Set();for(let offset=0;;offset+=50){const result=await (await request('pending.php?limit=50&offset='+offset)).json();for(const row of result.data.items)purposes.add(row.purpose);if(!result.data.pagination.has_more)break;}
  assert.ok(purposes.has('DOCTOR_PROFILE_PHOTO')&&purposes.has('PHYSICIAN_PERSONAL_LOGO'),'mixed inbox HTTP');
  assert.equal((await call(owner,'DELETE',token)).status,200);assert.equal((await (await call(owner)).json()).data.candidate,null);
  console.log('MR7_OWNER_HTTP_QA=PASS: 12MP/near10MiB, strong session/CSRF, forged owner denied, private source unreachable, mixed inbox, physician cannot review, purpose-specific approval');
 } finally {await rm(source.tmp_name,{force:true});for(const sid of sessions)php(`session_id('${sid}');session_start();session_destroy();`);}
}
