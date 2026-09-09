import assert from 'node:assert/strict';
import {execFileSync,spawn} from 'node:child_process';
import {mkdir,writeFile} from 'node:fs/promises';
const php=code=>execFileSync('php',['-r',code],{encoding:'utf8'}).trim();
const snapshot=()=>execFileSync('php',['modules/media/tests/MediaReviewReadSnapshot.php'],{encoding:'utf8'}).trim();
const before=snapshot(),base='http://127.0.0.1:8096',id='42adffaa-3344-47cf-9bf5-6831c969cbd5';
const session=php("session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['media_review_dev_operator'=>['account_id'=>'mr2_local_operator','expires_at'=>time()+600,'session_status'=>'active','account_active'=>true,'review_read_granted'=>true]];echo session_id();session_write_close();");
const physician=php("session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'mr4_physician','doctor_id'=>'1','role'=>'administrator'];echo session_id();session_write_close();");
const child=spawn('php',['-S','127.0.0.1:8096','-t','.'],{env:{...process.env,APP_ENV:'local',MXMED_ENV:'local',MXMED_ENVIRONMENT:'local',ENVIRONMENT:'local',MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED:'1'},stdio:['ignore','ignore','pipe']});child.stderr.on('data',()=>{});
let ws,contextId,sessionId,send,mode='normal';const requests=[];
const output=process.env.MR4_VISUAL_DIR||'/tmp/mxmed-mr4-visual';
try{
 await new Promise((resolve,reject)=>{let tries=0;const t=setInterval(async()=>{if(++tries>100||child.exitCode!==null){clearInterval(t);reject(new Error('HTTP server failed'));return;}try{await fetch(base+'/internal/media-review/');clearInterval(t);resolve();}catch{}},50);});
 for(const route of ['/internal/media-review/','/api/internal/media-review/pending.php']){
  for(const cookie of ['', 'PHPSESSID='+physician]){
   const r=await fetch(base+route+'?capability=media_review_read&account_id=mr3_good',{headers:{...(cookie?{Cookie:cookie}:{}),'X-Role':'internal_operator','X-Capability':'media_review_read'}});assert.equal(r.status,403);const text=await r.text();assert.ok(!text.includes(id)&&!text.includes('pending-cards'));
  }
 }
 const version=await (await fetch((process.env.CDP_URL||'http://127.0.0.1:9348')+'/json/version')).json();ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let seq=0;const pending=new Map();
 send=(method,params={},session=sessionId)=>new Promise((resolve,reject)=>{const rid=++seq;pending.set(rid,{resolve,reject});ws.send(JSON.stringify({id:rid,method,params,...(session?{sessionId:session}:{})}));});
 ws.addEventListener('message',async event=>{const m=JSON.parse(event.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);if(m.error)p?.reject(new Error(JSON.stringify(m.error)));else p?.resolve(m.result);return;}
  if(m.method==='Network.requestWillBeSent')requests.push(m.params.request);
  if(m.method==='Fetch.requestPaused'){
   const p=m.params;
   if(mode==='empty'||mode==='error')await send('Fetch.fulfillRequest',{requestId:p.requestId,responseCode:mode==='error'?500:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify({ok:true,data:{items:[],pagination:{limit:25,offset:0,has_more:false,next_offset:null}}})).toString('base64')});
   else if(mode==='image-failure')await send('Fetch.failRequest',{requestId:p.requestId,errorReason:'Failed'});
   else await send('Fetch.continueRequest',{requestId:p.requestId});
  }
 });
 contextId=(await send('Target.createBrowserContext',{},null)).browserContextId;
 const target=(await send('Target.createTarget',{url:'about:blank',browserContextId:contextId},null)).targetId;
 sessionId=(await send('Target.attachToTarget',{targetId:target,flatten:true},null)).sessionId;
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Network.setCookie',{name:'PHPSESSID',value:session,url:base,path:'/'});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.text);return r.result.value;};
 const until=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,50));}throw new Error('Timed out: '+expression);};
 await mkdir(output,{recursive:true});
 for(const [width,height] of [[1440,900],[1366,768],[390,844],[320,740]]){
  await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});await send('Page.navigate',{url:base+'/internal/media-review/'});
  await until("document.querySelector('.review-thumb img')?.naturalWidth>0");
  assert.equal(await evaluate("document.querySelectorAll('.card').length"),1);
  assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'horizontal overflow');
  assert.equal(await evaluate("getComputedStyle(document.querySelector('.review-thumb img')).objectFit"),'contain');
  assert.equal(await evaluate("document.querySelector('.review-thumb img').loading"),'lazy');
  const page=await send('Page.captureScreenshot',{format:'png'});await writeFile(`${output}/${width}-inbox.png`,Buffer.from(page.data,'base64'));
  await evaluate("document.querySelector('.card button').click()");await until("document.querySelector('dialog').open && document.getElementById('detail-image').naturalWidth>0");
  assert.equal(await evaluate("(()=>{const r=document.querySelector('dialog').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()"),true,'modal clipped');
  assert.equal(await evaluate("document.getElementById('detail-specs').textContent.includes('KB')"),true);
  const modal=await send('Page.captureScreenshot',{format:'png'});await writeFile(`${output}/${width}-detail.png`,Buffer.from(modal.data,'base64'));
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
  await until("!document.querySelector('dialog').open");await until("document.activeElement===document.querySelector('.card button')");
 }
 for(const state of ['empty','error','image-failure']){
  mode=state;await send('Fetch.enable',{patterns:[{urlPattern:state==='image-failure'?'*review-image.php*':'*pending.php*'}]});await send('Page.navigate',{url:base+'/internal/media-review/'});
  const expected=state==='empty'?'No hay medios pendientes de revisión.':state==='error'?'No fue posible cargar los medios pendientes.':'Imagen no disponible.';
  await until(`document.body?.innerText.includes(${JSON.stringify(expected)})`);
  if(state==='image-failure')assert.equal(await evaluate("document.querySelectorAll('.card button').length"),1);
  await send('Fetch.disable');
 }
 const own=requests.filter(r=>r.url.startsWith(base));assert.ok(own.every(r=>r.method==='GET'),'mutation request');assert.ok(!own.some(r=>/SOURCE|source\/|storage_key/.test(r.url)),'source request');assert.ok(own.some(r=>r.url.includes('review-image.php?submission_id='+id)),'existing private endpoint not used');
 assert.equal(snapshot(),before,'retained state changed');
 console.log('PASS: 1440x900, 1366x768, 390x844, 320x740; contained lazy REVIEW; modal fits; Escape/focus; empty/list-error/image-error; GET-only network; public/private snapshot unchanged');console.log('VISUAL_ARTIFACTS='+output);
 await send('Target.disposeBrowserContext',{browserContextId:contextId},null);contextId=null;
}finally{
 if(contextId&&send)await send('Target.disposeBrowserContext',{browserContextId:contextId},null).catch(()=>{});
 if(ws)ws.close();child.kill();for(const sid of [session,physician])php(`session_id('${sid}');session_start();session_destroy();`);
}
