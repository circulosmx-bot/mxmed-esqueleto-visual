// Real Leticia hydration; saves are intercepted, never sent to the Director DB.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';

const base = process.env.PUBLIC_URL || 'http://127.0.0.1:18143';
const output = process.env.QA_OUTPUT || '/tmp/mxmed-crd037';
const profile = await mkdtemp('/tmp/mxmed-crd03-browser-');
await mkdir(output, {recursive:true});

const chrome = spawn(
  process.env.MXMED_QA_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ['--headless=new', '--no-first-run', '--no-default-browser-check', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`],
  {stdio:'ignore'}
);

let ws;
try{
  let version;
  for(let attempt = 0; attempt < 100; attempt += 1){
    try{
      const port = (await readFile(`${profile}/DevToolsActivePort`, 'utf8')).split('\n')[0];
      version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json();
      break;
    }catch{
      await new Promise((resolve)=> setTimeout(resolve, 100));
    }
  }
  assert.ok(version?.webSocketDebuggerUrl, 'Chrome DevTools did not start');
  ws = new WebSocket(version.webSocketDebuggerUrl);
  await new Promise((resolve)=> ws.addEventListener('open', resolve, {once:true}));

  let sequence = 0;
  let session;
  const pending = new Map();
  const send = (method, params = {}, sid = session)=> new Promise((resolve, reject)=>{
    const id = ++sequence;
    pending.set(id, {resolve, reject, method});
    ws.send(JSON.stringify({id, method, params, ...(sid ? {sessionId:sid} : {})}));
  });
  ws.addEventListener('message', (event)=>{
    const message = JSON.parse(event.data);
    if(!message.id) return;
    const request = pending.get(message.id);
    pending.delete(message.id);
    message.error ? request.reject(Error(`${request.method}: ${JSON.stringify(message.error)}`)) : request.resolve(message.result);
  });

  const target = (await send('Target.createTarget', {url:'about:blank'}, null)).targetId;
  session = (await send('Target.attachToTarget', {targetId:target, flatten:true}, null)).sessionId;
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Log.enable');
  const consoleErrors=[];
  ws.addEventListener('message',event=>{
    const message=JSON.parse(event.data);
    if(message.method==='Runtime.exceptionThrown')consoleErrors.push({type:'exception',text:message.params.exceptionDetails.text});
    if(message.method==='Runtime.consoleAPICalled'&&message.params.type==='error')consoleErrors.push({type:'console',text:message.params.args.map(a=>a.value||a.description).join(' ')});
    if(message.method==='Log.entryAdded'&&message.params.entry.level==='error')consoleErrors.push({type:'log',source:message.params.entry.source,text:message.params.entry.text});
  });
  const evaluate = async(expression)=>{
    const result = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
    if(result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
  };
  const until = async(expression)=>{
    for(let attempt = 0; attempt < 180; attempt += 1){
      if(await evaluate(expression)) return;
      await new Promise((resolve)=> setTimeout(resolve, 100));
    }
    throw Error(`Browser timeout: ${expression}`);
  };
  const screenshot = async(name)=>{
    await new Promise(resolve=>setTimeout(resolve,250));
    const result = await send('Page.captureScreenshot',{format:'png'});
    await writeFile(`${output}/${name}`,Buffer.from(result.data,'base64'));
  };

  const original=(await(await fetch(base+'/api/profiles/private/doctor/1')).json()).data;
  const catalog=original.profile_theme.catalog;
  assert.equal(catalog.length,20);
  await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{const native=window.fetch;window.__crd037Patches=[];window.fetch=async(input,init={})=>{const url=new URL(typeof input==='string'?input:input.url,location.href);const method=String(init.method||(input instanceof Request?input.method:'GET')).toUpperCase();if(method==='PATCH'&&url.pathname==='/api/profiles/private/doctor/1'){const payload=JSON.parse(init.body);window.__crd037Patches.push(payload);const data=${JSON.stringify(original)};data.profile_theme.stored_key=payload.profile_theme_key;data.profile_theme.effective_key=payload.profile_theme_key||data.profile_theme.default_key;return new Response(JSON.stringify({ok:true,data}),{headers:{'Content-Type':'application/json'}});}if(!['GET','HEAD'].includes(method))throw Error('Unexpected mutation in read-only Director QA');return native(input,init);};})()`});
  const metrics=[];
  const rgb=hex=>'rgb('+[1,3,5].map(i=>parseInt(hex.slice(i,i+2),16)).join(', ')+')';
  const themeSnapshot=()=>evaluate(`([...document.querySelectorAll('#mx-profile-theme-swatches button')].map(b=>{const s=getComputedStyle(b),sw=getComputedStyle(b,'::before'),check=getComputedStyle(b,'::after'),r=b.getBoundingClientRect();return {key:b.dataset.themeKey,label:b.getAttribute('aria-label'),title:b.title,color:sw.backgroundColor,width:parseFloat(sw.width),height:parseFloat(sw.height),radius:parseFloat(sw.borderRadius),targetWidth:r.width,targetHeight:r.height,selected:b.getAttribute('aria-checked'),tab:b.tabIndex,ring:sw.boxShadow,check:check.content,focusOutline:s.outlineStyle};}))`);
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    await send('Page.navigate',{url:base+'/index.html?review=crd037'});
    await until('document.readyState==="complete" && typeof showPanel==="function"');
    await evaluate("showPanel('p-info');document.getElementById('t-info-datos-tab').click()");
    await until('document.querySelectorAll("#mx-profile-theme-swatches button").length===20 && document.getElementById("mxpi-current-name-value").textContent.includes("Leticia")');
    await new Promise(r=>setTimeout(r,1100));
    await evaluate("document.querySelectorAll('.modal.show [data-bs-dismiss=modal]').forEach(e=>e.click())");
    await new Promise(r=>setTimeout(r,500));
    const state=await evaluate(`(()=>{const label=document.querySelector('.mxpi-public-name-label'),value=document.querySelector('.mxpi-public-name-value'),note=document.querySelector('#mx-dg-media-card .mx-dg-card-note');return {width:${width},labelSize:parseFloat(getComputedStyle(label).fontSize),valueSize:parseFloat(getComputedStyle(value).fontSize),labelColor:getComputedStyle(label).color,valueColor:getComputedStyle(value).color,label:label.textContent,value:value.textContent,copy:note.textContent,copyFont:getComputedStyle(note).fontSize,overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,nameHeight:document.getElementById('mxpi-name-editor').getBoundingClientRect().height}})()`);
    assert.ok(Math.abs(state.labelSize/17.76-.85)<.001);assert.equal(state.valueSize,17.76);
    assert.equal(state.labelColor,'rgb(37, 150, 190)');assert.equal(state.valueColor,state.labelColor);
    assert.equal(state.label,'Nombre público en tu perfil:');assert.equal(state.value,'Dra. Leticia Muñoz Romo');
    assert.equal(state.copy,'Los cambios de imágenes requieren revisión; mientras tanto la versión actual permanece visible.');
    assert.equal(state.copyFont,width<500?'13.44px':'11.68px');assert.equal(state.overflow,false);metrics.push(state);
    const geometry=await evaluate(`(()=>{
      const buttons=[...document.querySelectorAll('#mx-profile-theme-swatches button')];
      const rects=buttons.map(b=>{const r=b.getBoundingClientRect(),s=getComputedStyle(b,'::before');const width=parseFloat(s.width),height=parseFloat(s.height);return {x:r.x+(r.width-width)/2,y:r.y+(r.height-height)/2,width,height}});
      const columns=rects.findIndex(r=>r.y>rects[0].y+1);const grid=getComputedStyle(document.getElementById('mx-profile-theme-swatches'));
      const reset=document.getElementById('mx-profile-theme-reset');const style=getComputedStyle(reset);
      const horizontal=rects.filter((r,i)=>i&&Math.abs(r.y-rects[i-1].y)<1).map(r=>{const i=rects.indexOf(r);return r.x-rects[i-1].x-r.width});
      const vertical=rects.filter((r,i)=>i>=columns).map((r,i)=>r.y-rects[i].y-r.height);
      return {columns,rows:Math.ceil(rects.length/columns),columnGap:parseFloat(grid.columnGap),rowGap:parseFloat(grid.rowGap),horizontal,vertical,
        description:document.querySelector('.mx-theme-admin__head .text-muted').textContent,
        resetBackground:style.backgroundColor,resetColor:style.color,resetRadius:style.borderRadius};})()`);
    assert.equal(geometry.description,'Puedes elegir un color para personalizar tu perfil.');
    assert.equal(geometry.resetBackground,'rgb(0, 174, 190)');assert.equal(geometry.resetColor,'rgb(255, 255, 255)');
    assert.equal(geometry.columnGap,8);assert.equal(geometry.rowGap,4);
    geometry.horizontal.forEach(g=>assert.ok(Math.abs(g-14)<.1));geometry.vertical.forEach(g=>assert.ok(Math.abs(g-14)<.1));
    if(width>500){assert.equal(geometry.columns,10);assert.equal(geometry.rows,2);}else assert.equal(geometry.columns,3);
    Object.assign(state,geometry);

    await evaluate("document.getElementById('mxpi-name-editor').scrollIntoView({block:'start',behavior:'instant'})");
    if(width===1440){
      await screenshot('crd037-public-name-typography.png');
      const clip=await evaluate("(()=>{const r=document.getElementById('mxpi-current-name').getBoundingClientRect();return {x:r.left+scrollX,y:r.top+scrollY,width:r.width,height:r.height,scale:1}})()");
      const shot=await send('Page.captureScreenshot',{format:'png',clip,captureBeyondViewport:true});
      await writeFile(output+'/crd037-public-name-color.png',Buffer.from(shot.data,'base64'));
      await evaluate("document.getElementById('mx-dg-media-card').scrollIntoView({block:'start',behavior:'instant'})");
      await screenshot('crd037-media-review-copy.png');
    }
    await evaluate("document.getElementById('mx-profile-theme-admin').scrollIntoView({block:'start',behavior:'instant'})");
    const swatches=await themeSnapshot();
    swatches.forEach((s,i)=>{
      assert.equal(s.key,catalog[i].key);assert.equal(s.label,catalog[i].label);assert.equal(s.title,catalog[i].label);
      assert.equal(s.color,rgb(catalog[i].accent));assert.equal(s.width,72);assert.equal(s.height,34);assert.equal(s.radius,5);
      assert.ok(s.targetWidth>=44&&s.targetHeight>=44);
    });
    assert.equal(swatches.filter(s=>s.selected==='true').length,1);
    assert.equal(swatches.filter(s=>s.tab===0).length,1);
    const selected=swatches.find(s=>s.selected==='true');assert.notEqual(selected.ring,'none');assert.ok(selected.check.includes('✓'));
    if(width===1440)await screenshot('crd038-theme-desktop-1440.png');
    if(width===1366)await screenshot('crd038-theme-desktop-1366.png');
    if(width===390)await screenshot('crd038-theme-mobile.png');
    // Real keyboard events exercise the unchanged roving radio selection.
    await evaluate("document.querySelector('#mx-profile-theme-swatches [aria-checked=true]').focus()");
    const initial=catalog.findIndex(t=>t.key===selected.key);
    for(const key of ['ArrowRight','ArrowDown','ArrowLeft','ArrowUp']){
      await send('Input.dispatchKeyEvent',{type:'keyDown',key,code:key});
      await send('Input.dispatchKeyEvent',{type:'keyUp',key,code:key});
    }
    assert.equal(await evaluate('document.activeElement.dataset.themeKey'),catalog[initial].key);
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowRight',code:'ArrowRight'});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'ArrowRight',code:'ArrowRight'});
    let changed=(await themeSnapshot()).find(s=>s.selected==='true');
    assert.equal(changed.key,catalog[(initial+1)%20].key);assert.equal(changed.focusOutline,'solid');assert.notEqual(changed.ring,'none');assert.ok(changed.check.includes('✓'));
    assert.equal(await evaluate('window.__crd037Patches.length'),0,'theme preview must not autosave');
    assert.equal(await evaluate("getComputedStyle(document.getElementById('mx-profile-theme-admin')).getPropertyValue('--profile-accent').trim()"),catalog[(initial+1)%20].accent);
    if(width===1440)await screenshot('crd038-theme-selected.png');
    await evaluate("document.getElementById('mxpi-save-btn').click()");
    await until('window.__crd037Patches.length===1 && !document.getElementById("mxpi-save-btn").disabled');
    const payload=await evaluate('window.__crd037Patches[0]');
    assert.equal(payload.profile_theme_key,changed.key);assert.equal(Object.hasOwn(payload,'display_name'),false);
    assert.equal(Object.hasOwn(payload,'gender'),false);assert.equal(Object.hasOwn(payload,'gender_label'),false);
    assert.equal(payload.bio_short,original.identity_public.bio_short);assert.equal(payload.prefix,original.identity_public.prefix);
    assert.equal(payload.professional_designation,original.identity_public.professional_designation);

    await send('DOM.enable');await send('CSS.enable');
    const documentNode=(await send('DOM.getDocument')).root.nodeId;
    const resetNode=(await send('DOM.querySelector',{nodeId:documentNode,selector:'#mx-profile-theme-reset'})).nodeId;
    for(const pseudo of ['hover','focus-visible','active']){
      await send('CSS.forcePseudoState',{nodeId:resetNode,forcedPseudoClasses:[pseudo]});
      await new Promise(resolve=>setTimeout(resolve,250));
      const state=await evaluate("(()=>{const s=getComputedStyle(document.getElementById('mx-profile-theme-reset'));return {background:s.backgroundColor,color:s.color,outline:s.outlineStyle}})()");
      assert.equal(state.background,'rgb(0, 143, 158)');assert.equal(state.color,'rgb(255, 255, 255)');
      if(pseudo==='focus-visible')assert.equal(state.outline,'solid');
    }
    await send('CSS.forcePseudoState',{nodeId:resetNode,forcedPseudoClasses:[]});
    await evaluate("document.getElementById('mx-profile-theme-reset').click()");
    assert.equal(await evaluate('window.__crd037Patches.length'),1,'reset preview must not autosave');
    await evaluate("document.getElementById('mxpi-save-btn').click()");
    await until('window.__crd037Patches.length===2 && !document.getElementById("mxpi-save-btn").disabled');
    assert.equal(await evaluate('window.__crd037Patches[1].profile_theme_key'),null);
    assert.equal(await evaluate('document.documentElement.scrollWidth>document.documentElement.clientWidth'),false);
  }
  // Public preview uses the existing bounded local query and canonical catalog.
  const publicResults=[];
  for(const [width,height] of [[1440,900],[1366,768],[390,844]]){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
    for(const theme of catalog){
      await send('Page.navigate',{url:base+'/profiles/doctor.php?doctor_id=1&mxmed_plan=professional&mxmed_theme_preview='+encodeURIComponent(theme.key)});
      await until(`document.body.dataset.profileTheme===${JSON.stringify(theme.key)}`);
      const state=await evaluate(`({theme:document.body.dataset.profileTheme,accent:getComputedStyle(document.body).getPropertyValue('--profile-accent').trim(),overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth})`);
      assert.equal(state.theme,theme.key);assert.equal(state.accent.toLowerCase(),theme.accent.toLowerCase());assert.equal(state.overflow,false);
      publicResults.push({width,...state});
    }
  }
  assert.deepEqual(consoleErrors,[],'no console errors introduced');
  await writeFile(output+'/metrics.json',JSON.stringify({admin:metrics,public:publicResults,consoleErrors},null,2));
  console.log('CRD038_BROWSER=PASS: balanced gaps, helper/reset states, zero console errors; split 15.096/17.76px exact blue; copy; 72x34/5px swatches; 78x44px targets; labels/catalog; ring/check/focus; keyboard; preview/reset; mocked grouped PATCH; 60 public theme previews; three viewports');
}finally{ws?.close();chrome.kill();await new Promise(r=>setTimeout(r,300));await rm(profile,{recursive:true,force:true});}
