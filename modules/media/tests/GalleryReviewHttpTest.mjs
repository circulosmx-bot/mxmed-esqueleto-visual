import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {cp,mkdir,mkdtemp,writeFile,readFile,rm} from 'node:fs/promises';
import {runGalleryVisualQA} from './GalleryReviewVisual.mjs';
const fixtureRoot=process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr10qa';
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
 const gallery=(doctor='')=>JSON.parse(execFileSync('php',['modules/media/tests/GalleryReviewFixture.php','candidate',doctor],{env,encoding:'utf8'}));
 const f=gallery(),opts=await options();
 for(const kind of ['','invalid','customer','wrong','inactive','blocked','expired','revoked_session','superseded','credential'])assert.equal((await mutate('approve-gallery.php',f.id,kind,opts.csrf)).status,403);
 assert.equal((await mutate('approve-gallery.php',f.id,'good','forged')).status,403);
 for(const extra of [{doctor_id:f.doctor},{owner_id:f.doctor},{media_id:'forged'},{storage_key:'forged'}])assert.equal((await mutate('approve-gallery.php',f.id,'good',opts.csrf,extra)).status,400);
 for(const cap of ['media_review_source_download','media_review_corrected_upload','media_review_request_replacement','media_review_improve'])grant('good',cap);
 const state=()=>sql("echo json_encode([$p->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll(),$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll()]);");
 const files=id=>JSON.parse(sql(`echo json_encode($p->query("SELECT * FROM media_review_files WHERE submission_id='${id}' ORDER BY role")->fetchAll());`));
 let rows=files(f.id);assert.deepEqual(rows.map(r=>r.role),['SOURCE','REVIEW']);
 const preview=await request('review-image.php?submission_id='+f.id);assert.equal(preview.status,200);assert.equal(preview.headers.get('cache-control'),'private, no-store');const reviewBytes=Buffer.from(await preview.arrayBuffer());assert.equal(createHash('sha256').update(reviewBytes).digest('hex'),rows.find(r=>r.role==='REVIEW').checksum_sha256);
 for(const file of rows)for(const key of [file.file_id,file.storage_key,encodeURIComponent(file.storage_key)])assert.equal((await fetch(base+'/api/media/index.php/public/'+key)).status,404);
 assert.equal((await mutate('improve-logo.php',f.id,'good',opts.csrf)).status,409);
 const original=await request('source-download.php?submission_id='+f.id);assert.equal(original.status,200);assert.equal(createHash('sha256').update(Buffer.from(await original.arrayBuffer())).digest('hex'),rows.find(r=>r.role==='SOURCE').checksum_sha256);
 const bytes=execFileSync('php',['-r','require "modules/media/tests/LogoImprovementFixture.php";$u=mr8Upload("white");echo file_get_contents($u["tmp_name"]);unlink($u["tmp_name"]);'],{env});
 let beforeState=state();const form=new FormData();form.append('submission_id',f.id);form.append('csrf',opts.csrf);form.append('corrected',new Blob([bytes],{type:'image/png'}),'corrected.png');
 assert.equal((await request('corrected.php','good',{method:'POST',body:form})).status,200);let corrected=files(f.id);assert.equal(corrected.find(r=>r.role==='SOURCE').checksum_sha256,rows.find(r=>r.role==='SOURCE').checksum_sha256);assert.deepEqual(corrected.map(r=>r.role),['SOURCE','REVIEW','CORRECTED']);assert.equal(state(),beforeState);
 sql("$p->exec(\"CREATE TRIGGER mr10_http_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'\");");
 try{assert.equal((await mutate('approve-gallery.php',f.id,'good',opts.csrf)).status,503);assert.equal(state(),beforeState);}finally{sql("$p->exec('DROP TRIGGER mr10_http_audit_failure');");}
 const approved=await mutate('approve-gallery.php',f.id,'good',opts.csrf);assert.equal(approved.status,200,errors);const result=await approved.json();const published=Buffer.from(await(await fetch(base+result.public_url)).arrayBuffer());assert.equal(createHash('sha256').update(published).digest('hex'),corrected.find(r=>r.role==='REVIEW').checksum_sha256);assert.equal((await mutate('approve-gallery.php',f.id,'good',opts.csrf)).status,409);
 const item=gallery(),owner=php(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'mr10_owner','doctor_id'=>'${item.doctor}'];echo session_id();session_write_close();`),other=php("session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'mr10_other','doctor_id'=>'mr10_other'];echo session_id();session_write_close();");
 const route=base+'/api/media/gallery-review-candidate.php';
 const ownerRequest=(cookie,init={})=>fetch(route,{...init,headers:{...(cookie?{Cookie:'PHPSESSID='+cookie}:{}),...init.headers}});
 try{
  assert.equal((await ownerRequest(null)).status,401);let response=await ownerRequest(owner);assert.equal(response.status,200);let data=(await response.json()).data;assert.equal(data.max_total_active,16);assert.equal(data.items.length,1);assert.ok(!/SOURCE|storage_key|private\/|reviewer|account_id|audit|capability/.test(JSON.stringify(data.items)));
  assert.equal((await fetch(route+'?doctor_id='+item.doctor,{headers:{Cookie:'PHPSESSID='+other}})).status,400);
  assert.equal((await ownerRequest(other,{method:'DELETE',headers:{'Content-Type':'application/json','X-Gallery-Review-Candidate-CSRF':(await(await ownerRequest(other)).json()).data.csrf_token},body:JSON.stringify({submission_id:item.id})})).status,404);
  const makeForm=()=>{const form=new FormData();form.append('image',new Blob([bytes],{type:'image/png'}),'gallery.png');return form;};
  assert.equal((await ownerRequest(owner,{method:'POST',body:makeForm()})).status,403);beforeState=state();
  for(let i=0;i<3;i++)assert.equal((await ownerRequest(owner,{method:'POST',headers:{'X-Gallery-Review-Candidate-CSRF':data.csrf_token},body:makeForm()})).status,200);
  data=(await(await ownerRequest(owner)).json()).data;assert.equal(data.items.length,4);assert.equal(state(),beforeState);
  assert.equal((await mutate('request-replacement.php',item.id,'good',opts.csrf,{reason_code:'QUALITY_INSUFFICIENT',feedback:'Envía una imagen más clara.'})).status,200);data=(await(await ownerRequest(owner)).json()).data;assert.equal(data.items.find(i=>i.submission_id===item.id).review_status,'NEEDS_WORK');
  const pending=data.items.find(i=>i.review_status==='PENDING_REVIEW');assert.equal((await ownerRequest(owner,{method:'DELETE',headers:{'Content-Type':'application/json','X-Gallery-Review-Candidate-CSRF':data.csrf_token},body:JSON.stringify({submission_id:pending.submission_id})})).status,200);assert.equal(state(),beforeState);
  assert.equal((await fetch(base+'/api/internal/media-review/pending.php',{headers:{Cookie:'PHPSESSID='+owner}})).status,403);
 }finally{for(const id of [owner,other])php(`session_id('${id}');session_start();session_destroy();`);}
 sql("$p->exec(\"UPDATE media_review_submissions SET review_status='WITHDRAWN' WHERE review_status='PENDING_REVIEW'\");");
 await runGalleryVisualQA({base,tokens:identity.tokens,gallery,sql,grant,revoke});
 assert.equal(snapshot(),before,'Director baseline changed');console.log('MR10_HTTP_QA=PASS: owner isolation/CSRF, private gallery intake/preview, audited source/corrected/replacement/approval, no logo improvement, public exact REVIEW');
}finally{
 if(child){try{process.kill(-child.pid,'SIGTERM');}catch{child.kill();}}
 if(identity)execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php','cleanup'],{env});
 if(root)await rm(root,{recursive:true,force:true});
}
