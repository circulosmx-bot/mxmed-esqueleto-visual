#!/usr/bin/env python3
import pathlib,sys,importlib.util,json,subprocess
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def load(name,path):
 s=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
c=load('closure_security_helpers','scripts/advisor-capability-closure02-browser.py');t=load('closure_consumer_helpers','scripts/advisor-capability-closure02-consumers.py');h=c.h;O=h.O;record=json.loads((O/'full-closure.json').read_text());summary=json.loads((O/'full-summary.json').read_text());f=json.loads((O/'closure-fixture.json').read_text());ws=json.loads((O/'workspace02-fixture.json').read_text());checks=[]
def check(n,ok):
 checks.append({'name':n,'pass':bool(ok)});(O/'closure-evidence/security.json').write_text(json.dumps(checks,indent=2));print(('PASS ' if ok else 'FAIL ')+n,flush=True);assert ok,n
with sync_playwright() as pw:
 b=pw.chromium.launch(headless=False);d=b.new_page(ignore_https_errors=True);h.native.login(d,h.origin,'qa_director@example.test',h.dp,h.native.DIRECTOR);a=b.new_page(ignore_https_errors=True);h.native.login(a,h.origin,record['email'],h.password)
 cookie=next(v['value'] for v in a.context.cookies() if v['name']=='__Host-mxmed_session');caps={x[0] for x in h.sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE' AND revoked_at IS NULL",(record['account'],))}
 for cap in ['physician_accreditation_review','professional_source_provision','provider_claim_review']:
  c.grants(d,record,caps-{cap})
  if cap=='physician_accreditation_review':
   check('Source grant cannot open C4 evidence',t.onboard(a,'snapshot',{'request_id':ws['claim_ready']['evidence_request_id']})['status']==403)
   check('Source-only canonical queue remains available',t.onboard(a,'snapshot')['status']==200)
  elif cap=='professional_source_provision':check('Accreditation grant cannot read protected source documents',t.onboard(a,'source_status',{'application_id':f['source']['application_id']})['status']==403)
  else:check('Revoked provider grant direct read denied',h.get(a,'/api/internal/workspace/requests.php?mode=provider_claims&request_id='+f['claims']['demo'])['status']==403)
  check('Revocation keeps exact same Advisor session '+cap,next(v['value'] for v in a.context.cookies() if v['name']=='__Host-mxmed_session')==cookie)
  check('Independent Media authority preserved '+cap,h.get(a,'/api/internal/workspace/read.php?doctor_id=workspace_aurora')['status']==200)
  c.grants(d,record,caps)
 check('Reserved internal_advisors_manage HTTP denied',h.get(a,'/api/internal/team/csrf.php')['status']==403)
 check('Mixed physician selector cannot be used in organization reader',h.get(a,'/api/internal/workspace/requests.php?mode=provider_claims&doctor_id=workspace_aurora')['status']==400)
 csrf=h.get(a,'/api/internal/workspace/requests.php?mode=review_access')['body']['data']['csrf_token']
 rejected=a.evaluate('''async ([csrf,id])=>{const r=await fetch('/api/internal/workspace/requests.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'invalid'},body:JSON.stringify({action:'provider_decide',input:{request_id:id,decision:'APPROVED',reason:'EVIDENCE_ACCEPTED'}})});return r.status;}''',[csrf,f['claims']['demo']]);check('Provider mutation invalid CSRF denied',rejected==403)
 check('Preserved unconsumed provider demonstration',h.get(a,'/api/internal/workspace/requests.php?mode=provider_claims&request_id='+f['claims']['demo'])['body']['data']['status']=='PENDING')
 # One-time preparation of a complete demonstration, not a second 14-capability exercise.
 allcaps=[*h.CAPS,'professional_source_provision','physician_accreditation_review','provider_claim_review'];c.grants(d,record,allcaps)
 config=json.loads((O/'administration-source.json').read_text());config['staff'][record['account']]={k:['1'] for k in ['physician_administrative_read','physician_locations_read','physician_operator_support_read']};(O/'administration-source.json').write_text(json.dumps(config))
 check('Original restricted reader remains read-only and exact-resource',h.admin(a)['status']==200 and h.admin(a,ref='mxmed-local-development:2')['status']==403)
 a.reload();expect(a.locator('#professional-reviews')).to_be_visible();expect(a.locator('#provider-reviews')).to_be_visible();h.choose(a,'Aurora','workspace_aurora');a.locator('#tab-images').click();expect(a.locator('#images-panel')).to_be_visible();check('Existing Advisor navigation preserved',True);a.screenshot(path=str(O/'closure-evidence/final-workspace.png'))
 (O/'oper09-advisor.json').write_text(json.dumps(record));b.close()
x=subprocess.run(['php','scripts/advisor-capability-closure02-reserved.php'],cwd=ROOT,env=h.E,input='{}',text=True,capture_output=True);print(x.stdout,flush=True);assert x.returncode==0,x.stderr
