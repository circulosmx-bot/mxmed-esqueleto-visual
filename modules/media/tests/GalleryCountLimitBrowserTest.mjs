// GAL01D browser QA. All gallery mutations are intercepted in memory; Director data stays read-only.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-gal01d-browser';
const profile = await mkdtemp('/tmp/mxmed-gal01d-chrome-');
await mkdir(output, {recursive: true});

const chrome = spawn(
  process.env.MXMED_QA_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ['--headless=new', '--no-first-run', '--no-default-browser-check', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`],
  {stdio: 'ignore'}
);

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
  const send = (method, params = {}, sid = session) => new Promise((resolve, reject) => {
    const id = ++sequence;
    pending.set(id, {resolve, reject, method});
    ws.send(JSON.stringify({id, method, params, ...(sid ? {sessionId: sid} : {})}));
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
    await new Promise(resolve => setTimeout(resolve, 200));
    const result = await send('Page.captureScreenshot', {format: 'png'});
    await writeFile(`${output}/${name}`, Buffer.from(result.data, 'base64'));
  };

  await send('Page.addScriptToEvaluateOnNewDocument', {source: `(() => {
    const originalFetch = window.fetch;
    const response = (data, status = 200) => Promise.resolve(Response.json(data, {status}));
    const tiny = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="120" height="90"%3E%3Crect width="120" height="90" fill="%2381e2df"/%3E%3C/svg%3E';
    const initial = Number(new URLSearchParams(location.search).get('gal_public') || 0);
    const images = Array.from({length: initial}, (_, index) => ({
      media_id: '00000000-0000-4000-8000-' + String(index + 1).padStart(12, '0'),
      public_url: tiny,
      alt_text: 'Fotografía ' + (index + 1)
    }));
    window.__gal01d = {images, pending: [], posts: 0, deletes: 0};
    window.fetch = (resource, options = {}) => {
      const raw = typeof resource === 'string' ? resource : resource.url;
      const url = new URL(raw, location.href);
      const method = String(options.method || 'GET').toUpperCase();
      if (url.pathname === '/api/media/gallery.php') {
        if (method === 'DELETE') {
          const id = url.searchParams.get('media_id');
          const index = __gal01d.images.findIndex(item => item.media_id === id);
          if (index >= 0) __gal01d.images.splice(index, 1);
          __gal01d.deletes += 1;
        }
        return response({ok: true, data: {csrf_token: 'gallery-csrf', images: __gal01d.images}});
      }
      if (url.pathname === '/api/media/gallery-review-candidate.php') {
        if (method === 'GET') return response({ok: true, data: {csrf_token: 'candidate-csrf'}});
        __gal01d.posts += 1;
        if (__gal01d.images.length + __gal01d.pending.length >= 16) {
          return response({ok: false, error: 'gallery_limit_reached'}, 409);
        }
        const id = '10000000-0000-4000-8000-' + String(__gal01d.pending.length + 1).padStart(12, '0');
        __gal01d.pending.push({id, purpose: 'DOCTOR_GALLERY', state: 'OPEN', preview_url: tiny});
        return response({ok: true, data: {created_submission_id: id}});
      }
      if (url.pathname === '/api/media/owner-review.php') {
        return response({ok: true, data: {items: __gal01d.pending}});
      }
      if (url.pathname === '/api/media/review-batch-submit.php') {
        return response({ok: true, data: {csrf: 'batch-csrf', can_submit_now: true}});
      }
      return originalFetch(resource, options);
    };
  })();`});

  const open = async (publicCount, width = 1440, height = 900) => {
    await send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: width < 500});
    await send('Page.navigate', {url: `${base}/index.html?review=gal01d&qa_tools=hide&gal_public=${publicCount}`});
    await until(`document.readyState==='complete' && typeof showPanel==='function' && document.getElementById('fotos-count')?.textContent==='${publicCount}' && !document.getElementById('fotos-drop')?.hasAttribute('aria-busy')`);
    await evaluate(`showPanel('p-info');document.getElementById('t-info-fotos-tab').click()`);
    await until(`document.getElementById('t-info-fotos').classList.contains('active') && !document.getElementById('fotos-drop').hasAttribute('aria-busy')`);
  };
  const counter = () => evaluate(`document.querySelector('.fotos-counter').textContent.replace(/\\s+/g,' ').trim()`);
  const picker = async number => {
    await evaluate(`(() => {const transfer=new DataTransfer();for(let index=0;index<${number};index+=1)transfer.items.add(new File(['image'], 'picker-'+index+'.png', {type:'image/png'}));const input=document.getElementById('fotos-input');input.files=transfer.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await until(`window.__gal01d.posts===${number} && !document.getElementById('fotos-drop').hasAttribute('aria-busy')`);
  };
  const externalDrop = async number => {
    await evaluate(`(() => {const transfer=new DataTransfer();for(let index=0;index<${number};index+=1)transfer.items.add(new File(['image'], 'drop-'+index+'.png', {type:'image/png'}));document.getElementById('fotos-drop').dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:transfer}));})()`);
    await until(`window.__gal01d.posts===${number} && !document.getElementById('fotos-drop').hasAttribute('aria-busy')`);
  };

  for (const [number, label] of [[0, '0 Fotografías'], [1, '1 Fotografía'], [8, '8 Fotografías'], [16, '16 Fotografías']]) {
    await open(number);
    assert.equal(await counter(), label);
    assert.equal(await evaluate(`document.querySelector('.fotos-counter').textContent.includes('de 16')`), false);
  }

  await open(15);
  await picker(1);
  assert.equal(await evaluate(`window.__gal01d.pending.length`), 1, '15 + 1 is accepted');
  assert.notEqual(await evaluate(`document.getElementById('fotos-msg').textContent`), 'Límite máximo de fotografías: 16');

  await open(15);
  await picker(2);
  assert.equal(await evaluate(`window.__gal01d.pending.length`), 1, 'existing sequential policy accepts the available slot');
  assert.equal(await evaluate(`document.getElementById('fotos-msg').textContent`), 'Límite máximo de fotografías: 16');

  await open(8);
  await picker(8);
  assert.equal(await evaluate(`window.__gal01d.pending.length`), 8, '8 + 8 is accepted');

  await open(8);
  await picker(9);
  assert.equal(await evaluate(`window.__gal01d.pending.length`), 8, '8 + 9 preserves sequential partial acceptance');
  assert.equal(await evaluate(`document.getElementById('fotos-msg').textContent`), 'Límite máximo de fotografías: 16');

  await open(16);
  await picker(1);
  assert.equal(await evaluate(`window.__gal01d.pending.length`), 0);
  assert.equal(await evaluate(`document.getElementById('fotos-msg').textContent`), 'Límite máximo de fotografías: 16');

  await open(16);
  await externalDrop(1);
  assert.equal(await evaluate(`document.getElementById('fotos-msg').textContent`), 'Límite máximo de fotografías: 16');

  await open(16);
  await evaluate(`document.querySelector('#fotos-grid > .foto-item .foto-x').click()`);
  await until(`document.getElementById('fotos-count').textContent==='15' && !document.getElementById('fotos-drop').hasAttribute('aria-busy')`);
  assert.equal(await counter(), '15 Fotografías');
  assert.equal(await evaluate(`window.__gal01d.deletes`), 1);

  for (const [width, height] of [[1440, 900], [1366, 768], [820, 1180], [390, 844]]) {
    await open(8, width, height);
    assert.equal(await counter(), '8 Fotografías');
    assert.equal(await evaluate(`document.documentElement.scrollWidth>innerWidth+1`), false);
    await screenshot(`gal01d-${width}.png`);
  }

  assert.deepEqual(runtimeErrors, [], 'no uncaught browser exceptions');
  assert.deepEqual(consoleErrors, [], 'no console errors');
  await writeFile(`${output}/report.json`, JSON.stringify({
    formats: ['0 Fotografías', '1 Fotografía', '8 Fotografías', '16 Fotografías'],
    max: 16,
    excessPolicy: 'sequential_partial_acceptance',
    filePickerFeedback: true,
    externalDropFeedback: true,
    deleteCountRefresh: true,
    viewports: [1440, 1366, 820, 390]
  }, null, 2));
  console.log('GAL01D_BROWSER=PASS: dynamic count; limit hidden; exact picker/drop excess feedback; sequential partial acceptance; delete refresh; four viewports; no overflow/errors');
} finally {
  ws?.close();
  chrome.kill('SIGTERM');
  await new Promise(resolve => setTimeout(resolve, 400));
  await rm(profile, {recursive: true, force: true, maxRetries: 3, retryDelay: 200});
}
