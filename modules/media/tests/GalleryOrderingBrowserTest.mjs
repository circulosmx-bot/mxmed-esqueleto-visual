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
  await evaluate(`window.galleryOrderQa={patches:[],deletes:[],uploads:[],mode:'ok'};window.galleryOrderRealFetch=window.fetch;window.galleryOrderRealUpload=window.mxmedMediaReview.upload;window.mxmedMediaReview.upload=async(purpose,file)=>window.galleryOrderQa.uploads.push({purpose,name:file.name});window.fetch=async(url,options={})=>{const method=options.method||'GET';if(String(url).includes('/api/media/gallery.php')&&method==='PATCH'){const ids=JSON.parse(options.body).media_ids;window.galleryOrderQa.patches.push(ids);if(window.galleryOrderQa.mode==='fail')return Response.json({ok:false,error:'gallery_unavailable',message:'No se pudo guardar el orden.'},{status:503});const live=await (await window.galleryOrderRealFetch('/api/media/gallery.php',{credentials:'same-origin'})).json(),byId=new Map(live.data.images.map(item=>[item.media_id,item]));return Response.json({ok:true,data:{images:ids.map(id=>byId.get(id)),csrf_token:live.data.csrf_token}});}if(String(url).includes('/api/media/gallery.php')&&method==='DELETE'){window.galleryOrderQa.deletes.push(String(url));const live=await (await window.galleryOrderRealFetch('/api/media/gallery.php',{credentials:'same-origin'})).json();return Response.json(live);}return window.galleryOrderRealFetch(url,options);};`);
  const initial = await order();
  assert.equal(initial.length, 8);
  assert.equal(await evaluate(`document.getElementById('fotos-order-toolbar').hidden`), true, 'save action starts hidden');
  assert.equal(await evaluate(`document.querySelectorAll('.foto-order-handle').length`), 0, 'visible drag patches removed');
  assert.ok(await evaluate(`[...document.querySelectorAll('#fotos-grid>[data-gallery-media-id]')].every((card,index)=>card.tabIndex===0&&card.getAttribute('aria-label').includes('Fotografía '+(index+1)+' de 8'))`), 'focusable cards expose ordered accessible labels');
  assert.equal(await evaluate(`new Set([...document.querySelectorAll('#fotos-grid>[data-gallery-media-id]')].map(card=>Math.round(card.getBoundingClientRect().top))).size`), 1, 'eight photos occupy one desktop row at 1440');
  assert.equal(await evaluate(`getComputedStyle(document.querySelector('#fotos-grid>[data-gallery-media-id]')).cursor`), 'grab');

  await evaluate(`(()=>{const transfer=new DataTransfer();transfer.items.add(new File(['qa'],'external-qa.png',{type:'image/png'}));document.getElementById('fotos-drop').dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:transfer}))})()`);
  await until(`window.galleryOrderQa.uploads.length===1&&!document.getElementById('fotos-drop').hasAttribute('aria-busy')`);
  assert.deepEqual(await evaluate(`window.galleryOrderQa.uploads[0]`), {purpose: 'gallery', name: 'external-qa.png'}, 'external file drop still uses gallery upload flow');
  assert.deepEqual(await order(), initial, 'external upload drop does not initiate internal reorder');

  await evaluate(`document.querySelector('#fotos-grid>[data-gallery-media-id] .foto-x').click()`);
  await until(`window.galleryOrderQa.deletes.length===1&&!document.getElementById('fotos-drop').hasAttribute('aria-busy')`);
  assert.deepEqual(await order(), initial, 'delete control does not initiate reorder');

  await evaluate(`document.querySelector('#fotos-grid>[data-gallery-media-id]').dispatchEvent(new KeyboardEvent('keydown',{key:'End',bubbles:true,cancelable:true}))`);
  assert.deepEqual(await order(), [...initial.slice(1), initial[0]], 'keyboard moves first to last');
  assert.equal(await evaluate(`window.galleryOrderQa.patches.length`), 0, 'keyboard reorder is local only');
  assert.equal(await evaluate(`document.getElementById('fotos-order-toolbar').hidden`), false);
  await evaluate(`document.getElementById('fotos-order-reset').click()`);
  assert.deepEqual(await order(), initial, 'reset restores saved baseline');
  assert.equal(await evaluate(`document.getElementById('fotos-order-toolbar').hidden`), true);

  await evaluate(`([...document.querySelectorAll('#fotos-grid>[data-gallery-media-id]')].at(-1)).dispatchEvent(new KeyboardEvent('keydown',{key:'Home',bubbles:true,cancelable:true}))`);
  assert.deepEqual(await order(), [initial.at(-1), ...initial.slice(0, -1)], 'keyboard moves last to first');
  assert.equal(await evaluate(`window.galleryOrderQa.patches.length`), 0, 'reverse keyboard reorder is local only');
  await evaluate(`document.getElementById('fotos-order-reset').click()`);

  await evaluate(`document.querySelector('#fotos-grid>[data-gallery-media-id]').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}))`);
  const crossRowDraft = await order();
  assert.notDeepEqual(crossRowDraft, initial, 'keyboard arrow moves a card across rows');
  await send('Emulation.setDeviceMetricsOverride', {width: 1366, height: 768, deviceScaleFactor: 1, mobile: false});
  await new Promise(resolve => setTimeout(resolve, 250));
  assert.deepEqual(await order(), crossRowDraft, 'local draft survives responsive resize');
  assert.equal(await evaluate(`new Set([...document.querySelectorAll('#fotos-grid>[data-gallery-media-id]')].map(card=>Math.round(card.getBoundingClientRect().top))).size`), 1, 'eight photos occupy one desktop row at 1366');
  await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 900, deviceScaleFactor: 1, mobile: false});
  await evaluate(`document.getElementById('fotos-order-reset').click()`);
  assert.deepEqual(await order(), initial);

  const source = await rect('#fotos-grid>[data-gallery-media-id]:first-child');
  const targetRect = await rect('#fotos-grid>[data-gallery-media-id]:nth-child(2)');
  await send('Input.dispatchMouseEvent', {type: 'mousePressed', x: source.x + source.width / 2, y: source.y + source.height / 2, button: 'left', buttons: 1, clickCount: 1});
  await send('Input.dispatchMouseEvent', {type: 'mouseMoved', x: targetRect.right - 4, y: targetRect.top + targetRect.height / 2, button: 'left', buttons: 1});
  await send('Input.dispatchMouseEvent', {type: 'mouseReleased', x: targetRect.right - 4, y: targetRect.top + targetRect.height / 2, button: 'left', buttons: 0, clickCount: 1});
  await new Promise(resolve => setTimeout(resolve, 250));
  assert.deepEqual(await order(), [initial[1], initial[0], ...initial.slice(2)], 'pointer drag from photo center reorders desktop cards');
  assert.equal(await evaluate(`window.galleryOrderQa.patches.length`), 0, 'pointer drag has no network write');
  await evaluate(`document.getElementById('fotos-order-reset').click()`);
  const alternateSource = await rect('#fotos-grid>[data-gallery-media-id]:first-child');
  const alternateTarget = await rect('#fotos-grid>[data-gallery-media-id]:nth-child(2)');
  await send('Input.dispatchMouseEvent', {type: 'mousePressed', x: alternateSource.left + alternateSource.width * .65, y: alternateSource.top + alternateSource.height * .72, button: 'left', buttons: 1, clickCount: 1});
  await send('Input.dispatchMouseEvent', {type: 'mouseMoved', x: alternateTarget.right - 5, y: alternateTarget.top + alternateTarget.height / 2, button: 'left', buttons: 1});
  await send('Input.dispatchMouseEvent', {type: 'mouseReleased', x: alternateTarget.right - 5, y: alternateTarget.top + alternateTarget.height / 2, button: 'left', buttons: 0, clickCount: 1});
  await new Promise(resolve => setTimeout(resolve, 250));
  assert.deepEqual(await order(), [initial[1], initial[0], ...initial.slice(2)], 'pointer drag from a second non-action area reorders cards');
  await screenshot('gal01-pointer-dirty-1440.png');
  await evaluate(`document.getElementById('fotos-order-save').click()`);
  await until(`window.galleryOrderQa.patches.length===1&&document.getElementById('fotos-order-toolbar').hidden`);
  assert.deepEqual(await order(), [initial[1], initial[0], ...initial.slice(2)], 'successful save updates baseline');

  for (const [width, height] of [[1440, 900], [1366, 768], [820, 1180], [390, 844]]) {
    await send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: width < 500});
    await new Promise(resolve => setTimeout(resolve, 250));
    assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth`), false, `no horizontal overflow at ${width}`);
    const columns = await evaluate(`(()=>{const cards=[...document.querySelectorAll('#fotos-grid>[data-gallery-media-id]')],top=Math.round(cards[0].getBoundingClientRect().top);return cards.filter(card=>Math.round(card.getBoundingClientRect().top)===top).length})()`);
    assert.equal(columns, width >= 1366 ? 8 : width === 820 ? 4 : 2, `responsive gallery columns at ${width}`);
    if (width === 1366) await screenshot('gal01-full-card-grid-1366.png');
    if (width === 390) await screenshot('gal01-mobile-390.png');
  }

  const mobileInitial = await order();
  await send('Emulation.setTouchEmulationEnabled', {enabled: true, maxTouchPoints: 1});
  await evaluate(`document.querySelector('#fotos-grid>[data-gallery-media-id]:first-child').scrollIntoView({block:'center'})`);
  await new Promise(resolve => setTimeout(resolve, 250));
  let mobileSource = await rect('#fotos-grid>[data-gallery-media-id]:first-child');
  let quickTouch = {x: mobileSource.left + mobileSource.width / 2, y: mobileSource.top + mobileSource.height / 2};
  await send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{...quickTouch, radiusX: 2, radiusY: 2}]});
  await send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{...quickTouch, y: quickTouch.y - 48, radiusX: 2, radiusY: 2}]});
  await send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
  await new Promise(resolve => setTimeout(resolve, 300));
  assert.deepEqual(await order(), mobileInitial, 'touch scroll movement before hold delay does not reorder');

  await evaluate(`document.querySelector('#fotos-grid>[data-gallery-media-id]:first-child').scrollIntoView({block:'center'})`);
  await new Promise(resolve => setTimeout(resolve, 250));
  mobileSource = await rect('#fotos-grid>[data-gallery-media-id]:first-child');
  const mobileTarget = await rect('#fotos-grid>[data-gallery-media-id]:nth-child(2)');
  const touchStart = {x: mobileSource.left + mobileSource.width / 2, y: mobileSource.top + mobileSource.height / 2};
  const touchEnd = {x: mobileTarget.left + mobileTarget.width / 2, y: mobileTarget.top + mobileTarget.height * .75};
  await send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{...touchStart, radiusX: 2, radiusY: 2}]});
  await new Promise(resolve => setTimeout(resolve, 300));
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

  await evaluate(`document.querySelector('#fotos-grid>[data-gallery-media-id]').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}));window.galleryOrderQa.mode='fail';document.getElementById('fotos-order-save').click()`);
  await until(`window.galleryOrderQa.patches.length===2&&document.getElementById('fotos-order-msg').classList.contains('is-error')`);
  const failedDraft = await order();
  assert.notDeepEqual(failedDraft, mobileInitial);
  assert.equal(await evaluate(`document.getElementById('fotos-order-toolbar').hidden`), false, 'failed save remains dirty');
  assert.ok((await evaluate(`document.getElementById('fotos-order-msg').textContent`)).includes('No se pudo guardar'));
  await evaluate(`document.getElementById('fotos-order-reset').click()`);
  assert.deepEqual(await order(), mobileInitial, 'failed local draft can be reset');

  await send('Emulation.setTouchEmulationEnabled', {enabled: false});
  await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 900, deviceScaleFactor: 1, mobile: false});
  await evaluate(`(()=>{const grid=document.getElementById('fotos-grid'),cards=[...grid.querySelectorAll(':scope>[data-gallery-media-id]')],anchor=grid.querySelector(':scope>[data-review-candidate]');cards.forEach((card,index)=>{const clone=card.cloneNode(true);clone.dataset.galleryMediaId='00000000-0000-4000-8000-'+String(index+1).padStart(12,'0');grid.insertBefore(clone,anchor)});document.getElementById('fotos-count').textContent='16'})()`);
  await new Promise(resolve => setTimeout(resolve, 250));
  assert.deepEqual(await evaluate(`(()=>{const cards=[...document.querySelectorAll('#fotos-grid>[data-gallery-media-id]')],tops=cards.map(card=>Math.round(card.getBoundingClientRect().top));return [...new Set(tops)].map(top=>tops.filter(value=>value===top).length)})()`), [8, 8], 'sixteen photos occupy exactly two rows of eight');
  const beforeBoundaryMove = await order();
  await evaluate(`document.querySelectorAll('#fotos-grid>[data-gallery-media-id]')[7].dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true,cancelable:true}))`);
  const afterBoundaryMove = await order();
  assert.equal(afterBoundaryMove[7], beforeBoundaryMove[8], 'position 8 can move across the row boundary');
  assert.equal(afterBoundaryMove[8], beforeBoundaryMove[7], 'position 9 receives the prior position 8 item');
  await screenshot('gal01-grid-16-1440.png');

  assert.deepEqual(runtimeErrors, [], 'no uncaught browser exceptions');
  assert.deepEqual(consoleErrors, [], 'no console errors');
  await writeFile(`${output}/report.json`, JSON.stringify({initial, fullCardMouse: true, touchHoldDelayMs: 250, touchMovementThreshold: 10, touchScrollPreserved: true, deleteStartsDrag: false, visibleHandle: false, keyboard: true, networkWriteOnDrag: false, failedSavePreservedDraft: true, columns: {1440: 8, 1366: 8, 820: 4, 390: 2}, sixteenPhotoRows: [8, 8]}, null, 2));
  console.log('GAL01_FULL_CARD_BROWSER=PASS: full-card mouse; touch hold; scroll safety; delete exclusion; keyboard; 8/4/2 columns; 16=8x2; local draft; no overflow/errors');
} finally {
  ws?.close();
  chrome.kill('SIGTERM');
  await new Promise(resolve => setTimeout(resolve, 300));
  await rm(profile, {recursive: true, force: true});
}
