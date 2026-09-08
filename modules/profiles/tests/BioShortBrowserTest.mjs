import assert from 'node:assert/strict';
import {mkdir, writeFile} from 'node:fs/promises';

const cdp = process.env.CDP_URL || 'http://127.0.0.1:9348';
const base = process.env.PUBLIC_URL || 'http://127.0.0.1:8092';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-bio-short-qa';
await mkdir(output, {recursive:true});
const tab = await (await fetch(`${cdp}/json/new?about:blank`, {method:'PUT'})).json();
const ws = new WebSocket(tab.webSocketDebuggerUrl);
await new Promise(resolve=>ws.addEventListener('open', resolve, {once:true}));
let id=0;
const pending=new Map();
const send=(method,params={})=>new Promise((resolve,reject)=>{
  pending.set(++id,{resolve,reject}); ws.send(JSON.stringify({id,method,params}));
});
ws.addEventListener('message',event=>{
  const m=JSON.parse(event.data); if(!m.id)return;
  const h=pending.get(m.id); pending.delete(m.id);
  m.error?h.reject(m.error):h.resolve(m.result);
});
const evaluate=async expression=>{
  const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
  if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));
  return r.result.value;
};
const wait=async expression=>{
  for(let i=0;i<120;i++){
    if(await evaluate(expression))return;
    await new Promise(r=>setTimeout(r,100));
  }
  throw Error(`Timeout: ${expression}`);
};
const samples=[
  'Atención médica profesional y cercana.',
  'Especialista en diabetes, tiroides y metabolismo. Atención médica a pacientes.',
  'Especialista en alteraciones del sistema endocrino y enfermedades metabólicas.',
  'Especialista en diabetes, tiroides y metabolismo. Atención integral para adultos y familias.'
];
samples[1]=Array.from(samples[1]).slice(0,75).join('');
samples[3]=Array.from(samples[3]).slice(0,90).join('');
assert.deepEqual(samples.map(s=>Array.from(s).length),[38,75,78,90]);
const results=[];
try{
  await send('Page.enable');
  for(const [width,height] of [[1440,900],[1366,768],[390,844],[320,740]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<600});
    await send('Page.navigate',{url:`${base}/profiles/doctor.php?doctor_id=1&mxmed_plan=professional`});
    await wait(`document.querySelector('.mxpp-bio')`);
    await evaluate('document.fonts.ready.then(()=>true)');
    for(const sample of samples){
      const measurement=await evaluate(`(()=>{
        const bio=document.querySelector('.mxpp-bio');
        bio.textContent=${JSON.stringify(sample)};
        bio.classList.toggle('mxpp-bio--long',Array.from(bio.textContent).length>75 && Array.from(bio.textContent).length<=90);
        const range=document.createRange();range.selectNodeContents(bio);
        const rects=Array.from(range.getClientRects());
        const r=bio.getBoundingClientRect();
        return {length:Array.from(bio.textContent).length,lines:new Set(rects.map(r=>r.top)).size,font:getComputedStyle(bio).fontSize,
          overflow:rects.some(x=>x.left<r.left-1||x.right>r.right+1),
          collision:r.bottom>document.querySelector('.mxpp-hero-brand-actions').getBoundingClientRect().top};
      })()`);
      results.push({width,...measurement});
      if(width>600)assert.ok(measurement.lines<=2,JSON.stringify(results.at(-1)));
      assert.equal(measurement.overflow,false);
      assert.equal(measurement.collision,false);
    }
    if(width<600){
      await evaluate(`document.querySelector('.mxpp-bio').scrollIntoView({block:'center',behavior:'instant'})`);
      await new Promise(resolve=>setTimeout(resolve,300));
    }
    const shot=await send('Page.captureScreenshot',{format:'png'});
    await writeFile(`${output}/${width}.png`,Buffer.from(shot.data,'base64'));
  }
  // Admin hydration and editing only: no save requests from this browser test.
  await send('Page.navigate',{url:`${base}/index.html`});
  await wait(`document.querySelector('#mxpi-bio-short')?.value && document.querySelector('#mxpi-bio-count')?.textContent !== '0 / 90'`);
  const admin=await evaluate(`(()=>{
    const field=document.querySelector('#mxpi-bio-short');
    const counter=document.querySelector('#mxpi-bio-count');
    const initial=counter.textContent===Array.from(field.value).length+' / 90';
    const insert=(text)=>{const data=new DataTransfer();data.setData('text/plain',text);field.dispatchEvent(new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}));};
    field.value='';field.setSelectionRange(0,0);insert('áéíóúñ');
    const accents=counter.textContent==='6 / 90';
    field.select();insert('😀'.repeat(91));
    const paste=Array.from(field.value).length===90 && counter.textContent==='90 / 90';
    const event=new InputEvent('beforeinput',{inputType:'insertText',data:'a',cancelable:true});
    field.dispatchEvent(event);
    const blocked=event.defaultPrevented && Array.from(field.value).length===90;
    const message=!document.querySelector('#mxpi-bio-limit').hidden;
    field.value='á'.repeat(89);field.setSelectionRange(89,89);
    field.dispatchEvent(new InputEvent('beforeinput',{inputType:'insertText',data:'😀',cancelable:true}));
    const unicodeTyping=Array.from(field.value).length===90;
    field.value='á'.repeat(75);field.dispatchEvent(new Event('input',{bubbles:true}));
    const deletion=counter.textContent==='75 / 90' && counter.classList.contains('text-muted');
    return {initial,accents,paste,blocked,message,unicodeTyping,deletion,maxlength:field.maxLength};
  })()`);
  for(const [key,value] of Object.entries(admin))assert.equal(value,key==='maxlength'?90:true,key);
  await evaluate(`(()=>{const field=document.querySelector('#mxpi-bio-short');document.body.append(field);field.style.cssText='position:fixed;top:0;left:0;z-index:999999';field.value='';field.focus();})()`);
  await send('Input.insertText',{text:'á'.repeat(90)});
  await send('Input.insertText',{text:'ñ'});
  assert.equal(await evaluate(`document.querySelector('#mxpi-bio-short').value`),'á'.repeat(90));
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Backspace',code:'Backspace',windowsVirtualKeyCode:8});
  await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Backspace',code:'Backspace',windowsVirtualKeyCode:8});
  assert.equal(await evaluate(`document.querySelector('#mxpi-bio-count').textContent`),'89 / 90');
  await writeFile(`${output}/results.json`,JSON.stringify({results,admin},null,2));
  console.log(JSON.stringify({results,admin,output},null,2));
}finally{
  ws.close();await fetch(`${cdp}/json/close/${tab.id}`);
}
