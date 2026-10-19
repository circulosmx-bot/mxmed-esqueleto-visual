/* Native browser zoom QA only. No product styles or authentication policy overrides. */
const fs=require('fs'),path=require('path'),cp=require('child_process'),assert=require('assert'),crypto=require('crypto');
const root=path.resolve(__dirname,'..'),owned=fs.realpathSync(process.argv[2]);
assert(/^\/private\/tmp\/mxmed-ase-adm02imp01-[A-Za-z0-9_]+$/.test(owned));assert.equal(fs.statSync(owned).uid,process.getuid());assert.equal(fs.statSync(owned).mode&511,448);
const approval=JSON.parse(fs.readFileSync(path.join(owned,'workflow-approval.json'))),env=JSON.parse(fs.readFileSync(path.join(owned,'review-env.json')));assert.equal(approval.root,root);assert(approval.expires>Date.now()/1000);
for(const [file,hash] of Object.entries(approval.source_hashes))assert.equal(crypto.createHash('sha256').update(fs.readFileSync(path.join(root,file))).digest('hex'),hash);
const pkg=cp.execFileSync('python3',['-c','import pathlib,playwright;print(pathlib.Path(playwright.__file__).parent/"driver/package")'],{encoding:'utf8'}).trim();
const pw=require(path.join(pkg,'lib/inprocess.js')),origin=env.MXMED_PREVIEW_ORIGIN;assert(/^https:\/\/127\.0\.0\.1:[0-9]+$/.test(origin));
const records=JSON.parse(fs.readFileSync(path.join(owned,'uxfunc02-records.json'))),shots=path.join(root,'docs/evidence/ase-adm02-panel07/screens');fs.mkdirSync(shots,{recursive:true});const results=[];
function check(name,ok){results.push({name,pass:!!ok});assert(ok,name);console.log('PASS '+name);}
(async()=>{
 for(const engine of ['chromium','webkit']){
  let browser,ctx,worker;
  if(engine==='chromium'){
   const ext=fs.mkdtempSync(path.join(owned,'zoom-extension-'));fs.writeFileSync(path.join(ext,'manifest.json'),JSON.stringify({manifest_version:3,name:'Owned native zoom QA',version:'1.0',permissions:['tabs'],background:{service_worker:'zoom.js'}}));fs.writeFileSync(path.join(ext,'zoom.js'),'chrome.runtime.onInstalled.addListener(()=>{});');
   ctx=await pw.chromium.launchPersistentContext(path.join(ext,'profile'),{headless:false,args:['--disable-extensions-except='+ext,'--load-extension='+ext],ignoreHTTPSErrors:true,viewport:{width:1366,height:768}});worker=ctx.serviceWorkers()[0]||await ctx.waitForEvent('serviceworker');
  }else{browser=await pw.webkit.launch();ctx=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:1366,height:768}});}
  try{
   const p=await ctx.newPage();await p.goto(origin+'/public/identity/acceso.php?next=%2Finternal%2Fmedia-review%2Findex.php%3Fsection%3Dequipo');await p.locator('#login-email').fill('qa_director@example.test');await p.locator('#login-password').fill(fs.readFileSync(path.join(owned,'fixture-password'),'utf8').trim());await p.locator('[data-submit]').click();await p.waitForURL('**/index.php?section=equipo');await p.locator('#add-member:not(:disabled)').waitFor();
   const before=await p.evaluate(()=>({width:innerWidth,dpr:devicePixelRatio}));
   if(engine==='chromium')await worker.evaluate(async origin=>{const t=(await chrome.tabs.query({})).find(t=>t.url.startsWith(origin+'/internal/'));await chrome.tabs.setZoom(t.id,1.25);if(await chrome.tabs.getZoom(t.id)!==1.25)throw Error('Zoom not applied');},origin);
   else{const impl=p._connection.toImpl(p),bi=browser._connection.toImpl(browser);await bi._browserSession.send('Playwright.setPageZoomFactor',{pageProxyId:impl.delegate._pageProxySession.sessionId,zoomFactor:1.25});}
   const after=await p.evaluate(()=>({width:innerWidth,dpr:devicePixelRatio,css:getComputedStyle(document.documentElement).zoom}));check(engine+' genuine 125% browser zoom changes CSS viewport and DPR; CSS zoom remains 1',Math.abs(before.width/after.width-1.25)<.01&&Math.abs(after.dpr/before.dpr-1.25)<.01&&after.css==='1');
   async function popup(id,parent){const trigger=p.locator('#'+id);await trigger.scrollIntoViewIfNeeded();await trigger.click();const pop=p.locator('.team-select-options:popover-open');await pop.waitFor();const box=await pop.boundingBox(),v=await p.evaluate(()=>({w:innerWidth,h:innerHeight}));check(engine+' native125 '+id+' expanded within viewport',box.x>=0&&box.y>=0&&box.x+box.width<=v.w+1&&box.y+box.height<=v.h+1);await p.keyboard.press('End');check(engine+' native125 '+id+' last option reachable',await pop.locator('button').last().evaluate(e=>{const b=e.getBoundingClientRect(),p=e.parentElement.getBoundingClientRect();return b.top>=p.top&&b.bottom<=p.bottom+1;}));await p.screenshot({path:path.join(shots,engine+'-native125-'+id+'.png')});await p.keyboard.press('Escape');check(engine+' native125 '+id+' Escape returns focus',await trigger.evaluate(e=>e===document.activeElement));if(parent)check(engine+' native125 '+id+' parent remains open',await p.locator('#'+parent).isVisible());}
   await popup('status-trigger');await p.locator('#add-member').click();await popup('new-channel-trigger','onboarding');await p.keyboard.press('Escape');await p.locator('[data-member="'+records.bruno.account+'"]').click();await p.locator('#personal-data summary').click();await p.locator('#member-edit').click();await p.locator('#personal-gender-trigger:not(:disabled)').waitFor();
   for(const k of ['gender','avatar','presentation'])await popup('personal-'+k+'-trigger','staff-profile');
   check(engine+' native125 long modal footer reachable',await p.locator('#save-staff-profile').evaluate(e=>{e.scrollIntoView({block:'nearest'});const r=e.getBoundingClientRect();return r.bottom<=innerHeight&&r.top>=0;}));
  }finally{if(browser)await browser.close();else await ctx.close();}
 }
 fs.writeFileSync(path.join(root,'docs/evidence/ase-adm02-panel07/native-zoom.json'),JSON.stringify({result:'PASS',checks:results,check_count:results.length},null,2));
})().catch(e=>{fs.writeFileSync(path.join(root,'docs/evidence/ase-adm02-panel07/native-zoom-failure.json'),JSON.stringify({result:'FAIL',checks:results,error:e.message},null,2));console.error(e.message);process.exitCode=1;});
