import {installOwnerReviewQaLogin} from './OwnerMediaReviewQaLogin.mjs';
import {ownerBrowser} from './OwnerMediaReviewBrowserTest.mjs';
import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {mkdtemp,mkdir,copyFile,writeFile,readFile,rm,realpath} from 'node:fs/promises';
import {randomBytes,createHash} from 'node:crypto';
const keep=process.argv.includes('--keep'), suffix=randomBytes(5).toString('hex');
const root=await mkdtemp('/tmp/mxmed-mr12a-'),fixtureRoot=await realpath(await mkdtemp('/tmp/mxmed-mr5-mr12a-'));
const names=['mxmed-mr12a-db-'+suffix,'mxmed-mr12a-session-'+suffix];let server,identity,created=[];
const env={...process.env,MR5_FIXTURE_ROOT:fixtureRoot,MR3_TEST_DB:'mxmed_gate4d_preview_mr3_mr12a_'+suffix,MR3_TEST_DB_HOST:'127.0.0.1',MR3_TEST_DB_PORT:'3309',MR3_TEST_DB_USER:'root',MR3_TEST_DB_PASS:'',MR3_VALKEY_PORT:'6387'};
const exec=(cmd,args,options={})=>execFileSync(cmd,args,{env,encoding:'utf8',stdio:['pipe','pipe','pipe'],...options}).trim();
const php=code=>exec('php',['-r',code]);
const sql=code=>php('require "modules/media/tests/ReviewBatchFixture.php";$p=mr5Pdo();'+code);
const base='http://127.0.0.1:8128';
async function cleanup(){if(server)try{process.kill(-server.pid);}catch{}for(const name of created)try{exec('docker',['stop',name]);}catch{}await rm(root,{recursive:true,force:true});await rm(fixtureRoot,{recursive:true,force:true});}
try {
 for(const [name,args] of [[names[0],['-p','127.0.0.1:3309:3306','-e','MYSQL_ALLOW_EMPTY_PASSWORD=yes','mysql:8.4']],[names[1],['-p','127.0.0.1:6387:6379','valkey/valkey:8-alpine']]]){exec('docker',['run','--rm','-d','--name',name,...args]);created.push(name);}
 for(let i=0;;i++){try{exec('docker',['exec',names[0],'mysqladmin','--protocol=TCP','-h127.0.0.1','ping']);break;}catch{if(i>90)throw Error('DB startup');await new Promise(r=>setTimeout(r,500));}}
 exec('php',['scripts/packaging/setup-test-db.php']);
 identity=JSON.parse(exec('php',['modules/identity/tests/InternalOperatorFixture.php','setup']));
 php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');foreach(['media_review_approve','media_review_request_replacement','media_review_correct','media_review_source_download'] as $cap)$p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_good',?,'ACTIVE')")->execute([$cap]);`);
 const paths=JSON.parse(await readFile('scripts/packaging/runtime-files.json','utf8'));
 for(const path of new Set([...paths,'api/media/owner-review.php','assets/js/owner-media-review.js'])){await mkdir(root+'/'+path.split('/').slice(0,-1).join('/'),{recursive:true});await copyFile(path,root+'/'+path);}
 await writeFile(root+'/api/mxmed-db.config.php',"<?php return ['mysql'=>['host'=>'127.0.0.1','port'=>3309,'dbname'=>'mxmed','user'=>'root','pass'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci']];");
 const doctor=sql('echo mr11Doctor();');
 await writeFile(root+'/index.html',(await readFile(root+'/index.html','utf8')).replace('data-doctor-id="1"','data-doctor-id="'+doctor+'"').replace('data-user-id="u_legacy_1"','data-user-id="mr12a_owner"').replace('data-doctor-name="Leticia Muñoz Alfaro"','data-doctor-name="Dra. Prueba MR12A"'));
 sql(`$p->exec(file_get_contents('modules/agenda/db/consultorios_schema.sql'));$p->exec("UPDATE profiles_doctors SET profile_status='active',is_public_candidate=1,professional_license='QA-SYNTHETIC',specialty_primary='Medicina general' WHERE doctor_id='${doctor}'");$p->exec("INSERT INTO consultorios(doctor_id,consultorio_id,titulo) VALUES('${doctor}','qa-office','Consultorio sintético')");`);
 const image=sql('$u=mr8Upload();echo $u["tmp_name"];');
 const png=await readFile(image);await rm(image);
 const session=sql(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'mr12a_owner','doctor_id'=>'${doctor}'];echo session_id();session_write_close();`);
 const other=sql("session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'mr12a_other','doctor_id'=>'mr12a_other'];echo session_id();session_write_close();");
 const publicState=()=>JSON.parse(sql(`echo json_encode($p->query("SELECT photo_url,logo_url FROM profiles_doctors WHERE doctor_id='${doctor}'")->fetch());`));
 // Establish three approved public assets through the accepted services, synthetic only.
 sql(`$d='${doctor}';foreach(['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY'] as $purpose){$f=mr11Candidate($d,$purpose);(new Media\\Services\\MediaReviewBatchService($p))->submit($d);[$private,$public]=mr5Storage();$class=match($purpose){'DOCTOR_PROFILE_PHOTO'=>Media\\Services\\ProfilePhotoApprovalService::class,'PHYSICIAN_PERSONAL_LOGO'=>Media\\Services\\PhysicianLogoApprovalService::class,default=>Media\\Services\\GalleryApprovalService::class};(new $class($p,$private,$public))->approve(mr5Context(),$f['id']);}`);
 const qa=await installOwnerReviewQaLogin({root,fixtureRoot,doctor,identityDb:env.MR3_TEST_DB});
 server=spawn('php',['-d','auto_prepend_file='+qa.prepend,'-d','upload_max_filesize=10M','-d','post_max_size=12M','-S','127.0.0.1:8128','-t',root],{env:{...env,...identity.env,...qa.environment,MXMED_PUBLIC_MEDIA_ROOT:fixtureRoot+'/public',MXMED_PRIVATE_MEDIA_ROOT:fixtureRoot+'/private',PHP_CLI_SERVER_WORKERS:'4',MXMED_PROFILES_API_BASE:base,MXMED_API_BASE:base},detached:true,stdio:['ignore','ignore','pipe']});
 let errors='';server.stderr.on('data',b=>errors+=b);
 for(let i=0;i<100;i++){try{await fetch(base+'/api/media/owner-review.php');break;}catch{await new Promise(r=>setTimeout(r,50));}}
 const owner=(route,init={},cookie=session)=>fetch(base+'/api/media/'+route,{...init,headers:{Cookie:'PHPSESSID='+cookie,...init.headers}});
 const review=(route,init={},token=identity.tokens.good)=>fetch(base+'/api/internal/media-review/'+route,{...init,headers:{Cookie:'__Host-mxmed_session='+token,...init.headers}});
 const data=async r=>{assert.equal(r.status,200,await r.clone().text());return r.json();};
 const listing=async()=> (await data(await owner('owner-review.php'))).data.items;
 const routes={photo:['profile-photo-review-candidate.php','X-Profile-Photo-Candidate-CSRF'],logo:['physician-logo-review-candidate.php','X-Physician-Logo-Candidate-CSRF'],gallery:['gallery-review-candidate.php','X-Gallery-Review-Candidate-CSRF']};
 const upload=async (key,cookie=session)=>{const [route,h]=routes[key],get=await data(await owner(route,{},cookie));const body=new FormData();body.append('image',new Blob([png],{type:'image/png'}),'synthetic.png');return owner(route,{method:'POST',headers:{[h]:get.data.csrf_token},body},cookie);};
 const submit=async()=>{const x=await data(await owner('review-batch-submit.php'));return data(await owner('review-batch-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:x.data.csrf})}));};
 // The documented manual login, including expiry recovery, is a separate auth path.
 const manualLogin=async()=>{
  const r=await fetch(base+'/qa-login.php?as=reviewer',{redirect:'manual'});
  assert.equal(r.status,302);assert.equal(r.headers.get('location'),'/internal/media-review/');
  const cookie=r.headers.getSetCookie().filter(c=>c.startsWith('mxmed_mr12a_qa=')&&!c.startsWith('mxmed_mr12a_qa=;')).at(-1);
  assert.ok(cookie&&cookie.includes('HttpOnly')&&!/;\s*secure(?:;|$)/i.test(cookie));
  assert.ok(r.headers.getSetCookie().some(c=>c.startsWith('__Host-mxmed_session=deleted;')&&/;\s*secure(?:;|$)/i.test(c)&&/max-age=0/i.test(c)));
  assert.equal(await r.text(),'');return cookie.split(';')[0];
 };
 const manualRequest=cookie=>fetch(base+'/api/internal/media-review/batches.php',{headers:cookie?{Cookie:cookie}:{}});
 assert.equal((await fetch(base+'/internal/media-review/')).status,403);
 assert.equal((await fetch(base+'/internal/media-review/',{headers:{Cookie:'PHPSESSID='+session}})).status,403);
 assert.equal((await manualRequest('mxmed_mr12a_qa='+ '0'.repeat(64))).status,403);
 const qaCookie=await manualLogin();await data(await manualRequest(qaCookie));
 assert.equal((await manualRequest(qaCookie+'; __Host-mxmed_session=invalid')).status,403,'canonical invalid prevents fallback');
 const capChange=state=>php(`$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=${env.MR3_TEST_DB}','root','');$p->exec("UPDATE internal_operator_grants SET status='${state}',revoked_at=${state==='ACTIVE'?'NULL':'CURRENT_TIMESTAMP'} WHERE account_id='mr3_good' AND capability='media_review_read'");`);
 capChange('REVOKED');assert.equal((await manualRequest(qaCookie)).status,403);
 assert.equal((await manualRequest(await manualLogin())).status,403,'login never grants capabilities');capChange('ACTIVE');
 const handle=qaCookie.split('=')[1],recordPath=fixtureRoot+'/qa-auth/'+createHash('sha256').update(handle).digest('hex')+'.json';
 // Simulate an expired/revoked canonical session without changing TTL or printing a token.
 exec('php',['-r',String.raw`require 'modules/identity/http/IdentityHttpComposition.php';Identity\Http\IdentityHttpComposition::registerAutoloader();$r=json_decode(file_get_contents('${recordPath}'),true);Identity\Http\IdentityHttpComposition::fromProcessEnvironment()->sessions()->logout($r['token']);`],{env:{...env,...identity.env}});
 assert.equal((await manualRequest(qaCookie)).status,403);await data(await manualRequest(await manualLogin()));
 assert.ok(!paths.some(p=>/qa-login|OwnerMediaReviewQaLogin|qa-auth/.test(p)));
 assert.ok(!qa.prepend.startsWith(await realpath(root)+'/'));
 await assert.rejects(readFile(root+'/qa-auth/prepend.php'),{code:'ENOENT'});
 const unavailableShim=await(await fetch(base+'/qa-auth/prepend.php')).text();
 assert.ok(!unavailableShim.includes('function mr12a_qa_allowed')&&!unavailableShim.includes('mr12a_qa_token_path'));
 console.log('MR12A1_MANUAL_HTTP=PASS: normal redirect; HttpOnly local cookie; no token delivery; invalid/owner/unauthenticated denied; capability revocation effective; invalid canonical session denied and fresh login recovers; helper outside package');
 const opts=await data(await review('approval-options.php'));
 const mutate=(route,id,extra={})=>review(route,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({submission_id:id,csrf:opts.csrf,...extra})});
 const old=publicState();for(const key of Object.keys(routes))await data(await upload(key));
 assert.deepEqual(publicState(),old);let items=await listing();assert.equal(items.length,3);assert.ok(items.every(x=>x.state==='OPEN'));
 assert.equal(sql(`echo $p->query("SELECT COUNT(*) FROM media_review_batches WHERE owner_id='${doctor}' AND status='OPEN'")->fetchColumn();`),'1');
 for(const item of items){assert.equal((await owner('owner-review.php?preview='+item.id)).status,200);assert.equal((await owner('owner-review.php?preview='+item.id,{},other)).status,404);}
 assert.equal((await owner('owner-review.php?role=SOURCE')).status,400);
 assert.equal((await fetch(base+'/api/media/owner-review.php')).status,401);
 assert.equal((await owner('review-batch-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:'forged'})})).status,403);
 await submit();assert.ok((await listing()).every(x=>x.state==='SUBMITTED'));
 const inbox=await data(await review('batches.php'));assert.equal(inbox.data.items.filter(x=>x.owner_id===doctor).length,1);
 for(const [purpose,route] of [['DOCTOR_PROFILE_PHOTO','approve.php'],['PHYSICIAN_PERSONAL_LOGO','approve-logo.php'],['DOCTOR_GALLERY','approve-gallery.php']]){const item=items.find(x=>x.purpose===purpose);await data(await mutate(route,item.id));assert.equal((await listing()).length,items.length-1);items=items.filter(x=>x.id!==item.id);}
 assert.equal((await listing()).length,0);assert.notEqual(publicState().photo_url,old.photo_url);assert.notEqual(publicState().logo_url,old.logo_url);
 assert.equal((await fetch(base+old.logo_url)).status,200,'historical logo');
 assert.equal(sql(`echo $p->query("SELECT COUNT(*) FROM media_assets WHERE owner_id='${doctor}' AND purpose='DOCTOR_GALLERY' AND status='READY'")->fetchColumn();`),'2');
 await data(await upload('photo'));await submit();const need=(await listing())[0];
 await data(await mutate('request-replacement.php',need.id,{reason_code:'QUALITY_INSUFFICIENT',feedback:'Usa una imagen más nítida.'}));
 assert.equal((await listing())[0].state,'NEEDS_WORK');assert.equal((await listing())[0].feedback,'Usa una imagen más nítida.');
 const oldBatch=sql(`echo $p->query("SELECT batch_id FROM media_review_submissions WHERE submission_id='${need.id}'")->fetchColumn();`);
 await data(await upload('photo'));const replacement=(await listing())[0];assert.equal(replacement.state,'OPEN');
 assert.notEqual(sql(`echo $p->query("SELECT batch_id FROM media_review_submissions WHERE submission_id='${replacement.id}'")->fetchColumn();`),oldBatch);
 await submit();await data(await mutate('approve.php',replacement.id));
 assert.equal((await mutate('approve.php',replacement.id)).status,409);
 assert.equal((await review('batches.php',{},identity.tokens.customer)).status,403);
 const capDoctor=sql('$d=mr11Doctor();mr10SeedPublic($d,15);echo $d;');
 const capSession=sql(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'cap_owner','doctor_id'=>'${capDoctor}'];echo session_id();session_write_close();`);
 await data(await upload('gallery',capSession));assert.equal((await upload('gallery',capSession)).status,409);
 const capId=sql(`echo $p->query("SELECT submission_id FROM media_review_submissions WHERE owner_id='${capDoctor}'")->fetchColumn();`);
 sql(`(new Media\\Services\\MediaReviewBatchService($p))->submit('${capDoctor}');`);
 await data(await mutate('approve-gallery.php',capId));assert.equal((await upload('gallery',capSession)).status,409);
 console.log('GALLERY_CAPACITY_16_HTTP=PASS');
 const publicPage=await fetch(base+'/profiles/doctor.php?doctor_id='+doctor);assert.equal(publicPage.status,200);assert.ok((await publicPage.text()).includes(publicState().photo_url));
 console.log('MR12A_HTTP_E2E=PASS: mixed batch; public preserved; photo/logo/gallery publication; historical logo; NEEDS_WORK new batch; owner previews and authorization/CSRF negatives');
 const browserImage=root+'/synthetic.png';await writeFile(browserImage,png);
 await ownerBrowser({base,image:browserImage});
 if(!keep)for(const name of ['ProfilePhotoApprovalTest','MediaReviewInterventionTest','PhysicianLogoApprovalTest','PhysicianLogoReviewTest','GalleryReviewTest','MediaReplacementTest','MediaReplacementAtomicTest','LogoImprovementTest','LogoImprovementAtomicTest','LogoImprovementReviewInputTest','MediaReviewInterventionPolicyTest','GalleryReviewPolicyTest','ReviewBatchTest','ReviewBatchAtomicTest','EmptyReviewBatchTest']){exec('docker',['exec','-i',names[0],'mysql','-uroot'],{input:'DROP DATABASE mxmed;'});exec('php',['scripts/packaging/setup-test-db.php']);exec('php',['-d','memory_limit=512M','modules/media/tests/'+name+'.php']);console.log(name+'=PASS');}
 if(keep){console.log('Synthetic UI: '+base+'/qa-login.php?as=owner');console.log('Synthetic public profile: '+base+'/profiles/doctor.php?doctor_id='+doctor);console.log('Synthetic reviewer: '+base+'/qa-login.php?as=reviewer');console.log('Press Ctrl-C to remove this disposable environment.');await new Promise(resolve=>process.once('SIGINT',resolve));}
} finally {await cleanup();}
