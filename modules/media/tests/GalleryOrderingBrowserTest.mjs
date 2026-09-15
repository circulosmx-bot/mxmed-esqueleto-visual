import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-gal01-browser';
const profile = await mkdtemp('/tmp/mxmed-gal01-chrome-');
await mkdir(output, {recursive: true});
const chrome = spawn(process.env.MXMED_QA_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
  '--headless=new', '--no-first-run', '--no-default-browser-check', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`,
], {stdio: 'ignore'});

let ws;
try {
  let version;
  for (let attempt = 0; attempt < 100; attempt += 1) {
    try {
      const port = (await readFile(`${profile}/DevToolsActivePort`, 'utf8')).split('\n')[0];
      version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json();
      break;
    } catch { await new Promise(resolve => setTimeout(resolve, 100)); }
  }
  assert.ok(version?.webSocketDebuggerUrl, 'Chrome DevTools did not start');
  ws = new WebSocket(version.webSocketDebuggerUrl);
  await new Promise(resolve => ws.addEventListener('open', resolve, {once: true}));
  let sequence = 0;
  let session;
  const pending = new Map();
  const runtimeErrors = [];
  const consoleErrors = [];
  const send = (method, params = {}, sessionId = session) => new Promise((resolve, reject) => {
    const id = ++sequence;
    pending.set(id, {resolve, reject, method});
    ws.send(JSON.stringify({id, method, params, ...(sessionId ? {sessionId} : {})}));
  });
  ws.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.method === 'Runtime.exceptionThrown') runtimeErrors.push(message.params);
    if (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') consoleErrors.push(message.params);
    if (!message.id) return;
    const request = pending.get(message.id);
    pending.delete(message.id);
    message.error ? request.reject(Error(`${request.method}: ${JSON.stringify(message.error)}`)) : request.resolve(message.result);
  });
  const target = (await send('Target.createTarget', {url: 'about:blank'}, null)).targetId;
  session = (await send('Target.attachToTarget', {targetId: target, flatten: true}, null)).sessionId;
  await send('Page.enable');
  await send('Runtime.enable');
  const evaluate = async expression => {
    const result = await send('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
    if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
  };
  const until = async expression => {
    for (let attempt = 0; attempt < 180; attempt += 1) {
      if (await evaluate(expression)) return;
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    throw Error(`Browser timeout: ${expression}`);
  };
  const screenshot = async name => {
    await new Promise(resolve => setTimeout(resolve, 250));
    const result = await send('Page.captureScreenshot', {format: 'png'});
    await writeFile(`${output}/${name}`, Buffer.from(result.data, 'base64'));
  };
  const order = () => evaluate(`[...document.querySelectorAll('#fotos-grid>[data-gallery-media-id]')].map(card=>card.dataset.galleryMediaId)`);
  const rect = selector => evaluate(`(()=>{const r=document.querySelector(${JSON.stringify(selector)}).getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height,left:r.left,right:r.right,top:r.top,bottom:r.bottom}})()`);

  await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 900, deviceScaleFactor: 1, mobile: false});
  await send('Page.navigate', {url: `${base}/index.html?qa_tools=hide&review=gal01`});
  await until(`document.readyState==='complete'&&typeof showPanel==='function'`);
  await evaluate(`showPanel('p-info');document.getElementById('t-info-fotos-tab').click();document.getElementById('mxmed_dev_role_switcher')?.remove()`);
  await until(`document.querySelectorAll('#fotos-grid>[data-gallery-media-id]').length===8`);
  await until(`!document.getElementById('fotos-drop').hasAttribute('aria-busy')`);
  await new Promise(resolve => setTimeout(resolve, 500));
  await evaluate(`window.galleryOrderQa={patches:[],mode:'ok'};window.galleryOrderRealFetch=window.fetch;window.fetch=async(url,options={})=>{if(String(url).includes('/api/media/gallery.php')&&(options.method||'GET')==='PATCH'){const ids=JSON.parse(options.body).media_ids;window.galleryOrderQa.patches.push(ids);if(window.galleryOrderQa.mode==='fail')return Response.json({ok:false,error:'gallery_unavailable',message:'No se pudo guardar el orden.'},{status:503});const live=await (await window.galleryOrderRealFetch('/api/media/gallery.php',{credentials:'same-origin'})).json(),byId=new Map(live.data.images.map(item=>[item.media_id,item]));return Response.json({ok:true,data:{images:ids.map(id=>byId.get(id)),csrf_token:live.data.csrf_token}});}return window.galleryOrderRealFetch(url,options);};`);
  const initial = await order();
  assert.equal(initial.length, 8);
  assert.equal(await evaluate(`document.getElementById('fotos-order-toolbar').hidden`), true, 'save action starts hidden');
  assert.ok(await evaluate(`[...document.querySelectorAll('.foto-order-handle')].every((handle,index)=>handle.getAttribute('aria-label').includes('fotografía '+(index+1)))`), 'ordered accessible labels');
  assert.equal(await evaluate(`document.querySelectorAll('[data-review-candidate] .foto-order-handle').length`), 0, 'review candidates are not reorderable');

  await evaluate(`document.querySelector('.foto-order-handle').dispatchEvent(new KeyboardEvent('keydown',{key:'End',bubbles:true,cancelable:true}))`);
  assert.deepEqual(await order(), [...initial.slice(1), initial[0]], 'keyboard moves first to last');
  assert.equal(await evaluate(`window.galleryOrderQa.patches.length`), 0, 'keyboard reorder is local only');
  assert.equal(await evaluate(`document.getElementById('fotos-order-toolbar').hidden`), false);
  await evaluate(`document.getElementById('fotos-order-reset').click()`);
  assert.deepEqual(await order(), initial, 'reset restores saved baseline');
  assert.equal(await evaluate(`document.getElementById('fotos-order-toolbar').hidden`), true);

  await evaluate(`document.querySelector('#fotos-grid>[data-gallery-media-id]:last-child .foto-order-handle').dispatchEvent(new KeyboardEvent('keydown',{key:'Home',bubbles:true,cancelable:true}))`);
  assert.deepEqual(await order(), [initial.at(-1), ...initial.slice(0, -1)], 'keyboard moves last to first');
  assert.equal(await evaluate(`window.galleryOrderQa.patches.length`), 0, 'reverse keyboard reorder is local only');
  await evaluate(`document.getElementById('fotos-order-reset').click()`);

  await evaluate(`document.querySelector('.foto-order-handle').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}))`);
  const crossRowDraft = await order();
  assert.notDeepEqual(crossRowDraft, initial, 'keyboard arrow moves a card across rows');
  await send('Emulation.setDeviceMetricsOverride', {width: 1366, height: 768, deviceScaleFactor: 1, mobile: false});
  await new Promise(resolve => setTimeout(resolve, 250));
  assert.deepEqual(await order(), crossRowDraft, 'local draft survives responsive resize');
  await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 900, deviceScaleFactor: 1, mobile: false});
  await evaluate(`document.getElementById('fotos-order-reset').click()`);
  assert.deepEqual(await order(), initial);

  const source = await rect('#fotos-grid>[data-gallery-media-id]:first-child .foto-order-handle');
  const targetRect = await rect('#fotos-grid>[data-gallery-media-id]:nth-child(2)');
  assert.equal(await evaluate(`document.elementFromPoint(${source.x + source.width / 2},${source.y + source.height / 2})?.closest('.foto-order-handle')!==null`), true, 'drag handle is the pointer hit target');
  await send('Input.dispatchMouseEvent', {type: 'mousePressed', x: source.x + source.width / 2, y: source.y + source.height / 2, button: 'left', buttons: 1, clickCount: 1});
  await send('Input.dispatchMouseEvent', {type: 'mouseMoved', x: targetRect.right - 4, y: targetRect.top + targetRect.height / 2, button: 'left', buttons: 1});
  await send('Input.dispatchMouseEvent', {type: 'mouseReleased', x: targetRect.right - 4, y: targetRect.top + targetRect.height / 2, button: 'left', buttons: 0, clickCount: 1});
  await new Promise(resolve => setTimeout(resolve, 250));
  assert.deepEqual(await order(), [initial[1], initial[0], ...initial.slice(2)], 'pointer drag reorders visible desktop cards');
  assert.equal(await evaluate(`window.galleryOrderQa.patches.length`), 0, 'pointer drag has no network write');
  await screenshot('gal01-pointer-dirty-1440.png');
  await evaluate(`document.getElementById('fotos-order-save').click()`);
  await until(`window.galleryOrderQa.patches.length===1&&document.getElementById('fotos-order-toolbar').hidden`);
  assert.deepEqual(await order(), [initial[1], initial[0], ...initial.slice(2)], 'successful save updates baseline');

  for (const [width, height] of [[1440, 900], [1366, 768], [820, 1180], [390, 844]]) {
    await send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: width < 500});
    await new Promise(resolve => setTimeout(resolve, 250));
    assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth`), false, `no horizontal overflow at ${width}`);
    if (width === 390) await screenshot('gal01-mobile-390.png');
  }

  const mobileInitial = await order();
  await send('Emulation.setTouchEmulationEnabled', {enabled: true, maxTouchPoints: 1});
  await evaluate(`document.querySelector('#fotos-grid>[data-gallery-media-id]:first-child').scrollIntoView({block:'center'})`);
  await new Promise(resolve => setTimeout(resolve, 250));
  const mobileSource = await rect('#fotos-grid>[data-gallery-media-id]:first-child');
  const mobileTarget = await rect('#fotos-grid>[data-gallery-media-id]:nth-child(2)');
  const touchStart = {x: mobileSource.left + mobileSource.width / 2, y: mobileSource.top + mobileSource.height / 2};
  const touchEnd = {x: mobileTarget.left + mobileTarget.width / 2, y: mobileTarget.top + mobileTarget.height * .75};
  await send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{...touchStart, radiusX: 2, radiusY: 2}]});
  await send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{...touchEnd, radiusX: 2, radiusY: 2}]});
  await send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
  await new Promise(resolve => setTimeout(resolve, 250));
  const mobileDragged = await order();
  assert.notDeepEqual(mobileDragged, mobileInitial, 'touch drag changes the local order');
  assert.deepEqual([...mobileDragged].sort(), [...mobileInitial].sort(), 'touch drag preserves the public asset set');
  assert.equal(await evaluate(`window.galleryOrderQa.patches.length`), 1, 'touch drag remains local');
  await screenshot('gal01-touch-dirty-390.png');
  await evaluate(`document.getElementById('fotos-order-reset').click()`);
  assert.deepEqual(await order(), mobileInitial);

  await evaluate(`document.querySelector('.foto-order-handle').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}));window.galleryOrderQa.mode='fail';document.getElementById('fotos-order-save').click()`);
  await until(`window.galleryOrderQa.patches.length===2&&document.getElementById('fotos-order-msg').classList.contains('is-error')`);
  const failedDraft = await order();
  assert.notDeepEqual(failedDraft, mobileInitial);
  assert.equal(await evaluate(`document.getElementById('fotos-order-toolbar').hidden`), false, 'failed save remains dirty');
  assert.ok((await evaluate(`document.getElementById('fotos-order-msg').textContent`)).includes('No se pudo guardar'));
  await evaluate(`document.getElementById('fotos-order-reset').click()`);
  assert.deepEqual(await order(), mobileInitial, 'failed local draft can be reset');

  assert.deepEqual(runtimeErrors, [], 'no uncaught browser exceptions');
  assert.deepEqual(consoleErrors, [], 'no console errors');
  await writeFile(`${output}/report.json`, JSON.stringify({initial, mouse: true, touch: true, keyboard: true, networkWriteOnDrag: false, failedSavePreservedDraft: true, viewports: [1440, 1366, 820, 390]}, null, 2));
  console.log('GAL01_GALLERY_ORDER_BROWSER=PASS: mouse; touch; keyboard; local draft; reset; explicit save; failed save; responsive; no overflow/errors');
} finally {
  ws?.close();
  chrome.kill('SIGTERM');
  await new Promise(resolve => setTimeout(resolve, 300));
  await rm(profile, {recursive: true, force: true});
}
