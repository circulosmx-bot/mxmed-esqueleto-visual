// Read-only Director review runtime: public photo/logo and eight gallery images.
// Review states and mutations use mocked fetch; an absent live candidate is simulated.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-sig03b-browser';
const profile = await mkdtemp('/tmp/mxmed-crd03-browser-');
await mkdir(output, {recursive:true});

const chrome = spawn(
  process.env.MXMED_QA_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ['--headless=new', '--no-first-run', '--no-default-browser-check', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`],
  {stdio:'ignore'}
);

let ws;
try{
  let version;
  for(let attempt = 0; attempt < 100; attempt += 1){
    try{
      const port = (await readFile(`${profile}/DevToolsActivePort`, 'utf8')).split('\n')[0];
      version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json();
      break;
    }catch{
      await new Promise((resolve)=> setTimeout(resolve, 100));
    }
  }
  assert.ok(version?.webSocketDebuggerUrl, 'Chrome DevTools did not start');
  ws = new WebSocket(version.webSocketDebuggerUrl);
  await new Promise((resolve)=> ws.addEventListener('open', resolve, {once:true}));

  let sequence = 0;
  let session;
  const pending = new Map();
  const runtimeExceptions = [];
  const send = (method, params = {}, sid = session)=> new Promise((resolve, reject)=>{
    const id = ++sequence;
    pending.set(id, {resolve, reject, method});
    ws.send(JSON.stringify({id, method, params, ...(sid ? {sessionId:sid} : {})}));
  });
  ws.addEventListener('message', (event)=>{
    const message = JSON.parse(event.data);
    if(message.method==='Runtime.exceptionThrown')runtimeExceptions.push(message.params);
    if(!message.id) return;
    const request = pending.get(message.id);
    pending.delete(message.id);
    message.error ? request.reject(Error(`${request.method}: ${JSON.stringify(message.error)}`)) : request.resolve(message.result);
  });

  const target = (await send('Target.createTarget', {url:'about:blank'}, null)).targetId;
  session = (await send('Target.attachToTarget', {targetId:target, flatten:true}, null)).sessionId;
  await send('Page.enable');
  await send('Runtime.enable');
  const evaluate = async(expression)=>{
    const result = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
    if(result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
  };
  const until = async(expression)=>{
    for(let attempt = 0; attempt < 180; attempt += 1){
      if(await evaluate(expression)) return;
      await new Promise((resolve)=> setTimeout(resolve, 100));
    }
    const detail=await evaluate(`({title:document.title,ready:document.readyState,deviceStatus:document.getElementById('signature-device-status')?.textContent,hidden:document.getElementById('signature-device-editor')?.hidden})`);throw Error('Browser timeout: '+expression+' '+JSON.stringify(detail)+' exceptions='+JSON.stringify(runtimeExceptions));
  };
  const screenshot = async(name)=>{
    await new Promise(resolve=>setTimeout(resolve,250));
    const result = await send('Page.captureScreenshot',{format:'png'});
    await writeFile(`${output}/${name}`,Buffer.from(result.data,'base64'));
  };



  await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{const original=fetch;window.handoffCalls=[];window.syntheticSignature='';window.handoffStatus='PENDING';window.deviceSaved=false;window.fetch=async(url,options={})=>{const u=String(url),method=options.method||'GET';const reply=data=>new Response(JSON.stringify({ok:true,data}),{status:200});if(u.includes('physician-signature.php'))return reply({owner_scope:'synthetic',csrf_token:'synthetic',signature:syntheticSignature?{image_data:syntheticSignature}:null});if(u.includes('signature-handoff-device.php')){const body=JSON.parse(options.body);if(body.image_data){deviceSaved=true;window.deviceImage=body.image_data;return reply({status:'COMPLETED'});}return reply({status:'PENDING'});}if(u.includes('signature-handoff.php')){handoffCalls.push({method,status:u.includes('?id=')});if(method==='POST')return reply({id:'00000000-0000-4000-8000-000000000001',ttl_seconds:300,url:location.origin+'/signature-handoff.php#'+'A'.repeat(43)});if(method==='DELETE')return reply({status:'EXPIRED'});if(u.includes('?id='))return reply({status:handoffStatus});return reply({csrf_token:'synthetic'});}return original(url,options);};})();`});
  await send('Page.navigate',{url:base+'/index.html?review=sig03b'});
  await until('document.readyState==="complete" && Boolean(window.mxmedPhysicianSignature)');
  await evaluate('showPanel("p-info");document.getElementById("t-info-datos-tab").click()');
  await new Promise(r=>setTimeout(r,1000));
  await evaluate('document.querySelectorAll(".modal.show [data-bs-dismiss=modal]").forEach(b=>b.click())');
  await new Promise(r=>setTimeout(r,400));
  const results=[];let actualFullscreen=false;
  for(const [width,height] of [[1440,900],[1366,768]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
    await evaluate('document.getElementById("dg-signature-handoff-open").click()');
    await until('document.getElementById("dg-signature-handoff-qr").querySelector("canvas")');
    assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth'),false);
    await screenshot('desktop-qr-'+width+'.png');
    if(process.env.MXMED_SIG03B_QR_DECODER){
      const decoder=spawn(process.env.MXMED_SIG03B_QR_DECODER,[output+'/desktop-qr-'+width+'.png'],{stdio:['pipe','pipe','pipe']});let message='';decoder.stdout.on('data',d=>message+=d);decoder.stdin.end(base+'/signature-handoff.php#'+'A'.repeat(43));const code=await new Promise(resolve=>decoder.on('exit',resolve));assert.equal(code,0,message);}

    await until('handoffCalls.some(c=>c.status)');
    await evaluate('bootstrap.Modal.getInstance(document.getElementById("dg-signature-handoff-modal")).hide()');
    await new Promise(r=>setTimeout(r,500));const count=await evaluate('handoffCalls.filter(c=>c.status).length');
    await new Promise(r=>setTimeout(r,2900));assert.equal(await evaluate('handoffCalls.filter(c=>c.status).length'),count);
    assert.ok(await evaluate('handoffCalls.some(c=>c.method==="DELETE")'));results.push({width,height,overflow:false,stopsOnClose:true});
  }
  await evaluate('document.getElementById("dg-signature-handoff-open").click()');
  await until('document.getElementById("dg-signature-handoff-qr").querySelector("canvas")');
  await evaluate(`(()=>{const p=document.getElementById('dg-signature-pad');const c=p.getContext('2d');c.beginPath();c.moveTo(10,10);c.lineTo(120,40);c.stroke();syntheticSignature=p.toDataURL();handoffStatus='COMPLETED';})()`);
  await until('document.getElementById("dg-signature-handoff-status").textContent.includes("vista previa")');
  assert.ok((await evaluate('mxmedPhysicianSignature.read()')).startsWith('data:image/png'));
  assert.ok(await evaluate(`document.querySelectorAll('img[src=\"'+mxmedPhysicianSignature.read()+'\"]').length>=7`),'clinical registered previews still use canonical remote signature');
  const completedCount=await evaluate('handoffCalls.filter(c=>c.status).length');await new Promise(r=>setTimeout(r,2900));assert.equal(await evaluate('handoffCalls.filter(c=>c.status).length'),completedCount);
  await evaluate('bootstrap.Modal.getInstance(document.getElementById("dg-signature-handoff-modal")).hide()');await new Promise(r=>setTimeout(r,400));
  await evaluate('handoffStatus="EXPIRED";document.getElementById("dg-signature-handoff-open").click()');
  await until('document.getElementById("dg-signature-handoff-status").textContent.includes("venció")');const expiredCount=await evaluate('handoffCalls.filter(c=>c.status).length');await new Promise(r=>setTimeout(r,2900));assert.equal(await evaluate('handoffCalls.filter(c=>c.status).length'),expiredCount);
  for(const [width,height,pointerType] of [[390,844,'touch'],[820,1180,'pen']]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await evaluate('window.deviceSaved=false');
    await send('Page.navigate',{url:base+'/signature-handoff.php#'+'A'.repeat(43)});
    await until('document.readyState==="complete"&&!document.getElementById("signature-device-editor").hidden');
    assert.equal(await evaluate('location.hash'),'');assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth'),false);
    if(width===820){
      await evaluate('delete document.getElementById("signature-device-editor").requestFullscreen');
      const b=await evaluate(`(()=>{const r=document.getElementById('signature-device-fullscreen').getBoundingClientRect();return {x:r.left+20,y:r.top+20}})()`);
      await send('Input.dispatchMouseEvent',{type:'mousePressed',x:b.x,y:b.y,button:'left',clickCount:1});await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:b.x,y:b.y,button:'left',clickCount:1});
      await new Promise(r=>setTimeout(r,500));actualFullscreen=await evaluate(`document.fullscreenElement===document.getElementById('signature-device-editor')`);if(actualFullscreen){await screenshot('fullscreen-tablet.png');await evaluate('document.exitFullscreen()');await new Promise(r=>setTimeout(r,200));}
    }
    const r=await evaluate('(()=>{const r=document.getElementById("signature-device-pad").getBoundingClientRect();return {x:r.left,y:r.top}})()');
    if(pointerType==='touch'){
      await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:r.x+30,y:r.y+40,id:1}]});await send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:r.x+100,y:r.y+90,id:1}]});await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
    }else{
      await send('Input.dispatchMouseEvent',{type:'mousePressed',x:r.x+30,y:r.y+40,button:'left',clickCount:1,pointerType:'pen'});await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:r.x+100,y:r.y+90,button:'left',buttons:1,pointerType:'pen'});await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:r.x+100,y:r.y+90,button:'left',clickCount:1,pointerType:'pen'});
    }
    assert.ok(await evaluate(`document.getElementById('signature-device-pad').getContext('2d').getImageData(0,0,1000,300).data.some((v,i)=>i%4===3&&v>0)`),'actual pointer creates ink');
    await evaluate('document.getElementById("signature-device-clear").click();document.getElementById("signature-device-save").click()');
    assert.equal(await evaluate('deviceSaved'),false);assert.ok(await evaluate('document.getElementById("signature-device-status").textContent.includes("Dibuja")'));
    // Exercise the denied/unavailable fullscreen fallback without relying on browser support.
    await evaluate('document.getElementById("signature-device-editor").requestFullscreen=()=>Promise.reject(Error());document.getElementById("signature-device-fullscreen").click()');
    await until('document.getElementById("signature-device-editor").classList.contains("fullscreen-fallback")');
    assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth'),false);
    await screenshot('device-'+width+'.png');
    await evaluate(`(()=>{const p=document.getElementById('signature-device-pad'),r=p.getBoundingClientRect();p.dispatchEvent(new PointerEvent('pointerdown',{pointerId:1,clientX:r.left+30,clientY:r.top+40}));p.dispatchEvent(new PointerEvent('pointermove',{pointerId:1,clientX:r.left+100,clientY:r.top+80}));p.dispatchEvent(new PointerEvent('pointerup',{pointerId:1}));document.getElementById('signature-device-save').click();})()`);
    await until('deviceSaved&&document.getElementById("signature-device-status").textContent.includes("guardada")');
    results.push({width,height,pointerType,overflow:false,clear:true,save:true,fullscreenFallback:true});
  }
  await send('Page.navigate',{url:base+'/signature-handoff.php#'+'A'.repeat(43)});await until('!document.getElementById("signature-device-editor").hidden');await evaluate('window.deviceSaved=false;document.getElementById("signature-device-cancel").click()');assert.equal(await evaluate('deviceSaved'),false);assert.equal(await evaluate('document.getElementById("signature-device-editor").hidden'),true);
  assert.equal(runtimeExceptions.length,0,JSON.stringify(runtimeExceptions));
  await writeFile(output+'/results.json',JSON.stringify({results,actualFullscreen,qrDecoded:!!process.env.MXMED_SIG03B_QR_DECODER,mobileCancelNoSave:true,stopsOnComplete:true,stopsOnExpire:true,canonicalRefresh:true,consoleExceptions:runtimeExceptions},null,2));
  console.log('SIG03B_BROWSER=PASS '+output);
}finally{ws?.close();chrome.kill('SIGTERM');await new Promise(r=>setTimeout(r,500));await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:300});}
