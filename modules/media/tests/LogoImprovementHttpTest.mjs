import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {cp,mkdir,mkdtemp,writeFile,readFile,rm} from 'node:fs/promises';
import {runImprovementVisualQA} from './LogoImprovementVisual.mjs';
const fixtureRoot=process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr8qa';
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
 const f=candidate();
 for(const kind of ['', 'invalid','customer','wrong','good','revoked_grant','inactive','blocked','expired','revoked_session','superseded','credential'])for(const route of ['improve-logo.php','accept-improvement.php','discard-improvement.php'])assert.equal((await mutate(route,f.id,kind)).status,403,'denied '+kind);
 grant('wrong','media_review_corrected_upload');assert.equal((await mutate('improve-logo.php',f.id,'wrong')).status,403);revoke('wrong','media_review_corrected_upload');
 grant('good','media_review_improve');let opts=await options();assert.equal(opts.can_improve,true);
 assert.equal((await mutate('improve-logo.php',f.id,'good','forged')).status,403);
 for(const key of ['doctor_id','role','threshold','actor','capability','path'])assert.equal((await mutate('improve-logo.php',f.id,'good',opts.csrf,{[key]:'forged'})).status,400);
 const publicState=()=>sql("echo json_encode([$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll(),$p->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll()]);");
 const beforePublic=publicState();const fileState=()=>sql(`echo json_encode($p->query("SELECT * FROM media_review_files WHERE submission_id='${f.id}' ORDER BY role")->fetchAll());`);
 const beforeFiles=fileState();let r=await mutate('improve-logo.php',f.id,'good',opts.csrf);assert.equal(r.status,200,errors);const generated=await r.json();assert.equal(generated.status,'PROPOSAL_CREATED');assert.ok(!/storage_key|private\/|checksum|SOURCE/.test(JSON.stringify(generated)));assert.equal(publicState(),beforePublic);
 const all=JSON.parse(fileState()),proposal=all.find(f=>f.role==='AUTO_PROPOSAL');assert.equal(all.find(f=>f.role==='REVIEW').checksum_sha256,JSON.parse(beforeFiles).find(f=>f.role==='REVIEW').checksum_sha256);
 const preview=await request('improvement-preview.php?submission_id='+f.id);assert.equal(preview.status,200);assert.equal(preview.headers.get('cache-control'),'private, no-store');assert.equal(preview.headers.get('content-type'),'image/webp');const proposedBytes=Buffer.from(await preview.arrayBuffer());assert.equal(createHash('sha256').update(proposedBytes).digest('hex'),proposal.checksum_sha256);
 for(const query of ['&role=IMPROVEMENT_INPUT','&role=SOURCE','&path=forged','&storage_key=forged'])assert.equal((await request('improvement-preview.php?submission_id='+f.id+query)).status,400);
 const lossless=all.find(f=>f.role==='IMPROVEMENT_INPUT');assert.equal(proposal.input_file_id,lossless.file_id);assert.equal(proposal.input_checksum_sha256,lossless.checksum_sha256);
 for(const key of [lossless.file_id,lossless.storage_key,encodeURIComponent(lossless.storage_key),proposal.file_id,proposal.storage_key,encodeURIComponent(proposal.storage_key)])assert.equal((await fetch(base+'/api/media/index.php/public/'+key)).status,404);
 revoke('good','media_review_improve');assert.equal((await mutate('accept-improvement.php',f.id,'good',opts.csrf)).status,403);grant('good','media_review_improve');
 const beforeAuditFail=fileState();sql("$p->exec(\"CREATE TRIGGER mr8_http_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'\");");
 try{for(const route of ['improve-logo.php','accept-improvement.php','discard-improvement.php']){assert.equal((await mutate(route,f.id,'good',opts.csrf)).status,503);assert.equal(fileState(),beforeAuditFail);assert.equal(publicState(),beforePublic);}}finally{sql("$p->exec('DROP TRIGGER mr8_http_audit_failure');");}
 assert.equal((await mutate('accept-improvement.php',f.id,'good',opts.csrf)).status,200);assert.equal(publicState(),beforePublic);assert.equal(JSON.parse(fileState()).find(f=>f.role==='REVIEW').checksum_sha256,proposal.checksum_sha256);
 assert.equal((await request('improvement-preview.php?submission_id='+f.id)).status,409);
 const repeated=await mutate('improve-logo.php',f.id,'good',opts.csrf);assert.equal((await repeated.json()).status,'ALREADY_IMPROVED');
 const approved=await post(f.id,'good',opts.csrf);assert.equal(approved.status,200);const result=await approved.json();assert.deepEqual(Buffer.from(await(await fetch(base+result.public_url)).arrayBuffer()),proposedBytes);assert.equal((await mutate('improve-logo.php',f.id,'good',opts.csrf)).status,409);
 for(const kind of ['gradient','photo','transparent']){const item=candidate(kind),before=publicState();const response=await mutate('improve-logo.php',item.id,'good',opts.csrf);assert.equal(response.status,200);assert.ok(['NO_SAFE_IMPROVEMENT','ALREADY_TRANSPARENT'].includes((await response.json()).status));assert.equal(publicState(),before);}
 const physician=php("session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'mr8_physician','doctor_id'=>'"+f.doctor+"'];echo session_id();session_write_close();");
 try{assert.equal((await fetch(base+'/api/internal/media-review/improve-logo.php',{method:'POST',headers:{Cookie:'PHPSESSID='+physician,'Content-Type':'application/json','X-Capability':'media_review_improve'},body:JSON.stringify({submission_id:f.id,csrf:opts.csrf})})).status,403);}finally{php(`session_id('${physician}');session_start();session_destroy();`);}
 sql("$p->exec(\"UPDATE media_review_submissions SET review_status='WITHDRAWN' WHERE review_status='PENDING_REVIEW'\");");
 await runImprovementVisualQA({base,tokens:identity.tokens,candidate,sql,grant,revoke});
 assert.equal(snapshot(),before,'Director baseline changed');console.log('MR8_HTTP_QA=PASS: canonical authority/CSRF, private fixed preview, no leaks, generate/accept/approval, abstention, audit rollback, revoked grants, Director unchanged');
} finally {
 if(child){try{process.kill(-child.pid,'SIGTERM');}catch{child.kill();}}
 if(identity)execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php','cleanup'],{env});
 if(root)await rm(root,{recursive:true,force:true});
}
