// Read-only Director review runtime: one existing SUBMITTED photo candidate,
// public photo/logo and eight gallery images. All mutation/state QA uses mocked fetch.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd032';
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

  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
  await send('Page.navigate',{url:base+'/index.html?review=crd032'});
  await until('document.readyState==="complete" && Boolean(window.mxmedMediaReview)');
  await evaluate('showPanel("p-info");document.getElementById("t-info-datos-tab").click()');
  await new Promise(resolve=>setTimeout(resolve,900));
  await evaluate('document.querySelectorAll(".modal.show [data-bs-dismiss=modal]").forEach(b=>b.click())');
  await new Promise(resolve=>setTimeout(resolve,400));
  await until('document.querySelector("[data-review-candidate=photo] img")?.naturalWidth>0');
  const real=await evaluate(`({pending:document.querySelectorAll('[data-review-candidate]').length,photo:document.querySelector('#mxpi-photo-control img:not([data-review-candidate] img)').src,logo:document.querySelector('[data-profile-logo-upload] img:not([data-review-candidate] img)').src,tabHeight:document.getElementById('t-info-datos').getBoundingClientRect().height,mediaHeight:document.getElementById('mx-dg-media-card').getBoundingClientRect().height,preview:document.querySelector('[data-review-candidate=photo] img').getAttribute('src'),standalone:document.querySelectorAll('.mx-owner-media-review').length})`);
  assert.equal(real.pending,1);assert.equal(real.standalone,0);
  assert.equal(await evaluate(`document.querySelector('[data-review-candidate=photo] .mx-media-review-badge').textContent`),'En revisión');
  await screenshot('crd032-leticia-inline-photo-review-1440.png');
  await screenshot('crd032-no-standalone-review-panel.png');
  await evaluate(`window.reviewMock={items:[],mode:'ok',canSubmit:true,calls:[],clicked:[]};window.realFetch=window.fetch;window.fetch=async(url,options={})=>{const m=window.reviewMock,route=String(url).split('/').pop();if(!['owner-review.php','review-batch-submit.php','profile-photo-review-candidate.php','physician-logo-review-candidate.php','gallery-review-candidate.php'].includes(route))return window.realFetch(url,options);m.calls.push({route,method:options.method||'GET',headers:options.headers,body:typeof options.body==='string'?options.body:null,form:options.body instanceof FormData});if(m.mode==='network')throw Error('network');if(m.mode==='parse')return new Response('invalid',{status:200});if(m.mode==='401'||m.mode==='403'||m.mode==='503')return Response.json({ok:false,error:'mock'},{status:Number(m.mode)});if(route==='owner-review.php')return Response.json({ok:true,data:{items:m.items}});if(route==='review-batch-submit.php'){if(options.method==='POST')m.items=m.items.map(i=>({...i,state:'SUBMITTED'}));return Response.json({ok:true,data:{csrf:'batch-csrf',can_submit_now:m.canSubmit}})}if(options.method==='DELETE'){const purpose={'profile-photo-review-candidate.php':'DOCTOR_PROFILE_PHOTO','physician-logo-review-candidate.php':'PHYSICIAN_PERSONAL_LOGO','gallery-review-candidate.php':'DOCTOR_GALLERY'}[route];m.items=m.items.filter(i=>i.purpose!==purpose || (options.body && i.id!==JSON.parse(options.body).submission_id))}return Response.json({ok:true,data:{csrf_token:'candidate-csrf'}})};document.querySelectorAll('#mxpi-photo-input,#fotos-input,[data-profile-logo-upload] input[type=file]').forEach(input=>input.click=()=>window.reviewMock.clicked.push(input.id||'logo'));window.setReview=async(items,mode='ok',canSubmit=true)=>{Object.assign(window.reviewMock,{items,mode,canSubmit});await window.mxmedMediaReview.refresh()};`);
  const items=['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY','DOCTOR_GALLERY'].map((purpose,i)=>({id:'mock-'+i,purpose,state:'OPEN',preview_url:'/assets/img/dr-female.svg',reason:'QUALITY_INSUFFICIENT',feedback:'Usa una imagen más nítida. <script>safe</script>'}));
  // Use the exact supplied REVIEW preview URL; never fetch SOURCE or replace public media.
  items.forEach(i=>i.preview_url=real.preview);
  await evaluate(`setReview(${JSON.stringify(items)})`);
  assert.deepEqual(await evaluate(`['photo','logo','gallery'].map(key=>document.querySelectorAll('[data-review-candidate='+key+']').length)`),[1,1,2]);
  assert.equal(await evaluate(`document.querySelectorAll('.mx-media-review-badge[data-review-state=OPEN]').length`),4);
  assert.equal(await evaluate(`document.querySelectorAll('.mx-media-review-announcement').length`),1);
  assert.ok(await evaluate(`[...document.querySelectorAll('[data-review-candidate]')].every(n=>n.getAttribute('aria-label').includes('Pendiente de enviar') && n.querySelector('img').getAttribute('src')===${JSON.stringify(real.preview)})`));
  assert.equal(await evaluate(`document.querySelector('.mx-media-review-batch span').textContent`),'4 cambios pendientes');
  assert.equal(await evaluate(`document.querySelector('#mxpi-photo-control img:not([data-review-candidate] img)').src`),real.photo);
  assert.equal(await evaluate(`document.querySelector('[data-profile-logo-upload] img:not([data-review-candidate] img)').src`),real.logo);
  await screenshot('crd032-open-pending-send.png');
  await evaluate(`document.querySelector('.mx-media-review-batch button').click()`);
  await until(`document.querySelectorAll('.mx-media-review-badge[data-review-state=SUBMITTED]').length===4 && !document.querySelector('.mx-media-review-batch')`);
  assert.equal(await evaluate(`JSON.parse(window.reviewMock.calls.find(c=>c.route==='review-batch-submit.php'&&c.method==='POST').body).csrf`),'batch-csrf');
  await evaluate(`setReview(${JSON.stringify(items.slice(0,1))},'ok',false)`);
  assert.equal(await evaluate(`document.querySelector('.mx-media-review-batch button').disabled`),true);
  assert.equal(await evaluate(`document.querySelector('.mx-media-review-batch span').textContent`),'1 cambio pendiente');
  for(const key of ['photo','logo','gallery']){
    await evaluate(`setReview(${JSON.stringify(items.map(i=>({...i,state:'SUBMITTED'})))})`);
    await evaluate(`document.querySelector('[data-review-candidate=${key}] button').click()`);
    await until(`!document.querySelector('[data-review-id=mock-${key==='photo'?0:key==='logo'?1:2}]')`);
    const call=await evaluate(`window.reviewMock.calls.filter(c=>c.method==='DELETE').at(-1)`);
    assert.ok(Object.values(call.headers).includes('candidate-csrf'));
    if(key==='gallery')assert.equal(JSON.parse(call.body).submission_id,'mock-2');
  }
  const needs=items.map(i=>({...i,state:'NEEDS_WORK'}));
  await evaluate(`setReview(${JSON.stringify(needs)})`);
  assert.equal(await evaluate(`document.querySelectorAll('.mx-media-review-observations[open]').length`),0);
  await evaluate(`document.querySelector('[data-review-candidate=photo] summary').click()`);
  assert.ok(await evaluate(`document.querySelector('[data-review-candidate=photo] details').open && document.querySelector('[data-review-candidate=photo] details').textContent.includes('QUALITY_INSUFFICIENT')`));
  assert.equal(await evaluate(`document.querySelector('[data-review-candidate=photo] details script')`),null);
  await screenshot('crd032-needs-work.png');
  for(const key of ['photo','logo','gallery'])await evaluate(`document.querySelector('[data-review-candidate=${key}] button').click()`);
  assert.deepEqual(await evaluate('window.reviewMock.clicked'),['mxpi-photo-input','mx-dg-logo','fotos-input']);
  for(const key of ['photo','logo','gallery'])await evaluate(`window.mxmedMediaReview.upload('${key}',new File(['synthetic'],'synthetic.png',{type:'image/png'}))`);
  assert.equal(await evaluate(`window.reviewMock.calls.filter(c=>c.method==='POST'&&c.form).length`),3);
  assert.equal(await evaluate(`window.reviewMock.calls.filter(c=>c.method==='POST'&&c.route==='review-batch-submit.php').length`),1,'upload does not auto-submit');
  for(const mode of ['ok','401']){
    await evaluate(`setReview([],'${mode}')`);
    assert.equal(await evaluate(`document.querySelectorAll('[data-media-review-ui]').length`),0,'empty/session reserves zero review height');
  }
  await evaluate(`setReview([],'403')`);
  assert.equal(await evaluate(`document.querySelectorAll('.mx-media-review-feedback button').length`),0);
  assert.ok(await evaluate(`document.querySelector('.mx-media-review-feedback').textContent.includes('acceso')`));
  for(const mode of ['network','503','parse']){
    await evaluate(`setReview([],'${mode}')`);
    assert.ok(await evaluate(`document.querySelector('.mx-media-review-feedback button').textContent==='Reintentar'`));
    await evaluate(`window.reviewMock.mode='ok';document.querySelector('.mx-media-review-feedback button').click()`);
    await until(`!document.querySelector('[data-media-review-ui]')`);
  }
  await evaluate(`setReview(${JSON.stringify(items)})`);
  await evaluate(`document.getElementById('t-info-fotos-tab').click()`);
  await new Promise(resolve=>setTimeout(resolve,1000));
  assert.equal(await evaluate(`document.querySelectorAll('#fotos-grid .foto-item:not([data-review-candidate])').length`),8);
  assert.equal(await evaluate(`document.querySelectorAll('#fotos-grid [data-review-candidate=gallery]').length`),2,'public gallery refresh preserves inline candidates');
  await evaluate(`document.getElementById('t-info-datos-tab').click()`);
  const viewports=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await new Promise(resolve=>setTimeout(resolve,400));
    const sizes=await evaluate(`({width:innerWidth,scroll:document.documentElement.scrollWidth,card:document.getElementById('mx-dg-media-card').getBoundingClientRect().height,candidates:[...document.querySelectorAll('#mx-dg-media-card .mx-media-review-thumbnail')].map(n=>({w:n.getBoundingClientRect().width,h:n.getBoundingClientRect().height}))})`);
    assert.ok(sizes.scroll<=sizes.width,JSON.stringify(sizes));await evaluate(`document.getElementById('t-info-fotos-tab').click()`);await new Promise(resolve=>setTimeout(resolve,400));
    assert.ok(await evaluate(`document.documentElement.scrollWidth<=innerWidth`),'gallery overflow');
    assert.ok(await evaluate(`[...document.querySelectorAll('#fotos-grid .foto-item:not([data-review-candidate])')].every(n=>Math.abs(n.getBoundingClientRect().width-n.getBoundingClientRect().height)<1)`),'public gallery thumbnail dimensions preserved');
    await evaluate(`document.getElementById('t-info-datos-tab').click()`);viewports.push({width,height,...sizes});
    if(width===390){await evaluate(`document.getElementById('mxpi-photo-control').scrollIntoView({block:'start',behavior:'instant'})`);await screenshot('crd032-mobile-inline-review.png');}else if(width===1366)await screenshot('crd032-inline-review-1366.png');
  }
  await writeFile(output+'/inline-report.json',JSON.stringify({real,viewports,states:['OPEN','SUBMITTED','NEEDS_WORK','empty','401','403','network','503','parse'],publicUnchanged:true,standaloneHeightAfter:0},null,2));
  console.log('INLINE_MEDIA_REVIEW_BROWSER=PASS: exact purpose routing; public preserved; states; submit CSRF; withdraw CSRF; feedback/replacement; candidate upload; zero empty/401 height; errors/retry; gallery async preservation; accessibility; 3 viewports');
}finally{ws?.close();chrome.kill();await new Promise(resolve=>setTimeout(resolve,300));await rm(profile,{recursive:true,force:true});}
