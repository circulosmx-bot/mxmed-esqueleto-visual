#!/usr/bin/env python3
"""One existing Requests decision, scoped summary and history; owned synthetic only."""
import pathlib,sys,json,subprocess,importlib.util
import pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
def run(path):
 review=module('requests_review',ROOT/'scripts/staff-control04-review.py')
 owned,approval=review.owned_configuration(path);info,origin,expiry,record=review.verified_preview(owned,approval)
 env=json.loads((owned/'review-env.json').read_text())
 conn=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'],autocommit=True)
 def sql(q,args=()):
  with conn.cursor() as c:c.execute(q,args);return c.fetchall()
 assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==owned/'mysql'
 def cli(action):
  p=subprocess.run(['php','scripts/ase-advisor-workspace02-fixture.php',action],cwd=ROOT,env=env,text=True,capture_output=True)
  assert p.returncode==0,'canonical_fixture_failed'
  result=json.loads(p.stdout);assert result.get('ok'),'canonical_fixture_rejected'
 # Existing fixture creates fictional requests; it explicitly makes Aurora active/FREE.
 # This is fixture setup, not an effect of the preceding Advisor media approval.
 if not (owned/'workspace02-fixture.json').exists():cli('prepare')
 fixture=json.loads((owned/'workspace02-fixture.json').read_text())
 helpers=module('requests_existing',ROOT/'scripts/ase-advisor-workspace03-browser.py');ws=helpers.ws
 results={'checks':[],'fixture_note':'Existing WORKSPACE02 prepare sets synthetic Aurora active/FREE; not a Media decision effect.'}
 out=ROOT/'docs/evidence/staff-control04/requests-smoke.json'
 def check(name,ok):
  results['checks'].append({'name':name,'pass':bool(ok)});out.write_text(json.dumps(results,indent=2));print(('PASS ' if ok else 'FAIL ')+name,flush=True);assert ok,name
 with sync_playwright() as pw:
  b=pw.chromium.launch();d=b.new_page(ignore_https_errors=True);a=b.new_page(ignore_https_errors=True)
  ws.review.login(d,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR);ws.old.selected(d,record)
  if not d.locator('#use-requests').is_visible():d.locator('[data-expand="use-requests"]').click()
  caps=['physician_request_read','physician_request_evidence_read','physician_new_profile_resolve','physician_pending_summary_read']
  for cap in caps:d.locator('[data-capability="'+cap+'"]').check()
  d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=25000)
  check('Director explicitly grants four Requests/summary capabilities through existing UI',all(sql("SELECT grant_id FROM internal_operator_grants WHERE account_id=%s AND capability=%s AND status='ACTIVE'",(record['account'],cap)) for cap in caps))
  if not sql('SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id=%s',(record['account'],)):cli('enroll_advisor')
  password=(owned/'applicant-password').read_text().strip()
  ws.review.login(a,origin,record['email'],password,review.ADVISOR);expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false')
  grouped=helpers.pending(a,'workspace_aurora')['context']
  check('Existing physician groups combine authorized Requests and Media',grouped['sections']['requests']['visible_count']>=1 and grouped['sections']['images']['visible_count']>=1)
  rid=fixture['information']['application_id'];helpers.open_request(a,rid);ws.inspect_evidence(a)
  code=ws.fresh_mfa(a,owned,sql,record['account']);a.locator('#request-information').click()
  a.locator('#request-reason').fill('Solicitar aclaración ficticia STAFF-CONTROL04.');a.locator('#request-password').fill(password);a.locator('#request-mfa').fill(code);a.locator('#request-confirmation').check();a.locator('#confirm-request-decision').click()
  expect(a.locator('#request-status')).to_have_text('Información solicitada',timeout=30000)
  current=ws.snapshot(a,rid)['request'];pending=helpers.pending(a,application=rid)['context']
  check('Existing canonical decision requires password/MFA and leaves applicant response pending',current['state']=='NEEDS_INFORMATION' and current['waiting_applicant'] and not current['actionable'] and pending['actionable_count']==0)
  helpers.tab(a,'history');expect(a.locator('.history-entry')).not_to_have_count(0)
  check('Request decision remains in authorized canonical history',rid in a.locator('#history-list').inner_text())
  check('Advisor cannot use Director administration',ws.previous.read(a,'/api/internal/team/read.php')['status']==403)
  check('Cross-physician request context is rejected',ws.previous.read(a,ws.API+'?mode=pending&doctor_id=workspace_bruno&request_id='+fixture['claim_ready']['application_id'])['status'] in [403,409])
  a.screenshot(path=str(out.parent/'screens/requests-history.png'));b.close()
 conn.close();results['result']='PASS';out.write_text(json.dumps(results,indent=2))
if __name__=='__main__':run(sys.argv[1])
