import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {cp,mkdir,mkdtemp,writeFile,readFile,rm} from 'node:fs/promises';
import {runInterventionVisualQA} from './MediaReviewInterventionVisual.mjs';
const fixtureRoot=process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr6qa';
const env={...process.env,MR5_FIXTURE_ROOT:fixtureRoot,MR3_TEST_DB:'mxmed_gate4d_preview_mr3_mr6_'+Date.now(),MR3_TEST_DB_HOST:'127.0.0.1',MR3_TEST_DB_PORT:'3309',MR3_TEST_DB_USER:'root',MR3_TEST_DB_PASS:'',MR3_VALKEY_PORT:'6387'};
const php=(code)=>execFileSync('php',['-r',code],{env,encoding:'utf8'}).trim();
const sql=(code)=>php('require "modules/media/tests/ProfilePhotoApprovalFixture.php";$p=mr5Pdo();'+code);
const candidate=()=>JSON.parse(execFileSync('php',['modules/media/tests/ProfilePhotoApprovalFixture.php','candidate'],{env,encoding:'utf8'}));
const snapshot=()=>execFileSync('php',['modules/media/tests/MediaReviewReadSnapshot.php'],{encoding:'utf8'}).trim();
const before=snapshot();let child,root,identity;
try {
 identity=JSON.parse(execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php','setup'],{env,encoding:'utf8'}));
 // Dedicated synthetic identity database. No real grants are created.
 php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');foreach(['good','inactive','blocked','expired','revoked_session','superseded','credential'] as $k){$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_".$k."','media_review_approve','ACTIVE')");}$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_wrong','media_review_read','ACTIVE')");`);
 root=await mkdtemp('/tmp/mxmed-mr6-http-');
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
 const post=(id,kind,csrf,extra={})=>request('approve.php',kind,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({submission_id:id,...(csrf===undefined?{}:{csrf}),...extra})});
 const f=candidate();
 const sourceRoute='source-download.php?submission_id='+f.id;
 const fixtureUpload=()=>JSON.parse(execFileSync('php',['modules/media/tests/MediaReviewInterventionFixture.php','file'],{env,encoding:'utf8'}));
 const upload=fixtureUpload();
 const uploadRequest=async(id,kind,csrf,extra={},file=upload)=>{
  const form=new FormData();form.append('submission_id',id);if(csrf!==undefined)form.append('csrf',csrf);
  form.append('corrected',new Blob([await readFile(file.tmp_name)],{type:file.type}),file.name);
  for(const [k,v] of Object.entries(extra))form.append(k,v);
  return request('corrected.php',kind,{method:'POST',body:form});
 };
 for(const kind of ['', 'invalid','customer','wrong','good','revoked_grant','inactive','blocked','expired','revoked_session','superseded','credential']){
  assert.equal((await request(sourceRoute,kind)).status,403,'source denied '+kind);
  assert.equal((await uploadRequest(f.id,kind,'forged')).status,403,'corrected denied '+kind);
 }
 const grant=(kind,cap)=>php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_${kind}','${cap}','ACTIVE')");`);
 const revoke=(kind,cap)=>php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');$p->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='mr3_${kind}' AND capability='${cap}' AND status='ACTIVE'");`);
 grant('good','media_review_source_download');
 let opts=await options();assert.equal(opts.can_download_source,true);assert.equal(opts.can_upload_corrected,false);
 const response=await request(sourceRoute);assert.equal(response.status,200,errors);assert.equal(response.headers.get('content-type'),'image/png');
 assert.equal(response.headers.get('cache-control'),'private, no-store');assert.equal(response.headers.get('x-content-type-options'),'nosniff');assert.match(response.headers.get('content-disposition'),/^attachment; filename="foto-original-[a-f0-9]{8}\.png"$/);assert.notEqual(response.headers.get('access-control-allow-origin'),'*');
 const sourceHash=sql(`echo $p->query("SELECT checksum_sha256 FROM media_review_files WHERE submission_id='${f.id}' AND role='SOURCE'")->fetchColumn();`);
 assert.equal(createHash('sha256').update(Buffer.from(await response.arrayBuffer())).digest('hex'),sourceHash);
 assert.equal((await uploadRequest(f.id,'good',opts.csrf)).status,403,'source does not grant upload');
 assert.equal((await request(sourceRoute+'&role=SOURCE')).status,400);assert.equal((await request(sourceRoute+'&storage_key=forged')).status,400);
 revoke('good','media_review_source_download');assert.equal((await request(sourceRoute)).status,403);grant('good','media_review_source_download');
 grant('good','media_review_corrected_upload');opts=await options();assert.equal(opts.can_upload_corrected,true);
 for(const kind of ['inactive','blocked','expired','revoked_session','superseded','credential']){
  grant(kind,'media_review_source_download');grant(kind,'media_review_corrected_upload');
  assert.equal((await request(sourceRoute,kind)).status,403,'canonical session source '+kind);assert.equal((await uploadRequest(f.id,kind,opts.csrf)).status,403,'canonical session corrected '+kind);
 }
 for(const token of [undefined,'forged'])assert.equal((await uploadRequest(f.id,'good',token)).status,403,'CSRF');
 assert.equal((await uploadRequest(f.id,'good',opts.csrf,{doctor_id:'1'})).status,400);
 assert.equal((await request(sourceRoute,'customer',{headers:{'X-Role':'internal_operator','X-Capability':'media_review_source_download'}})).status,403);
 const publicState=()=>sql("echo json_encode([$p->query('SELECT * FROM media_assets')->fetchAll(),$p->query('SELECT * FROM profiles_doctors')->fetchAll()]);");
 const beforePublic=publicState();const beforeReview=sql(`echo $p->query("SELECT checksum_sha256 FROM media_review_files WHERE submission_id='${f.id}' AND role='REVIEW'")->fetchColumn();`);
 const correctedResponse=await uploadRequest(f.id,'good',opts.csrf);assert.equal(correctedResponse.status,200,errors);const corrected=await correctedResponse.json();
 assert.equal(corrected.review_status,'PENDING_REVIEW');assert.equal(corrected.technical_status,'READY');assert.ok(!JSON.stringify(corrected).includes('storage_key'));assert.equal(publicState(),beforePublic);
 const reviewHash=sql(`echo $p->query("SELECT checksum_sha256 FROM media_review_files WHERE submission_id='${f.id}' AND role='REVIEW'")->fetchColumn();`);assert.notEqual(reviewHash,beforeReview);
 const reviewResponse=await request('review-image.php?submission_id='+f.id);assert.equal(reviewResponse.status,200);assert.equal(createHash('sha256').update(Buffer.from(await reviewResponse.arrayBuffer())).digest('hex'),reviewHash);
 assert.equal(sql(`echo $p->query("SELECT checksum_sha256 FROM media_review_files WHERE submission_id='${f.id}' AND role='SOURCE'")->fetchColumn();`),sourceHash);
 assert.equal((await uploadRequest(f.id,'good',opts.csrf)).status,200,'second correction');assert.equal(sql(`echo $p->query("SELECT COUNT(*) FROM media_review_files WHERE submission_id='${f.id}'")->fetchColumn();`),'3');
 // Failure after existing CORRECTED: preserve both current private relationships and all public authority.
 const fileState=()=>sql(`echo json_encode($p->query("SELECT * FROM media_review_files WHERE submission_id='${f.id}' ORDER BY role")->fetchAll());`);const unchanged=fileState();
 sql("$p->exec(\"CREATE TRIGGER mr6_http_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'\");");
 try{const denied=await request(sourceRoute);assert.equal(denied.status,503);assert.match(denied.headers.get('content-type'),/json/);assert.equal((await denied.json()).ok,false);assert.equal((await uploadRequest(f.id,'good',opts.csrf)).status,503);}finally{sql("$p->exec('DROP TRIGGER mr6_http_audit_failure');");}
 assert.equal(fileState(),unchanged);assert.equal(publicState(),beforePublic);
 revoke('good','media_review_corrected_upload');assert.equal((await uploadRequest(f.id,'good',opts.csrf)).status,403);grant('good','media_review_corrected_upload');
 const approval=await post(f.id,'good',opts.csrf);assert.equal(approval.status,200);const published=await approval.json();
 const publicImage=await fetch(base+published.public_url);assert.equal(createHash('sha256').update(Buffer.from(await publicImage.arrayBuffer())).digest('hex'),reviewHash);
 assert.equal((await uploadRequest(f.id,'good',opts.csrf)).status,409,'cannot reopen approved');assert.equal((await request(sourceRoute)).status,409);
 assert.equal((await request('corrected-download.php?submission_id='+f.id)).status,404);
 const modern=candidate(),large=JSON.parse(execFileSync('php',['-d','memory_limit=256M','modules/media/tests/MediaReviewInterventionFixture.php','modern'],{env,encoding:'utf8'}));
 try{assert.ok((await readFile(large.tmp_name)).length>10000000);const stateBefore=publicState();const largeResponse=await uploadRequest(modern.id,'good',opts.csrf,{},large);assert.equal(largeResponse.status,200,'near-10 MiB / 12 MP HTTP source');const data=await largeResponse.json();assert.ok(Math.max(data.review.width,data.review.height)<=800&&data.review.byte_size<=153600);assert.equal(publicState(),stateBefore);}finally{await rm(large.tmp_name,{force:true});}
 // Prepare one pending item per visual viewport; other fixtures remain isolated.
 sql("$p->exec(\"UPDATE media_review_submissions SET review_status='WITHDRAWN' WHERE review_status='PENDING_REVIEW'\");");
 await runInterventionVisualQA({base,tokens:identity.tokens,candidate,sql,upload,grant,revoke});
 await rm(upload.tmp_name,{force:true});
 assert.equal(snapshot(),before,'Director baseline changed');
 console.log('MR6_HTTP_QA=PASS: SOURCE exact attachment/no-store, explicit capability/session/CSRF denials, correction/replacement, private-only state, audit failure zero bytes/rollback, MR5 new REVIEW publication; Director unchanged');
} finally {
 if(child){try{process.kill(-child.pid,'SIGTERM');}catch{child.kill();}}
 if(identity)execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php','cleanup'],{env});
 if(root)await rm(root,{recursive:true,force:true});
}
