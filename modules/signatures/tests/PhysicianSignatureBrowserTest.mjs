// Read-only Director review runtime: public photo/logo and eight gallery images.
// Review states and mutations use mocked fetch; an absent live candidate is simulated.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-sig03a-browser';
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
    throw Error(`Browser timeout: ${expression}`);
  };
  const screenshot = async(name)=>{
    await new Promise(resolve=>setTimeout(resolve,250));
    const result = await send('Page.captureScreenshot',{format:'png'});
    await writeFile(`${output}/${name}`,Buffer.from(result.data,'base64'));
  };


  await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{localStorage.setItem('mxmed.signature','legacy-unsafe');localStorage.setItem('mxmed.doctor.signature','legacy-unsafe');const original=fetch;window.signatureWrites=[];window.syntheticSignature='';window.fetch=async(url,options={})=>{if(String(url).includes('physician-signature.php')){const method=options.method||'GET';if(method!=='GET'){signatureWrites.push(method);syntheticSignature=method==='DELETE'?'':JSON.parse(options.body).image_data;}return new Response(JSON.stringify({ok:true,data:{owner_scope:'synthetic-owner',csrf_token:'synthetic-csrf',signature:syntheticSignature?{image_data:syntheticSignature}:null}}),{status:200});}return original(url,options);};})();`});
  await send('Page.navigate',{url:base+'/index.html?review=sig03a'});
  await until('document.readyState==="complete" && Boolean(window.mxmedPhysicianSignature)');
  await evaluate('showPanel("p-info");document.getElementById("t-info-datos-tab").click()');
  await new Promise(r=>setTimeout(r,1200));
  await evaluate('document.querySelectorAll(".modal.show [data-bs-dismiss=modal]").forEach(b=>b.click())');
  assert.equal(await evaluate('mxmedPhysicianSignature.read()'), '');
  assert.equal(await evaluate('signatureWrites.length'),0);
  const results=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await evaluate('document.getElementById("dg-signature-card").scrollIntoView({block:"center",behavior:"instant"});document.getElementById("dg-signature-change").click()');
    await new Promise(r=>setTimeout(r,200));
    assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth'),false);
    await evaluate(`(()=>{const p=document.getElementById('dg-signature-pad'),r=p.getBoundingClientRect();p.dispatchEvent(new PointerEvent('pointerdown',{pointerId:1,clientX:r.left+30,clientY:r.top+40}));p.dispatchEvent(new PointerEvent('pointermove',{pointerId:1,clientX:r.left+90,clientY:r.top+75}));p.dispatchEvent(new PointerEvent('pointerup',{pointerId:1}));})()`);
    await screenshot('signature-'+width+'.png');
    await evaluate('document.getElementById("dg-signature-save").click()');
    await until('document.getElementById("dg-signature-feedback").textContent==="Firma actualizada."');
    assert.ok((await evaluate('mxmedPhysicianSignature.read()')).startsWith('data:image/png;base64,'));
    results.push({width,height,overflow:false,save:true});
  }
  assert.ok(await evaluate(`document.querySelectorAll('img[src=\"'+mxmedPhysicianSignature.read()+'\"]').length>=7`), 'registered document previews use canonical image');
  await evaluate('window.confirm=()=>true;document.getElementById("dg-signature-delete").click()');
  await until('mxmedPhysicianSignature.read()===""');
  assert.deepEqual(await evaluate('signatureWrites'),['POST','POST','POST','DELETE']);
  assert.equal(await evaluate('localStorage.getItem("mxmed.signature")'),'legacy-unsafe');
  assert.equal(runtimeExceptions.length,0,JSON.stringify(runtimeExceptions));
  await writeFile(output+'/results.json',JSON.stringify({results,legacyNotPromoted:true,delete:true,consoleExceptions:runtimeExceptions},null,2));
  console.log('SIG03A_BROWSER=PASS '+output);
}finally{ws?.close();chrome.kill('SIGTERM');await new Promise(r=>setTimeout(r,500));await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:300});}
