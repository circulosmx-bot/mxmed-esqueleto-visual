// Read-only runtime QA. Requires local public server and Chrome with --remote-debugging-port=9348.
// Does not submit reservations or request OTP. Override origins/output via environment variables.
import fs from 'node:fs';
import assert from 'node:assert/strict';
const cdp = process.env.CDP_URL || 'http://127.0.0.1:9348';
const base = process.env.PUBLIC_URL || 'http://127.0.0.1:8092';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-schedule-conflict-qa';
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
// Execute the actual editor functions with an in-memory save adapter: no schedule/appointment writes.
const source=fs.readFileSync(new URL('../../../assets/js/app.js',import.meta.url),'utf8');
const section=(from,to)=>source.slice(source.indexOf(from),source.indexOf(to,source.indexOf(from)));
const code=[section('    const parseTimeParts =','    const isTurnoValidFromState'),section('    const hasValidWindow =','    const isAfternoonStart'),section('    const timeToMinutes =','    const getTurnType'),section('    const collectScheduleBlocksByConsultorio =','    const removeScheduleTurn'),section('    const buildPayloadDays =','    const updateConsultorioScheduleUI')].join('\n');
const report=[];
try{
 await send('Page.enable');await send('Runtime.enable');
 for(const [name,width,height] of [['desktop',1366,768],['narrow',320,740]]){
  await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
  const frame=(await send('Page.getFrameTree')).frameTree.frame.id;
  await send('Page.setDocumentContent',{frameId:frame,html:`<html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="${base}/assets/css/style.css"><style>body{padding:16px;font-family:Arial;color:#113d59}.sched-card{max-width:700px;padding:16px;border:1px solid #ddd;border-radius:10px}.alert-danger{background:#fff0f0;color:#842029;padding:16px;border:1px solid #edb7b7;border-radius:8px}.btn{padding:10px;border:1px solid #999;border-radius:6px}*{box-sizing:border-box}</style></head><body><section class="sched-card"><h2>Horario de atención · MAC Norte</h2><table><tbody id="editor"><tr><td><button id="edit">Editar horario</button></td></tr></tbody></table><div id="note"></div><div id="sync"></div></section></body></html>`});
  await ev(`(()=>{
    const DAYS=[{key:'mon',weekday:1,label:'Lunes'},{key:'tue',weekday:2,label:'Martes'}];
    const ARTIFICIAL_EMPTY_TIMES=new Set(['12:30']),EMPTY_TIME_DISPLAY='--:--';
    let saving=false,loading=false,activeScheduleConsultorioId='3',scheduleValidationSnapshot={};
    const activeBody=document.querySelector('#editor'),saveTimersByConsultorio=new Map();
    const row=(s,e,act=true)=>({act,a1:s,b1:e,a2:'',b2:''});
    const scheduleDoctorByConsultorio={'1':'1','2':'1','3':'1','9':'9'};
    const scheduleStateByConsultorio={'2':{mon:row('09:00','12:00')},'3':{mon:row('11:00','14:00')}};
    const getActiveConsultorioId=()=> '3', getActiveConsultorioScheduleState=()=>scheduleStateByConsultorio['3'];
    const getScheduleStateForConsultorio=id=>scheduleStateByConsultorio[id];
    const resolveScheduleContext=()=>({doctorId:'1',consultorioId:'3'});
    const normalizeConsultorioScheduleState=()=>{};
    const ensureValidationNoteNode=()=>document.querySelector('#note');
    const showValidationNote=text=>{document.querySelector('#note').textContent=text;};
    const showNote=text=>{document.querySelector('#sync').textContent=text;};
    const resolveConsultorioLabel=id=>({'1':'Torre Médica CMQ','2':'Star Médica','3':'MAC Norte'}[id]||id);
    const savedPayloads=[];
    let saves=0, response={ok:true,json:{ok:true}};
    const apiSaveSchedule=async payload=>{saves++;savedPayloads.push(payload);return response;};
    ${code}
    window.fixture={row,state:scheduleStateByConsultorio,queue:queuePersist,persist:persistSchedule,render:renderScheduleValidationMessage,
      conflicts:()=>conflictsForScheduleSave('3'),saves:()=>saves,payloads:()=>savedPayloads,setResponse:r=>response=r};
  })()`);
  await ev(`fixture.queue('3');fixture.queue('3');fixture.queue('3')`);
  await new Promise(r=>setTimeout(r,550));
  assert.equal(await ev('fixture.saves()'),0);
  assert.equal(await ev(`document.querySelectorAll('#note li').length`),1);
  assert.ok((await ev(`document.querySelector('#note').textContent`)).includes('de 11:00 a 12:00'));
  await ev(`fixture.state['1']={mon:fixture.row('10:00','13:00')};fixture.state['9']={mon:fixture.row('09:00','14:00')};fixture.queue('3')`);
  assert.equal(await ev(`document.querySelectorAll('#note li').length`),2);
  await screenshot(name);
  const geometry=await ev(`(()=>{const n=document.querySelector('#note');return {scroll:n.scrollWidth,width:n.clientWidth,body:document.body.scrollWidth,viewport:innerWidth}})()`);
  assert.ok(geometry.scroll<=geometry.width+1 && geometry.body<=width+1,JSON.stringify(geometry));
  await ev(`document.querySelector('#note button').click()`);
  assert.equal(await ev('document.activeElement.id'),'edit');
  await ev(`fixture.state['3'].mon=fixture.row('14:00','15:00');fixture.queue('3')`);
  await new Promise(r=>setTimeout(r,550));assert.equal(await ev('fixture.saves()'),1);
  // Server rejects a stale client draft even when its locally loaded schedules don't overlap.
  await ev(`fixture.setResponse({ok:false,json:{ok:false,error:'conflict',meta:{conflicts:[{consultorio_id_conflict:'2',weekday:1,overlap_window:{start_time:'14:00:00',end_time:'14:30:00'},conflict_scope:'other_consultorio'}]}}});fixture.queue('3')`);
  await new Promise(r=>setTimeout(r,550));assert.equal(await ev('fixture.saves()'),2);
  assert.ok((await ev(`document.querySelector('#note').textContent`)).includes('14:00 a 14:30'));
  await new Promise(r=>setTimeout(r,650));assert.equal(await ev('fixture.saves()'),2);
  assert.equal(await ev(`fixture.state['3'].mon.a1`),'14:00');
  await ev(`fixture.setResponse(new Promise(resolve=>window.releaseSave=resolve));fixture.state['3'].mon=fixture.row('15:00','16:00');fixture.queue('3')`);
  await new Promise(r=>setTimeout(r,500));
  await ev(`fixture.state['3'].mon=fixture.row('16:00','17:00');fixture.queue('3')`);
  await new Promise(r=>setTimeout(r,500));
  await ev(`fixture.setResponse({ok:true,json:{ok:true}});window.releaseSave({ok:false,json:{error:'conflict'}})`);
  await new Promise(r=>setTimeout(r,550));
  assert.equal(await ev('fixture.saves()'),4);
  assert.equal(await ev(`fixture.payloads().at(-1).days[0].windows[0].start_time`),'16:00');
  assert.equal(await ev(`fixture.state['3'].mon.a1`),'16:00');
  report.push({name,geometry,multiple:true,actualIntersection:true,backFocus:true,invalidSaves:0,correctedSaves:1,staleServerRejection:true,noRetryLoop:true});
 }
 fs.writeFileSync(`${output}/result.json`,JSON.stringify({report,adapter:'in-memory saves; real production editor functions',databaseWrites:0},null,2));
 console.log('PASS: immediate actual overlap, all conflicts, stopped autosave, corrected save, stale server rejection, no retry, editable draft, desktop/narrow and back focus');
}finally{ws.close();await fetch(`${cdp}/json/close/${tab.id}`);}
