import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {cp,mkdir,mkdtemp,writeFile,readFile,rm} from 'node:fs/promises';
import {runBatchVisualQA} from './ReviewBatchVisual.mjs';
const fixtureRoot=process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr11qa';
const env={...process.env,MR5_FIXTURE_ROOT:fixtureRoot,MR3_TEST_DB:'mxmed_gate4d_preview_mr3_mr8_'+Date.now(),MR3_TEST_DB_HOST:'127.0.0.1',MR3_TEST_DB_PORT:'3309',MR3_TEST_DB_USER:'root',MR3_TEST_DB_PASS:'',MR3_VALKEY_PORT:'6387'};
const php=(code)=>execFileSync('php',['-r',code],{env,encoding:'utf8'}).trim();
const sql=(code)=>php('require "modules/media/tests/ProfilePhotoApprovalFixture.php";$p=mr5Pdo();'+code);
const candidate=(kind='white',w=600,h=300)=>JSON.parse(execFileSync('php',['modules/media/tests/LogoImprovementFixture.php','candidate',kind,String(w),String(h)],{env,encoding:'utf8'}));
const snapshot=()=>execFileSync('php',['modules/media/tests/MediaReviewReadSnapshot.php'],{encoding:'utf8'}).trim();
const before=snapshot();let child,root,identity;
try {
 identity=JSON.parse(execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php','setup'],{env,encoding:'utf8'}));
 // Dedicated synthetic identity database. No real grants are created.
 php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');foreach(['good','inactive','blocked','expired','revoked_session','superseded','credential'] as $k){$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_".$k."','media_review_approve','ACTIVE')");}$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_wrong','media_review_read','ACTIVE')");`);
 root=await mkdtemp('/tmp/mxmed-mr8-http-');
 // Copy executable sources so the app's media PDO points ONLY at disposable MySQL.
 for(const dir of ['modules','api','internal'])await cp(dir,root+'/'+dir,{recursive:true,filter:src=>!src.endsWith('mxmed-db.config.php')});
 await mkdir(root+'/assets/js',{recursive:true});await mkdir(root+'/assets/css',{recursive:true});
 for(const file of ['assets/js/media-review-inbox.js','assets/css/media-review-inbox.css'])await cp(file,root+'/'+file);
 await writeFile(root+'/api/mxmed-db.config.php',"<?php return ['mysql'=>['host'=>'127.0.0.1','port'=>3309,'dbname'=>'mxmed','user'=>'root','pass'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci']];");
 const base='http://127.0.0.1:8116';
 child=spawn('php',['-d','upload_max_filesize=10M','-d','post_max_size=12M','-d','memory_limit=256M','-S','127.0.0.1:8116','-t',root],{env:{...env,...identity.env,PHP_CLI_SERVER_WORKERS:'4',MXMED_PUBLIC_MEDIA_ROOT:fixtureRoot+'/public',MXMED_PRIVATE_MEDIA_ROOT:fixtureRoot+'/private'},detached:true,stdio:['ignore','ignore','pipe']});
 let errors='';child.stderr.on('data',b=>{errors+=b.toString();});
 for(let i=0;i<100;i++){try{await fetch(base+'/api/internal/media-review/approval-options.php');break;}catch{await new Promise(r=>setTimeout(r,50));}}
 const request=(route,kind='good',init={})=>fetch(base+'/api/internal/media-review/'+route,{...init,headers:{...(kind?{Cookie:'__Host-mxmed_session='+(identity.tokens[kind]||'invalid')}:{}),...init.headers}});
 const options=async(kind='good')=>{const r=await request('approval-options.php',kind);return {status:r.status,...await r.json()};};
 const post=(id,kind,csrf,extra={})=>request('approve-logo.php',kind,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({submission_id:id,...(csrf===undefined?{}:{csrf}),...extra})});
 const grant=(kind,cap)=>php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_${kind}','${cap}','ACTIVE')");`);
 const revoke=(kind,cap)=>php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');$p->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='mr3_${kind}' AND capability='${cap}' AND status='ACTIVE'");`);
 const mutate=(route,id,kind='good',csrf='forged',extra={})=>request(route,kind,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({submission_id:id,csrf,...extra})});

 const batch=(n=16,mixed=true)=>JSON.parse(execFileSync('php',['modules/media/tests/ReviewBatchFixture.php','batch',String(n),mixed?'1':'0'],{env,encoding:'utf8'}));
 const legacy=()=>JSON.parse(execFileSync('php',['modules/media/tests/ReviewBatchFixture.php','legacy'],{env,encoding:'utf8'}));
 const batchSql=code=>sql('require_once "modules/media/tests/ReviewBatchFixture.php";'+code);
 const f=batch(),route=base+'/api/media/review-batch-submit.php';
 const owner=php('session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=["user_id"=>"mr11_owner","doctor_id"=>"'+f.doctor+'"];echo session_id();session_write_close();');
 const ownerRequest=(init={},cookie=owner)=>fetch(route,{...init,headers:{Cookie:'PHPSESSID='+cookie,...init.headers}});
 try{
  assert.equal((await fetch(route)).status,401);
  const state=(await(await ownerRequest()).json()).data;assert.equal(state.item_count,18);assert.equal(state.has_open_batch,true);assert.equal(state.can_submit_now,true);
  assert.equal(Date.parse(state.auto_submit_after.replace(' ','T')+'Z')-Date.parse(state.last_activity_at.replace(' ','T')+'Z'),1800000);
  assert.ok(!/batch_id|owner_id|storage|SOURCE|feedback/.test(JSON.stringify(state)));
  assert.equal((await ownerRequest({method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:'bad'})})).status,403);
  for(const extra of [{owner_id:f.doctor},{batch_id:f.batch_id}])assert.equal((await ownerRequest({method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:state.csrf,...extra})})).status,400);
  assert.equal((await request('batch.php?batch_id='+f.batch_id)).status,404);
  const submit=()=>ownerRequest({method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:state.csrf})});
  assert.equal((await submit()).status,200);assert.equal((await submit()).status,409);
  for(const kind of ['', 'invalid','customer','inactive','blocked','expired','revoked_session','superseded','credential'])for(const path of ['batches.php','batch.php?batch_id='+f.batch_id])assert.equal((await request(path,kind)).status,403);
  const detailResponse=await request('batch.php?batch_id='+f.batch_id);assert.equal(detailResponse.status,200);assert.equal(detailResponse.headers.get('cache-control'),'private, no-store');const detail=(await detailResponse.json()).data;
  assert.equal(detail.items.length,18);assert.ok(!/storage_key|SOURCE|checksum|feedback/.test(JSON.stringify(detail)));
  assert.equal((await request('batches.php?owner_id='+f.doctor)).status,400);
  assert.equal((await request('batches.php?limit=51')).status,400);
  assert.equal((await request('batch.php?batch_id='+f.batch_id,'wrong')).status,200);
  const optionsData=await options();
  for(const purpose of ['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO']){const item=detail.items.find(i=>i.purpose===purpose);assert.equal((await mutate(purpose==='DOCTOR_PROFILE_PHOTO'?'approve.php':'approve-logo.php',item.submission_id,'good',optionsData.csrf)).status,200);}
  const after=(await(await request('batch.php?batch_id='+f.batch_id)).json()).data;assert.equal(after.batch.approved_count,2);assert.equal(after.batch.item_count,18);
  assert.equal((await request('pending.php')).status,200);assert.ok(!(await(await request('pending.php')).json()).data.items.some(i=>detail.items.some(j=>i.submission_id===j.submission_id)));
  batchSql('mr11Candidate("'+f.doctor+'","DOCTOR_PROFILE_PHOTO");');assert.equal((await(await ownerRequest()).json()).data.has_open_batch,true);
 }finally{php('session_id("'+owner+'");session_start();session_destroy();');}
 // Execute the real CLI against the disposable copy, never the Director database.
 const inactive=batch(1,false);batchSql('$p->exec("UPDATE media_review_batches SET last_activity_at=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL 31 MINUTE) WHERE batch_id=\\x27'+inactive.batch_id+'\\x27");');
 const cli=JSON.parse(execFileSync('php',[root+'/modules/media/bin/submit-inactive-review-batches.php','100'],{env,encoding:'utf8'}));assert.ok(cli.submitted>=1);
 assert.equal(JSON.parse(execFileSync('php',[root+'/modules/media/bin/submit-inactive-review-batches.php','100'],{env,encoding:'utf8'})).submitted,0);
 assert.throws(()=>execFileSync('php',[root+'/modules/media/bin/submit-inactive-review-batches.php','101'],{env,stdio:'pipe'}));
 batchSql('$p->exec("UPDATE media_review_submissions SET review_status=\\x27WITHDRAWN\\x27 WHERE review_status=\\x27PENDING_REVIEW\\x27");');
 for(const cap of ['media_review_source_download','media_review_corrected_upload','media_review_request_replacement','media_review_improve'])grant('good',cap);
 await runBatchVisualQA({base,tokens:identity.tokens,batch,legacy,sql:batchSql});
 await writeFile(root+'/api/mxmed-db.config.php', "<?php return ['mysql'=>['host'=>'127.0.0.1','port'=>1,'dbname'=>'unavailable','user'=>'nobody','pass'=>'','charset'=>'utf8mb4']];");
 assert.throws(()=>execFileSync('php',[root+'/modules/media/bin/submit-inactive-review-batches.php'],{env,stdio:'pipe'}));
 assert.equal(snapshot(),before,'Director baseline changed');
 console.log('MR11_HTTP_QA=PASS: canonical read/session authorization, owner scope/CSRF, 18 members, legacy isolation, individual photo/logo publication, real bounded CLI, no Director changes');
}finally{
 if(child){try{process.kill(-child.pid,'SIGTERM');}catch{child.kill();}}
 if(identity)execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php','cleanup'],{env});
 if(root)await rm(root,{recursive:true,force:true});
}
