#!/usr/bin/env python3
"""Owner-verified controls; canonical login, independent browser profiles, native viewport."""
import pathlib,json,os,sys,time,subprocess,hashlib,hmac,struct,re
ROOT=pathlib.Path(__file__).resolve().parents[1]
DIRECTOR='/internal/media-review/index.php?section=equipo'
ADVISOR='/internal/media-review/index.php'

def login(page,origin,email,password,target=DIRECTOR):
 from playwright.sync_api import expect
 page.goto(origin+'/public/identity/acceso.php?next=%2Finternal%2Fmedia-review%2Findex.php%3Fsection%3Dequipo')
 page.locator('#login-email').fill(email);page.locator('#login-password').fill(password);page.locator('[data-submit]').click()
 page.wait_for_url('**/index.php?section=equipo')
 if target!=DIRECTOR:page.goto(origin+target)

def native_browser(pw):
 # Fixed emulated 900px content can exceed the physical macOS work area. Let Chrome
 # measure its real client area, and fit the native window INCLUDING browser chrome.
 browser=pw.chromium.launch(headless=False,args=['--window-position=0,30','--window-size=1280,800'])
 ctx=browser.new_context(ignore_https_errors=True,no_viewport=True);page=ctx.new_page()
 fit_window(ctx,page)
 return browser,ctx,page

def fit_window(ctx,page):
 cdp=ctx.new_cdp_session(page);win=cdp.send('Browser.getWindowForTarget')
 screen=page.evaluate('({x:screen.availLeft,y:screen.availTop,width:screen.availWidth,height:screen.availHeight})')
 cdp.send('Browser.setWindowBounds',{'windowId':win['windowId'],'bounds':{'windowState':'normal'}})
 cdp.send('Browser.setWindowBounds',{'windowId':win['windowId'],'bounds':{'left':screen['x'],'top':screen['y'],'width':min(1366,screen['width']),'height':screen['height']}})
 page.wait_for_timeout(250);cdp.detach()

def totp(owned):
 v=hmac.new((owned/'workflow-totp-secret').read_bytes(),struct.pack('>Q',int(time.time())//30),hashlib.sha1).digest();n=v[-1]&15
 return str((int.from_bytes(v[n:n+4],'big')&0x7fffffff)%1000000).zfill(6)

def main():
 os.umask(0o077);owned=pathlib.Path(sys.argv[1]).resolve();action=sys.argv[2] if len(sys.argv)>2 else 'status'
 assert owned.parent==pathlib.Path('/private/tmp') and re.fullmatch('mxmed-ase-adm02imp01-[A-Za-z0-9_]+',owned.name)
 assert owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
 approval=json.loads((owned/'workflow-approval.json').read_text());info=json.loads((owned/'review-info.json').read_text())
 assert approval['root']==str(ROOT)
 if action=='stop':
  subprocess.run(['python3',str(ROOT/'scripts/ase-adm02-workflow-review.py'),str(owned),'--stop'],check=True);return
 assert info['expiry_unix']>time.time() and approval['expires']>time.time()
 assert (owned/'oper09-head').read_text().strip()==subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
 assert all(hashlib.sha256((ROOT/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
 origin=info['url'].split('/internal/')[0];assert re.fullmatch(r'https://127\.0\.0\.1:[0-9]+',origin)
 record=json.loads((owned/'oper09-advisor.json').read_text())
 if action in ['credentials','credentials-director','credentials-advisor']:
  advisor=action=='credentials-advisor';subprocess.run(['pbcopy'],input=(owned/('applicant-password' if advisor else 'fixture-password')).read_bytes().strip(),check=True)
  print('Contraseña ficticia copiada. Cuenta: '+(record['email'] if advisor else 'qa_director@example.test'))
 elif action=='mfa':print(totp(owned))
 elif action in ['open','open-director','open-advisor']:
  for actor in (['director','advisor'] if action=='open' else [action.removeprefix('open-')]):
   marker=owned/('logout-'+actor)
   if marker.exists():marker.unlink()
   with (owned/('oper09-'+actor+'-browser.log')).open('ab') as log:subprocess.Popen([sys.executable,__file__,str(owned),'browser-'+actor],stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
  print('Abriendo login canónico en Chromium independiente; no se prolonga el servicio ni la sesión.')
 elif action in ['logout-director','logout-advisor']:
  (owned/action).touch();print('Cierre canónico solicitado para las ventanas propias de '+action.removeprefix('logout-')+'.')
 elif action.startswith('browser-'):
  from playwright.sync_api import sync_playwright,expect
  actor=action.removeprefix('browser-');assert actor in ['director','advisor']
  with sync_playwright() as pw:
   browser,ctx,p=native_browser(pw)
   login(p,origin,record['email'] if actor=='advisor' else 'qa_director@example.test',(owned/('applicant-password' if actor=='advisor' else 'fixture-password')).read_text().strip(),ADVISOR if actor=='advisor' else DIRECTOR)
   if actor=='director':
    p.locator('[data-member="'+record['account']+'"]').click();p.locator('#personal-data summary').click()
    # Same delivered native window: real wheel, keyboard, hit-testing and footer click.
    import importlib.util
    spec=importlib.util.spec_from_file_location('oper09_browser',ROOT/'scripts/ase-adm02-oper09-browser.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
    proof=m.delivered_modal(p,ctx,owned)
   else:
    expect(p.locator('#batch-cards [data-batch-id]')).to_have_count(1);p.locator('#batch-cards [data-batch-id]').click();p.get_by_role('button',name='Ver imagen de Perfil ficticio').click();expect(p.locator('#detail-image')).to_be_visible();assert p.locator('#detail-image').evaluate('(e)=>e.complete&&e.naturalWidth>0');proof={'canonical_login':True,'independent_advisor':True,'actual_review_image_loaded':True};p.screenshot(path=str(owned/'oper09-advisor-review.png'))
   (owned/('oper09-'+actor+'-ready.json')).write_text(json.dumps({'opened_at':time.time(),'proof':proof}))
   while browser.is_connected() and time.time()<info['expiry_unix'] and not (owned/'stop-review').exists():
    try:
     if (owned/('logout-'+actor)).exists():

      import importlib.util
      spec=importlib.util.spec_from_file_location('oper09_logout',ROOT/'scripts/ase-adm02-oper09-browser.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
      result=m.logout(p);assert result['status']==200 and result['body']['state']=='LOGOUT_SUCCESS';p.goto(origin+'/public/identity/acceso.php');break
     p.wait_for_timeout(1000)
    except Exception:break
   if browser.is_connected():browser.close()
 elif action=='status':print(json.dumps({'director_url':origin+DIRECTOR,'advisor_url':origin+ADVISOR,'expires':info['expiry_unix']}))
 else:raise SystemExit('Usa open, open-director, open-advisor, credentials-director, credentials-advisor, mfa, logout-director, logout-advisor, status o stop.')
if __name__=='__main__':main()
