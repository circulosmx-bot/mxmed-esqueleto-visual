// Requires an isolated local review runtime: doctor 1 has trusted synthetic
// professional + three verified specialties; doctor 2 has legacy fields only.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd03';
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
  const send = (method, params = {}, sid = session)=> new Promise((resolve, reject)=>{
    const id = ++sequence;
    pending.set(id, {resolve, reject, method});
    ws.send(JSON.stringify({id, method, params, ...(sid ? {sessionId:sid} : {})}));
  });
  ws.addEventListener('message', (event)=>{
    const message = JSON.parse(event.data);
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
    await new Promise((resolve)=> setTimeout(resolve, 220));
    await evaluate(`document.getElementById('mxmed_dev_role_switcher')?.style.setProperty('display','none','important')`);
    const result = await send('Page.captureScreenshot', {format:'png'});
    await writeFile(`${output}/${name}`, Buffer.from(result.data, 'base64'));
  };

  await send('Page.addScriptToEvaluateOnNewDocument',{source:`for(const id of ['browser','1','2'])localStorage.setItem('mxmed.ui.visibility_help_seen.v1:'+id,'1')`});
  const open = async(legacy=false)=>{
    await send('Page.navigate',{url:`${base}/index.html?fixture=${legacy?'legacy':'canonical'}&review=crd03`});
    await until(`document.readyState==='complete' && typeof showPanel==='function'`);
    await evaluate(`showPanel('p-info');document.getElementById('t-info-datos-tab').click()`);
    await until(`document.querySelector('#mx-credential-list')?.dataset.mode==='${legacy?'legacy':'canonical'}'`);
    await evaluate(`document.querySelector('#mx-visibility-help-modal.show .btn-close')?.click()`);
    await until(`!document.querySelector('#mx-visibility-help-modal.show')`);
  };
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
  await open();
  const dto=await evaluate(`fetch('/api/profiles/private/doctor/1').then(r=>r.json()).then(r=>r.data)`);
  assert(dto.verified_identity && dto.public_name_policy && dto.verified_credentials);
  assert.equal(dto.verified_credentials.specialties.length,3);
  const compatibility=()=>evaluate(`JSON.stringify({nodes:['ced-prof','uni-prof','esp-1','ced-esp','uni-esp','esp-2','ced-extra','uni-extra','esp-3'].map(id=>[id,document.getElementById(id)?.value]),stores:Object.fromEntries(Object.entries(localStorage).filter(([k])=>k.startsWith('dp:')||k.startsWith('chips:')))})`);
  const contactValue=await evaluate(`document.getElementById('mx-admin-phone').value==='4491230001'?'4491230002':'4491230001'`);
  await evaluate(`(()=>{const input=document.getElementById('mx-admin-phone');input.value='${contactValue}';input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('blur',{bubbles:true}));})()`);
  await until(`document.getElementById('mx-admin-phone').dataset.persistedValue==='${contactValue}'`);
  await open();
  await until(`document.getElementById('mx-admin-phone').value==='${contactValue}'`);
  assert.equal(await evaluate(`document.querySelector('#mxpi-photo-preview img')?.dataset.avatarKind`),'generic');
  assert.equal(await evaluate(`typeof window.mxmedMediaReview`),'object');
  assert.equal(await evaluate(`document.querySelectorAll('#tabs-info [role="tab"]').length`),5);
  assert.equal(await evaluate(`document.getElementById('ced-prof').value`),'0099');
  assert.equal(await evaluate(`document.getElementById('ced-esp').value`),'0088');
  const storedBefore=await compatibility();
  assert.equal(await evaluate(`document.querySelectorAll('#mxpi-specialty-secondary').length`),0);
  await evaluate(`document.getElementById('mx-public-identity-card').scrollIntoView({block:'start',behavior:'instant'})`);
  await screenshot('crd03-datos-generales-no-classifications.png');
  const measurements=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<600});
    await evaluate(`document.getElementById('t-info-formacion-tab').click();document.getElementById('mx-formacion-credentials-card').scrollIntoView({block:'start',behavior:'instant'})`);
    const state=await evaluate(`(()=>{
      const host=document.getElementById('mx-credential-list');
      const ids=['ced-prof','uni-prof','esp-1','ced-esp','uni-esp','esp-2','ced-extra','uni-extra','esp-3'];
      return {professional:host.querySelectorAll('[data-kind="professional"]').length,specialties:host.querySelectorAll('[data-kind="specialty"]').length,
        primary:host.querySelectorAll('.mx-credential-primary').length,primaryName:host.querySelector('.mx-credential-primary')?.parentElement.querySelector('h3').textContent,
        inputs:host.querySelectorAll('input,select,button,textarea').length,
        legacyVisible:ids.filter(id=>document.getElementById(id).getBoundingClientRect().height>0).length,
        unique:ids.every(id=>document.querySelectorAll('#'+id).length===1),
        overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,
        height:document.getElementById('mx-formacion-credentials-card').getBoundingClientRect().height,
        secondary:document.getElementById('t-info-formacion').innerText.includes('Especialidad secundaria'),
        chips:['cert-input','cursos-input','dipl-input','miem-input'].every(id=>document.getElementById(id).getBoundingClientRect().height>0)};
    })()`);
    assert.deepEqual(state,{...state,professional:1,specialties:3,primary:1,primaryName:'Nefrología',inputs:0,legacyVisible:0,unique:true,overflow:false,secondary:false,chips:true});
    measurements.push({width,...state});
    await screenshot(width===390?'crd03-formacion-mobile.png':`crd03-formacion-credentials-${width}.png`);
  }
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
  await screenshot('crd03-formacion-three-specialties.png');
  await screenshot('crd03-formacion-primary-specialty.png');
  // Exercise cardinality and defensive status handling without writing data.
  await evaluate(`window.__crd03Dto=${JSON.stringify(dto)}`);
  for(const n of [0,1,3,10]){
    const count=await evaluate(`(()=>{const d=structuredClone(window.__crd03Dto);d.verified_credentials.specialties=Array.from({length:${n}},(_,i)=>({...d.verified_credentials.specialties[0],credential_id:String(100+i),is_primary:false}));d.primary_specialty_credential_id=null;mxmedRenderCredentials(d);return document.querySelectorAll('#mx-credential-list [data-kind="specialty"]').length})()`);
    assert.equal(count,n);
  }
  for(const [verification,lifecycle] of [['PENDING_REVIEW','ACTIVE'],['REJECTED','ACTIVE'],['VERIFIED','INACTIVE'],['VERIFIED','REVOKED']]){
    assert.equal(await evaluate(`(()=>{const d=structuredClone(window.__crd03Dto);d.verified_credentials.professional=null;d.verified_credentials.specialties=d.verified_credentials.specialties.map(x=>({...x,verification_status:'${verification}',lifecycle_status:'${lifecycle}'}));mxmedRenderCredentials(d);return document.querySelectorAll('#mx-credential-list .mx-credential-verified').length})()`),0);
  }
  await evaluate(`mxmedRenderCredentials(window.__crd03Dto)`);
  assert.equal(await compatibility(),storedBefore,'canonical renderer must never rewrite legacy nodes/stores');
  await open(true);
  await evaluate(`document.getElementById('t-info-formacion-tab').click();document.getElementById('mx-formacion-credentials-card').scrollIntoView({block:'start',behavior:'instant'})`);
  const legacy=await evaluate(`({text:document.getElementById('mx-credential-list').innerText,badges:document.querySelectorAll('#mx-credential-list .mx-credential-verified').length})`);
  assert(legacy.text.includes('0012345') && legacy.text.includes('Endocrinología') && legacy.text.includes('0076543'));
  assert.equal(legacy.badges,0);assert(!legacy.text.includes('Área histórica'));
  await screenshot('crd03-formacion-legacy-fallback.png');
  await evaluate(`document.getElementById('t-info-datos-tab').click();document.getElementById('dg-signature-qr-btn').click()`);
  assert.equal(await evaluate(`localStorage.getItem('mxmed.signatureSession')`),null,'SIG01 baseline unchanged');
  await writeFile(output+'/measurements.json',JSON.stringify(measurements,null,2));
  console.log('CRD03_BROWSER=PASS '+JSON.stringify(measurements));
}finally{
  ws?.close();chrome.kill();if(chrome.exitCode===null)await new Promise(r=>chrome.once('exit',r));await rm(profile,{recursive:true,force:true,maxRetries:3,retryDelay:100});
}
