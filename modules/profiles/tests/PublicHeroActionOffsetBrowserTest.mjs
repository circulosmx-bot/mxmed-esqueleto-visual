// Read-only visual QA for the coordinated public hero action offset.
import assert from 'node:assert/strict';

const cdp = process.env.CDP_URL || 'http://127.0.0.1:9348';
const base = process.env.PUBLIC_URL || 'http://127.0.0.1:8092';
const tab = await (await fetch(`${cdp}/json/new?about:blank`, {method: 'PUT'})).json();
const ws = new WebSocket(tab.webSocketDebuggerUrl);
await new Promise(resolve => ws.addEventListener('open', resolve, {once: true}));
let id = 0;
const pending = new Map();
const send = (method, params = {}) => new Promise((resolve, reject) => {
  pending.set(++id, {resolve, reject}); ws.send(JSON.stringify({id, method, params}));
});
ws.addEventListener('message', event => {
  const message = JSON.parse(event.data);
  if (!message.id) return;
  const handler = pending.get(message.id); pending.delete(message.id);
  message.error ? handler.reject(message.error) : handler.resolve(message.result);
});
const evaluate = async expression => {
  const result = await send('Runtime.evaluate', {expression, returnByValue: true});
  if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
  return result.result.value;
};
const wait = async expression => {
  for (let i = 0; i < 120; i++) {
    if (await evaluate(expression)) return;
    await new Promise(resolve => setTimeout(resolve, 75));
  }
  throw Error(`Timed out: ${expression}`);
};
try {
  await send('Page.enable'); await send('Runtime.enable');
  for (const [width, height] of [[1440, 900], [1366, 768], [390, 844], [320, 740]]) {
    await send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: width < 600});
    await send('Page.navigate', {url: `${base}/profiles/doctor.php?doctor_id=1&mxmed_plan=professional`});
    await wait(`document.querySelector('.mxpp-hero-brand-actions')`);
    const layout = await evaluate(`(()=>{const group=document.querySelector('.mxpp-hero-brand-actions');const logo=group.querySelector('.mxpp-physician-logo');const about=group.querySelector('[data-mxpp-profile-view-trigger="about"]');const consultation=group.querySelector('[data-mxpp-profile-view-trigger="consultation"]');const panel=document.querySelector('[data-mxpp-content-panel]');const r=group.getBoundingClientRect();return {transform:getComputedStyle(group).transform,children:[logo,about,consultation].every(Boolean),overflow:r.left < -1 || r.right > innerWidth + 1,overlap:r.bottom > panel.getBoundingClientRect().top};})()`);
    assert.equal(layout.transform, 'matrix(1, 0, 0, 1, 0, 6)');
    assert.equal(layout.children, true);
    assert.equal(layout.overflow, false);
    assert.equal(layout.overlap, false);
    await evaluate(`document.querySelector('[data-mxpp-profile-view-trigger="about"]').click()`);
    assert.equal(await evaluate(`document.querySelector('[data-mxpp-content-panel]').dataset.profileView`), 'about');
    await evaluate(`document.querySelector('[data-mxpp-profile-view-trigger="consultation"]').click()`);
    assert.equal(await evaluate(`document.querySelector('[data-mxpp-content-panel]').dataset.profileView`), 'consultation');
  }
  console.log('PASS: 6px grouped offset, no overlap/overflow, actions intact at 1440/1366/390/320');
} finally {
  ws.close(); await fetch(`${cdp}/json/close/${tab.id}`);
}
