#!/usr/bin/env python3
"""Only closure checks; existing admission helpers, no historical consumer exercise."""
import sys,pathlib,importlib.util,json,time,hashlib
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('closure_helpers',ROOT/'scripts/advisor-full-grants-qa01-browser.py');h=importlib.util.module_from_spec(s);s.loader.exec_module(h)
O=h.O;out=O/'closure-evidence';out.mkdir(exist_ok=True);checks=[]
def check(name,ok,**details):
 checks.append({'name':name,'pass':bool(ok),**details});(out/'checks.json').write_text(json.dumps(checks,indent=2));print(('PASS ' if ok else 'FAIL ')+name,flush=True);assert ok,name
def grants(d,r,desired):
 h.select(d,r)
 for family in ['requests','media','accreditation','providers','operators','profiles']:
  if not d.locator('#use-'+family).is_visible():d.locator('[data-expand="use-'+family+'"]').click()
 changed=False
 for cap in [*h.CAPS,'professional_source_provision','physician_accreditation_review','provider_claim_review']:
  box=d.locator('[data-capability="'+cap+'"]');expect(box).to_be_enabled()
  if box.is_checked()!=(cap in desired):box.set_checked(cap in desired);changed=True
 if changed:d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=30000)
 actual={x[0] for x in h.sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE' AND revoked_at IS NULL",(r['account'],))};check('Canonical UI grants '+r['email'],actual==set(desired))
def main():
 with sync_playwright() as pw:
  b=pw.chromium.launch(headless=False);d=b.new_page(ignore_https_errors=True,viewport={'width':1440,'height':900});h.native.login(d,h.origin,'qa_director@example.test',h.dp,h.native.DIRECTOR)
  demo=h.admitted(d,'closure');summary=h.admitted(d,'summary')
  h.native.login(d,h.origin,'qa_director@example.test',h.dp,h.native.DIRECTOR)
  grants(d,demo,['media_review_read','professional_source_provision','physician_accreditation_review','provider_claim_review']);grants(d,summary,['media_review_read','physician_pending_summary_read']);h.enroll(demo)
  c=json.loads((O/'closure-scope.json').read_text());fixtures=json.loads((O/'workspace02-fixture.json').read_text());c['accounts']=[demo['account'],summary['account'],*map(lambda v:v['account_id'],fixtures.values())];c['profiles']=['workspace_aurora'];c['staff']={demo['account']:['professional_source_provision','physician_accreditation_review']};(O/'closure-scope.json').write_text(json.dumps(c))
  a=b.new_page(ignore_https_errors=True);h.native.login(a,h.origin,summary['email'],h.password);expect(a.locator('#staff-role')).to_have_text('Asesor')
  cookie=next(v['value'] for v in a.context.cookies() if v['name']=='__Host-mxmed_session')
  before=h.get(a,'/api/internal/workspace/requests.php?mode=pending&doctor_id=workspace_aurora');assert before['status']==200,before
  before=before['body']['data'];check('Summary adds request counts without request detail authority',before['summary_authorized'] and before['context']['sections']['requests']['summary_only'] and before['context']['sections']['requests']['visible_count']>0 and not before['context']['permissions']['requests'])
  grants(d,summary,['media_review_read'])
  after=h.get(a,'/api/internal/workspace/requests.php?mode=pending&doctor_id=workspace_aurora');assert after['status']==200,after
  after=after['body']['data'];check('Same session revocation removes protected request fields, retains media fields',not after['summary_authorized'] and 'requests' not in after['context']['sections'] and after['context']['sections']['images']==before['context']['sections']['images'] and next(v['value'] for v in a.context.cookies() if v['name']=='__Host-mxmed_session')==cookie)
  for mode in ['read','pending']:
   check('Direct application access denied after revocation '+mode,h.get(a,'/api/internal/workspace/requests.php?mode='+mode+'&request_id='+fixtures['claim_ready']['application_id'])['status']==403)
  check('Summary outside independently authorized physician denied',h.get(a,'/api/internal/workspace/requests.php?mode=pending&doctor_id=workspace_out_of_scope')['status']==403)
  (out/'summary-fields.json').write_text(json.dumps({'before':before,'after':after,'same_session':True},indent=2))
  h.native.login(a,h.origin,demo['email'],h.password);expect(a.locator('#professional-reviews')).to_be_visible();expect(a.locator('#provider-reviews')).to_be_visible();a.screenshot(path=str(out/'workspace-consumers.png'))
  a.locator('#professional-reviews').click();expect(a.locator('[data-onboarding]')).to_be_visible();a.screenshot(path=str(out/'accreditation.png'));check('Original accreditation/source screen reachable from Advisor',True)
  b.close()
if __name__=='__main__':main()
