// Run under DoctorProfilePhotoHttpTest with PROFILE_PHOTO_BROWSER_QA=1 and a local CDP browser.
import assert from 'node:assert/strict';
import {mkdir,writeFile} from 'node:fs/promises';

const cdp = process.env.CDP_URL || 'http://127.0.0.1:9348';
const base = process.env.PUBLIC_URL || 'http://127.0.0.1:8091';
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
  for (let i = 0; i < 600; i++) {
    if (await evaluate(expression)) return;
    await new Promise(resolve => setTimeout(resolve, 75));
  }
  console.log(await evaluate(`({ready:document.readyState,status:document.querySelector('#mxpi-photo-status')?.textContent,script:!!document.querySelector('script[src*=\"profile-photo.js\"]')})`));
  throw Error(`Timed out: ${expression}`);
};


try {
await send('Page.enable');await send('Network.enable');
await send('Network.setCookie',{name:'PHPSESSID',value:process.env.PHOTO_QA_SESSION,url:base});
await send('Page.navigate',{url:base+'/index.html'});
await wait(`document.querySelector('#mxpi-photo-preview img')?.getAttribute('src')?.includes('/api/media/')`);
await evaluate('window.__beforePhotoReload=true');await send('Page.reload');
await wait(`!window.__beforePhotoReload && document.readyState==='complete' && document.querySelector('#mxpi-photo-preview img')?.getAttribute('src')?.includes('/api/media/')`);
assert.equal(await evaluate(`document.querySelector('#mxpi-photo-input').accept`),'image/jpeg,image/png,image/webp');
assert.equal(await evaluate(`document.querySelector('#mxpi-photo-input').disabled`),false);
await evaluate(`document.querySelector('[data-panel="p-info"]').click();document.querySelector('#t-info-datos-tab').click()`);
await send('DOM.enable');const root=await send('DOM.getDocument');const input=await send('DOM.querySelector',{nodeId:root.root.nodeId,selector:'#mxpi-photo-input'});
await send('DOM.setFileInputFiles',{nodeId:input.nodeId,files:[process.cwd()+'/assets/img/doctors/avatars/dr-male.png']});
await wait(`document.querySelector('#mxpi-photo-status').textContent==='Guardada'`);
await wait(`document.querySelector('#mxpi-photo-preview img').complete && document.querySelector('#mxpi-photo-preview img').naturalWidth>0`);
await evaluate(`document.querySelector('#mxpi-photo-control').scrollIntoView({block:'center',behavior:'instant'})`);
await mkdir('/tmp/mxmed-profile-photo-qa',{recursive:true});const adminShot=await send('Page.captureScreenshot',{format:'png'});await writeFile('/tmp/mxmed-profile-photo-qa/admin.png',Buffer.from(adminShot.data,'base64'));
const results=[];
for(const [width,height] of [[1440,900],[1366,768],[390,844],[320,740]]) {
await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<600});
await send('Page.navigate',{url:base+'/profiles/doctor.php?doctor_id=1&mxmed_plan=standard'});
await wait(`document.querySelector('.mxpp-avatar')?.complete && document.querySelector('.mxpp-avatar')?.naturalWidth>0 && document.fonts.status==='loaded'`);
const g=await evaluate(`(()=>{const im=document.querySelector('.mxpp-avatar'),a=im.getBoundingClientRect(),b=document.querySelector('[data-gallery-open]').getBoundingClientRect();return {real:im.src.includes('/api/media/index.php/public/'),fit:getComputedStyle(im).objectFit,transform:getComputedStyle(im).transform,ratio:a.width/a.height,width:a.width,overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,right:a.right-b.right,bottom:a.bottom-b.bottom,text:document.querySelector('[data-gallery-open]').textContent.trim()}})()`);
assert.equal(g.real,true);assert.equal(g.fit,'cover');assert.equal(g.transform,'none');assert.ok(Math.abs(g.ratio-.8)<.01);assert.equal(g.width,width<600?180:205);assert.equal(g.overflow,false);assert.ok(Math.abs(g.right-8)<1);assert.ok(Math.abs(g.bottom-8)<1);
await evaluate(`document.querySelector('[data-gallery-open]').scrollIntoView({block:'center',behavior:'instant'})`);
await mkdir('/tmp/mxmed-profile-photo-qa',{recursive:true});const shot=await send('Page.captureScreenshot',{format:'png'});await writeFile('/tmp/mxmed-profile-photo-qa/'+width+'.png',Buffer.from(shot.data,'base64'));
results.push({width,height,...g});
}
await writeFile('/tmp/mxmed-profile-photo-qa/results.json',JSON.stringify(results,null,2));console.log('PASS: admin normal reload/server preview, four public viewport geometries and unmirrored real photo');
} finally {ws.close();await fetch(`${cdp}/json/close/${tab.id}`);}
