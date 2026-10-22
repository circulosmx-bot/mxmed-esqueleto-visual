#!/usr/bin/env python3
"""Focused new consumer decisions over real canonical HTTP sessions."""
import pathlib,sys,json,importlib.util,time,subprocess,datetime
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('closure_helpers',ROOT/'scripts/advisor-full-grants-qa01-browser.py');h=importlib.util.module_from_spec(s);s.loader.exec_module(h)
O=h.O;out=O/'closure-evidence';record=json.loads((O/'full-closure.json').read_text());fixture=json.loads((O/'closure-fixture.json').read_text());ws=json.loads((O/'workspace02-fixture.json').read_text());checks=[]
def check(name,ok,**details):
 checks.append({'name':name,'pass':bool(ok),**details});(out/'consumer-checks.json').write_text(json.dumps(checks,indent=2));print(('PASS ' if ok else 'FAIL ')+name,flush=True);assert ok,name
def onboard(p,action,data=None):
 return p.evaluate('''async ([action,data])=>{const s=await(await fetch('/api/identity/index.php/current-session')).json();const csrf=s.csrf_token;const r=await fetch('/api/identity/physician-onboarding.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action,view:'staff',csrf_token:csrf,...data})});const type=r.headers.get('content-type')||'';return {status:r.status,body:type.includes('json')?await r.json():{bytes:(await r.arrayBuffer()).byteLength}};}''',[action,data or {}])
def good(p,action,data=None):
 r=onboard(p,action,data);assert r['status']==200,(action,r);return r['body'].get('data',r['body'])
def code(p):return h.req.fresh_mfa(p,O,h.sql,record['account'])
def ceremony(p,action,data):
 c=good(p,action,data);return c

