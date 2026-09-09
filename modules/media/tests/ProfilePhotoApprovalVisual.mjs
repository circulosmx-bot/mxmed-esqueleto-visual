import assert from 'node:assert/strict';
import {mkdir,writeFile} from 'node:fs/promises';

export async function runApprovalVisualQA({base,tokens,candidate,sql}) {
 const output=process.env.MR5_VISUAL_DIR||'/tmp/mxmed-mr5-visual';await mkdir(output,{recursive:true});
 const version=await (await fetch((process.env.CDP_URL||'http://127.0.0.1:9348')+'/json/version')).json();
 const ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let seq=0,sessionId,contextId,paused=null;const pending=new Map(),requests=[];
 const send=(method,params={},session=sessionId)=>new Promise((resolve,reject)=>{const id=++seq;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId:session}:{})}));});
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);if(m.error)p?.reject(new Error(JSON.stringify(m.error)));else p?.resolve(m.result);return;}
  if(m.method==='Network.requestWillBeSent')requests.push(m.params.request);
  if(m.method==='Fetch.requestPaused')paused=m.params.requestId;
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
  await cookie(tokens.good);
  for(const [width,height] of [[1440,900],[1366,768],[390,844],[320,740]]) {
   const fixture=candidate();
   await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
   await send('Page.navigate',{url:base+'/internal/media-review/'});
   await until("document.querySelector('.review-thumb img')?.naturalWidth>0");
   assert.equal(await evaluate("document.querySelectorAll('.card').length"),1);
   await evaluate("document.querySelector('.card button').click()");
   await until("!document.getElementById('approve-photo').hidden && document.getElementById('detail-image').naturalWidth>0");
   await evaluate("document.getElementById('approve-photo').focus()");await key('Enter',13);
   await until("!document.getElementById('approval-confirmation').hidden && document.activeElement.id==='cancel-approval'");
   await key('Enter',13);await until("document.getElementById('approval-confirmation').hidden && document.activeElement.id==='approve-photo'");
   await key('Enter',13);await until("!document.getElementById('approval-confirmation').hidden");
   assert.equal(await evaluate("(()=>{const d=document.querySelector('dialog'),r=d.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight&&d.scrollWidth<=d.clientWidth})()"),true,'modal bounds');
   assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'horizontal overflow');
   await evaluate("document.getElementById('confirm-approval').scrollIntoView({block:'nearest'})");
   assert.equal(await evaluate("(()=>{const r=document.getElementById('confirm-approval').getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight&&r.left>=0&&r.right<=innerWidth})()"),true,'confirmation accessible');
   await screenshot(width+'-confirmation');
   await send('Fetch.enable',{patterns:[{urlPattern:'*approve.php',requestStage:'Request'}]});
   const oldPosts=requests.filter(r=>r.method==='POST').length;
   await evaluate("document.getElementById('confirm-approval').focus()");await key('Enter',13);
   for(let i=0;i<120&&!paused;i++)await new Promise(r=>setTimeout(r,50));assert.ok(paused,'approval request paused');
   assert.equal(await evaluate("document.getElementById('confirm-approval').disabled && document.getElementById('cancel-approval').disabled && document.getElementById('confirm-approval').textContent==='Publicando…'"),true,'busy controls');
   await evaluate("document.getElementById('confirm-approval').click()");await key('Escape',27);assert.equal(await evaluate("document.querySelector('dialog').open"),true,'busy escape guarded');
   await screenshot(width+'-busy');
   await send('Fetch.continueRequest',{requestId:paused});paused=null;await send('Fetch.disable');
   await until("document.getElementById('inbox-message').textContent==='Foto aprobada y publicada.'");
   assert.equal(await evaluate("document.querySelectorAll('.card').length"),0,'stale card removed');
   assert.equal(requests.filter(r=>r.method==='POST').length,oldPosts+1,'one frontend POST');
   assert.equal(sql(`echo $p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='${fixture.id}'")->fetchColumn();`),'APPROVED');
   await screenshot(width+'-success');
  }
  candidate();await cookie(tokens.wrong);await send('Page.navigate',{url:base+'/internal/media-review/'});
  await until("document.querySelector('.review-thumb img')?.naturalWidth>0");await evaluate("document.querySelector('.card button').click()");
  await until("document.querySelector('dialog').open");assert.equal(await evaluate("document.getElementById('approve-photo').hidden && document.getElementById('approval-confirmation').hidden"),true,'read-only control hidden');
  await screenshot('320-read-only');await key('Escape',27);await until("!document.querySelector('dialog').open && document.activeElement===document.querySelector('.card button')");
  assert.ok(!requests.some(r=>/source.php|source-image.php|\/source\/|storage_key/.test(r.url)),'SOURCE network access');
  console.log('VISUAL_QA=PASS: four viewports, keyboard confirmation/cancel, no clipping, busy/double-submit guard, four real synthetic publications, queue refresh/success, read-only hidden; '+output);
 } catch(error) {
  console.error(await evaluate("JSON.stringify({focus:document.activeElement?.id,open:document.querySelector('dialog')?.open,confirm:document.getElementById('approval-confirmation')?.hidden,approve:document.getElementById('approve-photo')?.hidden})"));await screenshot('failure');throw error;
 } finally {
  if(paused)await send('Fetch.continueRequest',{requestId:paused}).catch(()=>{});
  if(contextId)await send('Target.disposeBrowserContext',{browserContextId:contextId},null).catch(()=>{});
  ws.close();
 }
}
