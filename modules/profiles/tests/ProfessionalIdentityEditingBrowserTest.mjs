// Read-only Director runtime. Grouped PATCH tests use intercepted responses.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd034';
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

  const before=(await(await fetch(base+'/api/profiles/private/doctor/1')).json()).data;
  const label='Describe brevemente tu actividad profesional para el encabezado de tu perfil';
  const placeholder='Ej. Resume en una frase tu actividad profesional y los principales servicios que brindas.';
  const results=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate',{url:base+'/index.html?review=crd034'});
    await until('document.readyState==="complete" && Boolean(window.mxmedMediaReview)');
    await evaluate('showPanel("p-info");document.getElementById("t-info-datos-tab").click()');
    await until(`document.getElementById('mxpi-bio-short').value===${JSON.stringify(before.identity_public.bio_short)}`);
    await new Promise(r=>setTimeout(r,900));await evaluate('document.querySelectorAll(".modal.show [data-bs-dismiss=modal]").forEach(b=>b.click())');await new Promise(r=>setTimeout(r,400));
    const state=await evaluate(`(()=>{const prefix=document.getElementById('mxpi-prefix').getBoundingClientRect(),designation=document.getElementById('mxpi-professional-designation').getBoundingClientRect(),bio=document.getElementById('mxpi-bio-short');return {width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth,genderControls:document.querySelectorAll('#mxpi-gender-label,#dp-genero,label[for=mxpi-gender-label]').length,gender:document.body.dataset.profileGender,prefixValue:document.getElementById('mxpi-prefix').value,designationValue:document.getElementById('mxpi-professional-designation').value,bio:bio.value,placeholder:bio.placeholder,label:document.querySelector('label[for=mxpi-bio-short]').firstChild.textContent,max:bio.maxLength,prefix:{left:prefix.left,top:prefix.top,width:prefix.width},designation:{left:designation.left,top:designation.top,width:designation.width},header:document.querySelector('.mx-gh-identity-name-text').textContent}})()`);
    assert.equal(state.genderControls,0);assert.equal(state.gender,before.identity_public.gender);assert.equal(state.prefixValue,before.identity_public.prefix);assert.equal(state.designationValue,before.identity_public.professional_designation);assert.equal(state.bio,before.identity_public.bio_short);assert.equal(state.label,label);assert.equal(state.placeholder,placeholder);assert.equal(state.max,150);assert.equal(state.overflow,false);assert.ok(state.header.includes('Leticia Muñoz Romo'));
    if(width>500){assert.ok(state.prefix.left<state.designation.left);assert.ok(state.prefix.width<state.designation.width/2);assert.ok(Math.abs(state.prefix.top-state.designation.top)<2);}else assert.ok(state.prefix.top<state.designation.top);
    await evaluate(`document.getElementById('mxpi-prefix').scrollIntoView({block:'center',behavior:'instant'})`);
    if(width===1440){await screenshot('crd034-professional-row-no-gender-1440.png');await screenshot('crd034-leticia-existing-bio.png');}if(width===390)await screenshot('crd034-mobile-professional-fields.png');results.push(state);
  }
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
  // Grouped saves are intercepted; the Director database is never mutated.
  await evaluate(`window.crd034Calls=[];window.crd034Private=${JSON.stringify(before)};window.crd034Fetch=window.fetch;window.fetch=(url,options={})=>{if(options.method==='PATCH'&&String(url).includes('/api/profiles/private/doctor/')){const body=JSON.parse(options.body);window.crd034Calls.push(body);Object.assign(window.crd034Private.identity_public,body);return Promise.resolve(Response.json({ok:true,data:window.crd034Private}));}return window.crd034Fetch(url,options)};`);
  const save=async()=>{await evaluate(`document.getElementById('mxpi-save-btn').click()`);await until(`!document.getElementById('mxpi-save-btn').disabled`);};
  await evaluate(`document.getElementById('mxpi-bio-short').value='';document.getElementById('mxpi-bio-short').dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('mxpi-prefix').value='Dr.';document.getElementById('mxpi-prefix').dispatchEvent(new Event('change',{bubbles:true}));`);
  await evaluate(`document.getElementById('mxpi-bio-short').scrollIntoView({block:'center',behavior:'instant'})`);await screenshot('crd034-bio-label-placeholder-empty.png');await save();
  const empty=await evaluate(`window.crd034Calls.at(-1)`);assert.equal(empty.bio_short,null);assert.equal(empty.prefix,'Dr.');assert.ok(!Object.hasOwn(empty,'gender')&&!Object.hasOwn(empty,'gender_label'));
  await evaluate(`document.getElementById('mxpi-professional-designation').value='Médica general';document.getElementById('mxpi-professional-designation').dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('mxpi-bio-short').value='á'.repeat(150);document.getElementById('mxpi-bio-short').dispatchEvent(new Event('input',{bubbles:true}));`);await save();
  const full=await evaluate(`window.crd034Calls.at(-1)`);assert.equal(full.professional_designation,'Médica general');assert.equal(Array.from(full.bio_short).length,150);assert.ok(!Object.hasOwn(full,'gender')&&!Object.hasOwn(full,'gender_label'));assert.equal(await evaluate(`window.crd034Private.identity_public.gender`),before.identity_public.gender);
  assert.equal(await evaluate(`window.crd034Private.identity_public.gender_label`),before.identity_public.gender_label);
  assert.equal(await evaluate(`window.crd034Calls.some(c=>c.bio_short===${JSON.stringify(placeholder)})`),false);
  const after=(await(await fetch(base+'/api/profiles/private/doctor/1')).json()).data;assert.deepEqual(after,before);
  await writeFile(output+'/report.json',JSON.stringify({results,groupedPatches:[empty,full],directorDataUnchanged:true},null,2));
  console.log('CRD034_BROWSER=PASS: no gender UI; internal hydration preserved; current prefix/designation/Bio; exact label/placeholder; three viewports; grouped PATCH omits both gender fields; empty Bio sends null, never placeholder; 150 Unicode characters; Leticia unchanged');
}finally{ws?.close();chrome.kill();await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true});}
