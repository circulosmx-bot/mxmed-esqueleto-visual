#!/usr/bin/env python3
"""R2 owner-verified native review; independent canonical staff/Director logins."""
import pathlib,json,os,sys,time,subprocess,hashlib,re,importlib.util
from urllib.parse import quote
ROOT=pathlib.Path(__file__).resolve().parents[1]
DIRECTOR='/internal/media-review/index.php?section=equipo'
ADVISOR='/internal/staff-workspace/index.php'
spec=importlib.util.spec_from_file_location('workspace_native',ROOT/'scripts/ase-adm02-oper09-review.py');native=importlib.util.module_from_spec(spec);spec.loader.exec_module(native)
native_browser=native.native_browser

def login(page,origin,email,password,target=ADVISOR):
 page.goto(origin+'/public/identity/acceso.php?next='+quote(target,safe=''))
 page.locator('#login-email').fill(email);page.locator('#login-password').fill(password);page.locator('[data-submit]').click()
 page.wait_for_url(origin+target)

def main():
 os.umask(0o077);owned=pathlib.Path(sys.argv[1]).resolve();action=sys.argv[2] if len(sys.argv)>2 else 'status'
 assert owned.parent==pathlib.Path('/private/tmp') and re.fullmatch('mxmed-ase-adm02imp01-[A-Za-z0-9_]+',owned.name)
 assert owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
 approval=json.loads((owned/'workflow-approval.json').read_text());info=json.loads((owned/'review-info.json').read_text())
 assert approval['root']==str(ROOT)
 if action=='stop':
  subprocess.run(['python3',str(ROOT/'scripts/ase-adm02-workflow-review.py'),str(owned),'--stop'],check=True);return
 assert info['expiry_unix']>time.time() and approval['expires']>time.time()
 assert (owned/'workspace-head').read_text().strip()==subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
 assert all(hashlib.sha256((ROOT/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
 origin=info['url'].split('/internal/')[0];assert re.fullmatch(r'https://127\.0\.0\.1:[0-9]+',origin)
 record=json.loads((owned/'oper09-advisor.json').read_text())
 if action in ['credentials','credentials-director','credentials-advisor']:
  advisor=action=='credentials-advisor';subprocess.run(['pbcopy'],input=(owned/('applicant-password' if advisor else 'fixture-password')).read_bytes().strip(),check=True)
  print('Contraseña ficticia copiada. Cuenta: '+(record['email'] if advisor else 'qa_director@example.test'))
 elif action=='mfa':print(native.totp(owned))
 elif action in ['open','open-director','open-advisor']:
  for actor in (['director','advisor'] if action=='open' else [action.removeprefix('open-')]):
   marker=owned/('logout-workspace-'+actor)
   if marker.exists():marker.unlink()
   with (owned/('workspace-'+actor+'-browser.log')).open('ab') as log:subprocess.Popen([sys.executable,__file__,str(owned),'browser-'+actor],stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
  print('Abriendo dos sesiones Identity independientes en Chromium; vigencia original preservada.')
 elif action in ['logout-director','logout-advisor']:
  (owned/('logout-workspace-'+action.removeprefix('logout-'))).touch();print('Cierre canónico solicitado.')
 elif action.startswith('browser-'):
  from playwright.sync_api import sync_playwright,expect
  actor=action.removeprefix('browser-');assert actor in ['director','advisor']
  with sync_playwright() as pw:
   browser,ctx,p=native_browser(pw)
   login(p,origin,record['email'] if actor=='advisor' else 'qa_director@example.test',(owned/('applicant-password' if actor=='advisor' else 'fixture-password')).read_text().strip(),ADVISOR if actor=='advisor' else DIRECTOR)
   if actor=='director':
    p.locator('[data-member="'+record['account']+'"]').click();expect(p.locator('#detail-title')).to_contain_text('Irene')
   else:
    p.locator('#discovery-list [data-submission-id]').first.click();expect(p.locator('#review-image')).to_be_visible()
   proof=p.evaluate('({width:innerWidth,height:innerHeight,outerWidth,outerHeight,availableHeight:screen.availHeight,scrollWidth:document.documentElement.scrollWidth})')
   assert proof['outerHeight']<=proof['availableHeight'] and proof['scrollWidth']<=proof['width']
   p.screenshot(path=str(owned/('workspace-'+actor+'-review.png')))
   (owned/('workspace-'+actor+'-ready.json')).write_text(json.dumps({'opened_at':time.time(),'proof':proof}))
   while browser.is_connected() and time.time()<info['expiry_unix'] and not (owned/'stop-review').exists():
    try:
     if (owned/('logout-workspace-'+actor)).exists():
      result=p.evaluate('''async()=>{const b=await(await fetch('/api/identity/index.php/current-session',{cache:'no-store'})).json();const r=await fetch('/api/identity/index.php/logout',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':b.csrf_token},body:JSON.stringify({csrf_token:b.csrf_token})});return {status:r.status,body:await r.json()};}''')
      assert result['status']==200 and result['body']['state']=='LOGOUT_SUCCESS';p.goto(origin+'/public/identity/acceso.php');break
     p.wait_for_timeout(1000)
    except Exception:break
   if browser.is_connected():browser.close()
 elif action=='status':print(json.dumps({'director_url':origin+DIRECTOR,'workspace_url':origin+ADVISOR,'expires':info['expiry_unix']}))
 else:raise SystemExit('Usa open, open-director, open-advisor, credentials-director, credentials-advisor, mfa, logout-director, logout-advisor, status o stop.')
if __name__=='__main__':main()
