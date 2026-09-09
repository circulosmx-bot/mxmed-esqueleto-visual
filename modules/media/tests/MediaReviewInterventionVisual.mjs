import assert from 'node:assert/strict';
import {mkdir,writeFile} from 'node:fs/promises';

export async function runInterventionVisualQA({base,tokens,candidate,sql,upload,grant,revoke}) {
 const output=process.env.MR6_VISUAL_DIR||'/tmp/mxmed-mr6-visual';await mkdir(output,{recursive:true});
 const version=await (await fetch((process.env.CDP_URL||'http://127.0.0.1:9348')+'/json/version')).json();
 const ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let seq=0,sessionId,contextId,paused=null,fileChooser=null,failReview=false;const pending=new Map(),requests=[];
 const send=(method,params={},session=sessionId)=>new Promise((resolve,reject)=>{const id=++seq;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId:session}:{})}));});
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);if(m.error)p?.reject(new Error(JSON.stringify(m.error)));else p?.resolve(m.result);return;}
  if(m.method==='Network.requestWillBeSent')requests.push(m.params.request);
  if(m.method==='Page.fileChooserOpened')fileChooser=m.params;
  if(m.method==='Fetch.requestPaused'){if(failReview)send('Fetch.failRequest',{requestId:m.params.requestId,errorReason:'Failed'}).catch(()=>{});else paused=m.params.requestId;}
 });
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const until=async expression=>{for(let i=0;i<120;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,50));}throw new Error('Timed out: '+expression);};
 const key=async(name,code)=>{await send('Input.dispatchKeyEvent',{type:'keyDown',key:name,code:name,windowsVirtualKeyCode:code,...(name==='Enter'?{text:'\r',unmodifiedText:'\r'}:{})});await send('Input.dispatchKeyEvent',{type:'keyUp',key:name,code:name,windowsVirtualKeyCode:code});};
 const screenshot=async name=>{const r=await send('Page.captureScreenshot',{format:'png'});await writeFile(output+'/'+name+'.png',Buffer.from(r.data,'base64'));};
 try {
  contextId=(await send('Target.createBrowserContext',{},null)).browserContextId;
  const target=(await send('Target.createTarget',{url:'about:blank',browserContextId:contextId},null)).targetId;
  sessionId=(await send('Target.attachToTarget',{targetId:target,flatten:true},null)).sessionId;
  await send('Page.enable');await send('Page.bringToFront');await send('Runtime.enable');await send('Network.enable');
  const cookie=async token=>{await send('Network.setCookie',{name:'__Host-mxmed_session',value:token,url:base,path:'/',secure:true,httpOnly:true,sameSite:'Lax'});};
  await cookie(tokens.good);await mkdir(output+'/downloads',{recursive:true});await send('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:output+'/downloads',browserContextId:contextId},null);await send('Page.setInterceptFileChooserDialog',{enabled:true});
  for(const [width,height] of [[1440,900],[1366,768],[390,844],[320,740]]) {
   const fixture=candidate();
   await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
   await send('Page.navigate',{url:base+'/internal/media-review/'});
   await until("document.querySelector('.review-thumb img')?.naturalWidth>0");
   assert.equal(await evaluate("document.querySelectorAll('.card').length"),1);
   await evaluate("document.querySelector('.card button').click()");
   await until("!document.getElementById('approve-photo').hidden && document.getElementById('detail-image').naturalWidth>0");
   await until("!document.getElementById('download-source').hidden && !document.getElementById('choose-corrected').hidden");
   await evaluate("document.getElementById('download-source').focus()");await key('Enter',13);await until("document.getElementById('intervention-message').textContent==='Original listo para descargar.'");
   await evaluate("document.getElementById('choose-corrected').focus()");await key('Enter',13);
   for(let i=0;i<120&&!fileChooser;i++)await new Promise(r=>setTimeout(r,50));assert.ok(fileChooser,'native file picker');
   await send('DOM.setFileInputFiles',{files:[upload.tmp_name],backendNodeId:fileChooser.backendNodeId});fileChooser=null;
   await until("!document.getElementById('submit-corrected').hidden");
   assert.equal(await evaluate("document.getElementById('corrected-filename').textContent.includes('/')"),false,'no private filename path');
   await evaluate("document.getElementById('submit-corrected').scrollIntoView({block:'nearest'})");
   assert.equal(await evaluate("(()=>{const d=document.querySelector('dialog'),r=d.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight&&d.scrollWidth<=d.clientWidth})()"),true,'modal bounds');
   assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'horizontal overflow');
   await screenshot(width+'-selected');
   await send('Fetch.enable',{patterns:[{urlPattern:'*corrected.php',requestStage:'Request'}]});
   const oldPosts=requests.filter(r=>r.method==='POST').length;
   await evaluate("document.getElementById('submit-corrected').focus()");await key('Enter',13);
   for(let i=0;i<120&&!paused;i++)await new Promise(r=>setTimeout(r,50));assert.ok(paused,'correction request paused');
   assert.equal(await evaluate("document.getElementById('submit-corrected').disabled && document.getElementById('approve-photo').disabled && document.getElementById('intervention-message').textContent==='Procesando versión corregida…'"),true,'busy controls');
   await evaluate("document.getElementById('submit-corrected').click()");await key('Escape',27);assert.equal(await evaluate("document.querySelector('dialog').open"),true,'busy escape guarded');
   assert.equal(await evaluate("(()=>{const r=document.getElementById('intervention-message').getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight})()"),true,'busy message visible');
   await screenshot(width+'-busy');
   await send('Fetch.continueRequest',{requestId:paused});paused=null;await send('Fetch.disable');
   await until("document.getElementById('intervention-message').textContent==='Versión corregida lista para revisión.' && document.getElementById('detail-image').naturalWidth===640 && !document.getElementById('approve-photo').disabled");
   assert.equal(await evaluate("document.getElementById('detail-specs').textContent.includes('640 × 360')"),true,'new REVIEW metadata');
   assert.equal(requests.filter(r=>r.method==='POST').length,oldPosts+1,'one corrected POST');
   assert.equal(sql(`echo $p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='${fixture.id}'")->fetchColumn();`),'PENDING_REVIEW');
   await screenshot(width+'-corrected');
   await evaluate("document.getElementById('approve-photo').focus()");await key('Enter',13);
   await until("!document.getElementById('approval-confirmation').hidden");await evaluate("document.getElementById('confirm-approval').focus()");await key('Enter',13);
   await until("document.getElementById('inbox-message').textContent==='Foto aprobada y publicada.'");
   assert.equal(await evaluate("document.querySelectorAll('.card').length"),0,'stale card removed');
   assert.equal(requests.filter(r=>r.method==='POST').length,oldPosts+2,'corrected then approval POST');
   assert.equal(sql(`echo $p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='${fixture.id}'")->fetchColumn();`),'APPROVED');
   await screenshot(width+'-published');
  }
  candidate();await send('Page.navigate',{url:base+'/internal/media-review/'});await until("document.querySelector('.review-thumb img')?.naturalWidth===420");await evaluate("document.querySelector('.card button').click()");await until("document.getElementById('detail-image').naturalWidth===420 && !document.getElementById('choose-corrected').hidden");
  await evaluate("document.getElementById('choose-corrected').focus()");await key('Enter',13);for(let i=0;i<120&&!fileChooser;i++)await new Promise(r=>setTimeout(r,50));assert.ok(fileChooser);await send('DOM.setFileInputFiles',{files:[upload.tmp_name],backendNodeId:fileChooser.backendNodeId});fileChooser=null;
  failReview=true;await send('Fetch.enable',{patterns:[{urlPattern:'*review-image.php*',requestStage:'Request'}]});await evaluate("document.getElementById('submit-corrected').click()");await until("document.getElementById('intervention-message').textContent.includes('Versión corregida guardada. Recarga')");
  assert.equal(await evaluate("document.getElementById('approve-photo').disabled && document.getElementById('detail-image').hidden"),true,'failed refresh cannot approve stale image');await screenshot('320-refresh-failed');
  await send('Fetch.disable');failReview=false;await send('Page.navigate',{url:base+'/internal/media-review/'});await until("document.querySelector('.review-thumb img')?.naturalWidth===640");
  await cookie(tokens.wrong);await send('Page.navigate',{url:base+'/internal/media-review/'});
  await until("document.querySelector('.review-thumb img')?.naturalWidth>0");await evaluate("document.querySelector('.card button').click()");
  await until("document.querySelector('dialog').open");assert.equal(await evaluate("document.getElementById('approve-photo').hidden && document.getElementById('approval-confirmation').hidden"),true,'read-only control hidden');assert.equal(await evaluate("document.getElementById('design-intervention').hidden"),true,'read-only design hidden');
  await screenshot('320-read-only');await key('Escape',27);await until("!document.querySelector('dialog').open && document.activeElement===document.querySelector('.card button')");
  grant('wrong','media_review_source_download');await send('Page.navigate',{url:base+'/internal/media-review/'});await until("document.querySelector('.card button')");await evaluate("document.querySelector('.card button').click()");await until("!document.getElementById('download-source').hidden");assert.equal(await evaluate("document.getElementById('choose-corrected').hidden"),true,'source-only control');
  revoke('wrong','media_review_source_download');grant('wrong','media_review_corrected_upload');await send('Page.navigate',{url:base+'/internal/media-review/'});await until("document.querySelector('.card button')");await evaluate("document.querySelector('.card button').click()");await until("!document.getElementById('choose-corrected').hidden");assert.equal(await evaluate("document.getElementById('download-source').hidden"),true,'corrected-only control');
  assert.ok(!requests.some(r=>/corrected-download|\/source\/|storage_key/.test(r.url)),'private path request');
  console.log('MR6_VISUAL_QA=PASS: four viewports, audited SOURCE download, native file picker, busy guard, refreshed REVIEW, MR5 approval, keyboard, capability-specific controls, no overflow; '+output);
 } catch(error) {
  console.error(await evaluate("JSON.stringify({focus:document.activeElement?.id,open:document.querySelector('dialog')?.open,confirm:document.getElementById('approval-confirmation')?.hidden,approve:document.getElementById('approve-photo')?.hidden,disabled:document.getElementById('approve-photo')?.disabled,width:document.getElementById('detail-image')?.naturalWidth,message:document.getElementById('intervention-message')?.textContent})"));await screenshot('failure');throw error;
 } finally {
  if(paused)await send('Fetch.continueRequest',{requestId:paused}).catch(()=>{});
  if(contextId)await send('Target.disposeBrowserContext',{browserContextId:contextId},null).catch(()=>{});
  ws.close();
 }
}
