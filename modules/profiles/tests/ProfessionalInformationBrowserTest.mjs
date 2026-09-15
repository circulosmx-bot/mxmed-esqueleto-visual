// Read-only Director review runtime: public photo/logo and eight gallery images.
// Review states and mutations use mocked fetch; an absent live candidate is simulated.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.IP01A_TEST_URL;
if(!base)throw Error('IP01A_TEST_URL must point to the synthetic test router; never use Director data.');
const output = process.env.QA_OUTPUT || '/tmp/ip01a-qa/screenshots';
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
    console.log(await evaluate(`JSON.stringify({active:[...document.querySelectorAll('#tabs-info .active')].map(x=>x.id),dirty:mxmedProfessionalInformation.dirty(),busy:mxmedProfessionalInformation.busy,modal:document.getElementById('mxpi-unsaved-navigation-modal').className,error:document.getElementById('mxpi-unsaved-navigation-error').textContent,feedback:document.getElementById('professional-information-feedback').textContent,service:document.getElementById('srv1').value,button:document.getElementById('mxpi-unsaved-discard').disabled})`));console.log(runtimeExceptions);throw Error(`Browser timeout: ${expression}`);
  };
  const screenshot = async(name)=>{
    await new Promise(resolve=>setTimeout(resolve,250));
    const result = await send('Page.captureScreenshot',{format:'png'});
    await writeFile(`${output}/${name}`,Buffer.from(result.data,'base64'));
  };



  await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{
    localStorage.setItem('chips:cert','["UNTRUSTED LEGACY"]');localStorage.setItem('dp:srv1','UNTRUSTED SERVICE');
    const originalFetch=window.fetch;window.ipWrites=[];window.ipStorageWrites=[];window.ipFail=false;
    const set=Storage.prototype.setItem;Storage.prototype.setItem=function(k,v){if(/^(chips:|dp:srv|dp:professional-summary)/.test(k))ipStorageWrites.push(k);return set.call(this,k,v)};
    window.fetch=(url,options={})=>{if(String(url).includes('/api/profiles/professional-information.php')&&options.method==='PUT'){ipWrites.push(JSON.parse(options.body));if(ipFail)return Promise.resolve(new Response(JSON.stringify({ok:false,message:'Fallo sintético'}),{status:503}));}return originalFetch(url,options);};
  })();`});
  async function open(){await evaluate('window.__ipOpening=true');await send('Page.navigate',{url:base+'/index.html?qa_tools=hide'});await until(`!window.__ipOpening&&document.readyState==='complete'&&window.mxmedProfessionalInformation?.loaded`);await evaluate(`showPanel('p-info');document.getElementById('t-info-formacion-tab').click()`);await new Promise(r=>setTimeout(r,500));}
  const type=async(id,value)=>evaluate(`(()=>{const i=document.getElementById(${JSON.stringify(id)});i.value=${JSON.stringify(value)};i.dispatchEvent(new Event('input',{bubbles:true}));})()`);
  const click=async selector=>{await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);await new Promise(r=>setTimeout(r,350));};
  const dirty=()=>evaluate('mxmedProfessionalInformation.dirty()');
  const writes=()=>evaluate('ipWrites.length');
  async function add(scope,value){await type(scope+'-input',value);await click('#'+scope+'-add');}
  async function save(){await click('#mxpi-save-btn');await until(`!mxmedProfessionalInformation.busy`);assert.equal(await dirty(),false);}
  async function guardedExit(){await click('#t-info-datos-tab');await until(`document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`);}
  console.log('OPEN');await open();console.log('LOADED');assert.equal(await dirty(),false);assert.equal(await evaluate(`document.getElementById('mxpi-floating-save').hidden`),true);
  assert.equal(await evaluate(`document.getElementById('cert-list').textContent.includes('UNTRUSTED')`),false);
  // IP01B: real focus/keyboard/pointer gestures; all mutations stay in the synthetic draft.
  await evaluate(`document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m)?.hide())`);
  await new Promise(r=>setTimeout(r,450));
  const focusInput=async(scope,value)=>{
    await evaluate(`document.getElementById('${scope}-input').focus()`);await type(scope+'-input',value);
  };
  const key=async(key,code)=>{await send('Input.dispatchKeyEvent',{type:'keyDown',key,code,windowsVirtualKeyCode:key==='Tab'?9:13});await send('Input.dispatchKeyEvent',{type:'keyUp',key,code,windowsVirtualKeyCode:key==='Tab'?9:13});};
  const pointer=async(selector,touch=false)=>{
    const point=await evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});e.scrollIntoView({block:'center',behavior:'instant'});const r=e.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}})()`);
    if(touch){await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[point]});await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});}
    else{await send('Input.dispatchMouseEvent',{type:'mousePressed',...point,button:'left',clickCount:1});await send('Input.dispatchMouseEvent',{type:'mouseReleased',...point,button:'left',clickCount:1});}
  };
  const chipCount=scope=>evaluate(`document.querySelectorAll('#${scope}-list .chip').length`);
  for(const scope of ['cert','cursos','dipl','miem','enf','trt']){
    const initial=await chipCount(scope);
    for(const [n,gesture] of ['enter','add','blur','tab','blur-add'].entries()){
      await focusInput(scope,'  IP01B '+scope+' '+gesture+'  ');
      if(gesture==='enter')await key('Enter','Enter');
      else if(gesture==='tab'){await key('Tab','Tab');assert.equal(await evaluate(`document.activeElement.id==='${scope}-input'`),false);}
      else if(gesture==='blur')await evaluate(`document.getElementById('professional-summary').focus()`);
      else if(gesture==='add')await click('#'+scope+'-add');
      else await pointer('#'+scope+'-add');
      assert.equal(await chipCount(scope),initial+n+1,scope+' '+gesture);
      assert.equal(await evaluate(`document.getElementById('${scope}-input').value`),'');
    }
    for(const value of ['   ',' IP01B '+scope+' enter ', 'x'.repeat(scope==='enf'||scope==='trt'?41:51)]){
      await focusInput(scope,value);await evaluate(`document.getElementById('professional-summary').focus()`);
      assert.equal(await chipCount(scope),initial+5,scope+' rejects empty/duplicate/invalid');
      if(value.startsWith('x'))assert.equal(await evaluate(`document.getElementById('${scope}-input').value`),value);
    }
    await type(scope+'-input','');
  }
  assert.equal(await dirty(),true);assert.equal(await writes(),0);assert.deepEqual(await evaluate('ipStorageWrites'),[]);
  await new Promise(r=>setTimeout(r,4200));assert.equal(await evaluate(`document.getElementById('mxpi-floating-save').hidden`),false);
  // Persist/reload every category using only the real synthetic grouped endpoint.
  await save();const blurSaved=await evaluate('ipWrites[0]');await open();
  for(const [scope,key]of [['cert','CERTIFICATION'],['cursos','COURSE'],['dipl','DIPLOMA'],['miem','MEMBERSHIP'],['enf','DISEASE'],['trt','TREATMENT']]){
    assert.deepEqual(await evaluate(`[...document.querySelectorAll('#${scope}-list .chip')].map(c=>c.firstChild.textContent)`),blurSaved.items[key]);
    await focusInput(scope,'Revertir IP01B');await evaluate(`document.getElementById('professional-summary').focus()`);
    await click('#'+scope+'-list .chip:last-of-type .chip-x');assert.equal(await dirty(),false);
  }
  // Pending text becomes dirty before actual navigation click and remains guarded.
  await focusInput('cert','Salir pendiente IP01B');await pointer('#t-info-datos-tab');
  await until(`document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`);
  await click('#mxpi-unsaved-discard');await until(`document.getElementById('t-info-datos').classList.contains('active')`);
  assert.equal(await writes(),0);await open();assert.equal(await evaluate(`document.getElementById('cert-list').textContent.includes('Salir pendiente IP01B')`),false);
  for(const [width,height]of [[1440,900],[1366,768],[820,1180],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await focusInput('cert','Touch IP01B');await pointer('#cert-add',true);assert.equal(await dirty(),true);
    await click('#cert-list .chip:last-of-type .chip-x');assert.equal(await dirty(),false);
    assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth`),false);await screenshot('ip01b-'+width+'.png');
  }
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
  console.log('IP01B_GESTURES_ALL_SIX_CATEGORIES=PASS');
  await type('srv1','Servicio QA');assert.equal(await dirty(),true);assert.equal(await writes(),0);
  assert.equal(await evaluate(`document.getElementById('mxpi-floating-save').hidden`),true);
  await new Promise(r=>setTimeout(r,4200));assert.equal(await evaluate(`document.getElementById('mxpi-floating-save').hidden`),false);
  await add('cert','Certificación QA');await add('miem','Membresía QA');await add('enf','Enfermedad QA');await add('trt','Tratamiento QA');
  await click('#cursos-list .chip-x');await type('professional-summary','Resumen profesional QA');
  // Ordered disease and treatment drafts, including keyboard/touch reorder controls.
  for(const scope of ['enf','trt']){await click('#'+scope+'-list .chip-sort-link');await click('#sort-list li:first-child button:last-child');await click('#sort-save');}
  assert.equal(await writes(),0);assert.deepEqual(await evaluate('ipStorageWrites'),[]);
  await save();console.log('SAVED');assert.equal(await writes(),1);const saved=await evaluate('ipWrites[0]');
  console.log('RELOAD');await open();console.log('RELOADED');assert.equal(await evaluate(`document.getElementById('professional-summary').value`),saved.public_professional_summary);
  assert.equal(await evaluate(`document.getElementById('srv1').value`),saved.items.SERVICE[0]);
  for(const [scope,key] of [['cert','CERTIFICATION'],['cursos','COURSE'],['dipl','DIPLOMA'],['miem','MEMBERSHIP'],['enf','DISEASE'],['trt','TREATMENT']]){
    assert.deepEqual(await evaluate(`[...document.querySelectorAll('#${scope}-list .chip')].map(c=>c.firstChild.textContent)`),saved.items[key]);
  }
  await type('srv1','Temporal');await type('srv1',saved.items.SERVICE[0]);assert.equal(await dirty(),false);
  await add('miem','Revertir');await click('#miem-list .chip:last-child .chip-x');assert.equal(await dirty(),false);
  await type('srv1','No guardar');await guardedExit();await click('#mxpi-unsaved-edit');assert.equal(await dirty(),true);assert.equal(await evaluate(`document.getElementById('srv1').value`),'No guardar');
  console.log('DISCARD');await guardedExit();await click('#mxpi-unsaved-discard');await until(`document.getElementById('t-info-datos').classList.contains('active')`);assert.equal(await writes(),0);
  await click('#t-info-formacion-tab');assert.equal(await evaluate(`document.getElementById('srv1').value`),saved.items.SERVICE[0]);
  await type('srv1','Guardar y salir');await guardedExit();await click('#mxpi-unsaved-save');await until(`document.getElementById('t-info-datos').classList.contains('active')`);assert.equal(await writes(),1);
  await click('#t-info-formacion-tab');await type('srv1','Error pendiente');await evaluate('ipFail=true');await guardedExit();await click('#mxpi-unsaved-save');assert.equal(await dirty(),true);assert.equal(await evaluate(`document.getElementById('t-info-profesional').classList.contains('active')`),true);assert.equal(await evaluate(`document.getElementById('srv1').value`),'Error pendiente');await click('#mxpi-unsaved-edit');await evaluate('ipFail=false');
  const unload=await evaluate(`(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented})()`);assert.equal(unload,true);
  await click('#t-info-formacion-tab');assert.equal(await evaluate(`document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`),false);
  await evaluate(`selectInfoTab('#t-servicios');selectInfoTab('#t-enfermedades')`);assert.equal(await dirty(),true);assert.equal(await evaluate(`document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`),false);
  await save();
  await evaluate(`document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m)?.hide())`);await new Promise(r=>setTimeout(r,450));
  for(const [width,height]of [[1440,900],[1366,768],[820,1180],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await evaluate(`document.getElementById('mx-professional-formacion-heading').scrollIntoView({block:'start',behavior:'instant'})`);
    assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth`),false);
    await evaluate(`document.activeElement?.blur()`);
    console.log('IP01C_METRICS',width,await evaluate(`JSON.stringify({section:document.getElementById('t-info-formacion').getBoundingClientRect().height,chip:document.querySelector('#cert-list .chip').getBoundingClientRect().height})`));
    // IP01D: scoped add affordance remains secondary, readable and contained.
    assert.deepEqual(await evaluate(`(()=>{
      const failures=[];
      document.querySelectorAll('#t-info-profesional .chip-add').forEach(button=>{
        const plus=button.querySelector('.chip-plus'),word=button.querySelector('.chip-word');
        if(getComputedStyle(plus).fontSize!=='22px'||getComputedStyle(word).fontSize!=='12px')failures.push('icon scale');
        if(button.tagName!=='BUTTON'||word.textContent!=='agregar')failures.push('semantics');
        const r=button.getBoundingClientRect();
        for(const child of [plus,word]){const c=child.getBoundingClientRect();if(c.left<r.left||c.right>r.right||c.top<r.top||c.bottom>r.bottom)failures.push('clipped content');}
      });return failures;
    })()`),[]);
    // IP01C: every removal affordance stays within its capsule, including wrapped mobile labels.
    assert.deepEqual(await evaluate(`(()=>{
      const failures=[];
      document.querySelectorAll('#t-info-profesional .chip').forEach(chip=>{
        const button=chip.querySelector('.chip-x'),r=chip.getBoundingClientRect(),b=button.getBoundingClientRect(),s=getComputedStyle(button);
        if(b.left<r.left||b.right>r.right||b.top<r.top||b.bottom>r.bottom)failures.push('outside chip');
        if(b.width<24||b.height<24)failures.push('small target');
        if(s.position==='absolute'||s.color==='rgb(255, 255, 255)')failures.push('floating badge');
        if(!button.getAttribute('aria-label')?.startsWith('Eliminar '))failures.push('accessible name');
      });return failures;
    })()`),[]);
    await evaluate(`document.querySelector('#cert-list .chip-x').focus()`);
    await key('Tab','Tab');
    assert.equal(await evaluate(`getComputedStyle(document.activeElement).outlineStyle==='solid'`),true);
    await screenshot('ip01d-'+width+'.png');
  }
  assert.deepEqual(await evaluate('ipStorageWrites'),[]);assert.equal(runtimeExceptions.length,0);
  console.log('IP01A_BROWSER_QA=PASS');
}finally{ws?.close();chrome.kill('SIGTERM');await new Promise(r=>setTimeout(r,500));await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:300});}
