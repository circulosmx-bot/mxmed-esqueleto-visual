import assert from 'node:assert/strict';
import {mkdir,writeFile} from 'node:fs/promises';
export async function runReplacementVisualQA({base,tokens,fixture,sql,grant,revoke}){
 const output='/tmp/mxmed-mr9-visual';await mkdir(output,{recursive:true});const version=await(await fetch((process.env.CDP_URL||'http://127.0.0.1:9348')+'/json/version')).json();
 const ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let seq=0,sessionId,contextId;const pending=new Map(),requests=[];
 const send=(method,params={},session=sessionId)=>new Promise((resolve,reject)=>{const id=++seq;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId:session}:{})}));});
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p?.reject(new Error(JSON.stringify(m.error))):p?.resolve(m.result);}else if(m.method==='Network.requestWillBeSent')requests.push(m.params.request);else if(m.method==='Fetch.requestPaused')send('Fetch.failRequest',{requestId:m.params.requestId,errorReason:'Failed'}).catch(()=>{});});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const until=async expression=>{for(let i=0;i<160;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,50));}throw new Error('Timeout '+expression);};
 const key=async(name,code)=>{await send('Input.dispatchKeyEvent',{type:'keyDown',key:name,code:name,windowsVirtualKeyCode:code,...(name==='Enter'?{text:'\r',unmodifiedText:'\r'}:{})});await send('Input.dispatchKeyEvent',{type:'keyUp',key:name,code:name,windowsVirtualKeyCode:code});};
 const click=async id=>{await evaluate(`document.getElementById('${id}').focus()`);await key('Enter',13);};
 const shot=async name=>{const r=await send('Page.captureScreenshot',{format:'png'});await writeFile(output+'/'+name+'.png',Buffer.from(r.data,'base64'));};
 const pixel=async(id,x,y)=>evaluate(`(()=>{const i=document.getElementById('${id}'),c=document.createElement('canvas');c.width=i.naturalWidth;c.height=i.naturalHeight;const ctx=c.getContext('2d');ctx.drawImage(i,0,0);return Array.from(ctx.getImageData(Math.floor(c.width*${x}),Math.floor(c.height*${y}),1,1).data)})()`);
 try{
  contextId=(await send('Target.createBrowserContext',{},null)).browserContextId;const target=(await send('Target.createTarget',{url:'about:blank',browserContextId:contextId},null)).targetId;sessionId=(await send('Target.attachToTarget',{targetId:target,flatten:true},null)).sessionId;
  await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Page.bringToFront');
  const cookie=async value=>send('Network.setCookie',{name:'__Host-mxmed_session',value,url:base,path:'/',secure:true,httpOnly:true,sameSite:'Lax'});await cookie(tokens.good);
  for(const [width,height] of [[1440,900],[1366,768],[390,844],[320,740]])for(const kind of ['photo','logo']){
   const f=fixture(kind);await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});await send('Page.navigate',{url:base+'/internal/media-review/'});await until("document.querySelector('.card button')");await evaluate("document.querySelector('.card button').click()");await until("!document.getElementById('request-replacement').hidden");
   await click('request-replacement');await until("!document.getElementById('replacement-form').hidden");assert.equal(await evaluate("document.activeElement.id"),'replacement-reason');
   await click('cancel-replacement');assert.equal(await evaluate("document.getElementById('replacement-form').hidden"),true);await click('request-replacement');
   await evaluate("document.getElementById('replacement-reason').value='WRONG_MEDIA_TYPE';const t=document.getElementById('replacement-feedback');t.value='á'.repeat(401);t.dispatchEvent(new Event('input'))");assert.equal(await evaluate("document.getElementById('replacement-feedback').value.length"),400);
   const feedback='Envíe otra imagen. <script>alert(1)</script>';await evaluate(`document.getElementById('replacement-feedback').value=${JSON.stringify(feedback)};document.getElementById('replacement-feedback').dispatchEvent(new Event('input'))`);
   assert.equal(await evaluate("document.querySelectorAll('#replacement-form script').length"),0);
   assert.equal(await evaluate("(()=>{const d=document.querySelector('dialog');return document.documentElement.scrollWidth<=innerWidth&&d.scrollWidth<=d.clientWidth})()"),true,'no overflow');
   await evaluate("document.getElementById('replacement-form').scrollIntoView({block:'start'})");await shot(width+'-'+kind+'-decision');
   // Transport error leaves decision and user-facing text editable, without changing authority.
   await send('Fetch.enable',{patterns:[{urlPattern:'*request-replacement.php*',requestStage:'Request'}]});assert.equal(await evaluate("(()=>{document.getElementById('confirm-replacement').click();return ['confirm-replacement','cancel-replacement','replacement-reason','replacement-feedback','approve-photo','close-detail'].every(id=>document.getElementById(id).disabled)})()"),true,'busy_blocks_all_actions');await until("document.getElementById('replacement-message').textContent.includes('No fue posible')");await send('Fetch.disable');assert.equal(sql(`echo $p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='${f.id}'")->fetchColumn();`),'PENDING_REVIEW');
   assert.equal(await evaluate("document.getElementById('replacement-feedback').value"),feedback);
   await click('confirm-replacement');await evaluate("document.getElementById('confirm-replacement').click()");
   await until("!document.getElementById('review-detail').open && document.getElementById('inbox-message').textContent==='Se solicitó una nueva imagen al usuario.'");
   assert.equal(sql(`echo $p->query("SELECT COUNT(*) FROM platform_audit_events WHERE resource_reference='${f.id}' AND action='MEDIA_REVIEW_REPLACEMENT_REQUESTED'")->fetchColumn();`),'1');
   assert.equal(sql(`echo $p->query("SELECT review_feedback FROM media_review_submissions WHERE submission_id='${f.id}'")->fetchColumn();`),feedback);await shot(width+'-'+kind+'-success');
  }
  const f=fixture('photo');await cookie(tokens.wrong);await send('Page.navigate',{url:base+'/internal/media-review/'});await until("document.querySelector('.card button')");await evaluate("document.querySelector('.card button').click()");await until("document.querySelector('dialog').open");assert.equal(await evaluate("document.getElementById('request-replacement').hidden"),true,'read_only_hidden');
  console.log('MR9_VISUAL_QA=PASS: 8 viewport/purpose combinations; reasons; 400 chars; cancel; error/retry; keyboard; single submission; no overflow; plain text; capability visibility');
 }finally{if(contextId)await send('Target.disposeBrowserContext',{browserContextId:contextId},null);ws.close();}
}
