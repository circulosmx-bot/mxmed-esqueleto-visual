// Real canonical gallery fixtures; removed after QA.
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFile,mkdir,writeFile} from 'node:fs/promises';

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

const php=code=>execFileSync('php',['-r',code],{encoding:'utf8'}).trim();
const session=php("session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'gallery-browser-qa','doctor_id'=>'1'];echo session_id();session_write_close();");
const api=async(method='GET',body=null,token='')=>{
  const response=await fetch(base+'/api/media/gallery.php',{method,body,headers:{Cookie:'PHPSESSID='+session,'X-Gallery-CSRF':token}});
  const data=await response.json();assert.equal(response.status,200,JSON.stringify(data));return data.data;
};
const ids=[];
await mkdir('/tmp/mxmed-gallery-qa',{recursive:true});
try{
  const initial=await api();assert.equal(initial.images.length,0,'QA requires profile 1 gallery empty; never overwrite existing images');
  await send('Page.enable');await send('Network.enable');
  await send('Network.setCookie',{name:'PHPSESSID',value:session,url:base});
  const navigate=async(plan='standard')=>{
    await send('Page.navigate',{url:base+'/profiles/doctor.php?doctor_id=1&mxmed_plan='+plan});
    await wait(`document.querySelector('.mxpp-avatar')`);
  };
  await navigate();assert.equal(await evaluate(`!!document.querySelector('[data-gallery-open]')`),false);
  const upload=async()=>{
    const bytes=await readFile('assets/img/doctors/avatars/dr-female.png');
    const f=new FormData();f.append('image',new Blob([bytes],{type:'image/png'}),'fixture.png');
    const result=await api('POST',f,initial.csrf_token);
    result.images.forEach(x=>{if(!ids.includes(x.media_id))ids.push(x.media_id);});
  };
  await upload();await navigate();
  await wait(`document.querySelector('[data-gallery-open]')`);
  await evaluate(`document.querySelector('[data-gallery-open]').click()`);
  assert.equal(await evaluate(`document.querySelector('[data-gallery-next]').hidden`),true);
  await evaluate(`document.querySelector('[data-gallery-close]').click()`);
  for(let i=0;i<5;i++)await upload();
  // Admin list/reload uses canonical HTTP state and ignores any old localStorage.
  await send('Page.navigate',{url:base+'/index.html'});
  await wait(`document.querySelectorAll('#fotos-grid .foto-item').length===6`);
  await send('Page.reload');await wait(`document.querySelectorAll('#fotos-grid .foto-item').length===6`);
  for(const [width,height] of [[1440,900],[1366,768],[390,844],[320,740]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<600});
    await navigate();await wait(`document.querySelector('[data-gallery-open]')`);
    await evaluate(`document.querySelector('[data-gallery-open]').scrollIntoView({block:'center',behavior:'instant'})`);
    const scroll=await evaluate('scrollY');
    await evaluate(`document.querySelector('[data-gallery-open]').click()`);
    await wait(`document.querySelector('[data-gallery-main]').complete && document.querySelector('[data-gallery-main]').naturalWidth>0`);
    assert.equal(await evaluate(`document.querySelector('[data-gallery-index]').textContent`),'1 / 6');
    await evaluate(`document.querySelector('[data-gallery-next]').click()`);
    assert.equal(await evaluate(`document.querySelector('[data-gallery-index]').textContent`),'2 / 6');
    await evaluate(`document.querySelector('[data-gallery-prev]').click()`);
    assert.equal(await evaluate(`document.querySelector('[data-gallery-index]').textContent`),'1 / 6');
    await evaluate(`document.querySelector('[data-gallery-thumb="3"]').click()`);
    assert.equal(await evaluate(`document.querySelector('[data-gallery-index]').textContent`),'4 / 6');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowRight',code:'ArrowRight',windowsVirtualKeyCode:39});
    assert.equal(await evaluate(`document.querySelector('[data-gallery-index]').textContent`),'5 / 6');
    const layout=await evaluate(`(()=>{const d=document.querySelector('[data-gallery-dialog]');const r=d.getBoundingClientRect();return {open:d.open,overflow:r.left<0||r.right>innerWidth||r.top<0||r.bottom>innerHeight,fit:getComputedStyle(d.querySelector('[data-gallery-main]')).objectFit}})()`);
    assert.equal(layout.open,true);assert.equal(layout.overflow,false);assert.equal(layout.fit,'contain');
    assert.equal(await evaluate(`document.querySelector('[data-gallery-close]').getBoundingClientRect().right<=innerWidth`),true);
    const shot=await send('Page.captureScreenshot',{format:'png'});
    await writeFile('/tmp/mxmed-gallery-qa/'+width+'.png',Buffer.from(shot.data,'base64'));
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await wait(`!document.querySelector('[data-gallery-dialog]').open`);
    await wait(`document.activeElement===document.querySelector('[data-gallery-open]')`);
    assert.ok(Math.abs(await evaluate('scrollY')-scroll)<2);
    assert.equal(await evaluate(`document.activeElement===document.querySelector('[data-gallery-open]')`),true);
    console.log(width,'PASS');
  }
  await navigate('free');assert.equal(await evaluate(`!!document.querySelector('[data-gallery-open]')`),false);
  console.log('PASS: canonical 0/1/6 images, admin reload, entitlement, arrows/thumbnails/keyboard/Escape/focus/context, four sizes');
}finally{
  ws.close();await fetch(`${cdp}/json/close/${tab.id}`);
  for(const id of ids)php(`require 'api/_lib/db.php';require 'modules/media/bootstrap.php';$p=mxmed_pdo();$s=$p->prepare('SELECT storage_key FROM media_assets WHERE media_id=?');$s->execute(['${id}']);$key=$s->fetchColumn();if($key)mxmed_public_media_storage()->delete($key);$p->prepare('DELETE FROM media_assets WHERE media_id=?')->execute(['${id}']);`);
  php(`session_id('${session}');session_start();session_destroy();`);
}
