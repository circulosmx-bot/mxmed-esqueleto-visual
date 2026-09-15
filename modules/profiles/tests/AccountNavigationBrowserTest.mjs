// Read-only browser QA for account-level Header navigation.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-nav01';
const profile = await mkdtemp('/tmp/mxmed-nav01-browser-');
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
  const send = (method, params = {}, sid = session)=>new Promise((resolve, reject)=>{
    const id = ++sequence;
    pending.set(id, {resolve, reject, method});
    ws.send(JSON.stringify({id, method, params, ...(sid ? {sessionId:sid} : {})}));
  });
  const consoleErrors = [];
  ws.addEventListener('message', event=>{
    const message = JSON.parse(event.data);
    if(message.method === 'Runtime.exceptionThrown') consoleErrors.push(message.params.exceptionDetails?.text || 'Runtime exception');
    if(message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') consoleErrors.push('console.error');
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
  const screenshot = async name=>{
    const result = await send('Page.captureScreenshot', {format:'png'});
    await writeFile(`${output}/${name}`, Buffer.from(result.data, 'base64'));
  };

  await send('Page.addScriptToEvaluateOnNewDocument', {source:`(()=>{const native=window.fetch;window.__nav01Writes=[];window.fetch=(input,init={})=>{const method=String(init.method||(input instanceof Request?input.method:'GET')).toUpperCase();if(!['GET','HEAD'].includes(method)){window.__nav01Writes.push(method);return Promise.reject(Error('NAV01 read-only QA forbids writes'));}return native(input,init);};})()`});

  const metrics = [];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride', {width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate', {url:`${base}/index.html?review=nav01-${width}&qa_tools=hide`});
    await until('document.readyState==="complete" && typeof jumpTo==="function" && !!window.bootstrap');
    await evaluate(`showPanel('p-info')`);

    const structure = await evaluate(`(()=>({
      profile:[...document.querySelectorAll('.menu-sub[data-group="perfil"] .menu-sub-btn')].map(el=>el.querySelector('.l1')?.textContent.trim()),
      oldSecurity:document.querySelectorAll('.menu-sub[data-group="perfil"] [data-panel="p-seguridad"]').length,
      oldSubscription:document.querySelectorAll('.menu-sub[data-group="perfil"] [data-panel="p-suscripcion"]').length,
      account:[...document.querySelectorAll('.mx-hb-account-menu .dropdown-item')].map(el=>{const copy=el.cloneNode(true);copy.querySelectorAll('[aria-hidden="true"]').forEach(node=>node.remove());return copy.textContent.trim()}),
      accountTargets:[...document.querySelectorAll('[data-account-panel]')].map(el=>el.dataset.accountPanel),
      planNavigates:document.querySelector('.mx-gh-current-plan-trigger').hasAttribute('data-panel')||document.querySelector('.mx-gh-current-plan-trigger').hasAttribute('data-account-panel'),
      logoutCount:document.querySelectorAll('[data-header-logout]').length,
      panelCounts:[document.querySelectorAll('#p-seguridad').length,document.querySelectorAll('#p-suscripcion').length],
      overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth
    }))()`);
    assert.deepEqual(structure.profile, ['Datos Personales','Consultorio','Opiniones']);
    assert.equal(structure.oldSecurity, 0);
    assert.equal(structure.oldSubscription, 0);
    assert.deepEqual(structure.account, ['Seguridad','Plan y suscripción','Cerrar sesión']);
    assert.deepEqual(structure.accountTargets, ['p-seguridad','p-suscripcion']);
    assert.equal(structure.planNavigates, false);
    assert.equal(structure.logoutCount, 1);
    assert.deepEqual(structure.panelCounts, [1,1]);
    assert.equal(structure.overflow, false);

    await evaluate(`document.getElementById('mxHeaderAccount').click()`);
    await until(`document.querySelector('.mx-hb-account-menu').classList.contains('show')`);
    await screenshot(`nav01-account-menu-${width}.png`);
    await evaluate(`document.querySelector('[data-account-panel="p-seguridad"]').click()`);
    await until(`!document.getElementById('p-seguridad').classList.contains('d-none')`);
    const security = await evaluate(`(()=>({
      current:document.querySelector('[data-account-panel="p-seguridad"]').getAttribute('aria-current'),
      menuOpen:document.querySelector('.mx-hb-account-menu').classList.contains('show'),
      profileSubActive:!!document.querySelector('.menu-sub[data-group="perfil"] .menu-sub-btn.active'),
      profileMainActive:document.querySelector('.menu-main[data-group="perfil"]').classList.contains('active')
    }))()`);
    assert.equal(security.current, 'page');
    assert.equal(security.menuOpen, false);
    assert.equal(security.profileSubActive, false);
    assert.equal(security.profileMainActive, false);

    await evaluate(`document.getElementById('mxHeaderAccount').click()`);
    await until(`document.querySelector('.mx-hb-account-menu').classList.contains('show')`);
    await evaluate(`document.querySelector('[data-account-panel="p-suscripcion"]').click()`);
    await until(`!document.getElementById('p-suscripcion').classList.contains('d-none')`);
    assert.deepEqual(await evaluate(`(()=>({
      current:document.querySelector('[data-account-panel="p-suscripcion"]').getAttribute('aria-current'),
      profileSubActive:!!document.querySelector('.menu-sub[data-group="perfil"] .menu-sub-btn.active'),
      profileMainActive:document.querySelector('.menu-main[data-group="perfil"]').classList.contains('active')
    }))()`), {current:'page',profileSubActive:false,profileMainActive:false});

    // Canonical panel ids remain valid for internal/direct navigation.
    await evaluate(`jumpTo('p-seguridad')`);
    assert.equal(await evaluate(`!document.getElementById('p-seguridad').classList.contains('d-none')`), true);
    await evaluate(`jumpTo('p-suscripcion')`);
    assert.equal(await evaluate(`!document.getElementById('p-suscripcion').classList.contains('d-none')`), true);

    // Existing profile destinations remain operational.
    for(const panelId of ['p-info','p-consultorio','p-opiniones']){
      await evaluate(`document.querySelector('.menu-sub[data-group="perfil"] [data-panel="${panelId}"]').click()`);
      assert.equal(await evaluate(`!document.getElementById('${panelId}').classList.contains('d-none')`), true);
    }

    // Native buttons remain in the tab order; Bootstrap Escape closes the menu.
    await evaluate(`document.getElementById('mxHeaderAccount').focus();document.getElementById('mxHeaderAccount').click()`);
    await until(`document.querySelector('.mx-hb-account-menu').classList.contains('show')`);
    assert.deepEqual(await evaluate(`[...document.querySelectorAll('[data-account-panel]')].map(el=>({tag:el.tagName,type:el.type,tabIndex:el.tabIndex,disabled:el.disabled}))`), [
      {tag:'BUTTON',type:'button',tabIndex:0,disabled:false},
      {tag:'BUTTON',type:'button',tabIndex:0,disabled:false}
    ]);
    await evaluate(`document.querySelector('[data-account-panel="p-suscripcion"]').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',code:'Escape',bubbles:true,cancelable:true}))`);
    await until(`!document.querySelector('.mx-hb-account-menu').classList.contains('show')`);
    assert.equal(await evaluate(`document.getElementById('mxHeaderAccount').getAttribute('aria-expanded')`), 'false');

    metrics.push({width,height,structure,security});
    assert.deepEqual(await evaluate(`window.__nav01Writes`), []);
  }

  // A dirty Personal Information edit routes the new controls through the existing guard.
  await send('Emulation.setDeviceMetricsOverride', {width:1440,height:900,deviceScaleFactor:1,mobile:false});
  await send('Page.navigate', {url:`${base}/index.html?review=nav01-guard&qa_tools=hide`});
  await until('document.readyState==="complete" && typeof jumpTo==="function"');
  await evaluate(`showPanel('p-info');document.getElementById('t-info-datos-tab').click()`);
  await until(`document.getElementById('mxpi-bio-short')?.value.length>0 && !document.getElementById('mxpi-save-btn').disabled`);
  const originalBio = await evaluate(`document.getElementById('mxpi-bio-short').value`);
  await evaluate(`(()=>{const input=document.getElementById('mxpi-bio-short');input.value+= ' QA';input.dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('mxHeaderAccount').click();})()`);
  await until(`document.querySelector('.mx-hb-account-menu').classList.contains('show')`);
  await evaluate(`document.querySelector('[data-account-panel="p-seguridad"]').click()`);
  await until(`document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`);
  await new Promise(resolve=>setTimeout(resolve, 450));
  assert.equal(await evaluate(`!document.getElementById('p-info').classList.contains('d-none')`), true);
  await evaluate(`document.getElementById('mxpi-unsaved-edit').click()`);
  await until(`!document.getElementById('mxpi-unsaved-navigation-modal').classList.contains('show')`);
  await evaluate(`(()=>{const input=document.getElementById('mxpi-bio-short');input.value=${JSON.stringify(originalBio)};input.dispatchEvent(new Event('input',{bubbles:true}));bootstrap.Dropdown.getOrCreateInstance(document.getElementById('mxHeaderAccount')).hide();})()`);
  assert.deepEqual(await evaluate(`window.__nav01Writes`), []);
  assert.deepEqual(consoleErrors, []);
  await writeFile(`${output}/navigation-metrics.json`, JSON.stringify({metrics,consoleErrors}, null, 2));
  console.log('ACCOUNT_NAVIGATION_BROWSER=PASS: Sidebar cleanup, Header order/routes/active state, profile destinations, plan/logout preservation, keyboard/Escape, dirty guard, 3 viewports, no writes/errors');
}finally{
  ws?.close();
  chrome.kill('SIGKILL');
  await new Promise(resolve=>setTimeout(resolve, 300));
  await rm(profile, {recursive:true,force:true,maxRetries:5,retryDelay:100});
}
