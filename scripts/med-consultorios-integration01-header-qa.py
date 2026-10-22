#!/usr/bin/env python3
"""Capture synthetic header typography and responsive layout without changing styles."""
import json,pathlib,sys,os,time
from playwright.sync_api import sync_playwright
ROOT=pathlib.Path(__file__).resolve().parents[1]
D=pathlib.Path(sys.argv[1]).resolve();S=json.loads((D/'state.json').read_text());assert S['source']==str(ROOT) and D.name.startswith('mxmed-init02-') and D.stat().st_uid==os.getuid() and S['expires']>time.time()
C=json.loads((D/'credentials.json').read_text())['owner'];O=json.loads((D/'state.json').read_text())['origin'];out=ROOT/'docs/evidence/med-consultorios-integration01/header';out.mkdir(parents=True,exist_ok=True)
with sync_playwright() as pw:
 for engine in ['chromium','webkit']:
  b=getattr(pw,engine).launch();c=b.new_context(ignore_https_errors=True,viewport={'width':1694,'height':900});p=c.new_page();fail=[];p.on('requestfailed',lambda r:fail.append({'url':r.url.split('?')[0],'reason':r.failure}));p.goto(O+'/public/identity/acceso.php');p.locator('[name=email]').fill(C['email']);p.locator('[name=password]').fill(C['password']);p.locator('[data-submit]').click();p.wait_for_url('**/private-app.php');p.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'");p.wait_for_timeout(2500)
  r=p.evaluate('''async()=>{await document.fonts.ready;return {name:document.querySelector('.mx-gh-identity-name-text').textContent,fonts:Array.from(document.fonts).map(f=>({family:f.family,status:f.status,weight:f.weight})),elements:['.mx-global-header','.mx-gh-identity-name','.mx-gh-identity-name-text','.mx-gh-user-avatar'].map(s=>{let e=document.querySelector(s),c=getComputedStyle(e),r=e.getBoundingClientRect();return {selector:s,font:c.font,fontFamily:c.fontFamily,fontSize:c.fontSize,fontWeight:c.fontWeight,lineHeight:c.lineHeight,width:r.width,height:r.height,margin:c.margin,padding:c.padding}})}}''');r['failed']=fail;assert r['name']=='Dra. Aurora Prueba Segura';assert r['elements'][2]['fontWeight']=='800' and 'Asap' in r['elements'][2]['fontFamily'];r['fonts']=[f for f in r['fonts'] if f['status']=='loaded']
  p.locator('.mx-global-header').screenshot(path=str(out/(engine+'.png')))
  if engine=='chromium':
   cd=c.new_cdp_session(p);root=cd.send('DOM.getDocument');n=cd.send('DOM.querySelector',{'nodeId':root['root']['nodeId'],'selector':'.mx-gh-identity-name-text'});cd.send('CSS.enable');r['renderedFonts']=cd.send('CSS.getPlatformFontsForNode',{'nodeId':n['nodeId']})
  r['responsive']=[]
  for width in [390,768]:
   p.set_viewport_size({'width':width,'height':900});p.wait_for_timeout(300);r['responsive'].append(p.evaluate("({width:innerWidth,headerWidth:document.querySelector('.mx-global-header').getBoundingClientRect().width,nameFont:getComputedStyle(document.querySelector('.mx-gh-identity-name-text')).font,avatarWidth:document.querySelector('.mx-gh-user-avatar').getBoundingClientRect().width})"));p.locator('.mx-global-header').screenshot(path=str(out/(engine+'-'+str(width)+'.png')))
  (out/(engine+'.json')).write_text(json.dumps(r,indent=2));print(engine,'header desktop/mobile captured');b.close()
