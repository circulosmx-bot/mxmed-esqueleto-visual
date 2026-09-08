// Read-only visual QA for the public "VER DOMICILIO" theme exceptions.
import assert from 'node:assert/strict';

const cdp = process.env.CDP_URL || 'http://127.0.0.1:9348';
const base = process.env.PUBLIC_URL || 'http://127.0.0.1:8092';
const tab = await (await fetch(`${cdp}/json/new?about:blank`, {method: 'PUT'})).json();
const ws = new WebSocket(tab.webSocketDebuggerUrl);
await new Promise(resolve => ws.addEventListener('open', resolve, {once: true}));
let id = 0;
const pending = new Map();
const send = (method, params = {}) => new Promise((resolve, reject) => {
  pending.set(++id, {resolve, reject});
  ws.send(JSON.stringify({id, method, params}));
});
ws.addEventListener('message', event => {
  const message = JSON.parse(event.data);
  if (!message.id) return;
  const handler = pending.get(message.id);
  pending.delete(message.id);
  message.error ? handler.reject(message.error) : handler.resolve(message.result);
});
const evaluate = async expression => {
  const result = await send('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true});
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
const matrix = [
  ['mxmed_teal', '#022f50', 1440, 900],
  ['soft_lavender', '#022f50', 1366, 768],
  ['dusty_pink', '#022f50', 390, 844],
  ['soft_gold', '#022f50', 320, 740],
  ['clinical_sky', '#022f50', 1440, 900],
  ['clinical_light_sky', '#022f50', 1366, 768],
  ['royal_blue', '#022f50', 390, 844],
  ['medical_blue', '#01afb7', 1366, 768],
  ['warm_ivory', '#01afb7', 390, 844],
  ['clinical_pink', '#01afb7', 320, 740],
];
try {
  await send('Page.enable');
  await send('Runtime.enable');
  const results = [];
  for (const [theme, expected, width, height] of matrix) {
    await send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: width < 600});
    await send('Page.navigate', {url: `${base}/profiles/doctor.php?doctor_id=1&mxmed_plan=professional&mxmed_theme_preview=${theme}`});
    await wait(`document.querySelector('[data-mxpp-profile-view-trigger="consultation"]')`);
    await evaluate(`document.querySelector('[data-mxpp-profile-view-trigger="consultation"]').click()`);
    await wait(`document.querySelector('.mxpp-content-panel__return') && document.querySelector('.mxpp-content-panel__return').offsetParent !== null`);
    const actual = await evaluate(`(()=>{const location=document.querySelector('.mxpp-content-panel__return');const reserve=document.querySelector('.mxpp-content-panel__reserve');const cs=getComputedStyle(location);const r=location.getBoundingClientRect();return {theme:document.body.dataset.profileTheme,locationBg:cs.backgroundColor,locationColor:cs.color,transition:cs.transitionProperty,reserveBg:getComputedStyle(reserve).backgroundColor,overflow:r.left < -1 || r.right > innerWidth + 1 || location.scrollWidth > location.clientWidth};})()`);
    assert.equal(actual.theme, theme);
    assert.equal(actual.locationBg, `rgb(${parseInt(expected.slice(1, 3), 16)}, ${parseInt(expected.slice(3, 5), 16)}, ${parseInt(expected.slice(5, 7), 16)})`);
    assert.equal(actual.locationColor, 'rgb(255, 255, 255)');
    assert.match(actual.transition, /background-color/);
    assert.match(actual.transition, /border-color/);
    assert.equal(actual.reserveBg, 'rgb(1, 175, 183)');
    assert.equal(actual.overflow, false);
    await evaluate(`document.querySelector('.mxpp-content-panel__return').click()`);
    await wait(`document.querySelector('[data-mxpp-content-panel]')?.dataset.profileView === 'location'`);
    results.push(actual);
  }
  console.log(`PASS: ${results.length} theme/viewports, location CTA colors and return action`);
} finally {
  ws.close();
  await fetch(`${cdp}/json/close/${tab.id}`);
}
