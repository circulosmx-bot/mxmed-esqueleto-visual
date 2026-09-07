// Read-only runtime QA. Requires local public server and Chrome with --remote-debugging-port=9348.
// Does not submit reservations or request OTP. Override origins/output via environment variables.
import fs from 'node:fs';
import assert from 'node:assert/strict';
const cdp = process.env.CDP_URL || 'http://127.0.0.1:9348';
const base = process.env.PUBLIC_URL || 'http://127.0.0.1:8092';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-daily-qa';
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
const report=[];
try {
  await send('Page.enable'); await send('Page.bringToFront'); await send('Runtime.enable'); await send('Network.enable');
  await send('Network.setCacheDisabled',{cacheDisabled:true});
  await send('Page.addScriptToEvaluateOnNewDocument',{source:`
    Object.defineProperty(window,'MxmedPublicDailyModal',{configurable:true,set(fn){
      Object.defineProperty(window,'MxmedPublicDailyModal',{value:(block,booking)=>{
        window.__qaDaily=fn(block,{...booking,choose(slot){window.__qaChosen=slot;booking.choose(slot)}});return window.__qaDaily;
      }});
    }});
  `});
  const ready=`document.querySelector('.mxpp-daily-dialog').open && !document.querySelector('[data-daily-results]').hasAttribute('aria-busy')`;
  const chooseDate=`document.querySelector('[data-full-day="2026-09-08"]')`;
  for(const [name,width,height] of [['desktop-1440',1440,900],['desktop-1366',1366,768],['mobile-390',390,844],['mobile-320',320,740]]) {
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate',{url:`${base}/profiles/doctor.php?doctor_id=1&mxmed_plan=professional`});
    await wait(`document.querySelectorAll('.mxpp-agenda-compact__day').length===3`);
    await wait(`!!${chooseDate}`);
    const normal=await ev(`Array.from(document.querySelectorAll('.mxpp-agenda-compact__day')).map(d=>({date:d.querySelector('h3').textContent,count:d.querySelectorAll('.mxpp-agenda-compact__slot').length,all:d.querySelector('[data-full-day]')?.textContent}))`);
    assert.ok(normal.every(d=>d.count>0 && d.count<=10));
    const previewGeometry=await ev(`Array.from(document.querySelectorAll('.mxpp-agenda-compact__slot')).map(e=>({width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height}))`);
    assert.ok(previewGeometry.every(r=>r.width===previewGeometry[0].width && r.height===previewGeometry[0].height && r.height>=44),'sparse/full chip geometry');
    assert.equal(await ev(`getComputedStyle(${chooseDate}).marginLeft !== '0px'`),true,'secondary action right aligned');
    assert.equal(await ev(`${chooseDate}.textContent`),'Ver más citas');
    await ev(`${chooseDate}.scrollIntoView({block:'center',behavior:'instant'})`); await screenshot(name+'-preview');
    await ev(`${chooseDate}.focus()`);
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',text:'\r',unmodifiedText:'\r',windowsVirtualKeyCode:13});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',windowsVirtualKeyCode:13});
    await wait(ready);
    assert.equal(await ev(`document.querySelectorAll('.mxpp-daily-slot').length`),18);
    assert.equal(await ev(`document.querySelector('#mxpp-daily-title').textContent`),'Horarios disponibles');
    assert.equal(await ev(`document.querySelector('[data-daily-status]').textContent`),'');
    assert.equal(await ev(`!!document.querySelector('.mxpp-daily-dialog header [data-daily-next]')`),true);
    const chip=await ev(`(()=>{const e=document.querySelector('.mxpp-daily-slot'),r=e.getBoundingClientRect();return {width:r.width,height:r.height}})()`);
    assert.deepEqual(chip,previewGeometry[0],'same preview/modal chip size');
    assert.equal(await ev(`document.activeElement.getAttribute('aria-label')`),'Cerrar');
    const rows=await ev(`Array.from(document.querySelectorAll('.mxpp-daily-slot')).map(e=>e.textContent)`);
    assert.ok(rows[0].includes('09:00 h') && rows[0].includes('CMQ'));
    assert.ok(rows[10].includes('16:00 h') && rows[10].includes('Star Médica'));
    assert.ok(!rows[10].includes('16:30'));
    const geo=await ev(`(()=>{const d=document.querySelector('.mxpp-daily-dialog'),r=d.getBoundingClientRect();return {left:r.left,right:r.right,client:d.clientWidth,scroll:d.scrollWidth,slots:Array.from(d.querySelectorAll('.mxpp-daily-slot')).map(e=>({height:e.getBoundingClientRect().height,client:e.clientWidth,scroll:e.scrollWidth}))}})()`);
    assert.ok(geo.left>=0 && geo.right<=width+1 && geo.scroll<=geo.client+1);
    assert.ok(geo.slots.every(s=>s.height>=44 && s.scroll<=s.client+1));
    await screenshot(name+'-modal');
    // Native dialog Escape returns focus to the exact opener; no reservation is made.
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',windowsVirtualKeyCode:27});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',windowsVirtualKeyCode:27});
    await wait(`!document.querySelector('.mxpp-daily-dialog').open`);
    assert.equal(await ev(`document.activeElement.dataset.fullDay`),'2026-09-08');
    await ev(`${chooseDate}.click()`); await wait(ready);
    const url=await ev('location.href');
    const identity=await ev('window.__qaPageIdentity=Math.random()');
    await ev(`document.querySelector('[data-daily-next]').focus()`);
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',text:'\r',unmodifiedText:'\r',windowsVirtualKeyCode:13});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',windowsVirtualKeyCode:13}); await wait(ready);
    assert.ok((await ev(`document.querySelector('#mxpp-daily-date').textContent`)).includes('9 de septiembre'));
    await ev(`document.querySelector('[data-daily-prev]').click()`); await wait(ready);
    assert.deepEqual(await ev(`Array.from(document.querySelectorAll('.mxpp-daily-slot')).map(e=>e.textContent)`),rows);
    for(let i=0;i<5;i++){await ev(`document.querySelector('[data-daily-next]').click()`);await wait(ready);}
    assert.equal(await ev(`document.querySelector('[data-daily-status]').textContent`),'No hay horarios disponibles para este día.');
    assert.equal(await ev(`document.querySelector('[data-daily-next]').disabled`),false);
    assert.equal(await ev('location.href'),url);
    assert.equal(await ev('window.__qaPageIdentity'),identity);
    await screenshot(name+'-empty');
    for(let i=0;i<5;i++){await ev(`document.querySelector('[data-daily-prev]').click()`);await wait(ready);}
    await ev(`document.querySelectorAll('.mxpp-daily-slot')[10].click()`);
    await wait(`document.querySelector('[data-mxpp-booking-modal]').hidden===false`);
    const selected=await ev('window.__qaChosen');
    assert.equal(selected.consultorio_id,'2');assert.equal(selected.start_at,'2026-09-08 16:00:00');assert.equal(selected.end_at,'2026-09-08 16:30:00');
    assert.equal(await ev(`document.querySelector('[data-mxpp-confirm-office]').textContent`),'Star Médica');
    await screenshot(name+'-booking');
    await ev(`document.querySelector('[data-mxpp-booking-modal] [data-mxpp-booking-close]').click()`);
    await wait(`document.querySelector('[data-mxpp-booking-modal]').hidden`);
    await ev(`${chooseDate}.closest('article').querySelector('.mxpp-agenda-compact__slot').click()`);
    await wait(`document.querySelector('[data-mxpp-booking-modal]').hidden===false`);
    assert.equal(await ev(`document.querySelector('[data-mxpp-confirm-office]').textContent`),'Torre Médica CMQ');
    await ev(`document.querySelector('[data-mxpp-booking-modal] [data-mxpp-booking-close]').click()`);
    await ev(`document.querySelector('[data-mxpp-agenda-next]').click()`);
    await wait(`Array.from(document.querySelectorAll('.mxpp-agenda-compact__day h3')).some(e=>e.textContent.includes('12 de septiembre'))`);
    assert.equal(await ev(`Array.from(document.querySelectorAll('.mxpp-agenda-compact__day')).find(e=>e.querySelector('h3').textContent.includes('12 de septiembre')).querySelectorAll('[data-full-day]').length`),0);
    await ev(`document.querySelector('[data-mxpp-agenda-next]').click()`);
    await wait(`document.querySelector('.mxpp-agenda-compact__slot')?.dataset.slotDate==='2026-09-14'`);
    const afterEmpty=await ev(`Array.from(document.querySelectorAll('.mxpp-agenda-compact__day')).map(d=>d.querySelector('.mxpp-agenda-compact__slot')?.dataset.slotDate)`);
    const availablePage=await ev(`fetch('/api/agenda/index.php/public/availability?doctor_id=1&mode=global_days&date=2026-09-13&mxmed_plan=professional').then(r=>r.json()).then(p=>p.data.days.map(d=>d.date))`);
    assert.deepEqual(afterEmpty,availablePage,'main pagination follows available-day authority');
    assert.equal(afterEmpty.length,3);assert.ok(!afterEmpty.includes('2026-09-13'),'empty Sunday skipped');
    await ev(`document.querySelector('[data-mxpp-agenda-prev]').click()`);
    await ev(`document.querySelector('[data-mxpp-agenda-prev]').click()`);
    assert.deepEqual(await ev(`Array.from(document.querySelectorAll('.mxpp-agenda-compact__day')).map(d=>({date:d.querySelector('h3').textContent,count:d.querySelectorAll('.mxpp-agenda-compact__slot').length,all:d.querySelector('[data-full-day]')?.textContent}))`),normal);
    if(name==='desktop-1440') {
      const bounds=await ev(`fetch('/api/agenda/index.php/public/availability?doctor_id=1&mode=day&date=2026-09-08&mxmed_plan=professional').then(r=>r.json()).then(p=>p.meta)`);
      await ev(`window.__qaDaily.open(${JSON.stringify(bounds.min_date)},${chooseDate})`); await wait(ready);
      assert.equal(await ev(`document.querySelector('[data-daily-prev]').disabled`),true);
      await ev(`document.querySelector('.mxpp-daily-dialog [data-daily-close]').click()`);
      await ev(`window.__qaDaily.open(${JSON.stringify(bounds.max_date)},${chooseDate})`); await wait(ready);
      assert.equal(await ev(`document.querySelector('[data-daily-next]').disabled`),true);
      await ev(`document.querySelector('.mxpp-daily-dialog [data-daily-close]').click()`);
    }
    await ev(`document.querySelector('[data-mxpp-next-available]').click()`);
    await wait(`document.querySelectorAll('.mxpp-next-dialog__result').length===3`);
    report.push({name,normal,total:rows.length,rows,geometry:geo,selected,emptyDay:true,modalDayNavigation:true,mainNavigation:true,keyboardFocus:true,nextAvailable:true});
  }
  await send('Page.navigate',{url:`${base}/profiles/doctor.php?doctor_id=1`});
  await wait(`document.querySelector('#mxpp-dev-plan-select')?.value==='free'`);
  assert.equal(await ev(`!!document.querySelector('[data-mxpp-agenda-compact]')`),false);
  assert.equal(calls.filter(r=>r.method!=='GET').length,0);assert.deepEqual(errors,[]);
  const apiCalls=calls.filter(r=>r.url.includes('/public/availability')).map(r=>new URL(r.url).search);
  assert.ok(apiCalls.some(q=>q.includes('mode=day') && q.includes('date=2026-09-13')));
  fs.writeFileSync(`${output}/result.json`,JSON.stringify({report,freeGating:true,getOnly:true,jsErrors:errors,apiCalls},null,2));
  console.log('PASS: 18 total/10 preview, full-day 18, global result-owned booking from modal and card, day/main navigation, empty, Free, dedicated next, keyboard and four responsive sizes; GET only');
}finally{ws.close();await fetch(`${cdp}/json/close/${tab.id}`);}
