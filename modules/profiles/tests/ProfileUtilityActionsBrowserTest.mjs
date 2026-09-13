// Read-only Director runtime QA: all profile/media writes are forbidden.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd035';
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

  await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{const native=window.fetch;window.__crd035Writes=[];window.fetch=(input,init={})=>{const method=String(init.method||(input instanceof Request?input.method:'GET')).toUpperCase();if(!['GET','HEAD'].includes(method)){window.__crd035Writes.push(method);return Promise.reject(Error('Read-only QA forbids writes'));}return native(input,init);};})()`});
  const metrics=[];
  const ids=['mxpi-verified-data-trigger','mx-visibility-help-trigger','mx-public-profile-link'];
  const labels=['Ver datos verificados','Visibilidad de los datos','Ver perfil público'];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate',{url:base+'/index.html?review=crd035'});
    await until('document.readyState==="complete" && typeof showPanel==="function"');
    await evaluate("showPanel('p-info');document.getElementById('t-info-datos-tab').click()");
    await until('document.getElementById("mxpi-bio-short")?.value.length>0 && !document.getElementById("mxpi-verified-data-trigger").hidden');
    await new Promise(r=>setTimeout(r,1100));
    await evaluate("document.querySelectorAll('.modal.show [data-bs-dismiss=modal]').forEach(e=>e.click())");
    await new Promise(r=>setTimeout(r,500));
    await evaluate('window.scrollTo(0,0)');
    const state=await evaluate(`(()=>{const ids=${JSON.stringify(ids)};const box=id=>document.getElementById(id).getBoundingClientRect();const actions=document.querySelector('#p-info .mx-profile-utility-actions');return {width:${width},tabsToMediaTop:box('mx-dg-media-card').top-box('tabs-info').bottom,nameBlockHeight:box('mxpi-name-editor').height,firstScreenContentBottom:box('mxpi-bio-short').bottom+scrollY,order:[...actions.children].map(e=>e.id),labels:[...actions.children].map(e=>e.querySelector('span:not([aria-hidden])')?.textContent||e.textContent),counts:ids.map(id=>document.querySelectorAll('#'+id).length),singleRow:Math.max(...ids.map(id=>box(id).top))-Math.min(...ids.map(id=>box(id).top))<2,overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,oldRow:!!document.querySelector('.mx-visibility-help-access'),meta:!!document.querySelector('.mxpi-name-editor__summary,#mxpi-public-name-preview,#mxpi-verified-full-name'),given:document.getElementById('mxpi-verified-given-names').value,first:document.getElementById('mxpi-verified-first-surname').textContent,second:document.getElementById('mxpi-verified-second-surname').textContent,showSecond:document.getElementById('mxpi-show-second-surname').checked,current:document.getElementById('mxpi-current-name-value').textContent,header:document.querySelector('.mx-gh-identity-name-text')?.textContent,sidebar:!!document.getElementById('mmSidebar'),link:document.getElementById('mx-public-profile-link').href,linkTarget:document.getElementById('mx-public-profile-link').target,writes:window.__crd035Writes}})()`);
    assert.deepEqual(state.order,ids);assert.deepEqual(state.labels,labels);assert.deepEqual(state.counts,[1,1,1]);
    assert.equal(state.overflow,false);assert.equal(state.oldRow,false);assert.equal(state.meta,false);
    assert.equal(state.given,'Leticia');assert.equal(state.first,'Muñoz');assert.equal(state.second,'Romo');assert.equal(state.showSecond,true);
    assert.equal(state.current,'Dra. Leticia Muñoz Romo');assert.ok(state.header.includes('Leticia'));assert.equal(state.sidebar,true);
    assert.equal(state.linkTarget,'_blank');assert.ok(state.link.includes('/profiles/doctor.php?doctor_id=1'));assert.deepEqual(state.writes,[]);
    if(width>500)assert.equal(state.singleRow,true);
    metrics.push(state);
    if(width>500)await screenshot(`crd035-top-actions-${width===1440?'1440':'1366'}.png`);
    if(width===1440){
      await screenshot('crd035-first-screen-density.png');
      await evaluate("document.getElementById('mxpi-name-editor').scrollIntoView({block:'start',behavior:'instant'})");
      await screenshot('crd035-name-block-compacted.png');
    }else if(width===390){
      await evaluate("document.querySelector('.mx-profile-utility-actions').scrollIntoView({block:'start',behavior:'instant'})");
      await screenshot('crd035-mobile-top-actions.png');
    }
    // Both original Bootstrap triggers must return focus at their new location.
    for(const [trigger,modal] of [['mxpi-verified-data-trigger','mxpi-verified-data-modal'],['mx-visibility-help-trigger','mx-visibility-help-modal']]){
      await evaluate(`document.getElementById('${trigger}').focus();document.getElementById('${trigger}').click()`);
      await until(`document.getElementById('${modal}').classList.contains('show')`);
      await new Promise(r=>setTimeout(r,400));
      assert.equal(await evaluate(`document.getElementById('${modal}').contains(document.activeElement)`),true);
      await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
      await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
      await until(`!document.getElementById('${modal}').classList.contains('show')`);
      await new Promise(r=>setTimeout(r,400));
      assert.equal(await evaluate('document.activeElement.id'),trigger);
    }
    assert.deepEqual(await evaluate('window.__crd035Writes'),[]);
  }
  const beforeTargets=(await send('Target.getTargets',{},null)).targetInfos.map(t=>t.targetId);
  await send('Runtime.evaluate',{expression:"document.getElementById('mx-public-profile-link').click()",userGesture:true});
  let publicTarget;
  for(let i=0;i<40;i++){
    publicTarget=(await send('Target.getTargets',{},null)).targetInfos.find(t=>!beforeTargets.includes(t.targetId)&&t.url.includes('/profiles/doctor.php?doctor_id=1'));
    if(publicTarget)break;await new Promise(r=>setTimeout(r,100));
  }
  assert.ok(publicTarget,'existing public link must open its public profile in a new tab');
  await send('Target.closeTarget',{targetId:publicTarget.targetId},null);
  await evaluate("document.getElementById('mxHeaderAccount').click()");
  assert.equal(await evaluate("document.querySelector('.mx-hb-account-menu').classList.contains('show')"),true);
  await evaluate("document.getElementById('mxHeaderAccount').click();document.querySelector('#mmSidebar [data-panel=\"p-ag-admin\"]').click()");
  await until("!document.getElementById('p-ag-admin').classList.contains('d-none')");
  await evaluate("document.querySelector('#mmSidebar [data-panel=\"p-info\"]').click()");
  await until("!document.getElementById('p-info').classList.contains('d-none')");
  assert.deepEqual(await evaluate('window.__crd035Writes'),[]);
  await writeFile(output+'/metrics.json',JSON.stringify(metrics,null,2));
  console.log('CRD035_BROWSER=PASS '+JSON.stringify(metrics.map(({width,tabsToMediaTop,nameBlockHeight,firstScreenContentBottom,singleRow})=>({width,tabsToMediaTop,nameBlockHeight,firstScreenContentBottom,singleRow}))));
}finally{ws?.close();chrome.kill();await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true});}
