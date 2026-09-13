// Real local DOM and read-only Leticia data; every persistence request is mocked.
import assert from 'node:assert/strict';
import {explicitSaveClockSource} from './ExplicitSaveBrowserClock.mjs';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd0318';
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
    await new Promise(resolve=>setTimeout(resolve,250));
    const result = await send('Page.captureScreenshot',{format:'png'});
    await writeFile(`${output}/${name}`,Buffer.from(result.data,'base64'));
  };
  const original=await(await fetch(base+'/api/profiles/private/doctor/1')).json();
  assert.equal(original.ok,true);assert.equal(original.data.verified_identity.given_names,'Leticia');
  const errors=[],unloadDialogs=[];let unloadAction=true;
  ws.addEventListener('message',({data})=>{
    const m=JSON.parse(data);
    if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);
    if(m.method==='Page.javascriptDialogOpening' && m.params.type==='beforeunload'){
      unloadDialogs.push(m.params.type);send('Page.handleJavaScriptDialog',{accept:unloadAction}).catch(()=>{});
    }
  });
  await send('Page.addScriptToEvaluateOnNewDocument',{source:explicitSaveClockSource+`
    window.__guardPatches=[];window.__guardMode='mock';window.__guardDelay=0;
    const originalFetch=window.fetch.bind(window);
    window.fetch=async(input,options={})=>{
      const url=new URL(typeof input==='string'?input:input.url,location.href);
      const method=String(options.method || 'GET').toUpperCase();
      if(method==='PATCH' && url.pathname==='/api/profiles/private/doctor/1'){
        const payload=JSON.parse(options.body);window.__guardPatches.push(payload);
        await new Promise(r=>setTimeout(r,window.__guardDelay));
        if(window.__guardMode==='error')return new Response(JSON.stringify({ok:false,error:'unavailable'}),{status:503});
        const result=${JSON.stringify(original)};
        Object.assign(result.data.identity_public,payload);
        result.data.profile_theme.stored_key=payload.profile_theme_key;
        if(payload.display_name){result.data.public_name_policy.current_display_name=payload.display_name;result.data.public_name_policy.current_display_name_policy_status='VALID'}
        return new Response(JSON.stringify(result),{status:200,headers:{'Content-Type':'application/json'}});
      }
      if(!['GET','HEAD'].includes(method))throw Error('Unexpected QA mutation');
      return originalFetch(input,options);
    };
  `});
  const click=async(selector)=>{
    const p=await evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});e.scrollIntoView({block:'center',behavior:'instant'});const r=e.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}})()`);
    await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...p});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...p});
  };
  const type=async(value)=>{await click('#mxpi-bio-short');await evaluate(`document.getElementById('mxpi-bio-short').select()`);await send('Input.insertText',{text:value})};
  const advance=ms=>evaluate(`window.__explicitSaveClock.advance(${ms})`);
  const reminder=()=>evaluate(`!document.getElementById('mxpi-floating-save').hidden`);
  const dirty=()=>evaluate(`window.__explicitSaveOptions.isDirty()`);
  const modal=()=>evaluate(`document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`);
  const patchCount=()=>evaluate(`window.__guardPatches.length`);
  const open=async(width=1440,height=900)=>{
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate',{url:base+'/index.html?review=crd0318&qa_tools=hide'});
    await until(`document.readyState==='complete' && document.getElementById('mxpi-save-btn')?.disabled===false && document.getElementById('mx-profile-theme-swatches')?.children.length===20`);
    await evaluate(`showPanel('p-info');document.getElementById('t-info-datos-tab').click()`);
    await new Promise(r=>setTimeout(r,1200));
    await evaluate(`document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m)?.hide())`);
    await new Promise(r=>setTimeout(r,450));
    assert.equal(await dirty(),false);assert.equal(await reminder(),false);
  };
  const attempt=async(selector)=>{
    // The same actual control is used by mouse/keyboard; avoid scrolling the
    // editor to off-screen nav controls during modal focus restoration checks.
    await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    assert.equal(await evaluate(`window.__explicitSaveOptions.isActive()`),true);
    await until(`document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`);
    assert.equal(await modal(),true);
    await new Promise(r=>setTimeout(r,400));
  };
  const keepEditing=async()=>{
    await click('#mxpi-unsaved-edit');await until(`!document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,400));
    assert.equal(await dirty(),true);assert.equal(await reminder(),true);
  };

  const prefixModal=()=>evaluate(`document.getElementById('mxpi-prefix-confirmation-modal').classList.contains('show')`);
  const choose=async value=>evaluate(`(()=>{const e=document.getElementById('mxpi-prefix');e.value=${JSON.stringify(value)};e.dispatchEvent(new Event('change',{bubbles:true}))})()`);
  const showConfirmation=async()=>{
    await until(`document.getElementById('mxpi-prefix-confirmation-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,400));
    assert.equal(await evaluate(`document.querySelectorAll('.modal.show').length`),1);
    assert.equal(await evaluate(`document.getElementById('mxpi-prefix-confirmation-title').textContent`),'Cambiar prefijo profesional');
    assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth`),false);
  };
  const cancelConfirmation=async()=>{
    await click('#mxpi-prefix-cancel');await until(`!document.getElementById('mxpi-prefix-confirmation-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,400));
    assert.equal(await dirty(),true);assert.equal(await evaluate(`document.getElementById('t-info-datos').classList.contains('active')`),true);
  };
  const confirm=async()=>{await click('#mxpi-prefix-confirm');await until(`!document.getElementById('mxpi-prefix-confirmation-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,450))};
  const initialPrefix=original.data.identity_public.prefix;
  const bio=original.data.identity_public.bio_short;
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await open(width,height);
    await choose('Mtro.');assert.equal(await dirty(),true);assert.equal(await prefixModal(),false);assert.equal(await patchCount(),0);
    assert.ok((await evaluate(`document.getElementById('mxpi-current-name-value').textContent`)).includes('Mtro. Leticia Muñoz Romo'));
    await advance(3999);assert.equal(await reminder(),false);await advance(1);assert.equal(await reminder(),true);
    assert.equal(await evaluate(`getComputedStyle(document.getElementById('mxpi-floating-save')).backgroundColor`),'rgba(129, 226, 223, 0.5)');
    await screenshot(`crd0318-save-tray-${width}.png`);
    await click('#mxpi-save-btn');await showConfirmation();assert.equal(await patchCount(),0);
    assert.ok((await evaluate(`document.getElementById('mxpi-prefix-confirmation-body').textContent`)).includes(`de «${initialPrefix}» a «Mtro.»`));
    await screenshot(width<500?'crd0318-mobile-prefix-confirmation.png':`crd0318-prefix-confirmation-${width}.png`);
    await cancelConfirmation();assert.equal(await patchCount(),0);assert.equal(await evaluate(`document.getElementById('mxpi-prefix').value`),'Mtro.');assert.equal(await evaluate(`document.activeElement.id`),'mxpi-save-btn');
    await choose(initialPrefix);assert.equal(await dirty(),false);assert.equal(await reminder(),false);
  }
  // Other explicit-save fields still save directly, with no prefix interruption.
  await open();await type(bio+' QA');await advance(4000);await click('#mxpi-save-btn');await until(`!window.__explicitSaveOptions.isDirty()`);
  assert.equal(await prefixModal(),false);assert.equal(await patchCount(),1);
  // Confirm once, preserving the entire grouped payload; duplicate clicks cannot write twice.
  await open();await choose('Mtro.');await type(bio+' QA');await advance(4000);await click('#mxpi-save-btn');await showConfirmation();
  await evaluate(`document.getElementById('mxpi-save-btn').click()`);assert.equal(await patchCount(),0);
  await evaluate(`document.getElementById('mxpi-prefix-confirm').click();document.getElementById('mxpi-prefix-confirm').click()`);
  await until(`!window.__explicitSaveOptions.isDirty()`);assert.equal(await patchCount(),1);
  const payload=await evaluate(`window.__guardPatches[0]`);assert.equal(payload.prefix,'Mtro.');assert.equal(payload.bio_short,bio+' QA');assert.equal(payload.profile_theme_key,original.data.profile_theme.stored_key);assert.equal(payload.display_name,'Leticia Muñoz Romo');
  // Navigation cancellation drops the original intent, keeping every draft.
  await open();await choose('Mtro.');await type(bio+' QA');await attempt('#t-info-formacion-tab');await click('#mxpi-unsaved-save');await showConfirmation();await cancelConfirmation();
  assert.equal(await patchCount(),0);assert.equal(await modal(),false);assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),bio+' QA');assert.equal(await evaluate(`document.activeElement.id`),'mxpi-prefix');
  await attempt('#t-info-formacion-tab');await click('#mxpi-unsaved-save');await showConfirmation();await confirm();await until(`document.getElementById('t-info-formacion').classList.contains('active')`);assert.equal(await patchCount(),1);assert.equal(await dirty(),false);
  // A failed confirmed PATCH retains drafts and blocks the original navigation.
  await open();await choose('Mtro.');await type(bio+' QA');await evaluate(`window.__guardMode='error'`);
  await attempt('#t-info-formacion-tab');await click('#mxpi-unsaved-save');await showConfirmation();await confirm();
  await until(`document.getElementById('mxpi-feedback').classList.contains('text-danger')`);assert.equal(await dirty(),true);assert.equal(await modal(),false);assert.equal(await patchCount(),1);assert.equal(await evaluate(`document.getElementById('mxpi-prefix').value`),'Mtro.');assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),bio+' QA');assert.equal(await evaluate(`document.getElementById('t-info-datos').classList.contains('active')`),true);
  await evaluate(`window.__guardMode='mock'`);await click('#mxpi-save-btn');await showConfirmation();await confirm();await until(`!window.__explicitSaveOptions.isDirty()`);assert.equal(await patchCount(),2);
  // Escape is cancellation; selecting a draft never autosaves.
  await open();await choose('Mtro.');await advance(4000);await click('#mxpi-save-btn');await showConfirmation();
  await send('Input.dispatchKeyEvent',{type:'rawKeyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await until(`!document.getElementById('mxpi-prefix-confirmation-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,400));assert.equal(await patchCount(),0);assert.equal(await dirty(),true);
  await choose(initialPrefix);assert.equal(await dirty(),false);


  // The first decision wins even if another button is clicked during modal fade-out.
  await open();await choose('Mtro.');await advance(4000);await click('#mxpi-save-btn');await showConfirmation();
  await evaluate(`document.getElementById('mxpi-prefix-cancel').click();document.getElementById('mxpi-prefix-confirm').click()`);
  await until(`!document.getElementById('mxpi-prefix-confirmation-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,400));assert.equal(await patchCount(),0);assert.equal(await dirty(),true);await choose(initialPrefix);assert.equal(await dirty(),false);
  // Clearing a persisted prefix has an explicit transition, without an invented value.
  await open();await choose('');await advance(4000);await click('#mxpi-save-btn');await showConfirmation();
  assert.ok((await evaluate(`document.getElementById('mxpi-prefix-confirmation-body').textContent`)).includes(`de «${initialPrefix}» a ningún prefijo`));await cancelConfirmation();assert.equal(await patchCount(),0);
  // An empty baseline is a read-only browser fixture; no local database mutation.
  await send('Page.addScriptToEvaluateOnNewDocument',{source:`
    const previousFetch=window.fetch;
    window.fetch=async(input,options={})=>{
      const url=new URL(typeof input==='string'?input:input.url,location.href);
      if(String(options.method||'GET').toUpperCase()==='GET'&&url.pathname==='/api/profiles/private/doctor/1'){
        const result=${JSON.stringify(original)};result.data.identity_public.prefix=null;
        return new Response(JSON.stringify(result),{status:200,headers:{'Content-Type':'application/json'}});
      }
      return previousFetch(input,options);
    };
  `});
  await open();await choose('Mtro.');await advance(4000);await click('#mxpi-save-btn');await showConfirmation();
  const emptyBody=await evaluate(`document.getElementById('mxpi-prefix-confirmation-body').textContent`);assert.ok(emptyBody.includes('a «Mtro.»; actualmente no tienes un prefijo'));assert.ok(!emptyBody.includes('«null»'));await cancelConfirmation();assert.equal(await patchCount(),0);await choose('');assert.equal(await dirty(),false);
  assert.deepEqual(errors,[]);
  await writeFile(output+'/prefix-confirmation-qa.json',JSON.stringify({payload,cancelPatchCount:0,confirmedPatchCount:1,viewports:[1440,1366,390],consoleErrors:errors},null,2));
  console.log('PREFIX_CONFIRMATION_BROWSER=PASS: changed-only, local preview, 4000ms/alpha50, cancel/escape/no PATCH, single grouped save, duplicate lock, navigation handoff/no stacking, failed save/retry/draft retention, 3 viewports');
}finally{
  ws?.close();chrome.kill('SIGKILL');await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:100});
}
