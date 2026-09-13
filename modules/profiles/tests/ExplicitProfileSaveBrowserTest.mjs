// Real Director hydration; every mutation intercepted in memory. Never writes Director data.
// Requires an isolated local review runtime: doctor 1 has trusted synthetic
// professional + three verified specialties; doctor 2 has legacy fields only.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd031';
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

  const original = await (await fetch(`${base}/api/profiles/private/doctor/1`)).json();
  assert.equal(original.ok, true);
  const errors = [];
  ws.addEventListener('message', ({data})=>{
    const message=JSON.parse(data);
    if(message.method==='Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
    if(message.method==='Runtime.consoleAPICalled' && message.params.type==='error') errors.push(message.params.args.map(a=>a.value).join(' '));
  });
  await send('Page.addScriptToEvaluateOnNewDocument', {source:`
    window.__qaWrites=[]; window.__qaStorageWrites=[]; window.__qaFail=false; window.__qaDelay=0; window.__qaReadDelay=0;
    const nativeFetch=window.fetch.bind(window);
    const nativeSet=Storage.prototype.setItem;
    Storage.prototype.setItem=function(k,v){window.__qaStorageWrites.push(k);return nativeSet.call(this,k,v)};
    window.fetch=async(input,options={})=>{
      const url=String(input), method=String(options.method || 'GET').toUpperCase();
      if(method==='GET'){const response=await nativeFetch(input,options);if(url==='/api/profiles/private/doctor/1')await new Promise(r=>setTimeout(r,window.__qaReadDelay));return response;}
      window.__qaWrites.push({url,method,payload:options.body ? JSON.parse(options.body) : null});
      if(method==='PATCH' && /^\\/api\\/profiles\\/private\\/doctor\\/1$/.test(url)){
        await new Promise(r=>setTimeout(r,window.__qaDelay));
        if(window.__qaFail) return new Response(JSON.stringify({ok:false,error:'unavailable'}),{status:503});
        const result=${JSON.stringify(original)};
        const payload=JSON.parse(options.body);
        Object.assign(result.data.identity_public,payload);
        result.data.profile_theme.stored_key=payload.profile_theme_key;
        result.data.public_name_policy.current_display_name_policy_status=payload.display_name ? 'CONFORMING' : result.data.public_name_policy.current_display_name_policy_status;
        return new Response(JSON.stringify(result),{status:200,headers:{'Content-Type':'application/json'}});
      }
      return new Response(JSON.stringify({ok:false,error:'blocked_qa_write'}),{status:503});
    };
  `});
  const open=async(width=1440,height=900)=>{
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate',{url:`${base}/index.html?review=crd039-test&qa_tools=hide`});
    await until(`document.getElementById('mxpi-save-btn') && !document.getElementById('mxpi-save-btn').disabled && document.getElementById('mx-profile-theme-swatches')?.children.length===20`);
    await evaluate(`showPanel('p-info');document.getElementById('t-info-datos-tab').click()`);
    await new Promise(r=>setTimeout(r,1300));
    await evaluate(`document.querySelectorAll('.modal.show').forEach(m=>bootstrap.Modal.getInstance(m)?.hide());document.getElementById('mxmed_dev_role_switcher')?.remove()`);
    await new Promise(r=>setTimeout(r,450));
  };
  const edit=async(id,value,event='input')=> evaluate(`(()=>{const c=document.getElementById(${JSON.stringify(id)});c.value=${JSON.stringify(value)};c.dispatchEvent(new Event(${JSON.stringify(event)},{bubbles:true}));c.dispatchEvent(new Event('blur'));})()`);
  const visible=()=>evaluate(`!document.getElementById('mxpi-floating-save').hidden`);
  const writes=()=>evaluate(`window.__qaWrites`);
  const theme=async(key)=>evaluate(`document.querySelector('#mx-profile-theme-swatches [data-theme-key="${key}"]').click()`);
  const current=await open();
  assert.equal(await visible(),false);
  assert.equal(await evaluate(`document.querySelectorAll('#mxpi-save-btn').length`),1);
  assert.equal(await evaluate(`document.querySelector('.mx-theme-admin__preview-content button').textContent`),'Así se vería el color en tu perfil');
  await screenshot('crd039-clean-no-save-button-1440.png');
  const identity=original.data.identity_public;
  await evaluate(`window.__qaReadDelay=700;document.querySelector('[data-profile-panel="p-info"], .menu-sub-btn[data-panel="p-info"]').click()`);
  await until(`document.getElementById('mxpi-save-btn').disabled`);
  await edit('mxpi-bio-short','Borrador durante recarga');
  await until(`!document.getElementById('mxpi-save-btn').disabled`);
  assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),'Borrador durante recarga');
  await edit('mxpi-bio-short',identity.bio_short);assert.equal(await visible(),false);
  await evaluate(`window.__qaReadDelay=0`);
  for(const [id,value] of [['mxpi-professional-designation',identity.professional_designation],['mxpi-bio-short',identity.bio_short]]){
    await edit(id,'Edición local');assert.equal(await visible(),true);
    await edit(id,value);assert.equal(await visible(),false);
  }
  await edit('mxpi-prefix','Dr.','change');assert.equal(await visible(),true);
  await edit('mxpi-prefix',identity.prefix,'change');assert.equal(await visible(),false);
  await evaluate(`document.getElementById('mxpi-show-second-surname').click()`);assert.equal(await visible(),true);
  await evaluate(`document.getElementById('mxpi-show-second-surname').click()`);assert.equal(await visible(),false);
  const originalKey=original.data.profile_theme.stored_key;
  const alternate=originalKey==='soft_coral'?'medical_blue':'soft_coral';
  await theme(alternate);assert.equal(await visible(),true);
  if(originalKey) await theme(originalKey);else await evaluate(`document.getElementById('mx-profile-theme-reset').click()`);
  assert.equal(await visible(),false);
  await evaluate(`document.getElementById('mx-profile-theme-reset').click()`);
  assert.equal(await visible(),originalKey!==null);
  if(originalKey) await theme(originalKey);
  assert.equal((await writes()).length,0);
  await edit('mxpi-professional-designation','Edición local');
  await theme(alternate);
  await evaluate(`localStorage.setItem('dp:mxpi-professional-designation','POISON');localStorage.setItem('dp:mxpi-bio-short','POISON');localStorage.setItem('dp:mxpi-prefix','Dr.');`);
  await open();
  assert.equal(await visible(),false);
  assert.equal(await evaluate(`document.getElementById('mxpi-professional-designation').value`),identity.professional_designation);
  assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),identity.bio_short);
  assert.equal(await evaluate(`document.querySelector('#mx-profile-theme-swatches [aria-checked="true"]').dataset.themeKey`),originalKey || 'mxmed_teal');
  assert.equal((await writes()).length,0);
  await edit('mxpi-bio-short','Borrador local');
  await evaluate(`document.getElementById('t-info-formacion-tab').click()`);
  assert.equal(await visible(),true);
  await evaluate(`document.getElementById('t-info-datos-tab').click();document.querySelector('[data-panel="p-info"]')?.click()`);
  await new Promise(r=>setTimeout(r,400));
  assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),'Borrador local');
  await edit('mxpi-verified-given-names',original.data.public_name_policy.allowed_given_name_presentations[0],'change');
  await edit('mxpi-prefix','Dr.','change');
  await theme(alternate);
  await screenshot('crd039-dirty-floating-save-1440.png');
  await evaluate(`document.getElementById('mx-profile-theme-admin').scrollIntoView({block:'center'})`);
  await screenshot('crd039-theme-preview-dirty.png');
  await evaluate(`window.__qaDelay=600;document.getElementById('mxpi-save-btn').click();document.getElementById('mxpi-save-btn').click();document.getElementById('mxpi-save-btn').dispatchEvent(new MouseEvent('click'))`);
  assert.equal(await evaluate(`document.getElementById('mxpi-save-btn').textContent`),'Guardando…');
  assert.equal(await evaluate(`document.getElementById('mxpi-save-btn').disabled`),true);
  await until(`document.getElementById('mxpi-feedback').textContent==='Cambios guardados' && document.getElementById('mxpi-floating-save').hidden`);
  assert.equal((await writes()).length,1);
  assert.ok(await evaluate(`document.getElementById('mx-profile-theme-feedback').textContent.endsWith('color actual.')`));
  const patch=(await writes())[0];
  assert.equal(patch.method,'PATCH');assert.equal(patch.payload.prefix,'Dr.');assert.equal(patch.payload.bio_short,'Borrador local');assert.equal(patch.payload.profile_theme_key,alternate);
  assert.equal(patch.payload.display_name,'Leticia Muñoz Romo');
  assert.deepEqual(Object.keys(patch.payload).sort(),['bio_short','display_name','prefix','professional_designation','profile_theme_key']);
  await evaluate(`document.getElementById('mx-public-identity-card').scrollIntoView({block:'center'})`);
  await screenshot('crd039-save-success.png');
  await edit('mxpi-professional-designation','Conservar tras error');
  await evaluate(`window.__qaFail=true;document.getElementById('mxpi-save-btn').click()`);
  await until(`!document.getElementById('mxpi-save-btn').disabled`);
  assert.equal(await visible(),true);
  assert.equal(await evaluate(`document.getElementById('mxpi-professional-designation').value`),'Conservar tras error');
  assert.ok(await evaluate(`document.getElementById('mxpi-feedback').classList.contains('text-danger')`));
  await evaluate(`window.__qaFail=false;document.getElementById('mxpi-save-btn').click()`);
  await until(`document.getElementById('mxpi-floating-save').hidden`);
  assert.equal((await writes()).length,3);
  await edit('mxpi-bio-short','Primer envío');
  await evaluate(`document.getElementById('mxpi-save-btn').click()`);
  await edit('mxpi-bio-short','Edición durante envío');
  await until(`!document.getElementById('mxpi-save-btn').disabled`);
  assert.equal(await visible(),true);
  assert.equal(await evaluate(`document.getElementById('mxpi-bio-short').value`),'Edición durante envío');
  await edit('mxpi-bio-short','Primer envío');assert.equal(await visible(),false);
  assert.deepEqual(await evaluate(`window.__qaStorageWrites.filter(k=>/^dp:mxpi-/.test(k))`),[]);
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await open(width,height);
    await edit('mxpi-bio-short','Borrador visible');
    await evaluate(`document.getElementById('mx-public-identity-card').scrollIntoView({block:'center'})`);
    const geometry=await evaluate(`(()=>{const b=document.getElementById('mxpi-save-btn'),r=b.getBoundingClientRect(),f=document.getElementById('mxpi-floating-save'),c=getComputedStyle(f);return {x:r.x,y:r.y,width:r.width,height:r.height,right:innerWidth-r.right,bottom:innerHeight-r.bottom,position:c.position,overflow:document.documentElement.scrollWidth>innerWidth,background:getComputedStyle(b).backgroundColor};})()`);
    assert.equal(geometry.position,'fixed');assert.equal(geometry.overflow,false);assert.equal(geometry.background,'rgb(0, 174, 190)');
    assert.ok(geometry.height>=44);assert.ok(geometry.bottom>=16);
    assert.equal(geometry.right,width<500?16:24);
    assert.equal(await evaluate(`(()=>{const b=document.getElementById('mxpi-save-btn'),r=b.getBoundingClientRect();return document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)===b})()`),true,'button is reachable in explicit local QA view');
    assert.ok(await evaluate(`parseFloat(getComputedStyle(document.querySelector('#p-info > .body')).paddingBottom)>=88`));
    if(width<500)assert.equal(geometry.width,width-32);
    await screenshot(width<500?'crd039-mobile-floating-save.png':`crd039-dirty-floating-save-${width}.png`);
    assert.equal((await writes()).length,0);
  }
  assert.deepEqual(errors,[],'No new console/runtime errors');
  console.log(JSON.stringify({result:'PASS',scenarios:'clean, edit/revert text/select/surname/theme, reset, no autosave/storage, reload, mounted tabs, one grouped PATCH, saving/double-submit, success, failure/retry, concurrent draft, 3 viewports, exact preview copy, no console errors',DirectorWrites:0}));
}finally{
  ws?.close();chrome.kill('SIGTERM');await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true,maxRetries:5,retryDelay:100});
}
