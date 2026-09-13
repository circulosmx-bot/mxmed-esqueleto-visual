// Read-only Director runtime. Synthetic read-model projections exercise modal filtering.
// No actual candidate mutation, credential mutation or public-pointer update.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd033';
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

  const canonicalResponse=await(await fetch(process.env.CANONICAL_URL||'http://127.0.0.1:18145/api/profiles/private/doctor/1')).json();
  assert.equal(canonicalResponse.ok,true);
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
  await send('Page.navigate',{url:base+'/index.html?review=crd033'});
  await until('document.readyState==="complete" && Boolean(window.mxmedMediaReview)');
  await evaluate('showPanel("p-info");document.getElementById("t-info-datos-tab").click()');
  await new Promise(r=>setTimeout(r,1000));
  await evaluate('document.querySelectorAll(".modal.show [data-bs-dismiss=modal]").forEach(b=>b.click())');
  await new Promise(r=>setTimeout(r,400));
  await until('document.querySelector("[data-review-candidate=photo] img")?.naturalWidth>0 && !document.getElementById("mxpi-verified-data-trigger").hidden');
  const actual=await evaluate(`(async()=>({private:(await(await fetch('/api/profiles/private/doctor/1')).json()).data,owner:(await(await fetch('/api/media/owner-review.php')).json()).data,mediaHeight:document.getElementById('mx-dg-media-card').getBoundingClientRect().height,identityChrome:document.querySelector('#mx-public-identity-card .card-body > .d-flex:has(.mxpi-title-row)')?.getBoundingClientRect().height||0}))()`);
  assert.equal(actual.owner.items.length,1);assert.equal(actual.owner.items[0].state,'SUBMITTED');
  const visibleCount=key=>evaluate(`[...document.querySelectorAll('${key==='photo'?'#mxpi-photo-control':'[data-profile-logo-upload]'} img')].filter(n=>n.getBoundingClientRect().width>0 && n.getBoundingClientRect().height>0).length`);
  assert.equal(await visibleCount('photo'),1);assert.equal(await visibleCount('logo'),1);
  assert.equal(await evaluate(`document.querySelector('[data-review-candidate=photo] img').getAttribute('src')`),actual.owner.items[0].preview_url);
  assert.equal(await evaluate(`document.querySelector('[data-review-candidate=photo] .mx-media-review-badge').textContent`),'En revisión');
  assert.equal(await evaluate(`document.querySelector('[data-profile-logo-upload] .mx-media-public-badge').textContent`),'Publicado');
  assert.equal(await evaluate(`document.querySelector('[data-profile-logo-upload] .mx-media-public-badge').getBoundingClientRect().width`),await evaluate(`document.getElementById('mx-dg-logo-prev').clientWidth`),'published badge stays on its thumbnail');
  assert.equal(await evaluate(`document.querySelector('#mx-public-identity-card .mxpi-title-row')`),null);
  assert.equal(await evaluate(`document.querySelector('#mx-public-identity-card .mxpi-name-editor__title, #mx-public-identity-card .mxpi-name-editor__heading')`),null);
  assert.equal(await evaluate(`document.querySelector('.mx-media-review-caption,#mxpi-verified-details,.mxpi-verified-details__grid')`),null);
  await screenshot('crd033-leticia-single-photo-thumbnail.png');await screenshot('crd033-photo-en-revision.png');
  await evaluate(`document.getElementById('mx-public-identity-card').scrollIntoView({block:'start',behavior:'instant'})`);
  await screenshot('crd033-name-fields-no-identity-header.png');
  await evaluate(`window.modalMutations=[];window.savedFetch=window.fetch;window.fetch=(url,options={})=>{if(options.method && !['GET','HEAD'].includes(options.method))window.modalMutations.push({url,method:options.method});return window.savedFetch(url,options)};`);
  const open=async()=>{await evaluate(`document.getElementById('mxpi-verified-data-trigger').focus();document.getElementById('mxpi-verified-data-trigger').click()`);await until(`document.getElementById('mxpi-verified-data-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,400));};
  const close=async()=>{await evaluate(`document.querySelector('#mxpi-verified-data-modal [data-bs-dismiss=modal]').click()`);await until(`!document.getElementById('mxpi-verified-data-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,400));assert.equal(await evaluate(`document.activeElement.id`),'mxpi-verified-data-trigger');};
  await open();
  assert.equal(await evaluate(`document.querySelector('[data-verified-modal-identity] p').textContent`),'Leticia Muñoz Romo');
  assert.equal(await evaluate(`document.querySelectorAll('[data-credential-mode=legacy] [data-verified=true]').length`),0);
  assert.equal(await evaluate(`document.querySelectorAll('[data-credential-mode=legacy] article').length`),2);
  assert.ok(await evaluate(`document.getElementById('mxpi-verified-data-content').textContent.includes('0123456') && document.getElementById('mxpi-verified-data-content').textContent.includes('6543210')`));
  assert.equal(await evaluate(`document.querySelectorAll('[data-credential-mode=legacy] .mx-verified-modal-status').length`),0);
  assert.equal(await evaluate(`document.getElementById('mxpi-verified-data-modal').getAttribute('aria-labelledby')`),'mxpi-verified-data-title');
  assert.equal(await evaluate(`document.querySelector('#mxpi-verified-data-modal .modal-title').textContent`),'Datos verificados');
  await evaluate(`document.getElementById('mxpi-prefix').focus()`);
  assert.ok(await evaluate(`document.getElementById('mxpi-verified-data-modal').contains(document.activeElement)`),'focus trapped');
  await screenshot('crd033-verified-data-modal-leticia.png');
  await close();
  await open();
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
  await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
  await until(`!document.getElementById('mxpi-verified-data-modal').classList.contains('show')`);await new Promise(r=>setTimeout(r,400));
  assert.equal(await evaluate(`document.activeElement.id`),'mxpi-verified-data-trigger');
  await evaluate(`window.mxmedRenderCredentials(${JSON.stringify(canonicalResponse.data)})`);
  await open();
  const expectedCanonical=canonicalResponse.data.verified_credentials;
  assert.equal(await evaluate(`document.querySelectorAll('[data-credential-mode=canonical] [data-kind=professional][data-verified=true]').length`),1);
  assert.equal(await evaluate(`document.querySelectorAll('[data-credential-mode=canonical] [data-kind=specialty][data-verified=true]').length`),expectedCanonical.specialties.length);
  assert.equal(await evaluate(`document.querySelector('[data-credential-mode=legacy]')`),null);
  for(const credential of [expectedCanonical.professional,...expectedCanonical.specialties])assert.ok(await evaluate(`document.getElementById('mxpi-verified-data-content').textContent.includes(${JSON.stringify(credential.license_number)})`));
  await screenshot('crd033-verified-data-modal-canonical-credentials.png');await close();
  const filtered=structuredClone(canonicalResponse.data);
  filtered.verified_identity={full_name:'Identidad canónica segura',source_reference:'SECRET_SOURCE',verified_by_account_id:'SECRET_OPERATOR',verified_at:'SECRET_TIME'};
  filtered.identity_public={...filtered.identity_public,display_name:'DISPLAY_NAME_MUST_NOT_BE_IDENTITY',gender:'SECRET_GENDER',bio_short:'SECRET_BIO',professional_designation:'SECRET_DESIGNATION'};
  filtered.verified_credentials.specialties.push({...expectedCanonical.specialties[0],credential_id:'pending',verification_status:'PENDING_REVIEW',license_number:'SECRET_PENDING'}, {...expectedCanonical.specialties[0],credential_id:'inactive',lifecycle_status:'INACTIVE',license_number:'SECRET_INACTIVE'});
  await evaluate(`window.mxmedRenderCredentials(${JSON.stringify(filtered)})`);
  const modalText=await evaluate(`document.getElementById('mxpi-verified-data-content').textContent`);
  assert.ok(modalText.includes('Identidad canónica segura'));assert.ok(!modalText.includes('SECRET_')&&!modalText.includes('DISPLAY_NAME_MUST_NOT_BE_IDENTITY'));
  assert.equal(await evaluate(`document.querySelectorAll('[data-credential-mode=canonical] [data-kind=specialty]').length`),expectedCanonical.specialties.length);
  const noInstitution=structuredClone(filtered);noInstitution.verified_credentials={professional:{...expectedCanonical.professional,institution_name:null},specialties:[]};
  await evaluate(`window.mxmedRenderCredentials(${JSON.stringify(noInstitution)})`);assert.equal(await evaluate(`document.querySelectorAll('[data-credential-mode=canonical] article').length`),1);
  const noIdentity={identity_public:{display_name:'Never verified'},verified_credentials:{professional:null,specialties:[]},verified_identity:null};
  await evaluate(`window.mxmedRenderCredentials(${JSON.stringify(noIdentity)})`);
  assert.equal(await evaluate(`document.querySelectorAll('#mxpi-verified-data-content .mx-verified-modal-status').length`),0);
  assert.ok(!await evaluate(`document.getElementById('mxpi-verified-data-content').textContent.includes('Never verified')`));
  await evaluate(`window.mxmedRenderCredentials(${JSON.stringify(actual.private)})`);
  // No candidate presentation returns to the unchanged published thumbnail.
  await evaluate(`window.ownerBeforeMock=window.fetch;window.fetch=(url,options={})=>String(url).endsWith('owner-review.php')?Promise.resolve(Response.json({ok:true,data:{items:[]}})):window.ownerBeforeMock(url,options);window.mxmedMediaReview.refresh()`);
  await until(`!document.querySelector('[data-review-candidate]')`);
  assert.equal(await visibleCount('photo'),1);assert.equal(await visibleCount('logo'),1);
  assert.equal(await evaluate(`document.getElementById('mxpi-photo-preview').getBoundingClientRect().height>0`),true);
  assert.equal(await evaluate(`document.querySelector('#mxpi-photo-preview .mx-media-public-badge').textContent`),'Publicada');
  assert.equal(await evaluate(`document.querySelector('#mxpi-photo-preview .mx-media-public-badge').getBoundingClientRect().width`),await evaluate(`document.getElementById('mxpi-photo-preview').clientWidth`));
  await evaluate(`document.getElementById('mx-dg-media-card').scrollIntoView({block:'start',behavior:'instant'})`);await screenshot('crd033-photo-publicada.png');
  const viewports=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await open();
    const dimensions=await evaluate(`(()=>{const modal=document.querySelector('#mxpi-verified-data-modal .modal-content').getBoundingClientRect();return {left:modal.left,right:modal.right,top:modal.top,bottom:modal.bottom,width:innerWidth,height:innerHeight,overflow:document.documentElement.scrollWidth>innerWidth}})()`);
    assert.ok(!dimensions.overflow);assert.ok(dimensions.left>=0&&dimensions.right<=width&&dimensions.top>=0&&dimensions.bottom<=height,JSON.stringify(dimensions));
    viewports.push({width,height,...dimensions});if(width===390)await screenshot('crd033-mobile-modal.png');await close();
  }
  const many=structuredClone(canonicalResponse.data);many.verified_credentials.specialties=Array.from({length:20},(_,i)=>({...expectedCanonical.specialties[0],credential_id:'many-'+i}));
  await evaluate(`window.mxmedRenderCredentials(${JSON.stringify(many)})`);await open();
  assert.ok(await evaluate(`document.querySelector('#mxpi-verified-data-modal .modal-body').scrollHeight>document.querySelector('#mxpi-verified-data-modal .modal-body').clientHeight`),'long credentials scroll internally');await close();
  assert.equal(await evaluate(`window.modalMutations.length`),0,'modal is read-only');
  await send('Page.navigate',{url:base+'/profiles/doctor.php?doctor_id=1&mxmed_plan=standard&review=crd033'});
  await until(`document.querySelector('.mxpp-avatar')?.naturalWidth>0`);
  assert.equal(await evaluate(`document.querySelector('.mxpp-avatar').getAttribute('src')`),actual.private.identity_public.photo_url);
  assert.equal(await evaluate(`document.querySelector('.mxpp-brand-logo')?.getAttribute('src')||document.querySelector('img[src*="a1e44098"]')?.getAttribute('src')`),actual.private.identity_public.logo_url);
  await writeFile(output+'/report.json',JSON.stringify({mediaHeight:actual.mediaHeight,identityChrome:actual.identityChrome,visiblePhotoCount:1,visibleLogoCount:1,publicPhotoUnchanged:true,canonicalSpecialties:expectedCanonical.specialties.length,viewports,modalMutations:0},null,2));
  console.log('CRD033_BROWSER=PASS: single media slot; approved public pointers; published badges; identity density; canonical identity; canonical active verified credentials; neutral legacy; no provenance/unrelated data; focus trap/X/Escape/return; mobile internal scroll; zero modal mutations');
}finally{ws?.close();chrome.kill();await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true});}
