#!/usr/bin/env python3
"""Explicit controls for a PANEL07 owner-verified loopback synthetic review only."""
import pathlib,json,os,sys,time,subprocess,hashlib,hmac,struct,re
ROOT=pathlib.Path(__file__).resolve().parents[1]
owned=pathlib.Path(sys.argv[1]).resolve();action=sys.argv[2] if len(sys.argv)>2 else 'status'
os.umask(0o077)
assert owned.parent==pathlib.Path('/private/tmp') and re.fullmatch('mxmed-ase-adm02imp01-[A-Za-z0-9_]+',owned.name)
assert owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
approval=json.loads((owned/'workflow-approval.json').read_text());info=json.loads((owned/'review-info.json').read_text())
assert approval['root']==str(ROOT) and info['expiry_unix']>time.time() and approval['expires']>time.time()
assert (owned/'panel07-head').read_text().strip()==subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
assert all(hashlib.sha256((ROOT/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
origin=info['url'].split('/internal/')[0];assert re.fullmatch(r'https://127\.0\.0\.1:[0-9]+',origin)
if action=='stop':
 subprocess.run(['python3',str(ROOT/'scripts/ase-adm02-workflow-review.py'),str(owned),'--stop'],check=True)
elif action=='credentials':
 # Only on explicit reviewer invocation. No raw secret in stdout, HTML, Git or a prefilled field.
 subprocess.run(['pbcopy'],input=(owned/'fixture-password').read_bytes().strip(),check=True)
 print('Contraseña ficticia copiada. En el login escribe qa_director@example.test y pégala manualmente.')
elif action=='mfa':
 v=hmac.new((owned/'workflow-totp-secret').read_bytes(),struct.pack('>Q',int(time.time())//30),hashlib.sha1).digest();n=v[-1]&15
 print(str((int.from_bytes(v[n:n+4],'big')&0x7fffffff)%1000000).zfill(6))
elif action=='open':
 with (owned/'panel07-browser.log').open('ab') as log:subprocess.Popen([sys.executable,__file__,str(owned),'browser'],stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
 print('Abriendo la revisión sintética mediante login Identity. El servicio no se recrea ni se prolonga.')
elif action=='browser':
 from playwright.sync_api import sync_playwright,expect
 records=json.loads((owned/'uxfunc02-records.json').read_text())
 with sync_playwright() as pw:
  browser=pw.chromium.launch(headless=False);ctx=browser.new_context(ignore_https_errors=True,viewport={'width':1366,'height':900});p=ctx.new_page()
  p.goto(origin+'/public/identity/acceso.php?next=%2Finternal%2Fmedia-review%2Findex.php%3Fsection%3Dequipo')
  p.locator('#login-email').fill('qa_director@example.test');p.locator('#login-password').fill((owned/'fixture-password').read_text().strip());p.locator('[data-submit]').click();p.wait_for_url('**/index.php?section=equipo')
  expect(p.locator('#add-member')).to_be_enabled();p.locator('[data-member="'+records['bruno']['account']+'"]').click();expect(p.locator('#detail-title')).to_contain_text('Bruno');p.locator('#personal-data summary').click()
  (owned/'panel07-browser-ready.json').write_text(json.dumps({'chromium_opened':True,'canonical_login':True,'active_advisor_selected':True,'personal_section_open':True,'opened_at':time.time()}))
  p.screenshot(path=str(owned/'panel07-review.png'))
  while browser.is_connected() and time.time()<info['expiry_unix'] and not (owned/'stop-review').exists():
   try:p.wait_for_timeout(1000)
   except Exception:break
  if browser.is_connected():browser.close()
elif action=='status':print(json.dumps({'url':info['url'],'expires':info['expiry_unix']}))
else:raise SystemExit('Usa status, open, credentials, mfa o stop.')
