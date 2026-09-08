// Read-only visual QA for the coordinated public hero action offset.
import assert from 'node:assert/strict';
import {mkdir,writeFile} from 'node:fs/promises';
await mkdir('/tmp/mxmed-avatar-qa',{recursive:true});

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
    await send('Page.navigate', {url: `${base}/profiles/doctor.php?doctor_id=1&mxmed_plan=free`});
    await wait(`document.querySelector('.mxpp-avatar')`);
    await wait(`document.querySelector('img.mxpp-avatar')?.complete`);
    assert.ok(await evaluate(`document.querySelector('img.mxpp-avatar').src.endsWith('/dr-female.png')`));
    for(const gender of ['male','female']){
      const before=await evaluate(`(()=>{const r=document.querySelector('.mxpp-avatar').getBoundingClientRect();return [r.x,r.y,r.width,r.height]})()`);
      await evaluate(`document.querySelector('.mxpp-avatar').src='/assets/img/doctors/avatars/dr-${gender}.png'`);
      await wait(`document.querySelector('.mxpp-avatar').complete && document.querySelector('.mxpp-avatar').naturalWidth===1122`);
      const result=await evaluate(`(()=>{const e=document.querySelector('.mxpp-avatar');const r=e.getBoundingClientRect();return {box:[r.x,r.y,r.width,r.height],fit:getComputedStyle(e).objectFit,ratio:r.width/r.height,overflow:r.left<0||r.right>innerWidth,natural:[e.naturalWidth,e.naturalHeight]}})()`);
      assert.deepEqual(result.box,before);
      assert.equal(result.fit,'cover'); assert.equal(result.overflow,false);
      assert.ok(Math.abs(result.ratio-.8)<.01);
      assert.deepEqual(result.natural,[1122,1402]);
      await evaluate(`document.querySelector('.mxpp-avatar').scrollIntoView({block:'center',behavior:'instant'})`);
      const shot=await send('Page.captureScreenshot',{format:'png'});
      await writeFile(`/tmp/mxmed-avatar-qa/${width}-${gender}.png`,Buffer.from(shot.data,'base64'));
      console.log(width,gender,result);
    }
    await send('Page.navigate',{url:`${base}/profiles/doctor.php?doctor_id=1&mxmed_plan=professional`});
    await wait(`document.querySelector('.mxpp-avatar--placeholder')`);

  }
  console.log('PASS: live Free female rendering, paid neutral fallback, both static portraits at four sizes; male image substituted only in DOM, no database writes');
} finally {
  ws.close(); await fetch(`${cdp}/json/close/${tab.id}`);
}
