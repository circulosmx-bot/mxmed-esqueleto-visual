// Read-only browser QA for public CONSULTA contact actions.
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
try {
  await send('Page.enable'); await send('Runtime.enable');
  for (const [width, height] of [[1440, 900], [1366, 768], [390, 844], [320, 740]]) {
    await send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: width < 600});
    await send('Page.navigate', {url: `${base}/profiles/doctor.php?doctor_id=1&mxmed_plan=professional`});
    await wait(`document.querySelector('[data-mxpp-profile-view-trigger="consultation"]')`);
    await evaluate(`document.querySelector('[data-mxpp-profile-view-trigger="consultation"]').click()`);
    await wait(`document.querySelector('.mxpp-content-panel__closure .mxpp-content-panel__contact-prompt')`);
    const first = await evaluate(`(()=>{const actions=document.querySelector('[data-mxpp-office-actions]:not([hidden])');const prompt=actions.querySelector('.mxpp-content-panel__contact-prompt');const phone=actions.querySelector('.mxpp-content-panel__contact-line--phone');const reserve=document.querySelector('.mxpp-content-panel__reserve');const box=actions.getBoundingClientRect();return {promptTag:prompt.tagName,promptAnchor:!!prompt.closest('a'),phoneHref:phone?.getAttribute('href'),phoneText:phone?.innerText,whatsapp:!!actions.querySelector('.mxpp-content-panel__contact-line--whatsapp'),reserveText:reserve?.innerText,overflow:box.left < -1 || box.right > innerWidth + 1};})()`);
    assert.deepEqual(first, {promptTag: 'P', promptAnchor: false, phoneHref: 'tel:4499788888', phoneText: 'call\nTel. Consultorio: 449 978 8888', whatsapp: false, reserveText: 'Reserva tu cita ahora', overflow: false});
    await evaluate(`document.querySelectorAll('[data-mxpp-consultorio-tab]')[1].click()`);
    await wait(`document.querySelector('[data-mxpp-office-actions="mxpp-consultorio-panel-2"]:not([hidden])')`);
    const second = await evaluate(`(()=>{const actions=document.querySelector('[data-mxpp-office-actions]:not([hidden])');return {phoneHref:actions.querySelector('.mxpp-content-panel__contact-line--phone')?.getAttribute('href'),whatsappHref:actions.querySelector('.mxpp-content-panel__contact-line--whatsapp')?.getAttribute('href'),hiddenFirst:document.querySelector('[data-mxpp-office-actions="mxpp-consultorio-panel-1"]').hidden,overflow:actions.scrollWidth > actions.clientWidth + 1};})()`);
    assert.deepEqual(second, {phoneHref: 'tel:4491532005', whatsappHref: 'https://wa.me/44921300796', hiddenFirst: true, overflow: false});
  }
  console.log('PASS: static prompt, selected-office phone/WhatsApp targets, reserve CTA, 1440/1366/390/320');
} finally {
  ws.close(); await fetch(`${cdp}/json/close/${tab.id}`);
}
