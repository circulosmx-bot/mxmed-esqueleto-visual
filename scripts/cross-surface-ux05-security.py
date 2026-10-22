#!/usr/bin/env python3
"""Focused real HTTP/UI tests. Evidence remains owned/private, never committed personal data."""
import pathlib,json,sys,importlib.util,concurrent.futures,ssl,urllib.request,urllib.error
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];o=pathlib.Path(sys.argv[1]).resolve();assert o.parent==pathlib.Path('/private/tmp') and o.name.startswith('mxmed-ase-adm02imp01-')
config=json.loads((o/'administration-source.json').read_text());origin=json.loads((o/'workspace-runtime.json').read_text())['origin'];ref=config['origin']+':'+config['staff']['qa_target']['physician_administrative_read'][0]
out=o/'ux05-evidence'/'portrait-security';out.mkdir(parents=True,exist_ok=True);checks=[]
def check(n,v):
 checks.append({'name':n,'pass':bool(v)});(out/'checks.json').write_text(json.dumps(checks,indent=2));print(('PASS ' if v else 'FAIL ')+n,flush=True);assert v,n
spec=importlib.util.spec_from_file_location('native',ROOT/'scripts/ase-advisor-workspace-review.py');native=importlib.util.module_from_spec(spec);spec.loader.exec_module(native)
password=(o/'fixture-password').read_text().strip()
def api(page,mode='admin_summary',**values):
 from urllib.parse import urlencode
 if mode!='admin_search' and 'profile_ref' not in values:values['profile_ref']=ref
 return page.evaluate('async path=>{const r=await fetch(path,{cache:"no-store"});return {status:r.status,body:await r.json()}}','/api/internal/workspace/requests.php?'+urlencode({'mode':mode,**values}))
def grant(d,cap,on):
 d.goto(origin+'/internal/media-review/index.php?section=equipo');expect(d.locator('#filter-advisors')).to_be_enabled();d.locator('#filter-advisors').click();expect(d.locator('#directory-content')).to_have_attribute('aria-busy','false');d.locator('[data-member="qa_target"]').click();box=d.locator('[data-capability="'+cap+'"]')
 for det in box.locator('xpath=ancestor::details').all():
  if not det.evaluate('(e)=>e.open'):det.locator('summary').first.click()
 if not box.is_visible():d.locator('[data-expand="use-'+('operators' if cap=='physician_operator_support_read' else 'profiles')+'"]').click()
 expect(box).to_be_enabled();box.set_checked(on)
 if d.locator('#save-permissions').is_enabled():d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=30000)
 check('Director canonical '+('grant ' if on else 'revoke ')+cap,True)
def select(a):
 a.goto(origin+'/internal/staff-workspace/index.php');expect(a.locator('#staff-role')).to_have_text('Asesor');expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000)
 a.locator('#physician-search').fill('Leticia');a.locator('#physician-search-form').evaluate('(f)=>f.requestSubmit()');a.locator('[data-doctor-id="'+ref+'"]').click();expect(a.locator('#physician-name')).to_have_text('Leticia Muñoz Romo',timeout=10000)
def search(p,text):
 if p.locator('#change-physician').is_visible():p.locator('#search-toggle').click()
 p.locator('#physician-search').fill(text);p.locator('#physician-search-form').evaluate('(f)=>f.requestSubmit()')
 expect(p.locator('#physician-workspace')).to_be_hidden()
 expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000)
def choose(p,identifier):
 p.locator('#discovery-list [data-doctor-id="'+identifier+'"]').first.click();expect(p.locator('#physician-workspace')).to_be_visible()
def locations(p):
 p.locator('#tab-offices').click();expect(p.locator('#offices-content article')).to_have_count(3,timeout=20000)
from urllib.parse import urlencode
with sync_playwright() as pw:
 b=pw.chromium.launch(headless=False);d=b.new_page(ignore_https_errors=True);a=b.new_page(ignore_https_errors=True)
 native.login(d,origin,'qa_director@example.test',password,'/internal/media-review/index.php?section=equipo');native.login(a,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php')
 def photo(page,reference=ref,**params):return page.request.get(origin+'/api/internal/workspace/requests.php?'+urlencode({'mode':'admin_portrait','profile_ref':reference,**params}))
 r=photo(a);check('Photo current scope returns supported public binary',r.status==200 and r.headers['content-type'].startswith('image/') and len(r.body())<2097152)
 check('Photo response not cached',r.headers.get('cache-control')=='private, no-store')
 check('Photo wrong source denied',photo(a,'other:1').status==403)
 check('Photo unauthorized ID denied',photo(a,config['origin']+':2').status==403)
 check('Photo arbitrary URL parameter denied',photo(a,url='http://127.0.0.1:8091/').status==403)
 unauth=b.new_page(ignore_https_errors=True);check('Photo without Identity session denied',photo(unauth).status==403);unauth.close()
 grant(d,'physician_administrative_read',False);check('Photo live grant revocation denied',photo(a).status==403);grant(d,'physician_administrative_read',True)
 saved=(o/'administration-source.json').read_bytes();revoked=json.loads(saved);revoked['staff']['qa_target']['physician_administrative_read']=[]
 try:(o/'administration-source.json').write_text(json.dumps(revoked));check('Photo resource scope revocation denied',photo(a).status==403)
 finally:(o/'administration-source.json').write_bytes(saved)
 search(a,'Aurora');choose(a,'workspace_aurora');expect(a.locator('#physician-avatar')).to_be_visible();check('Synthetic context does not retain original photograph',not a.locator('#physician-portrait').get_attribute('src'))
 search(a,'Leticia');choose(a,ref);locations(a);expect(a.locator('#physician-portrait')).to_be_visible();expect(a.locator('#physician-avatar')).to_be_hidden()
 a.locator('#development-interface-index > summary').click();a.locator('[data-copy-review="review-command-director"]').click();expect(a.locator('#review-command-feedback')).to_contain_text('Comando copiado');check('Director independent launcher command copy works',True)
 check('Index command targets current task', 'cross-surface-ux05-review.py' in a.locator('#review-command-director').inner_text())
 b.close()
print('UX05_SECURITY_PASS',len(checks),flush=True)
