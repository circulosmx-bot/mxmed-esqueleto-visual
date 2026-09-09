import assert from 'node:assert/strict';
import {mkdir,writeFile} from 'node:fs/promises';
export async function runBatchVisualQA({base,tokens,batch,legacy,sql}){
 const output='/tmp/mxmed-mr11-visual';await mkdir(output,{recursive:true});const version=await(await fetch((process.env.CDP_URL||'http://127.0.0.1:9348')+'/json/version')).json();
 let failAt=0,seenApprovals=0;
 const ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let seq=0,sessionId,contextId;const pending=new Map(),requests=[];
 const send=(method,params={},session=sessionId)=>new Promise((resolve,reject)=>{const id=++seq;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId:session}:{})}));});
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p?.reject(new Error(JSON.stringify(m.error))):p?.resolve(m.result);}else if(m.method==='Network.requestWillBeSent')requests.push(m.params.request);else if(m.method==='Fetch.requestPaused'){seenApprovals++;if(failAt&&seenApprovals===failAt)send('Fetch.fulfillRequest',{requestId:m.params.requestId,responseCode:503,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:btoa(JSON.stringify({ok:false,error:'unavailable'}))}).catch(()=>{});else send('Fetch.continueRequest',{requestId:m.params.requestId}).catch(()=>{});}});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const until=async expression=>{for(let i=0;i<160;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,50));}throw new Error('Timeout '+expression);};
 const key=async(name,code)=>{await send('Input.dispatchKeyEvent',{type:'keyDown',key:name,code:name,windowsVirtualKeyCode:code,...(name==='Enter'?{text:'\r',unmodifiedText:'\r'}:{})});await send('Input.dispatchKeyEvent',{type:'keyUp',key:name,code:name,windowsVirtualKeyCode:code});};
 const click=async id=>{await evaluate(`document.getElementById('${id}').focus()`);if(await evaluate(`document.getElementById('${id}').type==='checkbox'`)){await send('Input.dispatchKeyEvent',{type:'keyDown',key:' ',code:'Space',windowsVirtualKeyCode:32});await send('Input.dispatchKeyEvent',{type:'keyUp',key:' ',code:'Space',windowsVirtualKeyCode:32});}else await key('Enter',13);};
 const shot=async name=>{const r=await send('Page.captureScreenshot',{format:'png'});await writeFile(output+'/'+name+'.png',Buffer.from(r.data,'base64'));};
 const pixel=async(id,x,y)=>evaluate(`(()=>{const i=document.getElementById('${id}'),c=document.createElement('canvas');c.width=i.naturalWidth;c.height=i.naturalHeight;const ctx=c.getContext('2d');ctx.drawImage(i,0,0);return Array.from(ctx.getImageData(Math.floor(c.width*${x}),Math.floor(c.height*${y}),1,1).data)})()`);
 try{
  contextId=(await send('Target.createBrowserContext',{},null)).browserContextId;const target=(await send('Target.createTarget',{url:'about:blank',browserContextId:contextId},null)).targetId;sessionId=(await send('Target.attachToTarget',{targetId:target,flatten:true},null)).sessionId;
  await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Page.bringToFront');
  const cookie=async value=>send('Network.setCookie',{name:'__Host-mxmed_session',value,url:base,path:'/',secure:true,httpOnly:true,sameSite:'Lax'});await cookie(tokens.good);

  for(const [width,height] of [[1440,900],[1366,768],[390,844],[320,740]])for(const scenario of ['single','eighteen','mixed','legacy','partial']){
   const f=batch(scenario==='single'?1:16,scenario!=='single');
   const submit=f=>sql('(new Media\\Services\\MediaReviewBatchService($p))->submit("'+f.doctor+'");');submit(f);
   if(scenario==='mixed')submit(batch(2,true));if(scenario==='legacy')legacy();
   if(scenario==='partial')sql('$ids=$p->query("SELECT submission_id FROM media_review_submissions WHERE batch_id=\\x27'+f.batch_id+'\\x27 AND purpose=\\x27DOCTOR_GALLERY\\x27 ORDER BY created_at,submission_id")->fetchAll(PDO::FETCH_COLUMN);[$pr,$pu]=mr5Storage();for($i=0;$i<2;$i++)(new Media\\Services\\GalleryApprovalService($p,$pr,$pu))->approve(mr5Context(),$ids[$i]);(new Media\\Services\\MediaReplacementService($p,$pr))->request(mr9Context(),$ids[2],"OTHER");');
   await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});await send('Page.navigate',{url:base+'/internal/media-review/'});
   await until("document.querySelectorAll('.batch-card').length==="+(scenario==='mixed'?2:1)+"&&document.getElementById('pending-cards').getAttribute('aria-busy')==='false'");
   assert.equal(await evaluate("document.querySelectorAll('#pending-cards .card').length"),scenario==='legacy'?1:0,'no duplicate top-level members');
   await shot(width+'-'+scenario+'-queue');
   if(width===1440&&scenario==='legacy'){
    await evaluate("window.savedFetch=window.fetch;window.fetch=async(...args)=>{const response=await window.savedFetch(...args);if(String(args[0]).includes('/batch.php?')&&!window.heldBatch){window.heldBatch=true;await new Promise(resolve=>window.releaseBatch=resolve);}return response;}");
    await evaluate("[...document.querySelectorAll('[data-batch-id]')].find(b=>b.dataset.batchId==="+JSON.stringify(f.batch_id)+").click()");
    await until("!!window.releaseBatch");await click('close-batch');await until("!document.getElementById('batch-queue').hidden&&document.getElementById('pending-cards').getAttribute('aria-busy')==='false'");
    await evaluate("window.releaseBatch();window.fetch=window.savedFetch;new Promise(resolve=>setTimeout(resolve,100))");
    assert.equal(await evaluate("document.querySelectorAll('#pending-cards .card').length"),1,'late detail response cannot overwrite legacy queue');
   }
   await evaluate("[...document.querySelectorAll('[data-batch-id]')].find(b=>b.dataset.batchId==="+JSON.stringify(f.batch_id)+").focus()");
   await key('Enter',13);
   const n=scenario==='single'?1:18;await until("document.querySelectorAll('#pending-cards .card').length==="+n+"&&document.getElementById('batch-queue').hidden");
   assert.ok((await evaluate("document.getElementById('batch-summary').textContent")).startsWith(n+' medios'));
   assert.equal(await evaluate("document.activeElement.id"),'pending-title');
   assert.equal(await evaluate("[...document.querySelectorAll('#pending-cards img')].every(i=>i.loading==='lazy'&&getComputedStyle(i).objectFit==='contain')"),true);
   assert.equal(requests.some(r=>r.url.includes('source-download')),false,'no SOURCE preview');
   const pendingGallery=scenario==='single'?1:scenario==='partial'?13:16;
   await until("!document.getElementById('gallery-bulk').hidden");
   assert.equal(await evaluate("document.querySelectorAll('.gallery-select input').length"),pendingGallery);
   await evaluate("document.querySelector('.gallery-select').closest('.card').querySelector('button').focus()");await key('Enter',13);await until("document.querySelector('dialog').open&&document.getElementById('detail-image').naturalWidth>0");
   assert.equal(await evaluate("document.getElementById('logo-improvement').hidden"),true);assert.equal(await evaluate("document.getElementById('request-replacement').hidden||document.getElementById('design-intervention').hidden"),false);
   await shot(width+'-'+scenario+'-image');await click('close-detail');await until("!document.querySelector('dialog').open&&document.activeElement.closest('.card')!==null");
   await shot(width+'-'+scenario+'-items');await click('select-page-gallery');assert.equal(await evaluate("document.querySelectorAll('.gallery-select input:checked').length"),pendingGallery);
   let confirmed=pendingGallery;
   if(width===1440&&scenario==='eighteen'){sql('[$pr,$pu]=mr5Storage();(new Media\\Services\\GalleryApprovalService($p,$pr,$pu))->approve(mr5Context(),"'+f.id+'");');confirmed--;}
   if(width===1366&&scenario==='eighteen'){failAt=2;seenApprovals=0;await send('Fetch.enable',{patterns:[{urlPattern:'*approve-gallery.php*',requestStage:'Request'}]});confirmed=1;}
   await click('approve-selected-gallery');await until("document.getElementById('inbox-message').textContent.startsWith('Se confirmó la aprobación de ')");
   if(failAt){await send('Fetch.disable');failAt=0;}
   assert.ok((await evaluate("document.getElementById('inbox-message').textContent")).includes(confirmed+' de '+pendingGallery));
   assert.equal(await evaluate("document.querySelectorAll('#pending-cards .card').length"),n,'membership retained after approval');
   assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth"),true,'no horizontal overflow');
   await click('close-batch');await until("!document.getElementById('batch-queue').hidden&&document.getElementById('pending-cards').getAttribute('aria-busy')==='false'");
   await until("document.activeElement.id==='pending-title'||!!document.activeElement.dataset.batchId");
   sql('$p->exec("UPDATE media_review_submissions SET review_status=\\x27WITHDRAWN\\x27 WHERE review_status=\\x27PENDING_REVIEW\\x27");');
  }
  console.log('MR11_VISUAL_QA=PASS: four viewports x single/18/mixed/legacy/partial; batch-first, counts, independent controls, gallery multiselect, immutable membership, keyboard/focus, no overflow');
 }finally{if(contextId)await send('Target.disposeBrowserContext',{browserContextId:contextId},null);ws.close();}
}
