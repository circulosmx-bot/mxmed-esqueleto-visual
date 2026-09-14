// Run in a disposable runtime produced by the media QA harness, never Director DB.
import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {mkdir,writeFile,readFile,mkdtemp,rm} from 'node:fs/promises';
const cwd=process.cwd();
assert.ok(cwd.startsWith('/tmp/mxmed-mr02/')||cwd.startsWith('/private/tmp/mxmed-mr02/'),'isolated copied runtime required');
const php=code=>execFileSync('php',['-r',`require 'modules/media/tests/ReviewBatchFixture.php';$p=mr5Pdo();${code}`],{encoding:'utf8'}).trim();
const fixture=JSON.parse(php("$d=mr11Doctor();$u=mr8Upload();session_save_path(getcwd().'/qa-sessions');echo json_encode(['doctor'=>$d,'image'=>$u['tmp_name']]);"));
await mkdir('qa-sessions',{recursive:true});
const session=(doctor,actor)=>php(`session_save_path(getcwd().'/qa-sessions');session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'${actor}','doctor_id'=>'${doctor}'];echo session_id();session_write_close();`);
const sid=session(fixture.doctor,'mr02_http_owner'),other=session('mr02_http_other','mr02_http_other');
const base='http://127.0.0.1:18152';
const server=spawn('php',['-d','session.save_path='+cwd+'/qa-sessions','-S','127.0.0.1:18152','-t',cwd],{env:{...process.env,MXMED_PRIVATE_MEDIA_ROOT:process.env.MR5_FIXTURE_ROOT+'/private',MXMED_PUBLIC_MEDIA_ROOT:process.env.MR5_FIXTURE_ROOT+'/public'},stdio:'ignore'});
let chrome,ws,profile;
try {
 for(let i=0;i<50;i++){try{await fetch(base+'/assets/js/owner-media-review.js');break;}catch{await new Promise(r=>setTimeout(r,100));}}
 const req=async(route,options={},cookie=sid)=>{const r=await fetch(base+'/api/media/'+route,{...options,headers:{Cookie:'PHPSESSID='+cookie,...options.headers}});return {status:r.status,body:await r.json()};};
 const token=await req('review-batch-submit.php');assert.equal(token.status,200);const csrf=token.body.data.csrf;
 const post=(id,token=csrf,cookie=sid,extra={})=>req('review-candidate-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:token,submission_id:id,...extra})},cookie);
 assert.equal((await req('review-candidate-submit.php',{},'')).status,401);
 assert.equal((await req('review-candidate-submit.php')).status,405);
 assert.equal((await post('bad')).status,400);
 assert.equal((await post('00000000-0000-4000-8000-000000000000','wrong')).status,403);
 assert.equal((await post('00000000-0000-4000-8000-000000000000')).status,404);
 assert.equal((await post('bad',csrf,sid,{doctor_id:'other'})).status,400);
 const f=JSON.parse(php(`echo json_encode(mr11Candidate('${fixture.doctor}','DOCTOR_GALLERY'));`));
 const otherCsrf=(await req('review-batch-submit.php',{},other)).body.data.csrf;
 assert.equal((await post(f.id,otherCsrf,other)).status,404);
 assert.equal((await post(f.id)).status,200);assert.equal((await post(f.id)).body.data.already_submitted,true);
 // Close first setup batch and create unsent siblings in a fresh one.
 await req('review-batch-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf})});
 const siblings=JSON.parse(php(`echo json_encode([mr11Candidate('${fixture.doctor}','PHYSICIAN_PERSONAL_LOGO'),mr11Candidate('${fixture.doctor}','DOCTOR_GALLERY')]);`));
 const before=php(`echo json_encode($p->query("SELECT photo_url,logo_url FROM profiles_doctors WHERE doctor_id='${fixture.doctor}'")->fetch());`);
 // A minimal media DOM exercises the real shared upload/refresh code and HTTP services.
 await writeFile('mr02-browser.html',`<!doctype html><html><body><div id="p-info"><div id="mx-dg-media-card"><div class="mx-dg-card-head"></div><div id="mxpi-photo-control"></div><div data-profile-logo-upload></div></div><div id="fotos-drop"></div><div id="fotos-grid"></div></div><input id="photo-file" type="file"><p id="outcome"></p><script src="assets/js/owner-media-review.js"></script><script>photoFile=document.getElementById('photo-file');photoFile.onchange=async()=>{document.getElementById('outcome').textContent='busy';try{await mxmedMediaReview.upload('photo',photoFile.files[0]);document.getElementById('outcome').textContent='success'}catch(e){document.getElementById('outcome').textContent=e.message}finally{photoFile.value=''}};</script></body></html>`);
 profile=await mkdtemp('/tmp/mxmed-mr02-chrome-');chrome=spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',['--headless=new','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile],{stdio:'ignore'});
 let version;for(let i=0;i<100;i++){try{const port=(await readFile(profile+'/DevToolsActivePort','utf8')).split('\n')[0];version=await(await fetch('http://127.0.0.1:'+port+'/json/version')).json();break;}catch{await new Promise(r=>setTimeout(r,100));}}
 ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let seq=0,sessionId;const calls=new Map(),exceptions=[];
 const send=(method,params={},sid=sessionId)=>new Promise((resolve,reject)=>{const id=++seq;calls.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})}));});
 ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const p=calls.get(m.id);calls.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')exceptions.push(m.params);});
 const target=(await send('Target.createTarget',{url:'about:blank'},null)).targetId;sessionId=(await send('Target.attachToTarget',{targetId:target,flatten:true},null)).sessionId;await send('Runtime.enable');await send('Page.enable');await send('Network.enable');await send('Network.setCookie',{name:'PHPSESSID',value:sid,url:base});
 const evalJS=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const until=async expr=>{for(let i=0;i<100;i++){if(await evalJS(expr))return;await new Promise(r=>setTimeout(r,100));}throw Error('timeout '+expr);};
 await send('Page.navigate',{url:base+'/mr02-browser.html'});await until('Boolean(window.mxmedMediaReview) && document.querySelectorAll(".mx-media-review-candidate").length===3');
 await evalJS(`window.photoStates=[];new MutationObserver(()=>{const badge=document.querySelector('[data-review-candidate=photo] [data-review-state]');if(badge)photoStates.push(badge.dataset.reviewState)}).observe(document.getElementById('mxpi-photo-control'),{subtree:true,childList:true});`);
 const upload=async()=>{const {result}=await send('Runtime.evaluate',{expression:'document.getElementById("photo-file")'});await send('DOM.setFileInputFiles',{objectId:result.objectId,files:[fixture.image]});};
 await upload();await until('document.getElementById("outcome").textContent==="success"');
 assert.ok(!(await evalJS('photoStates')).includes('OPEN'),'no transient pending photo');
 assert.equal(await evalJS('document.querySelector("[data-review-candidate=photo] [data-review-state]").dataset.reviewState'),'SUBMITTED');
 let owner=(await req('owner-review.php')).body.data.items;
 for(const sib of siblings)assert.equal(owner.find(i=>i.id===sib.id).state,'OPEN');
 assert.equal((await req('review-batch-submit.php')).body.data.item_count,2);
 const savedPhoto=owner.find(i=>i.purpose==='DOCTOR_PROFILE_PHOTO').id;
 assert.equal((await req('review-batch-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf})})).status,200);
 assert.equal((await req('review-batch-submit.php')).body.data.item_count,0);
 assert.equal(php(`echo $p->query("SELECT COUNT(*) FROM platform_audit_events WHERE resource_reference='${savedPhoto}' AND action='MEDIA_REVIEW_CANDIDATE_SUBMITTED'")->fetchColumn();`),'1');
 // Audit failure after successful upload: real persisted candidate remains unsent.
 php(`$p->exec("CREATE TRIGGER mr02_http_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");`);
 try{await upload();await until('document.getElementById("outcome").textContent.includes("La foto se guardó")');}finally{php("$p->exec('DROP TRIGGER mr02_http_audit_failure');")}
 owner=(await req('owner-review.php')).body.data.items;const pending=owner.find(i=>i.purpose==='DOCTOR_PROFILE_PHOTO');assert.equal(pending.state,'OPEN');
 assert.equal(await evalJS('document.querySelector("[data-review-candidate=photo] [data-review-state]").dataset.reviewState'),'OPEN');
 assert.equal((await post(pending.id)).status,200);
 assert.equal(php(`echo json_encode($p->query("SELECT photo_url,logo_url FROM profiles_doctors WHERE doctor_id='${fixture.doctor}'")->fetch());`),before);
 assert.deepEqual(exceptions,[]);console.log('MR02_HTTP_BROWSER_QA=PASS: auth/CSRF/ownership/idempotence/photo-only auto-submit/no transient pending/sibling counters/full batch/failure recovery/public unchanged/no JS exceptions');
}finally{ws?.close();chrome?.kill();server.kill();if(profile)await rm(profile,{recursive:true,force:true});}