def main():
 with sync_playwright() as pw:
  b=pw.chromium.launch(headless=False);p=b.new_page(ignore_https_errors=True,viewport={'width':1440,'height':900});h.native.login(p,h.origin,record['email'],h.password)
  expect(p.locator('#provider-reviews')).to_be_visible();p.locator('#provider-reviews').click();expect(p.locator('#provider-claims article')).to_have_count(3)
  for key,decision in [('approve','APPROVED:EVIDENCE_ACCEPTED'),('reject','REJECTED:EVIDENCE_INSUFFICIENT')]:
   article=p.locator('#provider-claims article').filter(has_text='Laboratorio Sintético '+key);current=h.get(p,'/api/internal/workspace/requests.php?mode=provider_claims&request_id='+fixture['claims'][key]);assert current['status']==200,current
   if current['body']['data']['status']=='PENDING':
    article.locator('select').select_option(decision);article.locator('input[type=checkbox]').check();article.locator('button').click();expect(p.locator('#provider-feedback')).to_have_text('Decisión registrada por el servicio canónico.',timeout=15000)
   r=h.get(p,'/api/internal/workspace/requests.php?mode=provider_claims&request_id='+fixture['claims'][key]);data=r['body']['data'];check('Provider '+key+' uses canonical decision and domain journal',data['status']==decision.split(':')[0] and data['reviewed_by_account_id']==record['account'] and data['events'][-1]['actor_account_id']==record['account'])
  check('Provider case outside composition denied',h.get(p,'/api/internal/workspace/requests.php?mode=provider_claims&request_id='+fixture['claims']['outside'])['status']==403)
  check('Provider never changes physician owner',h.sql("SELECT COUNT(*) FROM auth_account_memberships WHERE assignment_source='provider_claim_approval' AND profile_doctor_id IS NOT NULL")[0][0]==0)
  p.screenshot(path=str(out/'provider-decisions.png'))
  p.goto(h.origin+'/internal/media-review/index.php?section=acreditacion');expect(p.locator('[data-onboarding]')).to_be_visible()
  # Current scope includes Aurora and the new source-only profile; Clara is excluded.
  check('Accreditation cross-physician request denied',onboard(p,'snapshot',{'request_id':ws['claim_approve']['evidence_request_id']})['status']==403)
  rid=ws['claim_ready']['evidence_request_id'];st=good(p,'snapshot',{'request_id':rid})['request']
  if st['status']!='APPROVED':
   bad=onboard(p,'review_begin',{'request_id':rid,'version':st['version'],'decision':'APPROVED','email':record['email'],'password':'incorrect-synthetic-password'});check('Accreditation wrong password denied',bad['status'] in [403,422])
   p.locator('[data-request-id="'+rid+'"]').click();expect(p.locator('[data-onboarding-form=review_begin]')).to_be_visible();p.locator('#review-email').fill(record['email']);p.locator('#review-password').fill(h.password)
   with p.expect_response(lambda r:r.url.endswith('/api/identity/physician-onboarding.php') and r.request.post_data_json.get('action')=='review_begin') as response:p.locator('[data-onboarding-form=review_begin] button[type=submit]').click()
   assert response.value.status==200,response.value.json();c=response.value.json()['data'];st=good(p,'snapshot',{'request_id':rid})['request']
   bad=onboard(p,'review_verify',{'request_id':rid,'version':st['version'],'challenge_id':c['challenge_id'],'challenge':c['challenge'],'code':'000000'});check('Accreditation invalid MFA denied',bad['status'] in [403,422])
   for button in p.locator('[data-document-list] button').all():
    button.click();expect(p.locator('[data-evidence-content] img')).to_be_visible();p.locator('[data-evidence-close]').click()
   p.locator('[data-onboarding-form=review_decide] [name=relationship_confirmed]').check();p.locator('#review-mfa').fill(code(p))
   with p.expect_response(lambda r:r.url.endswith('/api/identity/physician-onboarding.php') and r.request.post_data_json.get('action')=='review_decide') as response:p.locator('[data-onboarding-form=review_decide] button[type=submit]').click()
   assert response.value.status==200,response.value.json()
  result=good(p,'snapshot',{'request_id':rid})['request'];check('Accreditation original UI decision with exact documents and MFA',result['status']=='APPROVED');p.screenshot(path=str(out/'accreditation-approved.png'))
  app=fixture['source']['application_id'];st=good(p,'source_status',{'application_id':app})
  if st['state']=='NOT_REQUESTED':
   p.locator('[data-enrollment-application-id="'+app+'"]').click();expect(p.locator('[data-source-form=begin]')).to_be_visible();form=p.locator('[data-source-form=begin]');form.locator('[name=source_action]').select_option('COLLECT');form.locator('[name=email]').fill(record['email']);form.locator('[name=password]').fill(h.password)
   with p.expect_response(lambda r:r.url.endswith('/api/identity/physician-onboarding.php') and r.request.post_data_json.get('action')=='source_begin') as response:form.locator('button[type=submit]').click()
   assert response.value.status==200,response.value.json();c=response.value.json()['data']
   check('Source invalid MFA denied',onboard(p,'source_verify',{**{k:c[k] for k in ['case_id','challenge_id','challenge']},'code':'000000'})['status'] in [403,422])
   form=p.locator('[data-source-form=confirm]');form.locator('[name=code]').fill(code(p))
   with p.expect_response(lambda r:r.url.endswith('/api/identity/physician-onboarding.php') and r.request.post_data_json.get('action')=='source_decide') as response:form.locator('button[type=submit]').click()
   assert response.value.status==200,response.value.json()
  st=good(p,'source_status',{'application_id':app})
  if st['state']=='COLLECTING':
   x=subprocess.run(['php','scripts/advisor-capability-closure02-fixture.php','upload'],cwd=ROOT,env=h.E,input='{}',text=True,capture_output=True);assert x.returncode==0,x.stderr
   st=good(p,'source_status',{'application_id':app})
   for f in st['files']:check('Source protected document '+f['kind'],good(p,'source_document',{'application_id':app,'file_id':f['file_id']})['bytes']>0)
   facts=dict(given_names='Profesional',first_surname='Sintético',second_surname='Closure',license_number='SYNTHETIC-CLOSURE02',professional_area_label='Medicina sintética',institution_name='Institución sintética',identity_source='Revisión sintética',identity_reference='synthetic-closure-identity',identity_procedure='OFFICIAL_REGISTRY_HUMAN_CHECK',credential_source='Revisión sintética',credential_reference='synthetic-closure-credential',credential_procedure='DIRECT_ISSUING_INSTITUTION_CONFIRMATION',verified_at=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S'),verification_outcome='VERIFIED',relationship_confirmed=True)
   c=good(p,'source_begin',{'application_id':app,'source_action':'AUTHORIZE','facts':facts,'email':record['email'],'password':h.password});proof=good(p,'source_verify',{**{k:c[k] for k in ['case_id','challenge_id','challenge']},'code':code(p)});good(p,'source_decide',{'case_id':c['case_id'],'proof_id':proof['proof_id'],'proof':proof['proof']})
  st=good(p,'source_status',{'application_id':app})
  if st['state']=='FROZEN':good(p,'source_execute',{'application_id':app})
  check('Source provision uses original VID01/CRD02 writers',good(p,'source_status',{'application_id':app})['state']=='COMPLETE')
  check('Source scope forbids another physician',onboard(p,'source_status',{'application_id':ws['claim_approve']['application_id']})['status'] in [403,409])
  p.screenshot(path=str(out/'source-complete.png'));b.close()
if __name__=='__main__':main()
