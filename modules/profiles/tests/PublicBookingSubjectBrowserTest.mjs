// Local browser integration. Requires Chrome --remote-debugging-port=9348 and UI :8092.
// Mutation requests are fulfilled in-browser with the canonical endpoint shapes.
// No local database, OTP provider, or delivery transport is touched.
import assert from 'node:assert/strict';
import fs from 'node:fs';
const base = process.env.MXMED_TEST_UI || 'http://127.0.0.1:8092';
const cdp = process.env.MXMED_TEST_CDP || 'http://127.0.0.1:9348';
for (const url of [base, cdp]) assert.ok(['127.0.0.1', 'localhost'].includes(new URL(url).hostname), 'local environment only');
const tabs = await (await fetch(cdp + '/json')).json();
const ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
await new Promise(r => ws.addEventListener('open', r, {once: true}));
let id = 0; let slotTakenMode = false, otpFailureMode = false; const pending = new Map(), errors = [], records = [], mutations = [];
const send = (method, params = {}) => new Promise((resolve, reject) => {pending.set(++id, {resolve, reject}); ws.send(JSON.stringify({id, method, params}));});
ws.addEventListener('message', async e => {
  const m = JSON.parse(e.data);
  if (m.id) {const p = pending.get(m.id); pending.delete(m.id); m.error ? p.reject(m.error) : p.resolve(m.result); return;}
  if (m.method === 'Runtime.exceptionThrown') errors.push('browser exception');
  if (m.method !== 'Fetch.requestPaused') return;
  const p = m.params;
  try {
    if (p.request.method === 'POST' && p.request.url.includes('/api/agenda/index.php/public/appointments/reserve')) {
      const payload = JSON.parse(p.request.postData || '{}');
      mutations.push({route: 'reserve', payload});
      if (slotTakenMode) {
        slotTakenMode = false;
        await send('Fetch.fulfillRequest', {requestId: p.requestId, responseCode: 409,
          responseHeaders: [{name: 'Content-Type', value: 'application/json'}],
          body: Buffer.from(JSON.stringify({ok: false, error: 'slot_taken', message: 'slot taken'})).toString('base64')});
        return;
      }
      await send('Fetch.fulfillRequest', {requestId: p.requestId, responseCode: 200,
        responseHeaders: [{name: 'Content-Type', value: 'application/json'}],
        body: Buffer.from(JSON.stringify({ok: true, data: {appointment_id: 'apt-profile-test-' + mutations.length, status: 'pending_otp'}})).toString('base64')});
    } else if (p.request.method === 'POST' && p.request.url.includes('/api/agenda/index.php/public/otp/request')) {
      const payload = JSON.parse(p.request.postData || '{}');
      mutations.push({route: 'otp_request', payload});
      if (otpFailureMode) {
        otpFailureMode = false;
        await send('Fetch.fulfillRequest', {requestId: p.requestId, responseCode: 503,
          responseHeaders: [{name: 'Content-Type', value: 'application/json'}],
          body: Buffer.from(JSON.stringify({ok: false, error: 'otp_delivery_unavailable'})).toString('base64')});
        return;
      }
      await send('Fetch.fulfillRequest', {requestId: p.requestId, responseCode: 200,
        responseHeaders: [{name: 'Content-Type', value: 'application/json'}],
        body: Buffer.from(JSON.stringify({ok: true, data: {otp_id: 7000 + mutations.length, expires_in: 600, delivery_channel: 'email', destination_hint: 'p***@example.test'}})).toString('base64')});
    } else if (p.request.method === 'POST' && p.request.url.includes('/api/agenda/index.php/public/appointments/confirm')) {
      const payload = JSON.parse(p.request.postData || '{}');
      mutations.push({route: 'confirm', payload});
      await send('Fetch.fulfillRequest', {requestId: p.requestId, responseCode: 200,
        responseHeaders: [{name: 'Content-Type', value: 'application/json'}],
        body: Buffer.from(JSON.stringify({ok: true, data: {appointment_id: payload.appointment_id, status: 'confirmed'}})).toString('base64')});
    } else if (!['GET', 'HEAD', 'OPTIONS'].includes(p.request.method)) {
      throw Error('unexpected mutation route');
    } else if (p.responseStatusCode && p.request.url.includes('/profiles/doctor.php')) {
      const body = await send('Fetch.getResponseBody', {requestId: p.requestId});
      const html = (body.base64Encoded ? Buffer.from(body.body, 'base64').toString() : body.body)
        .replace('function openBookingModal(block, state) {', 'function openBookingModal(block, state) { window.__subjectTestState = state;');
      await send('Fetch.fulfillRequest', {requestId: p.requestId, responseCode: p.responseStatusCode,
        responseHeaders: p.responseHeaders.filter(h => !['content-length', 'content-encoding'].includes(h.name.toLowerCase())), body: Buffer.from(html).toString('base64')});
    } else await send('Fetch.continueRequest', {requestId: p.requestId});
  } catch { /* A navigation may cancel an intercepted resource. */ }
});
const ev = async expression => {const r = await send('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true}); assert.ok(!r.exceptionDetails, 'runtime evaluation'); return r.result.value;};
const wait = async expression => {for (let i = 0; i < 200; i++) {if (await ev(expression)) return; await new Promise(r => setTimeout(r, 75));} throw Error('Timed out: ' + expression);};
const click = selector => ev(`document.querySelector(${JSON.stringify(selector)}).click()`);
const step = name => ev(`!document.querySelector('[data-mxpp-booking-step="${name}"]').hidden`);
const key = async (key, code) => {await send('Input.dispatchKeyEvent', {type: 'keyDown', key, windowsVirtualKeyCode: code, ...(key === 'Enter' ? {text: '\r'} : {})}); await send('Input.dispatchKeyEvent', {type: 'keyUp', key, windowsVirtualKeyCode: code});};
const fill = values => ev(`Object.entries(${JSON.stringify(values)}).forEach(([name,value])=>{const e=document.querySelector('[data-mxpp-booking-form]').elements.namedItem(name);e.value=value;e.dispatchEvent(new Event('input',{bubbles:true}));})`);
const fillOtp = code => ev(`(()=>{const e=document.querySelector('[data-mxpp-booking-otp-code]');e.value=${JSON.stringify(code)};e.dispatchEvent(new Event('input',{bubbles:true}));})()`);
const patient = {first_name: 'Paciente', last_name: 'Sintético', second_last_name: '', mobile_phone: '5550000011', email: 'patient@example.test', birth_date: '2000-01-01', gender: 'F', reason: ''};
const booker = {'booker.name': 'Persona Sintética', 'booker.phone': '5550000022', 'booker.email': 'booker@example.test', 'booker.relationship': 'madre'};
const artifacts = process.env.MXMED_TEST_ARTIFACTS;
if (artifacts) fs.mkdirSync(artifacts, {recursive: true});
const shot = async name => {
  if (!artifacts) return;
  // Only the modal is captured; public doctor identity is redacted for evidence.
  await ev(`window.__shotNames=[...document.querySelectorAll('[data-mxpp-booking-doctor]')].map(e=>e.textContent);document.querySelectorAll('[data-mxpp-booking-doctor]').forEach(e=>e.textContent='Profesional de prueba');document.querySelector('.mxpp-dev-plan-switcher')?.style.setProperty('display','none')`);
  await ev(`window.scrollTo({top:0,left:0,behavior:'instant'})`);
  const clip = await ev(`(()=>{const r=document.querySelector('.mxpp-booking-modal__dialog').getBoundingClientRect();return {x:r.x+scrollX,y:r.y+scrollY,width:r.width,height:r.height,scale:1}})()`);
  const result = await send('Page.captureScreenshot', {format: 'png', clip, captureBeyondViewport: false});
  fs.writeFileSync(artifacts + '/' + name + '.png', Buffer.from(result.data, 'base64'), {mode: 0o600});
  await ev(`document.querySelectorAll('[data-mxpp-booking-doctor]').forEach((e,i)=>e.textContent=__shotNames[i]);delete window.__shotNames`);
};
await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable'); await send('Network.setCacheDisabled', {cacheDisabled: true});
await send('Fetch.enable', {patterns: [{urlPattern: '*', requestStage: 'Request'}, {urlPattern: '*/profiles/doctor.php*', requestStage: 'Response'}]});
try {
  for (const [device, width, height] of [['desktop',1440,1100], ['mobile',390,844], ['small',320,740]]) {
    for (const entry of ['direct', 'next']) {
      await send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: device !== 'desktop'});
      await send('Page.navigate', {url: base + '/profiles/doctor.php?doctor_id=1&mxmed_plan=professional&mxmed_theme_preview=mxmed_teal'});
      await wait(`document.querySelectorAll('.mxpp-agenda-compact__day').length===3`);
      if (entry === 'direct') {
        await ev(`document.querySelector('.mxpp-agenda-compact__slot').focus()`); await click('.mxpp-agenda-compact__slot');
      } else {
        await ev(`document.querySelector('[data-mxpp-next-available]').focus()`); await click('[data-mxpp-next-available]');
        await wait(`document.querySelectorAll('.mxpp-next-dialog__result').length===3`); await click('.mxpp-next-dialog__result button');
      }
      assert.ok(await step('confirm'));
      const slot = await ev('JSON.stringify(__subjectTestState.selectedSlot)');
      assert.ok(await ev(`document.querySelector('.mxpp-confirm-specialty').innerText==='ENDOCRINÓLOGA'`));
      await click('[data-mxpp-booking-next]'); assert.ok(await step('subject'));
      assert.equal(await ev(`document.querySelectorAll('[data-mxpp-booking-subject]').length`), 2);
      assert.ok(await ev(`document.activeElement.matches('[data-mxpp-booking-subject="self"]')`));
      assert.ok(await ev(`document.querySelector('.mxpp-booking-modal__dialog').getAttribute('aria-labelledby')==='mxpp-booking-subject-title'`));
      await ev('document.fonts.ready.then(()=>true)'); await shot(device + '-' + entry + '-subject');
      await ev(`document.querySelector('[data-mxpp-booking-step="subject"] footer button:last-child').focus()`); await key('Tab',9);
      assert.ok(await ev(`document.activeElement.matches('[data-mxpp-booking-step="subject"] .mxpp-next-dialog__close')`), 'focus trap');
      await ev(`document.querySelector('[data-mxpp-booking-subject="other"]').focus()`); await key('Enter',13); assert.ok(await step('patient'));
      assert.ok(await ev(`__subjectTestState.booker_is_patient===false&&!document.querySelector('[data-mxpp-booker-fields]').hidden&&document.querySelector('[name="booker.relationship"]').required`));
      assert.ok(await ev(`document.querySelector('#mxpp-booking-patient-title').textContent==='Reserva de cita'&&document.querySelector('#mxpp-booking-patient-section-title').textContent==='Datos del paciente'&&document.querySelector('[data-mxpp-booker-fields] legend').textContent==='Datos de quien solicita'&&document.querySelector('[data-mxpp-booking-submit]').textContent==='Continuar con la reserva'`), 'other-person hierarchy and CTA copy');
      await fill({...patient,...booker,'booker.relationship':''}); await click('[data-mxpp-booking-submit]');
      assert.ok(await step('patient')); assert.ok(await ev(`document.querySelector('[data-mxpp-booking-message]').textContent.includes('relación')&&__subjectTestState.preparedPayload===null`));
      await fill({'booker.relationship':'madre'});
      await click('[data-mxpp-booking-back]'); assert.ok(await step('subject'));
      assert.ok(await ev(`document.activeElement.matches('[data-mxpp-booking-subject="other"]')`));
      await click('[data-mxpp-booking-subject="other"]'); assert.ok(await ev(`document.querySelector('[name="booker.name"]').value==='Persona Sintética'`), 'draft retained');
      await ev(`document.querySelector('[data-mxpp-booker-fields]').scrollIntoView({block:'center',behavior:'instant'})`); await shot(device + '-' + entry + '-other-data');
      assert.ok(await ev(`(()=>{const d=document.querySelector('.mxpp-booking-modal__dialog');return d.scrollWidth<=d.clientWidth&&d.getBoundingClientRect().width<=innerWidth&&document.querySelector('[name="booker.relationship"]').labels.length===1})()`));
      await click('[data-mxpp-booking-submit]'); await wait(`!document.querySelector('[data-mxpp-booking-step="otp"]').hidden`); assert.ok(await ev(`document.activeElement.matches('[data-mxpp-booking-otp-code]')`), 'OTP focus moves to the code input'); await shot(device + '-' + entry + '-otp');
      assert.ok(await ev(`__subjectTestState.preparedPayload.booker_is_patient===false&&__subjectTestState.preparedPayload.booker.relationship==='madre'&&__subjectTestState.preparedPayload.patient.email!==__subjectTestState.preparedPayload.booker.email`));
      await fillOtp('123456'); await click('[data-mxpp-booking-otp-verify]'); await wait(`!document.querySelector('[data-mxpp-booking-step="success"]').hidden`); assert.ok(await ev(`document.activeElement.matches('[data-mxpp-booking-step="success"] button')`), 'success focus moves to its action'); await shot(device + '-' + entry + '-success');
      await key('Escape',27); assert.ok(await ev(`__subjectTestState.preparedPayload===null&&__subjectTestState.booker_is_patient===null`));
      assert.equal(await ev('JSON.stringify(__subjectTestState.selectedSlot)'),slot,'slot preserved on close');
      assert.ok(await ev(`document.activeElement.matches('.mxpp-agenda-compact__slot,[data-mxpp-next-available]')`));
      // Switching to self clears other-person draft; back preserves patient fields.
      await click('.mxpp-agenda-compact__slot'); await click('[data-mxpp-booking-next]'); await click('[data-mxpp-booking-subject="other"]');
      await fill({...patient,...booker}); await click('[data-mxpp-booking-back]'); await click('[data-mxpp-booking-subject="self"]');
      assert.ok(await ev(`__subjectTestState.booker_is_patient===true&&document.querySelector('[data-mxpp-booker-fields]').hidden&&[...document.querySelectorAll('[data-mxpp-booker-fields] input,[data-mxpp-booker-fields] select')].every(e=>!e.required&&e.value==='')`));
      assert.ok(await ev(`document.querySelector('#mxpp-booking-patient-title').textContent==='Reserva de cita'&&document.querySelector('#mxpp-booking-patient-section-title').textContent==='Datos del paciente'&&document.querySelector('[data-mxpp-booker-fields]').hidden&&document.querySelector('[data-mxpp-booking-submit]').textContent==='Continuar con la reserva'`), 'self hierarchy has no requester section');
      await shot(device + '-' + entry + '-self-data'); await click('[data-mxpp-booking-submit]'); await wait(`!document.querySelector('[data-mxpp-booking-step="otp"]').hidden`);
      assert.ok(await ev(`(()=>{const p=__subjectTestState.preparedPayload;return p.booker_is_patient===true&&p.booker.email===p.patient.email&&!('relationship' in p.booker)&&!JSON.stringify(p).includes('booker@example.test')&&p.patient_type==='first_time'})()`));
      await fillOtp('123456'); await click('[data-mxpp-booking-otp-verify]'); await wait(`!document.querySelector('[data-mxpp-booking-step="success"]').hidden`);
      await key('Escape',27);
      // Cancel directly from subject also preserves the selected slot and focus.
      await click('.mxpp-agenda-compact__slot'); await click('[data-mxpp-booking-next]'); await click('[data-mxpp-booking-step="subject"] footer [data-mxpp-booking-close]');
      assert.ok(await ev(`document.querySelector('[data-mxpp-booking-modal]').hidden&&__subjectTestState.selectedSlot!==null`));
      records.push({device,entry,self:true,other:true,relationshipRequired:true,switchCleanup:true,focus:true,noOverflow:true});
      console.log(device,entry,'PASS');
    }
  }
  // A collision stays in the modern profile and never triggers OTP.
  slotTakenMode = true;
  await click('.mxpp-agenda-compact__slot'); await click('[data-mxpp-booking-next]'); await click('[data-mxpp-booking-subject="self"]');
  await fill(patient); await click('[data-mxpp-booking-submit]');
  await wait(`document.querySelector('[data-mxpp-booking-modal]').hidden===true`);
  assert.ok(await ev(`!document.querySelector('[data-mxpp-agenda-alert]').hidden&&document.querySelector('[data-mxpp-agenda-alert]').textContent.includes('acaba de ser reservado')`), 'slot taken is patient-friendly');
  assert.ok(await ev(`__subjectTestState.appointmentId===null&&__subjectTestState.otpId===null`), 'slot taken does not retain or request OTP state');
  // A delivery failure retries the OTP request for the same reservation, without a second reserve.
  const reserveCountBeforeRetry = mutations.filter(m => m.route === 'reserve').length;
  otpFailureMode = true;
  await click('.mxpp-agenda-compact__slot'); await click('[data-mxpp-booking-next]'); await click('[data-mxpp-booking-subject="self"]');
  await fill(patient); await click('[data-mxpp-booking-submit]');
  await wait(`!document.querySelector('[data-mxpp-booking-step="patient"]').hidden&&document.querySelector('[data-mxpp-booking-message]').textContent.includes('No fue posible enviar')`);
  const retainedAppointment = await ev(`__subjectTestState.appointmentId`); assert.ok(retainedAppointment, 'failed delivery preserves pending appointment');
  await click('[data-mxpp-booking-submit]'); await wait(`!document.querySelector('[data-mxpp-booking-step="otp"]').hidden`);
  assert.equal(mutations.filter(m => m.route === 'reserve').length, reserveCountBeforeRetry + 1, 'retry does not create a second reservation');
  await fillOtp('123456'); await click('[data-mxpp-booking-otp-verify]'); await wait(`!document.querySelector('[data-mxpp-booking-step="success"]').hidden`); await key('Escape',27);
  assert.equal(errors.length,0,'no JS exceptions');
  assert.ok(mutations.length >= 24, 'both real endpoint paths are exercised for every desktop/mobile entry');
  const reserves = mutations.filter(m => m.route === 'reserve');
  const otpRequests = mutations.filter(m => m.route === 'otp_request');
  const confirms = mutations.filter(m => m.route === 'confirm');
  assert.ok(reserves.length >= 14 && otpRequests.length >= 14 && confirms.length >= 13, 'all profile endpoint stages were exercised');
  assert.ok(reserves.some(m => m.payload.booker_is_patient === false && m.payload.booker.relationship === 'madre'), 'other-person relationship reaches reserve');
  assert.ok(reserves.some(m => m.payload.booker_is_patient === true && !('relationship' in m.payload.booker)), 'self reserve preserves canonical shape');
  assert.ok(otpRequests.every(m => typeof m.payload.appointment_id === 'string' && m.payload.appointment_id.startsWith('apt-profile-test-')), 'OTP request is appointment-bound');
  assert.ok(confirms.every(m => /^\d{6}$/.test(m.payload.code) && m.payload.appointment_id && m.payload.otp_id), 'confirmation remains bound to appointment and OTP');
  if (artifacts) fs.writeFileSync(artifacts+'/proof.json',JSON.stringify({records,mutationRequests:mutations.length,endpointSequence:true,jsErrors:0},null,2),{mode:0o600});
  console.log('PublicBookingSubjectBrowserTest PASS: endpoint wiring mocked locally');
} finally {await send('Fetch.disable'); ws.close();}
