// Read-only local Director review QA: real profile writes are forbidden.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd036';
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

  // Observed CRD03.5 baseline, CSS pixels at the same three viewports.
  const before=[
    {width:1440,nameBlockHeight:143.671875,controlsTopY:510.515625,controlsTopGap:38.28125,lineHeight:15.390625,fontSize:11.84,controlHeight:42},
    {width:1366,nameBlockHeight:143.671875,controlsTopY:510.515625,controlsTopGap:38.28125,lineHeight:15.390625,fontSize:11.84,controlHeight:42},
    {width:390,nameBlockHeight:299.671875,controlsTopY:1566.3125,controlsTopGap:37.28125,lineHeight:15.390625,fontSize:11.84,controlHeight:42}
  ];
  const metrics=[];
  await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{const native=window.fetch;window.__crd036Writes=[];window.fetch=(input,init={})=>{const method=String(init.method||(input instanceof Request?input.method:'GET')).toUpperCase();if(!['GET','HEAD'].includes(method)){window.__crd036Writes.push(method);return Promise.reject(Error('Read-only QA forbids writes'));}return native(input,init);};})()`});
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate',{url:base+'/index.html?review=crd036'});
    await until('document.readyState==="complete" && typeof showPanel==="function"');
    await evaluate("showPanel('p-info');document.getElementById('t-info-datos-tab').click()");
    await until('document.getElementById("mxpi-current-name-value")?.textContent.includes("Leticia")');
    await new Promise(r=>setTimeout(r,1100));
    await evaluate("document.querySelectorAll('.modal.show [data-bs-dismiss=modal]').forEach(e=>e.click())");
    await new Promise(r=>setTimeout(r,500));
    await evaluate('window.scrollTo(0,0)');
    const state=await evaluate(`(()=>{
      const box=id=>document.getElementById(id).getBoundingClientRect();
      const line=document.getElementById('mxpi-current-name');const style=getComputedStyle(line);
      const controls=document.querySelector('.mxpi-verified-name__controls').getBoundingClientRect();
      const range=document.createRange();range.selectNodeContents(line);
      return {width:${width},nameBlockHeight:box('mxpi-name-editor').height,controlsTopY:controls.top+scrollY,
        controlsTopGap:controls.top-box('mxpi-name-editor').top,controlHeight:box('mxpi-verified-given-names').height,
        lineHeight:box('mxpi-current-name').height,fontSize:parseFloat(style.fontSize),cssLineHeight:parseFloat(style.lineHeight),
        color:style.color,labelFontSize:parseFloat(getComputedStyle(line.querySelector('.mxpi-public-name-label')).fontSize),valueFontSize:parseFloat(getComputedStyle(line.querySelector('.mxpi-public-name-value')).fontSize),textOverflow:style.textOverflow,whiteSpace:style.whiteSpace,
        lines:Math.ceil(range.getBoundingClientRect().height/parseFloat(style.lineHeight)),
        overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,
        heading:!!document.querySelector('.mxpi-name-editor__title,.mxpi-name-editor__heading'),
        copy:line.textContent.trim().replace(/\s+/g,' '),given:document.getElementById('mxpi-verified-given-names').value,
        first:document.getElementById('mxpi-verified-first-surname').textContent,second:document.getElementById('mxpi-verified-second-surname').textContent,
        showSecond:document.getElementById('mxpi-show-second-surname').checked,
        labels:[...document.querySelectorAll('.mxpi-verified-name__controls .form-label')].map(e=>e.textContent.trim()),
        labelFor:document.querySelector('label[for="mxpi-verified-given-names"]').control?.id,
        checkboxFor:document.querySelector('label[for="mxpi-show-second-surname"]').control?.id,
        utilities:[...document.querySelector('.mx-profile-utility-actions').children].map(e=>e.id),
        gender:document.querySelectorAll('#mx-public-identity-card #mxpi-gender,#mxpi-gender-label').length,
        descriptionLimit:document.getElementById('mxpi-bio-short').maxLength,
        bio:document.getElementById('mxpi-bio-short').value,writes:window.__crd036Writes};})()`);
    const baseline=before.find(m=>m.width===width);
    assert.equal(state.heading,false);assert.equal(state.copy,'Nombre público en tu perfil: Dra. Leticia Muñoz Romo');
    assert.equal(state.color,'rgb(37, 150, 190)');
    assert.ok(Math.abs(state.labelFontSize/state.valueFontSize-.85)<.01);
    assert.equal(state.valueFontSize,state.fontSize);
    assert.ok(Math.abs(state.fontSize/baseline.fontSize-1.5)<.01,'public name must be approximately 50% larger');
    assert.ok(state.controlsTopY<baseline.controlsTopY-20,'controls must move upward naturally');
    assert.ok(state.controlsTopGap<baseline.controlsTopGap-20,'removed heading must not reserve its old spacing');
    assert.equal(state.controlHeight,baseline.controlHeight,'actual controls must keep their height');
    assert.ok(state.nameBlockHeight<=baseline.nameBlockHeight+5,'emphasized name must not materially enlarge the block');
    assert.equal(state.overflow,false);assert.notEqual(state.textOverflow,'ellipsis');assert.notEqual(state.whiteSpace,'nowrap');
    if(width>500)assert.equal(state.lines,1,'desktop public name must remain a single line');
    assert.equal(state.given,'Leticia');assert.equal(state.first,'Muñoz');assert.equal(state.second,'Romo');assert.equal(state.showSecond,true);
    assert.deepEqual(state.labels,['Nombre(s)','Primer apellido','Segundo apellido']);
    assert.equal(state.labelFor,'mxpi-verified-given-names');assert.equal(state.checkboxFor,'mxpi-show-second-surname');
    assert.deepEqual(state.utilities,['mxpi-verified-data-trigger','mx-visibility-help-trigger','mx-public-profile-link']);
    assert.equal(state.gender,0);assert.equal(state.descriptionLimit,150);
    assert.equal(state.bio,'Especialista en alteraciónes del sistema endocrino y enfermedades metabólicas.');
    assert.deepEqual(state.writes,[]);metrics.push(state);
    await evaluate("document.getElementById('mxpi-name-editor').scrollIntoView({block:'start',behavior:'instant'})");
    await screenshot(width===1440?'crd036-name-block-compact-1440.png':width===1366?'crd036-name-block-1366.png':'crd036-name-block-mobile.png');
    if(width===1440){
      const clip=await evaluate("(()=>{const r=document.getElementById('mxpi-name-editor').getBoundingClientRect();return {x:r.left+scrollX,y:r.top+scrollY,width:r.width,height:r.height,scale:1}})()");
      const shot=await send('Page.captureScreenshot',{format:'png',clip,captureBeyondViewport:true});
      await writeFile(output+'/crd036-public-name-emphasis-1440.png',Buffer.from(shot.data,'base64'));
    }
  }
  await writeFile(output+'/metrics.json',JSON.stringify(metrics,null,2));
  console.log('CRD036_BROWSER=PASS '+JSON.stringify(metrics.map(({width,nameBlockHeight,controlsTopY,controlsTopGap,lineHeight,fontSize,color,lines})=>({width,nameBlockHeight,controlsTopY,controlsTopGap,lineHeight,fontSize,color,lines}))));
}finally{ws?.close();chrome.kill();await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true});}
