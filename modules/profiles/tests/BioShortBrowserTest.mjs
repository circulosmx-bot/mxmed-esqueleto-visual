// Read-only Leticia review QA. Stress text is DOM-only and grouped PATCH is mocked.
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
  const plan = process.env.QA_PUBLIC_PLAN || 'standard'; // Existing local plan preview; no plan/data writes.
  const current = await (await fetch(`${base}/api/profiles/public/doctor/1`)).json();
  assert.equal(current.ok,true);
  const currentBio = current.data.professional.bio_short;
  const currentPrivate = await (await fetch(`${base}/api/profiles/private/doctor/1`)).json();
  const natural = length => Array.from('Atención médica especializada en diabetes, tiroides y metabolismo. Diagnóstico y seguimiento personalizados para adultos y familias. Prevención y bienestar.').slice(0,length).join('');
  const samples = [
    ['leticia',currentBio], ['100',natural(100)], ['120',natural(120)], ['140',natural(140)], ['150',natural(150)],
    ['narrow-150','i'.repeat(150)], ['wide-150','W'.repeat(150)],
    ['accents-150',Array.from('áéíóúñ ÁÉÍÓÚÑ atención médica '.repeat(8)).slice(0,150).join('')],
  ];
  assert.equal(Array.from(samples[4][1]).length,150);
  const results=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<600});
    await send('Page.navigate',{url:`${base}/profiles/doctor.php?doctor_id=1&mxmed_plan=${plan}`});
    await until(`document.readyState==='complete' && document.querySelector('.mxpp-bio')`);
    await evaluate(`document.fonts.ready.then(()=>true)`);
    await new Promise(resolve=>setTimeout(resolve,200));
    const originalMarkup = await evaluate(`({hero:document.querySelector('.mxpp-hero-brand-actions')?.outerHTML,licenses:[...document.querySelectorAll('.mxpp-license-inline')].map(e=>e.textContent),consultorios:[...document.querySelectorAll('[data-mxpp-consultorio-panel]')].map(e=>e.outerHTML),agenda:[...document.querySelectorAll('[data-mxpp-agenda-compact]')].map(e=>e.dataset)})`);
    for(const [name,text] of samples){
      await evaluate(`document.querySelector('.mxpp-bio').textContent=${JSON.stringify(text)};window.dispatchEvent(new Event('resize'))`);
      await new Promise(resolve=>setTimeout(resolve,220));
      const measurement = await evaluate(`(()=>{
        const bio=document.querySelector('.mxpp-bio');
        const normal=()=>{const range=document.createRange();range.selectNodeContents(bio);const rects=[...range.getClientRects()];const r=bio.getBoundingClientRect();const css=getComputedStyle(bio);return {scrollHeight:bio.scrollHeight,clientHeight:bio.clientHeight,lineHeight:css.lineHeight,height:r.height,lines:new Set(rects.map(x=>Math.round(x.top*10))).size,font:parseFloat(css.fontSize),overflow:rects.some(x=>x.left<r.left-1||x.right>r.right+1),hidden:css.overflowY==='hidden'||css.textOverflow==='ellipsis'||parseInt(css.webkitLineClamp)>0,collision:r.bottom>document.querySelector('.mxpp-hero-brand-actions').getBoundingClientRect().top+1};};
        const fitted=normal(),inline=bio.style.fontSize;
        bio.style.removeProperty('font-size');const baseline=normal();
        const normalFont=baseline.font;
        bio.style.fontSize=inline;
        let priorLines=null;
        if(fitted.font<normalFont){bio.style.fontSize=Math.min(normalFont,fitted.font+0.5)+'px';priorLines=normal().lines;bio.style.fontSize=inline;}
        return {...fitted,text:bio.textContent,length:Array.from(bio.textContent).length,normalFont,normalLines:baseline.lines,priorLines,pageOverflow:document.documentElement.scrollWidth>innerWidth+1};
      })()`);
      const entry={width,name,...measurement};results.push(entry);
      assert.equal(measurement.text,text,'No clipping/truncation or duplicate copy');
      assert.equal(measurement.hidden,false);assert.equal(measurement.overflow,false);assert.equal(measurement.pageOverflow,false);assert(measurement.font>=16);
      const target=width>768?2:3;
      if(measurement.normalLines<=target)assert.equal(measurement.font,measurement.normalFont,JSON.stringify(entry));
      if(measurement.lines>target)assert.equal(measurement.font,16,'Only the readability floor may allow extra natural lines');
      if(measurement.font<measurement.normalFont && measurement.font>16)assert(measurement.priorLines>target,'Reduction stops at first fit');
      assert.equal(measurement.collision,false,'Brand/action row does not overlap Bio');
      if(width===1440 && name==='leticia')await screenshot('crd031-public-leticia-current-bio.png');
      if(width===1440 && name==='120')await screenshot('crd031-public-bio-120.png');
      if(width===1440 && name==='150')await screenshot('crd031-public-bio-150.png');
      if(width===390 && name==='150')await screenshot('crd031-public-mobile-bio.png');
    }
    assert.deepEqual(await evaluate(`({hero:document.querySelector('.mxpp-hero-brand-actions')?.outerHTML,licenses:[...document.querySelectorAll('.mxpp-license-inline')].map(e=>e.textContent),consultorios:[...document.querySelectorAll('[data-mxpp-consultorio-panel]')].map(e=>e.outerHTML),agenda:[...document.querySelectorAll('[data-mxpp-agenda-compact]')].map(e=>e.dataset)})`), originalMarkup,'Bio fitting leaves all other public sections unchanged');
  }
  const adminResults=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<600});
    await send('Page.navigate',{url:`${base}/index.html?review=crd031-leticia`});
    await until(`document.readyState==='complete' && typeof showPanel==='function'`);
    await evaluate(`showPanel('p-info');document.getElementById('t-info-datos-tab').click()`);
    await until(`document.querySelector('#mxpi-bio-short')?.value===${JSON.stringify(currentBio)} && document.querySelector('#mx-credential-list')?.dataset.mode==='legacy'`);
    await new Promise(resolve=>setTimeout(resolve,700));
    await evaluate(`document.querySelectorAll('.modal.show [data-bs-dismiss=modal]').forEach(e=>e.click())`);
    await until(`!document.querySelector('.modal.show')`);
    const state=await evaluate(`({width:innerWidth,genderControls:document.querySelectorAll('#mxpi-gender-label').length,maxlength:document.querySelector('#mxpi-bio-short').maxLength,counter:document.querySelector('#mxpi-bio-count').textContent,statusNodes:document.querySelectorAll('#mxpi-profile-status,#mxpi-public-candidate,label[for=mxpi-profile-status],label[for=mxpi-public-candidate]').length,header:document.querySelector('.mx-gh-identity-name-text').textContent,photo:document.querySelector('#mxpi-photo-preview img').src,logo:document.querySelector('#mx-dg-logo-img').src,credentials:document.querySelector('#mx-credential-list').textContent,pageOverflow:document.documentElement.scrollWidth>innerWidth+1})`);
    assert.equal(state.genderControls,0);assert.equal(state.maxlength,150);assert.equal(state.counter,Array.from(currentBio).length+' / 150');assert.equal(state.statusNodes,0);assert(state.header.includes('Leticia Muñoz Romo'));assert.equal(state.pageOverflow,false);
    assert(state.photo.includes(currentPrivate.data.identity_public.photo_url));assert(state.logo.includes(currentPrivate.data.identity_public.logo_url)||state.logo.includes('a1e44098-a0ef-403f-b51e-8bee88100ef8'));
    assert(state.credentials.includes('0123456')&&state.credentials.includes('6543210'));adminResults.push(state);
    await evaluate(`document.getElementById('mxpi-prefix').scrollIntoView({block:'center'})`);
    if(width===1440){await screenshot('crd031-admin-gender-bio-1440.png');await screenshot('crd031-admin-no-system-status-fields.png');}
  }
  // Input/dirty/grouped-save checks use a mocked PATCH response only. Leticia DB is never mutated.
  const editing = await evaluate(`(()=>{
    const input=document.querySelector('#mxpi-bio-short'),counter=document.querySelector('#mxpi-bio-count');
    const insert=text=>{const data=new DataTransfer();data.setData('text/plain',text);input.dispatchEvent(new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}));};
    input.value='';input.setSelectionRange(0,0);insert('áéíóúñ');const accents=counter.textContent==='6 / 150';
    input.select();insert('😀'.repeat(151));const paste=Array.from(input.value).length===150&&counter.textContent==='150 / 150';
    const extra=new InputEvent('beforeinput',{inputType:'insertText',data:'a',cancelable:true});input.dispatchEvent(extra);
    const blocked=extra.defaultPrevented&&Array.from(input.value).length===150;
    input.value='á'.repeat(149);input.setSelectionRange(149,149);input.dispatchEvent(new InputEvent('beforeinput',{inputType:'insertText',data:'😀',cancelable:true}));const unicode=Array.from(input.value).length===150;
    input.value='ñ'.repeat(151);input.dispatchEvent(new Event('input',{bubbles:true}));const invalid=!input.checkValidity();
    return {accents,paste,blocked,unicode,invalid};
  })()`);
  for(const value of Object.values(editing))assert.equal(value,true);
  const savedDto=await evaluate(`fetch('/api/profiles/private/doctor/1').then(r=>r.json())`);
  await evaluate(`(()=>{
    const dto=${JSON.stringify(savedDto)},originalFetch=window.fetch;
    window.__qaBioPatches=[];
    window.fetch=async(url,options={})=>{
      if(String(url).includes('/private/doctor/1')&&options.method==='PATCH'){
        const payload=JSON.parse(options.body);window.__qaBioPatches.push(payload);
        Object.assign(dto.data.identity_public,payload);if(payload.display_name){dto.data.public_name_policy.current_display_name=payload.display_name;dto.data.public_name_policy.current_display_name_policy_status='VALID';}
        return new Response(JSON.stringify(dto),{status:200,headers:{'Content-Type':'application/json'}});
      }
      return originalFetch(url,options);
    };
    document.querySelector('#mxpi-save-btn').click();
  })()`);
  assert.equal(await evaluate(`window.__qaBioPatches.length`),0,'151 characters cannot be saved from UI');
  await evaluate(`(()=>{
    const bio=document.getElementById('mxpi-bio-short');bio.value='ñ'.repeat(150);bio.dispatchEvent(new Event('input',{bubbles:true}));
    const names=document.getElementById('mxpi-verified-given-names');names.value='Leticia';names.dispatchEvent(new Event('change',{bubbles:true}));
    const second=document.getElementById('mxpi-show-second-surname');second.checked=false;second.dispatchEvent(new Event('change',{bubbles:true}));
    document.getElementById('mxpi-save-btn').click();
  })()`);
  await until(`window.__qaBioPatches.length===1 && document.querySelector('#mxpi-feedback').textContent.includes('Cambios guardados')`);
  const patch=await evaluate(`window.__qaBioPatches[0]`);
  assert.deepEqual(Object.keys(patch).sort(),['display_name','professional_designation','prefix','bio_short','profile_theme_key'].sort());
  assert.equal(Array.from(patch.bio_short).length,150);assert(!('profile_status' in patch));assert(!('is_public_candidate' in patch));
  assert.equal(await evaluate(`document.querySelector('#mxpi-feedback').classList.contains('text-success')`),true,'Save clears dirty feedback');
  const narrow=results.find(r=>r.width===1440&&r.name==='narrow-150'),wide=results.find(r=>r.width===1440&&r.name==='wide-150');
  assert.notEqual(narrow.font,wide.font,'Equal character counts with different rendered width fit differently');
  await writeFile(`${output}/results.json`,JSON.stringify({results,adminResults,editing,groupedPatch:patch},null,2));
  console.log(JSON.stringify({results:results.map(({text,...r})=>r),adminResults,editing,output},null,2));
}finally{
  ws?.close();chrome.kill();await new Promise(resolve=>chrome.once('exit',resolve));await rm(profile,{recursive:true,force:true});
}
