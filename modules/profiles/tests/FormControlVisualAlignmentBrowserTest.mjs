// Read-only computed-style and behavior QA for IP02 form-control alignment.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.IP02_TEST_URL || process.env.PUBLIC_URL;
if(!base) throw Error('IP02_TEST_URL must point to a local read-only physician review runtime.');
const output = process.env.QA_OUTPUT || '/tmp/mxmed-ip02';
const profile = await mkdtemp('/tmp/mxmed-ip02-browser-');
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
      await new Promise(resolve=>setTimeout(resolve, 100));
    }
  }
  assert.ok(version?.webSocketDebuggerUrl, 'Chrome DevTools did not start');
  ws = new WebSocket(version.webSocketDebuggerUrl);
  await new Promise(resolve=>ws.addEventListener('open', resolve, {once:true}));

  let sequence = 0;
  let session;
  const pending = new Map();
  const errors = [];
  const send = (method, params = {}, sid = session)=>new Promise((resolve, reject)=>{
    const id = ++sequence;
    pending.set(id, {resolve, reject, method});
    ws.send(JSON.stringify({id, method, params, ...(sid ? {sessionId:sid} : {})}));
  });
  ws.addEventListener('message', event=>{
    const message = JSON.parse(event.data);
    if(message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails?.text || 'Runtime exception');
    if(message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') errors.push('console.error');
    if(!message.id) return;
    const request = pending.get(message.id);
    pending.delete(message.id);
    message.error ? request.reject(Error(`${request.method}: ${JSON.stringify(message.error)}`)) : request.resolve(message.result);
  });

  const target = (await send('Target.createTarget', {url:'about:blank'}, null)).targetId;
  session = (await send('Target.attachToTarget', {targetId:target, flatten:true}, null)).sessionId;
  await send('Page.enable');
  await send('Runtime.enable');
  const evaluate = async expression=>{
    const result = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
    if(result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
  };
  const until = async expression=>{
    for(let attempt = 0; attempt < 180; attempt += 1){
      if(await evaluate(expression)) return;
      await new Promise(resolve=>setTimeout(resolve, 100));
    }
    throw Error(`Browser timeout: ${expression}`);
  };
  const pointer = async selector=>{
    await evaluate(`document.querySelector(${JSON.stringify(selector)}).scrollIntoView({block:'center',behavior:'instant'})`);
    const rect = await evaluate(`(()=>{const r=document.querySelector(${JSON.stringify(selector)}).getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}})()`);
    await send('Input.dispatchMouseEvent', {type:'mousePressed',x:rect.x,y:rect.y,button:'left',clickCount:1});
    await send('Input.dispatchMouseEvent', {type:'mouseReleased',x:rect.x,y:rect.y,button:'left',clickCount:1});
  };
  const screenshot = async name=>{
    const image = await send('Page.captureScreenshot', {format:'png'});
    await writeFile(`${output}/${name}`, Buffer.from(image.data, 'base64'));
  };
  const style = selector=>evaluate(`(()=>{const el=document.querySelector(${JSON.stringify(selector)}),c=getComputedStyle(el),p=getComputedStyle(el,'::placeholder');return {background:c.backgroundColor,borderColor:c.borderColor,borderWidth:c.borderWidth,borderStyle:c.borderStyle,borderRadius:c.borderRadius,height:c.height,minHeight:c.minHeight,fontFamily:c.fontFamily,fontSize:c.fontSize,fontWeight:c.fontWeight,color:c.color,placeholderColor:p.color,placeholderOpacity:p.opacity,paddingTop:c.paddingTop,paddingRight:c.paddingRight,paddingBottom:c.paddingBottom,paddingLeft:c.paddingLeft,boxShadow:c.boxShadow,outline:c.outline,transition:c.transition,opacity:c.opacity,cursor:c.cursor}})()`);
  const shared = value=>({
    background:value.background,
    borderColor:value.borderColor,
    borderWidth:value.borderWidth,
    borderStyle:value.borderStyle,
    borderRadius:value.borderRadius,
    height:value.height,
    fontFamily:value.fontFamily,
    fontSize:value.fontSize,
    fontWeight:value.fontWeight,
    color:value.color,
    paddingTop:value.paddingTop,
    paddingBottom:value.paddingBottom,
    paddingLeft:value.paddingLeft,
    boxShadow:value.boxShadow,
    outline:value.outline,
    transition:value.transition
  });

  await send('Page.addScriptToEvaluateOnNewDocument', {source:`(()=>{for(const id of ['browser','1','2'])localStorage.setItem('mxmed.ui.visibility_help_seen.v1:'+id,'1');const native=window.fetch;window.__ip02Writes=[];window.fetch=(input,init={})=>{const method=String(init.method||(input instanceof Request?input.method:'GET')).toUpperCase();if(!['GET','HEAD'].includes(method)){window.__ip02Writes.push(method);return Promise.reject(Error('IP02 read-only QA forbids writes'));}return native(input,init);};})()`});

  const targets = ['#cert-input','#cursos-input','#dipl-input','#miem-input','#srv1','#srv2','#srv3','#srv4','#enf-input','#trt-input'];
  const results = [];
  for(const [width,height] of [[1440,900],[1366,768],[820,1180],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride', {width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate', {url:`${base}/index.html?review=ip02-${width}&qa_tools=hide`});
    await until(`document.readyState==='complete' && typeof showPanel==='function'`);
    await evaluate(`localStorage.setItem('mxmed.ui.visibility_help_seen.v1:'+(document.body.dataset.doctorId||document.body.dataset.userId||'browser'),'1')`);
    await evaluate(`showPanel('p-info');document.getElementById('t-info-datos-tab').click()`);
    await until(`!document.getElementById('mxpi-save-btn').disabled`);
    await evaluate(`document.querySelectorAll('.modal.show [data-bs-dismiss=modal]').forEach(button=>button.click())`);
    await until(`!document.querySelector('.modal.show,.modal-backdrop')`);

    const master = await style('#mxpi-professional-designation');
    const placeholderMaster = await style('#mxpi-bio-short');
    await pointer('#mxpi-professional-designation');
    await until(`document.activeElement?.id==='mxpi-professional-designation'`);
    await new Promise(resolve=>setTimeout(resolve, 250));
    const masterFocus = await style('#mxpi-professional-designation');

    await evaluate(`bootstrap.Tab.getOrCreateInstance(document.getElementById('t-info-formacion-tab')).show()`);
    await until(`document.getElementById('t-info-profesional').classList.contains('active') && window.mxmedProfessionalInformation?.loaded===true`);
    for(const selector of targets){
      const current = await style(selector);
      assert.deepEqual(shared(current), shared(master), `${selector} matches Personal Information at ${width}`);
      assert.equal(current.placeholderColor, placeholderMaster.placeholderColor, `${selector} placeholder color at ${width}`);
      assert.equal(current.placeholderOpacity, placeholderMaster.placeholderOpacity, `${selector} placeholder opacity at ${width}`);
    }
    const summary = await style('#professional-summary');
    assert.equal(summary.borderColor, master.borderColor);
    assert.equal(summary.borderRadius, master.borderRadius);
    assert.equal(summary.background, master.background);
    assert.equal(summary.color, master.color);
    assert.equal(summary.boxShadow, master.boxShadow);
    assert.ok(parseFloat(summary.height) > parseFloat(master.height), 'textarea keeps its intentional height');

    await pointer('#cert-input');
    await until(`document.activeElement?.id==='cert-input'`);
    await new Promise(resolve=>setTimeout(resolve, 250));
    const targetFocus = await style('#cert-input');
    assert.equal(targetFocus.borderColor, masterFocus.borderColor);
    assert.equal(targetFocus.borderWidth, masterFocus.borderWidth);
    assert.equal(targetFocus.boxShadow, masterFocus.boxShadow);
    assert.equal(targetFocus.outline, masterFocus.outline);
    assert.equal(targetFocus.background, masterFocus.background);
    assert.equal(targetFocus.transition, masterFocus.transition);

    const protectedCredential = await style('#ced-prof');
    assert.notEqual(protectedCredential.borderColor, master.borderColor, 'read-only credential style remains distinct');
    assert.equal(await evaluate(`document.getElementById('ced-prof').matches(':read-only')`), true);
    assert.equal(await evaluate(`document.querySelectorAll('#t-info-profesional .chip').length>=0 && [...document.querySelectorAll('#t-info-profesional .chip-add')].every(button=>button.type==='button')`), true);

    // The existing blur gesture still creates only a local draft and can be discarded.
    const initialChipCount = await evaluate(`document.querySelectorAll('#cert-list .chip').length`);
    await evaluate(`(()=>{const input=document.getElementById('cert-input');input.value='IP02 prueba local ${width}';input.dispatchEvent(new Event('input',{bubbles:true}))})()`);
    await pointer('#srv1');
    await until(`document.querySelectorAll('#cert-list .chip').length===${initialChipCount + 1}`);
    assert.equal(await evaluate(`window.mxmedProfessionalInformation.dirty()`), true);
    await evaluate(`window.mxmedProfessionalInformation.discard()`);
    assert.equal(await evaluate(`window.mxmedProfessionalInformation.dirty()`), false);
    assert.deepEqual(await evaluate(`window.__ip02Writes`), []);
    await new Promise(resolve=>setTimeout(resolve, 250));

    await evaluate(`document.getElementById('t-info-formacion').scrollIntoView({block:'start',behavior:'instant'})`);
    const layout = await evaluate(`(()=>({overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,formationColumns:getComputedStyle(document.querySelector('#t-info-formacion>.row')).gridTemplateColumns,servicesColumns:getComputedStyle(document.querySelector('#t-info-servicios>.row')).gridTemplateColumns}))()`);
    assert.equal(layout.overflow, false);
    await screenshot(`ip02-professional-controls-${width}.png`);
    results.push({width,height,master,placeholderMaster,target:await style('#cert-input'),masterFocus,targetFocus,summary,protectedCredential,layout});
  }

  assert.deepEqual(errors, []);
  await writeFile(`${output}/computed-styles.json`, JSON.stringify({results,errors}, null, 2));
  console.log('IP02_FORM_CONTROL_BROWSER=PASS: computed normal/focus parity, textarea/read-only distinction, chip blur/discard, no writes/errors/overflow, 4 viewports');
}finally{
  ws?.close();
  chrome.kill('SIGKILL');
  await new Promise(resolve=>setTimeout(resolve, 300));
  await rm(profile, {recursive:true,force:true,maxRetries:5,retryDelay:100});
}
