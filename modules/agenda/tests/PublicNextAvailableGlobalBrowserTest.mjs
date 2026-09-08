// Read-only runtime QA. Requires local public server and Chrome with --remote-debugging-port=9348.
// Does not submit reservations or request OTP. Override origins/output via environment variables.
import fs from 'node:fs';
import assert from 'node:assert/strict';
const cdp = process.env.CDP_URL || 'http://127.0.0.1:9348';
const base = process.env.PUBLIC_URL || 'http://127.0.0.1:8092';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-global-next-qa';
fs.mkdirSync(output, {recursive: true});
const tab = await (await fetch(`${cdp}/json/new?about:blank`, {method: 'PUT'})).json();
const ws = new WebSocket(tab.webSocketDebuggerUrl);
await new Promise(r => ws.addEventListener('open', r, {once: true}));
let id = 0;
const pending = new Map(), calls = [], errors = [];
const send = (method, params = {}) => new Promise((resolve, reject) => {
  pending.set(++id, {resolve, reject}); ws.send(JSON.stringify({id, method, params}));
});
ws.addEventListener('message', e => {
  const m = JSON.parse(e.data);
  if (m.id) { const p = pending.get(m.id); pending.delete(m.id); m.error ? p.reject(m.error) : p.resolve(m.result); }
  if (m.method === 'Network.requestWillBeSent') calls.push(m.params.request);
  if (m.method === 'Runtime.exceptionThrown') errors.push(m.params.exceptionDetails);
});
const ev = async expression => {
  const r = await send('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true});
  if (r.exceptionDetails) throw Error(JSON.stringify(r.exceptionDetails));
  return r.result.value;
};
const wait = async expression => {
  for (let i = 0; i < 400; i++) {
    if (await ev(expression)) return;
    await new Promise(r => setTimeout(r, 75));
  }
  throw Error('Timeout: ' + expression);
};
const screenshot = async name => {
  await ev('document.fonts.ready.then(()=>true)');
  const result = await send('Page.captureScreenshot', {format: 'png'});
  fs.writeFileSync(`${output}/${name}.png`, Buffer.from(result.data, 'base64'));
};
const report = [];
try {
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  await send('Network.setCacheDisabled', {cacheDisabled: true});
  await send('Page.addScriptToEvaluateOnNewDocument', {source: `
    // Freeze only the search origin to reproduce Friday's last three slots; API data stays live.
    Object.defineProperty(window, 'MxmedPublicGlobalAvailability', {configurable:true, set(fn) {
      Object.defineProperty(window, 'MxmedPublicGlobalAvailability', {value: options => fn({...options, now:new Date('2026-09-12T00:29:00Z')})});
    }});
    Object.defineProperty(window, 'MxmedPublicNextAvailable', {configurable:true, set(fn) {
      Object.defineProperty(window, 'MxmedPublicNextAvailable', {value: (block, booking) => fn(block, {...booking,
        choose(slot) { window.__qaChosen = slot; booking.choose(slot); }
      })});
    }});
  `});
  const rows = `Array.from(document.querySelectorAll('.mxpp-next-dialog__result')).map(e=>e.innerText)`;
  const ready = `document.querySelectorAll('.mxpp-next-dialog__result').length===3 && !document.querySelector('.mxpp-next-dialog__results').hasAttribute('aria-busy')`;
  for (const [name, width, height] of [['desktop-1440',1440,900],['desktop-1366',1366,768],['mobile',390,844]]) {
    await send('Emulation.setDeviceMetricsOverride', {width,height,deviceScaleFactor:1,mobile:name==='mobile'});
    await send('Page.navigate', {url: `${base}/profiles/doctor.php?doctor_id=1&mxmed_plan=professional`});
    await wait(`document.querySelectorAll('.mxpp-agenda-compact__day').length===3`);
    const normal = await ev(`document.querySelector('.mxpp-agenda-compact__days')?.innerText || Array.from(document.querySelectorAll('.mxpp-agenda-compact__day')).map(e=>e.innerText).join('|')`);
    await ev(`document.querySelector('[data-mxpp-next-available]').click()`);
    await wait(ready);
    const first = await ev(rows);
    const availabilityLegend = await ev(`(()=>{const note=document.querySelector('.mx-ag-next-slots-info-note');return {text:note.innerText,scroll:note.scrollWidth,client:note.clientWidth};})()`);
    assert.equal(availabilityLegend.text,'La disponibilidad se actualiza continuamente. El horario queda reservado únicamente al completar la confirmación de la cita.');
    assert.ok(availabilityLegend.scroll <= availabilityLegend.client + 1,'availability legend has no horizontal overflow');
    assert.equal(await ev(`document.querySelectorAll('.mxpp-next-dialog .mx-ag-next-slot-main .mx-ag-next-slot-label').length`),0);
    assert.equal(await ev(`Array.from(document.querySelectorAll('.mxpp-next-dialog__result')).some(e=>e.innerText.toLowerCase().includes('fecha y hora'))`),false);
    assert.ok(await ev(`parseFloat(getComputedStyle(document.querySelector('.mxpp-next-dialog .mx-ag-next-slot-date')).fontSize) >= 20.7`),'next-available date is increased by twenty percent');
    assert.ok(first.every(r=>r.toLowerCase().includes('star médica') && r.toLowerCase().includes('11 de septiembre')), JSON.stringify(first));
    assert.ok(first[0].includes('18:30 h') && first[2].includes('19:30 h'));
    assert.equal(await ev(`document.querySelector('dialog.mxpp-next-dialog .mxpp-next-dialog__nav button:nth-child(2)').disabled`),false);
    await ev(`document.querySelector('dialog.mxpp-next-dialog .mxpp-next-dialog__nav button:nth-child(2)').click()`);
    await wait(ready);
    const second = await ev(rows);
    assert.ok(second.every(r=>r.toLowerCase().includes('mac norte') && r.toLowerCase().includes('12 de septiembre')), JSON.stringify(second));
    assert.ok(second[0].includes('09:00 h'));
    const geometry = await ev(`(()=>{const d=document.querySelector('.mxpp-next-dialog'), r=d.getBoundingClientRect();
      return {width:r.width,left:r.left,right:r.right,viewport:innerWidth,overflow:d.scrollWidth>d.clientWidth+1,
        cards:Array.from(d.querySelectorAll('.mxpp-next-dialog__result')).map(e=>({scroll:e.scrollWidth,width:e.clientWidth}))};})()`);
    assert.ok(geometry.left>=0 && geometry.right<=width+1 && !geometry.overflow);
    assert.ok(geometry.cards.every(c=>c.scroll<=c.width+1));
    await screenshot(name);
    await ev(`document.querySelector('dialog.mxpp-next-dialog .mxpp-next-dialog__nav button:first-child').click()`); await wait(ready);
    assert.deepEqual(await ev(rows), first);
    await ev(`document.querySelector('dialog.mxpp-next-dialog .mxpp-next-dialog__nav button:nth-child(2)').click()`); await wait(ready);
    assert.deepEqual(await ev(rows), second);
    for (let i=0;i<3;i++) {
      await ev(`document.querySelector('dialog.mxpp-next-dialog .mxpp-next-dialog__nav button:nth-child(2)').click()`); await wait(ready);
    }
    const mixed = await ev(rows);
    assert.ok(mixed[0].includes('12 de Septiembre') && mixed[0].includes('13:30 h'),JSON.stringify(mixed));
    assert.ok(mixed[1].includes('14 de Septiembre') && mixed[1].includes('09:00 h') && mixed[1].includes('TORRE MÉDICA CMQ'),JSON.stringify(mixed));
    await screenshot(name+'-mixed');
    for (let i=0;i<3;i++) {
      await ev(`document.querySelector('dialog.mxpp-next-dialog .mxpp-next-dialog__nav button:first-child').click()`); await wait(ready);
    }
    assert.deepEqual(await ev(rows), second);

    const currentNormal = await ev(`document.querySelector('.mxpp-agenda-compact__days')?.innerText || Array.from(document.querySelectorAll('.mxpp-agenda-compact__day')).map(e=>e.innerText).join('|')`);
    assert.equal(currentNormal, normal);
    await ev(`document.querySelector('.mxpp-next-dialog__result button').click()`);
    await wait(`document.querySelector('[data-mxpp-booking-modal]').hidden===false`);
    const selected = await ev('window.__qaChosen');
    assert.equal(selected.consultorio_id,'3'); assert.equal(selected.start_at,'2026-09-12 09:00:00');
    assert.equal(selected.end_at,'2026-09-12 09:30:00');
    assert.equal(await ev(`document.querySelector('[data-mxpp-confirm-office]').textContent`),'MAC Norte');
    assert.equal(await ev(`document.querySelector('[data-mxpp-booking-time]').textContent`),'09:00');
    await screenshot(name+'-booking');
    await ev(`document.querySelector('[data-mxpp-booking-next]').click(); document.querySelector('[data-mxpp-booking-subject="self"]').click()`);
    assert.equal(await ev(`document.querySelector('[data-mxpp-booking-modal]').hidden`),false);
    report.push({name, first, second, mixed, selected, geometry, normalAgendaUnchanged:true, previousNext:true, bookingHandoff:true});
  }
  await send('Page.navigate', {url: `${base}/profiles/doctor.php?doctor_id=1`});
  await wait(`document.querySelector('#mxpp-dev-plan-select')?.value==='free'`);
  assert.equal(await ev(`!!document.querySelector('[data-mxpp-next-available]')`),false);
  assert.equal(await ev(`!!document.querySelector('[data-mxpp-agenda-compact]')`),false);
  const availabilityCalls = calls.filter(r=>r.url.includes('/public/availability')).map(r=>new URL(r.url));
  assert.ok(availabilityCalls.some(u=>!u.searchParams.has('consultorio_id'))); // normal global Agenda still sends no single-office restriction
  assert.ok(availabilityCalls.some(u=>u.searchParams.get('consultorio_id')==='3'));
  assert.equal(calls.filter(r=>r.method!=='GET').length,0);
  assert.deepEqual(errors,[]);
  fs.writeFileSync(`${output}/result.json`,JSON.stringify({report,freeGating:true,requestsGetOnly:true,jsErrors:errors,availabilityRequests:availabilityCalls.map(u=>u.pathname+u.search)},null,2));
  console.log('PASS: live Friday → Saturday, global previous/next, MAC Norte booking handoff, normal agenda unchanged, Free gating, 1440x900/1366x768/390x844, GET only');
} finally { ws.close(); await fetch(`${cdp}/json/close/${tab.id}`); }
