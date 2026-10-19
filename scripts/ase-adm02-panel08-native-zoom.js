/* Native browser zoom QA only. No product styles or authentication policy overrides. */
const fs=require('fs'),path=require('path'),cp=require('child_process'),assert=require('assert'),crypto=require('crypto');
const root=path.resolve(__dirname,'..'),owned=fs.realpathSync(process.argv[2]);
assert(/^\/private\/tmp\/mxmed-ase-adm02imp01-[A-Za-z0-9_]+$/.test(owned));assert.equal(fs.statSync(owned).uid,process.getuid());assert.equal(fs.statSync(owned).mode&511,448);
const approval=JSON.parse(fs.readFileSync(path.join(owned,'workflow-approval.json'))),env=JSON.parse(fs.readFileSync(path.join(owned,'review-env.json')));assert.equal(approval.root,root);assert(approval.expires>Date.now()/1000);
for(const [file,hash] of Object.entries(approval.source_hashes))assert.equal(crypto.createHash('sha256').update(fs.readFileSync(path.join(root,file))).digest('hex'),hash);
const pkg=cp.execFileSync('python3',['-c','import pathlib,playwright;print(pathlib.Path(playwright.__file__).parent/"driver/package")'],{encoding:'utf8'}).trim();
const pw=require(path.join(pkg,'lib/inprocess.js')),origin=env.MXMED_PREVIEW_ORIGIN;assert(/^https:\/\/127\.0\.0\.1:[0-9]+$/.test(origin));
const records=JSON.parse(fs.readFileSync(path.join(owned,'uxfunc02-records.json'))),shots=path.join(root,'docs/evidence/ase-adm02-panel08/screens');fs.mkdirSync(shots,{recursive:true});const results=[];
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
   p.on('dialog',d=>d.accept());
   async function bottom(id,last){
    const d=p.locator('#'+id),body=d.locator(':scope > .team-dialog-body');await body.waitFor();await p.screenshot({path:path.join(shots,engine+'-native125-'+id+'-initial.png')});const box=await body.boundingBox();await p.mouse.move(box.x+box.width/2,box.y+box.height/2);for(let n=0;n<10;n++){await p.mouse.wheel(0,900);await p.waitForTimeout(70);}
    check(engine+' native125 '+id+' real wheel reaches bottom',await body.evaluate(e=>Math.abs(e.scrollHeight-e.clientHeight-e.scrollTop)<2));
    check(engine+' native125 '+id+' last content visible',await p.locator(last).evaluate(e=>{const r=e.getBoundingClientRect(),b=e.closest('.team-dialog-body').getBoundingClientRect();return r.top>=b.top-1&&r.bottom<=b.bottom+1;}));
    check(engine+' native125 '+id+' footer visible',await d.locator(':scope > .dialog-actions').evaluate(e=>{const r=e.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight;}));await p.screenshot({path:path.join(shots,engine+'-native125-'+id+'-bottom.png')});
   }
   await p.locator('#add-member').click();await bottom('onboarding','#new-image-help');await p.locator('#cancel-onboarding').click();await p.locator('#add-member').click();check(engine+' native125 wizard reopen',await p.locator('#onboarding .team-dialog-body').evaluate(e=>e.scrollTop<5));await p.locator('#cancel-onboarding').click();
   await p.locator('[data-member="'+records.bruno.account+'"]').click();await p.locator('#personal-data summary').click();await p.locator('#member-edit').click();await p.locator('#personal-gender-trigger:not(:disabled)').waitFor();await bottom('staff-profile','#portrait-version');await p.locator('#cancel-staff-profile').click();await p.locator('#member-edit').click();await p.locator('#personal-given_names:not(:disabled)').waitFor();check(engine+' native125 personal reopen focus',await p.locator('#personal-given_names').evaluate(e=>document.activeElement===e));await p.locator('#cancel-staff-profile').click();
  }finally{if(browser)await browser.close();else await ctx.close();}
 }
 fs.writeFileSync(path.join(root,'docs/evidence/ase-adm02-panel08/native-zoom.json'),JSON.stringify({result:'PASS',checks:results,check_count:results.length},null,2));
})().catch(e=>{fs.writeFileSync(path.join(root,'docs/evidence/ase-adm02-panel08/native-zoom-failure.json'),JSON.stringify({result:'FAIL',checks:results,error:e.message},null,2));console.error(e.message);process.exitCode=1;});
