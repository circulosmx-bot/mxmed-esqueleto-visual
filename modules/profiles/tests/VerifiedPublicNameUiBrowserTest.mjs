import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:8095';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-dg04b';
const profile = await mkdtemp('/tmp/mxmed-dg04b-browser-');
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

  const fixtureScript = `(()=>{
    try{
      localStorage.setItem('mxmed.ui.visibility_help_seen.v1:browser','1');
      localStorage.setItem('mxmed.ui.visibility_help_seen.v1:1','1');
    }catch{}
    const nativeFetch = window.fetch.bind(window);
    const verifiedIdentity = {
      given_names:'Luis Armando', first_surname:'Reynoso', second_surname:'Femat',
      full_name:'Luis Armando Reynoso Femat', verified_at:'2026-09-11 12:00:00', source:'synthetic_test'
    };
    const allowed = ['Luis','Armando','Luis Armando'];
    window.__dg04bPatchBodies = [];
    window.__dg04bRejectName = false;
    let persistedName = new URL(location.href).searchParams.get('mode') === 'nonconforming'
      ? 'Nombre histórico no conforme'
      : 'Luis Reynoso Femat';
    const responseData = ()=>{
      const legacy = new URL(location.href).searchParams.get('mode') === 'legacy';
      const nonconforming = persistedName === 'Nombre histórico no conforme';
      return {
        ok:true, error:null, data:{
          doctor_id:'1',
          identity_public:{display_name:persistedName,professional_designation:'Endocrinólogo',prefix:'Dr.',gender:'masculino',gender_label:'Masculino',professional_license:'1234567',specialty_license:'7654321',specialty_primary:'Endocrinología',specialty_secondary:[],bio_short:'Atención endocrinológica profesional.',photo_url:null,avatar_url:null,logo_url:null,profile_status:'published',is_public_candidate:true},
          profile_theme:{stored_key:null,effective_key:'mxmed_teal',default_key:'mxmed_teal',catalog:[]},
          verified_identity:legacy ? null : verifiedIdentity,
          public_name_policy:legacy
            ? {verified_identity_available:false,current_display_name:persistedName,allowed_given_name_presentations:[],first_surname_required:false,second_surname_optional:true,current_display_name_policy_status:'NOT_APPLICABLE'}
            : {verified_identity_available:true,current_display_name:persistedName,allowed_given_name_presentations:allowed,first_surname_required:true,second_surname_optional:true,current_display_name_policy_status:nonconforming?'LEGACY_NONCONFORMING':'VALID'}
        }, meta:{auth_mode:'transitional_open'}
      };
    };
    window.fetch = async(input, init = {})=>{
      const url = new URL(typeof input === 'string' ? input : input.url, location.origin);
      const method = String(init.method || 'GET').toUpperCase();
      if(url.pathname === '/api/profiles/private/doctor/1'){
        if(method === 'PATCH'){
          const payload = JSON.parse(String(init.body || '{}'));
          window.__dg04bPatchBodies.push(payload);
          if(window.__dg04bRejectName && Object.hasOwn(payload, 'display_name')){
            return new Response(JSON.stringify({ok:false,error:'invalid_public_display_name',message:'rejected'}), {status:422,headers:{'Content-Type':'application/json'}});
          }
          if(Object.hasOwn(payload, 'display_name')) persistedName = payload.display_name;
        }
        return new Response(JSON.stringify(responseData()), {status:200,headers:{'Content-Type':'application/json'}});
      }
      if(url.pathname.includes('/contact-points')){
        return new Response(JSON.stringify({ok:true,data:{items:[]}}), {status:200,headers:{'Content-Type':'application/json'}});
      }
      return nativeFetch(input, init);
    };
  })()`;
  await send('Page.addScriptToEvaluateOnNewDocument', {source:fixtureScript});

  const openProfile = async(mode = 'verified')=>{
    await send('Page.navigate', {url:`${base}/index.html?mode=${mode}&review=dg04b`});
    await until('document.readyState === "complete" && typeof window.showPanel === "function"');
    await evaluate(`showPanel('p-info');document.getElementById('t-info-datos-tab').click()`);
    await until('document.getElementById("mxpi-save-btn")?.disabled === false');
    await evaluate(`document.getElementById('mxmed_dev_role_switcher')?.style.setProperty('display','none','important');document.getElementById('mx-public-identity-card').scrollIntoView({block:'start',behavior:'instant'})`);
  };

  await send('Emulation.setDeviceMetricsOverride', {width:1440,height:900,deviceScaleFactor:1,mobile:false});
  await openProfile('verified');
  const verifiedState = await evaluate(`(()=>({
    verifiedVisible:!document.getElementById('mxpi-verified-name').hidden,
    legacyHidden:document.getElementById('mxpi-legacy-name').hidden,
    allowed:[...document.getElementById('mxpi-verified-given-names').options].map(o=>o.value),
    first:document.getElementById('mxpi-verified-first-surname').textContent,
    metaAbsent:!document.getElementById('mxpi-verified-full-name')&&!document.getElementById('mxpi-public-name-preview'),
    detailsClosed:!document.getElementById('mxpi-verified-data-modal').classList.contains('show'),
    noAdminCard:!document.getElementById('mx-dg-verified-card'),
    tabs:document.querySelectorAll('#tabs-info [role="tab"]').length,
    photoFallback:document.querySelector('#mxpi-photo-preview img')?.dataset.avatarKind,
    credentials:document.querySelectorAll('#t-info-formacion #ced-prof').length,
    mediaReview:typeof window.mxmedMediaReview==='object',
    identityFieldOrder:[...document.querySelectorAll('#mx-public-identity-card .mxpi-public-grid > div')]
      .map((field)=>field.querySelector('select, input, textarea')?.id)
      .filter((id)=>['mxpi-prefix','mxpi-professional-designation'].includes(id)),
    signatureIsLast:document.getElementById('t-info-datos').lastElementChild?.id==='dg-signature-card',
    overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth
  }))()`);
  assert.deepEqual(verifiedState.allowed, ['Luis','Armando','Luis Armando']);
  assert.deepEqual(verifiedState, {...verifiedState,verifiedVisible:true,legacyHidden:true,first:'Reynoso',metaAbsent:true,detailsClosed:true,noAdminCard:true,tabs:5,photoFallback:'generic',credentials:1,mediaReview:true,identityFieldOrder:['mxpi-prefix','mxpi-professional-designation'],signatureIsLast:true,overflow:false});
  assert.equal(verifiedState.allowed.includes('Fernando'), false);
  assert.equal(await evaluate(`(()=>{const ids=['mxpi-prefix','mxpi-professional-designation'];const boxes=ids.map((id)=>document.getElementById(id).getBoundingClientRect());return boxes[0].left<boxes[1].left&&Math.max(...boxes.map((box)=>box.top))-Math.min(...boxes.map((box)=>box.top))<2})()`), true, 'desktop identity fields must render as Prefijo, Denominación');
  assert.equal(await evaluate(`document.getElementById('dg-signature-card').getBoundingClientRect().top>=document.getElementById('mx-public-identity-card').getBoundingClientRect().bottom`), true, 'signature card must render below identity');
  await screenshot('dg04b-name-editor-1440.png');

  const expected = [];
  for(const given of ['Luis','Armando','Luis Armando']){
    for(const second of [false,true]){
      await evaluate(`(()=>{const select=document.getElementById('mxpi-verified-given-names');select.value=${JSON.stringify(given)};select.dispatchEvent(new Event('change',{bubbles:true}));const check=document.getElementById('mxpi-show-second-surname');check.checked=${second};check.dispatchEvent(new Event('change',{bubbles:true}));document.getElementById('mxpi-save-btn').click()})()`);
      await until(`window.__dg04bPatchBodies.length === ${expected.length+1} && document.getElementById('mxpi-save-btn').disabled === false`);
      expected.push(await evaluate('window.__dg04bPatchBodies.at(-1).display_name'));
      assert.equal(await evaluate('window.__dg04bPatchBodies.at(-1).prefix'), 'Dr.');
    }
  }
  assert.deepEqual(expected, ['Luis Reynoso','Luis Reynoso Femat','Armando Reynoso','Armando Reynoso Femat','Luis Armando Reynoso','Luis Armando Reynoso Femat']);

  await send('Emulation.setDeviceMetricsOverride', {width:1366,height:768,deviceScaleFactor:1,mobile:false});
  await evaluate(`document.getElementById('mx-public-identity-card').scrollIntoView({block:'start',behavior:'instant'})`);
  assert.equal(await evaluate('document.documentElement.scrollWidth>document.documentElement.clientWidth'), false);
  await screenshot('dg04b-name-editor-1366.png');
  await evaluate(`document.getElementById('mxpi-show-second-surname').checked=false;document.getElementById('mxpi-show-second-surname').dispatchEvent(new Event('change',{bubbles:true}))`);
  await screenshot('dg04b-second-surname-hidden.png');
  await evaluate(`document.getElementById('mxpi-show-second-surname').checked=true;document.getElementById('mxpi-show-second-surname').dispatchEvent(new Event('change',{bubbles:true}))`);
  await screenshot('dg04b-second-surname-visible.png');
  await screenshot('dg04b-verified-details-closed.png');
  await evaluate(`document.getElementById('mxpi-verified-data-trigger').click()`);
  await until(`document.getElementById('mxpi-verified-data-modal').classList.contains('show')`);
  await new Promise(resolve=>setTimeout(resolve,400));
  await screenshot('dg04b-verified-details-open.png');

  await send('Emulation.setDeviceMetricsOverride', {width:390,height:844,deviceScaleFactor:1,mobile:true});
  await evaluate(`document.querySelector('#mxpi-verified-data-modal [data-bs-dismiss=modal]').click()`);
  await until(`!document.getElementById('mxpi-verified-data-modal').classList.contains('show')`);
  await new Promise(resolve=>setTimeout(resolve,400));
  await evaluate(`document.getElementById('mx-public-identity-card').scrollIntoView({block:'start',behavior:'instant'})`);
  assert.equal(await evaluate('document.documentElement.scrollWidth>document.documentElement.clientWidth'), false);
  assert.equal(await evaluate(`(()=>{const ids=['mxpi-prefix','mxpi-professional-designation'];const tops=ids.map((id)=>document.getElementById(id).getBoundingClientRect().top);return tops[0]<tops[1]})()`), true, 'mobile identity fields must stack as Prefijo, Denominación');
  await screenshot('dg04b-name-editor-mobile.png');

  await send('Emulation.setDeviceMetricsOverride', {width:1366,height:768,deviceScaleFactor:1,mobile:false});
  await openProfile('nonconforming');
  assert.equal(await evaluate('window.__dg04bPatchBodies.length'), 0, 'page open must not PATCH');
  assert.equal(await evaluate('document.getElementById("mxpi-current-name-value").textContent'), 'Nombre histórico no conforme');
  assert.equal(await evaluate(`document.getElementById('mxpi-current-name').textContent.trim().replace(/\\s+/g,' ')`), 'Nombre público en tu perfil: Nombre histórico no conforme');
  assert.equal(await evaluate('document.getElementById("mxpi-verified-given-names").value'), '', 'nonconforming legacy name requires an explicit valid selection');
  await evaluate(`document.getElementById('mxpi-professional-designation').value='Endocrinología clínica';document.getElementById('mxpi-professional-designation').dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('mxpi-save-btn').click()`);
  await until('window.__dg04bPatchBodies.length === 1');
  assert.equal(await evaluate(`Object.hasOwn(window.__dg04bPatchBodies[0],'display_name')`), false, 'sibling save preserves a nonconforming legacy name');
  await evaluate(`const s=document.getElementById('mxpi-verified-given-names');s.value='Armando';s.dispatchEvent(new Event('change',{bubbles:true}));document.getElementById('mxpi-save-btn').click()`);
  await until('window.__dg04bPatchBodies.length === 2');
  assert.equal(await evaluate(`window.__dg04bPatchBodies[1].display_name`), 'Armando Reynoso Femat');
  await until(`document.querySelector('.mx-gh-identity-name-text')?.textContent==='Armando Reynoso Femat'`);

  await openProfile('verified');
  await evaluate(`window.__dg04bRejectName=true;const s=document.getElementById('mxpi-verified-given-names');s.value='Armando';s.dispatchEvent(new Event('change',{bubbles:true}));document.getElementById('mxpi-save-btn').click()`);
  await until('document.getElementById("mxpi-feedback").textContent.includes("identidad verificada")');
  assert.equal(await evaluate('document.getElementById("mxpi-feedback").textContent'), 'El nombre público debe corresponder con tu identidad verificada.');

  await openProfile('legacy');
  assert.equal(await evaluate('document.getElementById("mxpi-legacy-name").hidden'), false);
  assert.equal(await evaluate('document.getElementById("mxpi-verified-name").hidden'), true);
  assert.equal(await evaluate('document.getElementById("mxpi-verified-full-name")'), null);
  await screenshot('dg04b-legacy-fallback.png');
  await evaluate(`const n=document.getElementById('mxpi-display-name');n.value='Nombre público legado libre';n.dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('mxpi-save-btn').click()`);
  await until('window.__dg04bPatchBodies.length === 1');
  assert.equal(await evaluate(`window.__dg04bPatchBodies[0].display_name`), 'Nombre público legado libre');

  console.log(`DG04B_BROWSER=PASS screenshots=${output}`);
}finally{
  ws?.close();
  chrome.kill();
  await new Promise((resolve)=> setTimeout(resolve, 250));
  await rm(profile, {recursive:true,force:true});
}
