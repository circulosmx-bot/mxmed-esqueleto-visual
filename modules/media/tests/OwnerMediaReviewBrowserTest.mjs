import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdtemp,writeFile,readFile,rm} from 'node:fs/promises';
export async function ownerBrowser({base,image}) {
 const profile=await mkdtemp('/tmp/mxmed-mr12a-browser-');
 const chrome=spawn(process.env.MXMED_QA_CHROME||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',['--headless=new','--no-first-run','--no-default-browser-check','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0','--user-data-dir='+profile],{stdio:'ignore'});
 let ws;
 try{
  let version;for(let i=0;i<100;i++){try{const port=(await readFile(profile+'/DevToolsActivePort','utf8')).split('\n')[0];version=await(await fetch('http://127.0.0.1:'+port+'/json/version')).json();break;}catch{await new Promise(r=>setTimeout(r,100));}}
  ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
  let seq=0,session;const pending=new Map(),requests=[];
  const send=(method,params={},sid=session)=>new Promise((resolve,reject)=>{const id=++seq;pending.set(id,{resolve,reject,method});ws.send(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})}));});
  ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(p.method+": "+JSON.stringify(m.error))):p.resolve(m.result);}else if(m.method==='Network.requestWillBeSent')requests.push(m.params.request);});
  const target=(await send('Target.createTarget',{url:'about:blank'},null)).targetId;session=(await send('Target.attachToTarget',{targetId:target,flatten:true},null)).sessionId;
  await send('Page.enable');await send('Network.enable');await send('Runtime.enable');
  const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
  const until=async expression=>{for(let i=0;i<150;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100));}throw Error('Browser timeout: '+expression);};
  await send('Page.navigate',{url:base+'/internal/media-review/'});
  await until('document.body?.innerText.includes("Acceso restringido")');
  await send('Page.navigate',{url:base+'/qa-login.php?as=owner'});
  await until('location.pathname==="/index.html" && Boolean(window.mxmedMediaReview)');
  assert.equal(await evaluate('(async()=>{const r=await fetch("/internal/media-review/");return r.status})()'),403,'owner not reviewer');
  await until('document.readyState==="complete"');
  await new Promise(r=>setTimeout(r,1500));
  await evaluate('document.querySelector(".menu-sub-btn[data-panel=p-info]").click();document.getElementById("t-info-datos-tab").click()');
  await until('document.getElementById("mxpi-photo-control").getBoundingClientRect().height>0');
  for(const selector of ['#mxpi-photo-input','#mx-dg-media-card [data-profile-logo-upload] input[type=file]','#fotos-input']){
   const result=await send('Runtime.evaluate',{expression:'document.querySelector('+JSON.stringify(selector)+')'});const objectId=result.result.objectId;assert.ok(objectId,selector);
   const before=requests.filter(r=>r.method==='POST'&&r.url.includes('review-candidate.php')).length;
   await send('DOM.setFileInputFiles',{objectId,files:[image]});
   for(let i=0;i<100&&requests.filter(r=>r.method==='POST'&&r.url.includes('review-candidate.php')).length===before;i++)await new Promise(r=>setTimeout(r,100));
   assert.equal(requests.filter(r=>r.method==='POST'&&r.url.includes('review-candidate.php')).length,before+1,selector);
   await until('!document.querySelector("[data-profile-logo-upload][aria-busy=true]") && !document.getElementById("mxpi-photo-control").hasAttribute("aria-busy") && !document.getElementById("fotos-drop").hasAttribute("aria-busy")');
  }
  await until('[...document.querySelectorAll("section[aria-live] p")].filter(e=>e.textContent==="Pendiente de enviar").length===6');
  assert.ok(!requests.some(r=>r.method==='POST'&&(/\/api\/media\/(profile-photo|gallery)\.php/.test(r.url)||/\/logo(?:\?|$)/.test(r.url))),'no immediate-public upload');
  assert.ok(!requests.some(r=>/SOURCE|storage_key|\/source\//.test(r.url)),'no source request');
  for(const width of [1366,390]){await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<500});await evaluate('document.getElementById("mx-dg-media-card").nextElementSibling.scrollIntoView({behavior:"instant",block:"start"})');await new Promise(r=>setTimeout(r,600));const shot=await send('Page.captureScreenshot',{format:'png'});await writeFile('/tmp/mxmed-mr12a-owner-'+width+'.png',Buffer.from(shot.data,'base64'));}
  await evaluate('[...document.querySelectorAll("section[aria-live] button")].find(b=>b.textContent==="Enviar a revisión").click()');
  await until('[...document.querySelectorAll("section[aria-live] p")].filter(e=>e.textContent==="Enviado a revisión").length===6');
  assert.equal(await evaluate('[...document.querySelectorAll("section[aria-live] button")].filter(b=>b.textContent==="Enviar a revisión").length'),0);
  await send('Page.navigate',{url:base+'/qa-login.php?as=reviewer'});
  await until('location.pathname==="/internal/media-review/" && document.querySelectorAll("#batch-cards button").length===1');
  const cookies=(await send('Network.getCookies')).cookies;
  assert.ok(cookies.some(c=>c.name==='mxmed_mr12a_qa'&&c.httpOnly&&!c.secure));
  assert.ok(!cookies.some(c=>c.name==='__Host-mxmed_session'),'no canonical token in browser');
  assert.equal(await evaluate('document.cookie.includes("mxmed_mr12a_qa")'),false,'QA cookie is HttpOnly');
  assert.equal(await evaluate('(async()=>{const r=await fetch("/api/media/owner-review.php");return r.status})()'),401,'reviewer not owner');
  await evaluate('document.querySelector("#batch-cards button").click()');await until('document.querySelectorAll("#pending-cards .card").length===3');
  await evaluate('document.querySelector("#pending-cards .card button").click()');await until('!document.getElementById("approve-photo").hidden && !document.getElementById("approve-photo").disabled');
  await evaluate('document.getElementById("approve-photo").click();document.getElementById("confirm-approval").click()');await until('!document.querySelector("dialog").open');
  assert.ok(requests.some(r=>r.method==='POST'&&/approve(?:-logo|-gallery)?\.php/.test(r.url)));
  await send('Page.navigate',{url:base+'/qa-login.php?as=owner'});
  await until('location.pathname==="/index.html" && Boolean(window.mxmedMediaReview)');
  assert.equal(await evaluate('(async()=>{const r=await fetch("/internal/media-review/");return r.status})()'),403,'switch to owner clears reviewer');
  console.log('MR12A_BROWSER_LOGIN=PASS: unauthenticated denied; owner/reviewer normal qa-login redirects; no cookie injection; role isolation; authorized approval');
  console.log('MR12A_BROWSER=PASS: three actual UI uploads, pending/submitted rendering, manual submit, one batch card, reviewer approval, no direct-public POST; desktop/mobile screenshots in /tmp');
 }finally{ws?.close();chrome.kill();await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true});}
}
