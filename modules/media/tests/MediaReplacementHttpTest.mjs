import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {cp,mkdir,mkdtemp,writeFile,readFile,rm} from 'node:fs/promises';
import {runReplacementVisualQA} from './MediaReplacementVisual.mjs';
const fixtureRoot=process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr9qa';
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
 const fixture=kind=>JSON.parse(execFileSync('php',['modules/media/tests/MediaReplacementFixture.php','candidate',kind],{env,encoding:'utf8'}));
 const replace=(f,kind='good',csrf='forged',extra={})=>mutate('request-replacement.php',f.id,kind,csrf,{reason_code:'WRONG_MEDIA_TYPE',feedback:'Por favor envía otra imagen.',...extra});
 const f=fixture('logo');
 for(const kind of ['', 'invalid','customer','wrong','good','revoked_grant','inactive','blocked','expired','revoked_session','superseded','credential'])assert.equal((await replace(f,kind)).status,403,'denied '+kind);
 for(const kind of ['inactive','blocked','expired','revoked_session','superseded','credential']){grant(kind,'media_review_request_replacement');assert.equal((await request('request-replacement.php',kind,{method:'GET'})).status,403,'session/account rejected before method and CSRF '+kind);}
 grant('wrong','media_review_improve');assert.equal((await replace(f,'wrong')).status,403);revoke('wrong','media_review_improve');
 grant('wrong','media_review_request_replacement');const onlyOpts=await options('wrong');const onlyItem=fixture('photo');assert.equal((await replace(onlyItem,'wrong',onlyOpts.csrf)).status,200);revoke('wrong','media_review_request_replacement');assert.equal((await replace(onlyItem,'wrong',onlyOpts.csrf)).status,403);
 grant('good','media_review_request_replacement');let opts=await options();assert.equal(opts.can_request_replacement,true);
 assert.equal((await replace(f,'good','invalid')).status,403);
 for(const extra of [{reason_code:'UNKNOWN'},{reason_code:''},{feedback:[]},{feedback:null},{feedback:'á'.repeat(401)},...['doctor_id','owner_id','review_status','technical_status','role','capability','storage_key'].map(k=>({[k]:'forged'}))])assert.equal((await replace(f,'good',opts.csrf,extra)).status,400);
 const publicState=()=>sql("echo json_encode([$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll(),$p->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll()]);");
 const row=id=>JSON.parse(sql(`echo json_encode($p->query("SELECT * FROM media_review_submissions WHERE submission_id='${id}'")->fetch());`));
 let beforePublic=publicState(),beforeRow=row(f.id);
 sql("$p->exec(\"CREATE TRIGGER mr9_http_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'\");");
 try{assert.equal((await replace(f,'good',opts.csrf)).status,503);assert.deepEqual(row(f.id),beforeRow);assert.equal(publicState(),beforePublic);}finally{sql("$p->exec('DROP TRIGGER mr9_http_audit_failure');");}
 revoke('good','media_review_request_replacement');assert.equal((await replace(f,'good',opts.csrf)).status,403);grant('good','media_review_request_replacement');
 for(const kind of ['photo','logo']){
  const item=fixture(kind);const photo=kind==='photo';beforePublic=publicState();const feedback='Por favor envía únicamente la imagen. <script>alert(1)</script>';
  const r=await replace(item,'good',opts.csrf,{feedback});assert.equal(r.status,200,errors);assert.equal((await r.json()).review_status,'NEEDS_WORK');assert.equal(publicState(),beforePublic);
  const done=row(item.id);assert.equal(done.review_feedback,feedback);assert.equal(done.review_reason_code,'WRONG_MEDIA_TYPE');assert.ok(done.review_decided_at);
  assert.equal((await replace(item,'good',opts.csrf)).status,409);assert.deepEqual(row(item.id),done);
  const pending=await(await request('pending.php')).json();assert.ok(!pending.data.items.some(i=>i.submission_id===item.id));
  const ownerSession=doctor=>php(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'mr9_owner','doctor_id'=>'${doctor}'];echo session_id();session_write_close();`);
  const owner=ownerSession(item.doctor),other=ownerSession('mr9_other');
  const route=base+'/api/media/'+(photo?'profile-photo-review-candidate.php':'physician-logo-review-candidate.php');
  const ownGet=(cookie)=>fetch(route,{headers:cookie?{Cookie:'PHPSESSID='+cookie}:{}});
  try{
   assert.equal((await ownGet(null)).status,401);let r=await ownGet(owner);assert.equal(r.status,200);assert.ok(r.headers.get('cache-control').includes('no-store'));const payload=await r.json();const status=payload.data.candidate;
   assert.equal(status.review_feedback,feedback);assert.equal(status.reason_code,'WRONG_MEDIA_TYPE');assert.equal(status.review_status,'NEEDS_WORK');assert.ok(!/storage_key|private\/|reviewer|account_id|SOURCE|capability|audit/.test(JSON.stringify(status)));
   assert.equal((await(await ownGet(other)).json()).data.candidate,null);const forgedOwner=await fetch(route+'?doctor_id='+item.doctor,{headers:{Cookie:'PHPSESSID='+other}});if(photo){assert.equal(forgedOwner.status,200);assert.equal((await forgedOwner.json()).data.candidate,null);}else assert.equal(forgedOwner.status,400);
   assert.equal((await fetch(base+'/api/internal/media-review/request-replacement.php',{method:'POST',headers:{Cookie:'PHPSESSID='+owner,'Content-Type':'application/json','X-Capability':'media_review_request_replacement'},body:JSON.stringify({submission_id:item.id,reason_code:'OTHER',csrf:opts.csrf})})).status,403);
   const bytes=execFileSync('php',['-r',`require "modules/media/tests/LogoImprovementFixture.php";$u=mr8Upload();echo file_get_contents($u['tmp_name']);unlink($u['tmp_name']);`],{env});
   const form=new FormData();form.append('image',new Blob([bytes],{type:'image/png'}),'synthetic.png');
   r=await fetch(route,{method:'POST',headers:{Cookie:'PHPSESSID='+owner,[photo?'X-Profile-Photo-Candidate-CSRF':'X-Physician-Logo-Candidate-CSRF']:payload.data.csrf_token},body:form});assert.equal(r.status,200,await r.clone().text());
   const next=(await r.json()).data.candidate;assert.notEqual(next.submission_id,item.id);assert.equal(next.review_status,'PENDING_REVIEW');assert.equal(row(item.id).review_status,'NEEDS_WORK');assert.equal(publicState(),beforePublic);
  }finally{for(const id of [owner,other])php(`session_id('${id}');session_start();session_destroy();`);}
 }
 sql("$p->exec(\"UPDATE media_review_submissions SET review_status='WITHDRAWN' WHERE review_status='PENDING_REVIEW'\");");
 await runReplacementVisualQA({base,tokens:identity.tokens,fixture,sql,grant,revoke});
 assert.equal(snapshot(),before,'Director baseline changed');console.log('MR9_HTTP_QA=PASS: authority, CSRF, audit rollback, owner isolation, feedback, re-upload, public unchanged');
} finally {
 if(child){try{process.kill(-child.pid,'SIGTERM');}catch{child.kill();}}
 if(identity)execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php','cleanup'],{env});
 if(root)await rm(root,{recursive:true,force:true});
}
