// Real HTTP/controller integration. Saves allowed only on the explicitly gated disposable runtime.
// Requires an isolated local review runtime: doctor 1 has trusted synthetic
// professional + three verified specialties; doctor 2 has legacy fields only.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd0310';
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

  // Real HTTP and controller integration. No fetch interception or mocked authority responses.
  const disposable=process.env.MXMED_CRD0310_DISPOSABLE==='1';
  if(disposable){
    assert.equal(process.env.MXMED_CRD0310_DISPOSABLE_DB_PORT,'3331');
    assert.equal(new URL(base).hostname,'127.0.0.1');
    assert.notEqual(new URL(base).port,'18143','Never save against the Director runtime');
  }
  const original=await (await fetch(base+'/api/profiles/private/doctor/1')).json();
  assert.equal(original.ok,true);
  const identity=original.data.identity_public,policy=original.data.public_name_policy;
  assert.equal(original.data.verified_identity.given_names,disposable?'Luis Armando':'Leticia');
  const requests=[],exceptions=[];
  await send('Network.enable');
  ws.addEventListener('message',({data})=>{const m=JSON.parse(data);if(m.method==='Network.requestWillBeSent')requests.push({url:m.params.request.url,method:m.params.request.method,body:m.params.request.postData});if(m.method==='Runtime.exceptionThrown')exceptions.push(m.params.exceptionDetails.text)});
  // Read-only instrumentation observes initialization and snapshots without altering decisions.
  await send('Page.addScriptToEvaluateOnNewDocument',{source:`
    window.__realProfileAudit={instances:0,captures:[],events:[]};let factory;
    Object.defineProperty(window,'mxmedCreateDirtyTracker',{configurable:true,get(){return factory},set(value){factory=function(options){const t=value(options);window.__realProfileAudit.instances++;window.__realProfileTracker=t;const capture=t.captureBaseline.bind(t);t.captureBaseline=function(...args){window.__realProfileAudit.captures.push(t.currentState());return capture(...args)};return t}}});
    for(const type of ['input','change'])document.addEventListener(type,e=>{if(e.target.closest('#mx-public-identity-card'))window.__realProfileAudit.events.push({type,id:e.target.id,trusted:e.isTrusted})},true);
  `});
  const mutations=()=>requests.filter(r=>!['GET','HEAD'].includes(r.method));
  const click=async(selector)=>{
    const point=await evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});e.scrollIntoView({block:'center',behavior:'instant'});const r=e.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}})()`);
    await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...point});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...point});
  };
  const type=async(id,value)=>{
    await click('#'+id);
    await evaluate(`document.getElementById(${JSON.stringify(id)}).select()`);
    await send('Input.insertText',{text:value});
    assert.equal(await evaluate(`document.getElementById(${JSON.stringify(id)}).value`),value);
  };
  const choose=async(id,value)=>{
    // Standard select-option automation; values must exist in the real server-supplied options.
    // Text, checkbox, theme and button interactions use trusted CDP keyboard/mouse input.
    await evaluate(`(()=>{const e=document.getElementById(${JSON.stringify(id)});e.scrollIntoView({block:'center'});e.focus();const option=[...e.options].find(o=>o.value===${JSON.stringify(value)});if(!option || option.disabled)throw Error('Unknown canonical choice');option.selected=true;e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    assert.equal(await evaluate(`document.getElementById(${JSON.stringify(id)}).value`),value);
  };
  const assertDirty=async(expected)=>{
    assert.equal(await evaluate(`window.__realProfileTracker.isDirty()`),expected);
    assert.equal(await evaluate(`!document.getElementById('mxpi-floating-save').hidden`),expected);
    if(expected){
      const v=await evaluate(`(()=>{const f=document.getElementById('mxpi-floating-save'),b=document.getElementById('mxpi-save-btn'),r=b.getBoundingClientRect(),c=getComputedStyle(f);return {label:f.querySelector('.mxpi-dirty-label').textContent,button:b.textContent,display:c.display,visibility:c.visibility,width:f.getBoundingClientRect().width,reachable:document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)===b,overflow:document.documentElement.scrollWidth>innerWidth}})()`);
      assert.equal(v.label,'Cambios sin guardar');assert.equal(v.button,'Guardar cambios');assert.equal(v.visibility,'visible');assert.notEqual(v.display,'none');assert.equal(v.reachable,true);assert.equal(v.overflow,false);
    }
  };
  const open=async(width,height,hide)=>{
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate',{url:base+'/index.html?review=crd0310'+(hide?'&qa_tools=hide':'')});
    await until(`document.readyState==='complete' && document.getElementById('mxpi-save-btn')?.disabled===false && document.getElementById('mx-profile-theme-swatches')?.children.length===20`);
    await evaluate(`showPanel('p-info');document.getElementById('t-info-datos-tab').click()`);
    await new Promise(r=>setTimeout(r,1100));
    await evaluate(`document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m)?.hide())`);
    await new Promise(r=>setTimeout(r,400));
    if(disposable)await evaluate(`document.getElementById('mxmed_dev_role_switcher')?.remove()`);
    await assertDirty(false);
    assert.equal(await evaluate(`window.__realProfileAudit.instances`),1);
    assert.equal(await evaluate(`window.__realProfileAudit.captures.length`),1,'one complete initial hydration');
    assert.deepEqual(await evaluate(`window.__realProfileAudit.captures[0]`),await evaluate(`window.__realProfileTracker.currentState()`));
    assert.equal(await evaluate(`document.querySelector('#mxpi-verified-given-names option:checked').textContent`),disposable?'Luis':'Leticia');
    assert.deepEqual(await evaluate(`[...document.getElementById('mxpi-verified-given-names').options].map(o=>o.value)`),policy.allowed_given_name_presentations);
    assert.equal(await evaluate(`document.getElementById('mxpi-prefix').value`),identity.prefix);
  };
  const metrics=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    for(const hide of (disposable?[true]:[false,true])){
      await open(width,height,hide);
      const initialCaptures=await evaluate(`window.__realProfileAudit.captures.length`);
      if(width===1440&&hide){
        await screenshot('crd0310-clean-no-floating-save.png');
        await evaluate(`document.getElementById('mxpi-verified-given-names').scrollIntoView({block:'center'})`);
        await screenshot('crd0310-current-name-selected.png');
      }
      await type('mxpi-professional-designation',identity.professional_designation+'X');await assertDirty(true);
      if(width===1440&&hide)await screenshot('crd0310-dirty-floating-save.png');
      if(width===1366&&hide)await screenshot('crd0310-dirty-floating-save-1366.png');
      if(width===390&&hide)await screenshot('crd0310-mobile-dirty-save.png');
      if(!hide)await screenshot(`crd0310-qa-visible-${width}.png`);
      await type('mxpi-professional-designation',identity.professional_designation);await assertDirty(false);
      await type('mxpi-bio-short',identity.bio_short+'X');await assertDirty(true);
      if(width===1440&&hide)await screenshot('crd0310-dirty-bio.png');
      await type('mxpi-bio-short',identity.bio_short);await assertDirty(false);
      await choose('mxpi-prefix',identity.prefix==='Dr.'?'Dra.':'Dr.');await assertDirty(true);
      await choose('mxpi-prefix',identity.prefix);await assertDirty(false);
      await click('#mxpi-show-second-surname');await assertDirty(true);
      assert.ok(await evaluate(`document.getElementById('mxpi-current-name-value').textContent.endsWith(${JSON.stringify(original.data.verified_identity.first_surname)})`),'existing public summary previews the changed name');
      await click('#mxpi-show-second-surname');await assertDirty(false);
      if(policy.allowed_given_name_presentations.length>1){
        await choose('mxpi-verified-given-names',policy.allowed_given_name_presentations.find(v=>v!=='Luis'));await assertDirty(true);
        await choose('mxpi-verified-given-names','Luis');await assertDirty(false);
      }
      const stored=original.data.profile_theme.stored_key, alternate=stored==='soft_coral'?'medical_blue':'soft_coral';
      await click(`[data-theme-key="${alternate}"]`);await assertDirty(true);
      assert.equal(await evaluate(`document.querySelector('#mx-profile-theme-swatches [aria-checked=true]').dataset.themeKey`),alternate);
      if(width===1440&&hide)await screenshot('crd0310-theme-dirty.png');
      await click(`[data-theme-key="${stored || 'mxmed_teal'}"]`);await assertDirty(false);
      await click('#mx-profile-theme-reset');await assertDirty(stored!==null);
      if(stored)await click(`[data-theme-key="${stored}"]`);await assertDirty(false);
      if(width===1440&&hide)await screenshot('crd0310-reverted-clean.png');
      assert.equal(await evaluate(`window.__realProfileAudit.captures.length`),initialCaptures,'baseline never recaptured while editing');
      assert.ok(await evaluate(`['mxpi-professional-designation','mxpi-bio-short','mxpi-show-second-surname'].every(id=>window.__realProfileAudit.events.some(e=>e.id===id&&e.trusted))`),'trusted real text and checkbox input drives the actual controller');
      const geometry=await evaluate(`(()=>{const f=document.getElementById('mxpi-floating-save'),css=getComputedStyle(f);return {width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth,position:css.position,right:css.right,bottom:css.bottom,baseline:window.__realProfileAudit.captures[0]}})()`);
      assert.equal(geometry.overflow,false);assert.equal(geometry.position,'fixed');
      if(hide){assert.equal(geometry.right,width<500?'16px':'24px');assert.equal(geometry.bottom,width<500?'16px':'24px')}
      metrics.push({width,height,hide,...geometry});
      assert.equal(mutations().length,0,'No autosave before explicit Save');
    }
  }
  // Real reload reads persisted authority, including legacy-storage compatibility.
  await type('mxpi-bio-short',identity.bio_short+'X');
  await click('[data-theme-key="soft_coral"]');
  await open(1440,900,true);
  assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),identity.bio_short);
  assert.equal(await evaluate(`window.__realProfileTracker.currentState().profile_theme_key`),original.data.profile_theme.stored_key);
  assert.equal(mutations().length,0);
  if(disposable){
    const other=policy.allowed_given_name_presentations.find(v=>v!=='Luis');
    await choose('mxpi-verified-given-names',other);await assertDirty(true);
    await choose('mxpi-prefix','Dra.');
    await type('mxpi-professional-designation','Médica sintética QA');
    await type('mxpi-bio-short','Descripción sintética guardada explícitamente.');
    await click('[data-theme-key="soft_coral"]');
    assert.equal(mutations().length,0);
    await click('#mxpi-save-btn');
    await until(`document.getElementById('mxpi-feedback').textContent==='Cambios guardados' && document.getElementById('mxpi-floating-save').hidden`);
    const patches=mutations();assert.equal(patches.length,1);
    assert.equal(patches[0].method,'PATCH');const payload=JSON.parse(patches[0].body);
    const expected=[other,original.data.verified_identity.first_surname,original.data.verified_identity.second_surname].join(' ');
    assert.equal(payload.display_name,expected);assert.equal(payload.prefix,'Dra.');assert.equal(payload.bio_short,'Descripción sintética guardada explícitamente.');assert.equal(payload.professional_designation,'Médica sintética QA');assert.equal(payload.profile_theme_key,'soft_coral');
    assert.deepEqual(Object.keys(payload).sort(),['bio_short','display_name','prefix','professional_designation','profile_theme_key']);
    const priorNavigation=await evaluate('performance.timeOrigin');
    await send('Page.reload',{ignoreCache:true});
    await until(`performance.timeOrigin!==${priorNavigation} && document.readyState==='complete' && typeof showPanel==='function' && document.getElementById('mxpi-save-btn')?.disabled===false`);
    await evaluate(`showPanel('p-info');document.getElementById('t-info-datos-tab').click()`);
    assert.equal(await evaluate(`document.getElementById('mxpi-verified-given-names').value`),other);
    assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),payload.bio_short);
    await assertDirty(false);
    const persisted=await (await fetch(base+'/api/profiles/private/doctor/1')).json();
    for(const field of ['display_name','prefix','professional_designation','bio_short'])assert.equal(persisted.data.identity_public[field],payload[field]);
    assert.equal(persisted.data.profile_theme.stored_key,payload.profile_theme_key);
    assert.equal(persisted.data.public_name_policy.current_display_name_policy_status,'VALID');
    await screenshot('crd0310-real-save-reload-disposable.png');
  }
  assert.deepEqual(exceptions,[]);
  await writeFile(output+'/real-runtime-report.json',JSON.stringify({disposable,metrics,mutations:mutations(),initial:original.data,result:'PASS'},null,2));
  console.log(`REAL_RUNTIME_EXPLICIT_SAVE=PASS; trusted input; current canonical name; clean/change/revert; prefix/designation/Bio/surname/themes/reset; one baseline; reload; no autosave; 3 viewports; QA visible/hidden; real grouped save=${disposable}; no fetch mocks`);
}finally{ws?.close();chrome.kill();await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:100});}
