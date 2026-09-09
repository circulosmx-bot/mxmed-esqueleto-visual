import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {cp,mkdir,mkdtemp,writeFile,rm} from 'node:fs/promises';
import {runApprovalVisualQA} from './ProfilePhotoApprovalVisual.mjs';
const fixtureRoot=process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-qa';
const env={...process.env,MR5_FIXTURE_ROOT:fixtureRoot,MR3_TEST_DB:'mxmed_gate4d_preview_mr3_mr5_'+Date.now(),MR3_TEST_DB_HOST:'127.0.0.1',MR3_TEST_DB_PORT:'3309',MR3_TEST_DB_USER:'root',MR3_TEST_DB_PASS:'',MR3_VALKEY_PORT:'6387'};
const php=(code)=>execFileSync('php',['-r',code],{env,encoding:'utf8'}).trim();
const sql=(code)=>php('require "modules/media/tests/ProfilePhotoApprovalFixture.php";$p=mr5Pdo();'+code);
const candidate=()=>JSON.parse(execFileSync('php',['modules/media/tests/ProfilePhotoApprovalFixture.php','candidate'],{env,encoding:'utf8'}));
const snapshot=()=>execFileSync('php',['modules/media/tests/MediaReviewReadSnapshot.php'],{encoding:'utf8'}).trim();
const before=snapshot();let child,root,identity;
try {
 identity=JSON.parse(execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php','setup'],{env,encoding:'utf8'}));
 // Dedicated synthetic identity database. No real grants are created.
 php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');foreach(['good','inactive','blocked','expired','revoked_session','superseded','credential'] as $k){$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_".$k."','media_review_approve','ACTIVE')");}$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_wrong','media_review_read','ACTIVE')");`);
 root=await mkdtemp('/tmp/mxmed-mr5-http-');
 // Copy executable sources so the app's media PDO points ONLY at disposable MySQL.
 for(const dir of ['modules','api','internal'])await cp(dir,root+'/'+dir,{recursive:true,filter:src=>!src.endsWith('mxmed-db.config.php')});
 await mkdir(root+'/assets/js',{recursive:true});await mkdir(root+'/assets/css',{recursive:true});
 for(const file of ['assets/js/media-review-inbox.js','assets/css/media-review-inbox.css'])await cp(file,root+'/'+file);
 await writeFile(root+'/api/mxmed-db.config.php',"<?php return ['mysql'=>['host'=>'127.0.0.1','port'=>3309,'dbname'=>'mxmed','user'=>'root','pass'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci']];");
 const base='http://127.0.0.1:8115';
 child=spawn('php',['-S','127.0.0.1:8115','-t',root],{env:{...env,...identity.env,PHP_CLI_SERVER_WORKERS:'4',MXMED_PUBLIC_MEDIA_ROOT:fixtureRoot+'/public',MXMED_PRIVATE_MEDIA_ROOT:fixtureRoot+'/private'},detached:true,stdio:['ignore','ignore','pipe']});
 let errors='';child.stderr.on('data',b=>{errors+=b.toString();});
 for(let i=0;i<100;i++){try{await fetch(base+'/api/internal/media-review/approval-options.php');break;}catch{await new Promise(r=>setTimeout(r,50));}}
 const request=(route,kind='good',init={})=>fetch(base+'/api/internal/media-review/'+route,{...init,headers:{...(kind?{Cookie:'__Host-mxmed_session='+(identity.tokens[kind]||'invalid')}:{}),...init.headers}});
 const options=async(kind='good')=>{const r=await request('approval-options.php',kind);return {status:r.status,...await r.json()};};
 const post=(id,kind,csrf,extra={})=>request('approve.php',kind,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({submission_id:id,...(csrf===undefined?{}:{csrf}),...extra})});
 const f=candidate();
 for(const kind of ['', 'invalid','customer','wrong','revoked_grant','inactive','blocked','expired','revoked_session','superseded','credential'])assert.equal((await post(f.id,kind,'forged')).status,403,'denied '+kind);
 assert.equal((await options('wrong')).can_approve,false,'read-only UI authority');
 const valid=await options();assert.equal(valid.status,200);assert.equal(valid.can_approve,true);
 for(const csrf of [undefined,'forged'])assert.equal((await post(f.id,'good',csrf)).status,403,'CSRF required');
 assert.equal((await post(f.id,'good',valid.csrf,{doctor_id:'1'})).status,400);
 assert.equal((await post(f.id,'good',valid.csrf,{role:'internal_operator',capability:'media_review_approve'})).status,400);
 assert.equal((await request('approve.php?role=internal_operator','customer',{method:'POST',headers:{'Content-Type':'application/json','X-Capability':'media_review_approve'},body:JSON.stringify({submission_id:f.id,csrf:valid.csrf})})).status,403);
 assert.equal((await post('00000000-0000-4000-8000-000000000000','good',valid.csrf)).status,404);
 const response=await post(f.id,'good',valid.csrf);assert.equal(response.status,200,errors);const result=await response.json();
 assert.deepEqual(Object.keys(result).sort(),['ok','submission_id','review_status','published_media_id','public_url'].sort());
 const pub=await fetch(base+result.public_url);assert.equal(pub.status,200);assert.match(pub.headers.get('cache-control'),/immutable/);assert.equal(pub.headers.get('content-type'),'image/webp');assert.ok((await pub.arrayBuffer()).byteLength<=153600);
 assert.equal((await post(f.id,'good',valid.csrf)).status,409);
 // Revocation is observed on the next request, even with a previously issued valid CSRF.
 php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');$p->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='mr3_good' AND capability='media_review_approve'");`);
 assert.equal((await post(candidate().id,'good',valid.csrf)).status,403);
 php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');foreach(['good','wrong'] as $k)$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_".$k."','media_review_approve','ACTIVE')");`);
 const other=await options('wrong');assert.equal((await post(candidate().id,'wrong',valid.csrf)).status,403,'CSRF bound to session');
 const concurrent=candidate();
 const concurrentResults=await Promise.all([post(concurrent.id,'good',valid.csrf),post(concurrent.id,'wrong',other.csrf)]);
 assert.deepEqual(concurrentResults.map(r=>r.status).sort(),[200,409]);
 assert.equal(sql(`echo $p->query("SELECT COUNT(*) FROM platform_audit_events WHERE resource_reference='${concurrent.id}'")->fetchColumn();`),'1');
 assert.equal(sql(`echo $p->query("SELECT COUNT(*) FROM media_assets WHERE owner_id='${concurrent.doctor}' AND status='READY'")->fetchColumn();`),'1');
 // Actual HTTP audit failure: no public switch, no terminal status, no audit event.
 const failed=candidate();sql("$p->exec(\"CREATE TRIGGER mr5_http_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'\");");
 try {assert.equal((await post(failed.id,'good',valid.csrf)).status,503);}finally{sql("$p->exec('DROP TRIGGER mr5_http_audit_failure');");}
 assert.equal(sql(`echo $p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='${failed.id}'")->fetchColumn();`),'PENDING_REVIEW');
 assert.equal(sql(`echo $p->query("SELECT photo_url FROM profiles_doctors WHERE doctor_id='${failed.doctor}'")->fetchColumn();`),failed.old.public_url);
 // No SOURCE delivery path, even after successful approval.
 for(const route of ['source.php','source-image.php'])assert.equal((await request(route+'?submission_id='+f.id)).status,404);
 assert.equal((await request('review-image.php?submission_id='+f.id)).status,404);
 // One pending row for visual tests; all mutations remain in the disposable database.
 sql("$p->exec(\"UPDATE media_review_submissions SET review_status='WITHDRAWN' WHERE review_status='PENDING_REVIEW'\");");
 php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');$p->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='mr3_wrong' AND capability='media_review_approve'");`);
 await runApprovalVisualQA({base,tokens:identity.tokens,candidate,sql});
 assert.equal(snapshot(),before,'Director baseline changed');
 console.log('HTTP_QA=PASS: canonical session/capability denials, session-bound CSRF, strict input, immutable public REVIEW, revocation, repeat409, two-advisor concurrency200/409, audit failure503, SOURCE404; Director unchanged');
} finally {
 if(child){try{process.kill(-child.pid,'SIGTERM');}catch{child.kill();}}
 if(identity)execFileSync('php',['modules/identity/tests/InternalOperatorFixture.php','cleanup'],{env});
 if(root)await rm(root,{recursive:true,force:true});
}
