// Real Director runtime; writes mocked unless explicitly gated for temporary theme QA.
import assert from 'node:assert/strict';
import {explicitSaveClockSource} from './ExplicitSaveBrowserClock.mjs';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd0312';
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
  const realSave=process.env.MXMED_CRD0312_REAL_SAVE==='1';
  if(realSave)assert.equal(base,'http://127.0.0.1:18143');
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
        if(window.__guardMode==='real')return originalFetch(input,options);
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
    await send('Page.navigate',{url:base+'/index.html?review=crd0312&qa_tools=hide'});
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
  await open();
  await click('#t-info-formacion-tab');await until(`document.getElementById('t-info-formacion').classList.contains('active')`);assert.equal(await modal(),false);
  await click('#t-info-datos-tab');await until(`document.getElementById('t-info-datos').classList.contains('active')`);
  const bio=original.data.identity_public.bio_short;
  await type(bio+' QA');assert.equal(await dirty(),true);assert.equal(await reminder(),false);
  await screenshot('crd0312-dirty-before-delay.png');
  await advance(3999);assert.equal(await reminder(),false);
  await type(bio+' QA2');await advance(3999);assert.equal(await reminder(),false);
  const beforeReveal=await evaluate(`({focus:document.activeElement.id,scroll:scrollY})`);
  await advance(1);assert.equal(await reminder(),true);
  assert.deepEqual(await evaluate(`({focus:document.activeElement.id,scroll:scrollY})`),beforeReveal,'passive reveal never changes focus or scroll');
  await screenshot('crd0312-dirty-save-tray-after-delay.png');
  await type(bio+' QA3');assert.equal(await reminder(),true);
  await type(bio);assert.equal(await dirty(),false);assert.equal(await reminder(),false);
  await type(bio+' QA');await advance(1000);await type(bio);await advance(5000);assert.equal(await reminder(),false);
  await type(bio+' QA');await advance(1000);
  await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:1,y:1});
  await evaluate(`window.scrollBy(0,20)`);await advance(3000);assert.equal(await reminder(),true);await type(bio);
  // A relevant input event resets inactivity even when the value did not change.
  await type(bio+' QA');await advance(1500);
  await evaluate(`document.getElementById('mxpi-verified-given-names').dispatchEvent(new Event('input',{bubbles:true}))`);
  await advance(3999);assert.equal(await reminder(),false);await advance(1);assert.equal(await reminder(),true);await type(bio);
  await type(bio+' QA');assert.equal(await reminder(),false);
  await attempt('#t-info-formacion-tab');await screenshot('crd0312-unsaved-navigation-modal.png');
  await advance(5000);assert.equal(await reminder(),false,'timer paused behind navigation modal');
  assert.deepEqual(await evaluate(`['mxpi-unsaved-save','mxpi-unsaved-discard','mxpi-unsaved-edit'].map(id=>document.getElementById(id).textContent)`),['Guardar y continuar','Salir sin guardar','Seguir editando']);
  const initialCount=await patchCount();await keepEditing();assert.equal(await patchCount(),initialCount);assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),bio+' QA');
  assert.equal(await evaluate(`document.activeElement.id`),'t-info-formacion-tab');
  await attempt('#t-info-formacion-tab');
  await evaluate(`document.querySelector('[data-panel="p-seguridad"]').click()`);assert.equal(await evaluate(`document.querySelectorAll('#mxpi-unsaved-navigation-modal').length`),1);
  await click('#mxpi-unsaved-discard');await until(`document.getElementById('t-info-formacion').classList.contains('active') && !document.querySelector('.modal.show')`);
  assert.equal(await patchCount(),initialCount);assert.equal(await dirty(),false);
  await click('#t-info-datos-tab');await until(`!document.getElementById('mxpi-save-btn').disabled`);assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),bio);assert.equal(await reminder(),false);
  // Audit every currently rendered outgoing subtab and Sidebar destination.
  await type(bio+' QA');
  for(const selector of ['#t-info-servicios-tab','#t-info-enfermedades-tab','#t-info-fotos-tab',
    '[data-panel="p-consultorio"]','[data-panel="p-opiniones"]','[data-panel="p-seguridad"]','[data-panel="p-suscripcion"]',
    '.menu-main[data-group="agenda"]','.menu-main[data-panel="p-expediente"]','.menu-main[data-panel="p-pac-recetas"]',
    '.menu-main[data-panel="p-facturacion"]','.menu-main[data-panel="p-paquetes"]','.menu-main[data-panel="p-Notificaciones"]',
    '.mx-gh-brand[data-panel="p-resumen"]','[data-header-logout]']){
    await attempt(selector);await keepEditing();
  }
  // Space activates a focused button with a trusted keyboard click.
  await evaluate(`document.getElementById('t-info-formacion-tab').focus()`);
  await send('Input.dispatchKeyEvent',{type:'rawKeyDown',key:' ',code:'Space',windowsVirtualKeyCode:32});
  await send('Input.dispatchKeyEvent',{type:'keyUp',key:' ',code:'Space',windowsVirtualKeyCode:32});
  await until(`document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,400));
  await send('Input.dispatchKeyEvent',{type:'rawKeyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
  await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
  await until(`!document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,550));assert.equal(await dirty(),true);
  for(const selector of ['#mxpi-verified-data-trigger','#mx-visibility-help-trigger']){

    await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);await until(`!!document.querySelector('.modal.show')`);await new Promise(r=>setTimeout(r,400));assert.equal(await modal(),false);
    await evaluate(`document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m)?.hide())`);await new Promise(r=>setTimeout(r,550));
  }
  await evaluate(`document.querySelector('#mxHeaderAccount').click()`);assert.equal(await modal(),false);
  assert.equal(await evaluate(`document.getElementById('mx-public-profile-link').target`),'_blank');
  await evaluate(`document.getElementById('mx-public-profile-link').click()`);assert.equal(await modal(),false);assert.equal(await dirty(),true);
  // Browser close/reload uses native protection, never an automatic save.
  unloadAction=false;const priorOrigin=await evaluate('performance.timeOrigin');await send('Page.reload',{ignoreCache:true});
  for(let i=0;i<50&&!unloadDialogs.length;i++)await new Promise(r=>setTimeout(r,100));
  assert.ok(unloadDialogs.length>0,'trusted edit enables native beforeunload');await new Promise(r=>setTimeout(r,300));assert.equal(await evaluate('performance.timeOrigin'),priorOrigin);assert.equal(await dirty(),true);unloadAction=true;
  await type(bio);
  assert.equal(await evaluate(`(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented})()`),false);
  // A failed save must retain intent and edits; retry succeeds exactly once.
  await type(bio+' QA');await attempt('#t-info-formacion-tab');await evaluate(`window.__guardMode='error';window.__guardDelay=200`);const beforeError=await patchCount();
  await click('#mxpi-unsaved-save');await until(`!document.getElementById('mxpi-unsaved-save').disabled && !document.getElementById('mxpi-unsaved-navigation-error').hidden`);
  assert.equal(await patchCount(),beforeError+1);assert.equal(await modal(),true);assert.equal(await dirty(),true);assert.equal(await evaluate(`window.__explicitSaveOptions.isActive()`),true);assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),bio+' QA');
  await evaluate(`window.__guardMode='mock';window.__guardDelay=500;document.getElementById('mxpi-unsaved-save').click();document.getElementById('mxpi-unsaved-save').click();document.getElementById('mxpi-unsaved-discard').click()`);
  assert.equal(await evaluate(`document.getElementById('mxpi-unsaved-save').textContent`),'Guardando…');assert.equal(await evaluate(`document.getElementById('t-info-datos').classList.contains('active')`),true);
  await until(`document.getElementById('t-info-formacion').classList.contains('active') && !document.querySelector('.modal.show')`);assert.equal(await patchCount(),beforeError+2);assert.equal(await dirty(),false);
  await open();
  await evaluate(`document.querySelector('[data-theme-key="soft_coral"]').click()`);await attempt('#t-info-formacion-tab');
  await evaluate(`window.__guardMode=${JSON.stringify(realSave?'real':'mock')};window.__guardDelay=300`);await click('#mxpi-unsaved-save');
  await until(`document.getElementById('t-info-formacion').classList.contains('active') && !document.querySelector('.modal.show')`);assert.equal(await patchCount(),1);assert.equal(await dirty(),false);await screenshot('crd0312-save-and-continue.png');
  if(realSave){
    const saved=await(await fetch(base+'/api/profiles/private/doctor/1')).json();assert.equal(saved.data.profile_theme.stored_key,'soft_coral');
    assert.deepEqual(saved.data.identity_public,original.data.identity_public);
    await click('#t-info-datos-tab');
    await evaluate(`document.querySelector(${JSON.stringify(original.data.profile_theme.stored_key?'[data-theme-key="'+original.data.profile_theme.stored_key+'"]':'#mx-profile-theme-reset')}).click()`);await advance(4000);await click('#mxpi-save-btn');
    await until(`document.getElementById('mxpi-feedback').textContent==='Cambios guardados' && document.getElementById('mxpi-floating-save').hidden`);
    const restored=await(await fetch(base+'/api/profiles/private/doctor/1')).json();assert.equal(restored.data.profile_theme.stored_key,original.data.profile_theme.stored_key);assert.deepEqual(restored.data.identity_public,original.data.identity_public);
  }
  const metrics=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await open(width,height);await type(bio+' QA');assert.equal(await reminder(),false);await advance(4000);
    const m=await evaluate(`(()=>{const f=document.getElementById('mxpi-floating-save'),b=document.getElementById('mxpi-save-btn'),r=b.getBoundingClientRect(),c=getComputedStyle(f);return {width:innerWidth,right:innerWidth-r.right,bottom:innerHeight-r.bottom,left:r.left,overflow:document.documentElement.scrollWidth>innerWidth,visible:!f.hidden,background:getComputedStyle(b).backgroundColor,direction:c.flexDirection,card:c.backgroundColor,trayRight:c.right,trayBottom:c.bottom,opacity:c.opacity,modalBackground:getComputedStyle(document.querySelector('#mxpi-unsaved-navigation-modal .modal-content')).backgroundColor,reachable:document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)===b}})()`);
    assert.equal(m.right,width<500?29:45);assert.equal(m.bottom,width<500?29:45);assert.equal(m.overflow,false);assert.equal(m.visible,true);assert.equal(m.direction,'column');assert.equal(m.background,'rgb(0, 192, 64)');assert.equal(m.card,'rgba(255, 255, 255, 0.75)');assert.equal(m.modalBackground,'rgba(255, 255, 255, 0.75)');assert.equal(m.opacity,'1');assert.equal(m.trayRight,width<500?'16px':'32px');assert.equal(m.trayBottom,width<500?'16px':'32px');assert.equal(m.reachable,true);metrics.push(m);
    await screenshot(width<500?'crd0312-mobile-save-tray.png':`crd0312-save-tray-margins-${width}.png`);
    await attempt('#t-info-formacion-tab');
    if(width<500){await screenshot('crd0312-mobile-unsaved-modal.png');assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth`),false)}
    await keepEditing();await type(bio);assert.equal(await dirty(),false);assert.equal(await reminder(),false);
  }
  assert.deepEqual(errors,[]);await writeFile(output+'/navigation-metrics.json',JSON.stringify({metrics,realSave,unloadDialogs,consoleErrors:errors},null,2));
  console.log('EXPLICIT_SAVE_NAVIGATION_BROWSER=PASS: immediate dirty, deterministic idle/reset/revert/stability, passive focus/scroll, all outgoing tabs/sidebar/header/logout, non-navigation exemptions, exact destination, three modal actions, discard without PATCH, failure/retry/duplicate lock, native unload/clean, 3 viewports; real save='+realSave);
}finally{
  ws?.close();chrome.kill('SIGKILL');await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:100});
}
