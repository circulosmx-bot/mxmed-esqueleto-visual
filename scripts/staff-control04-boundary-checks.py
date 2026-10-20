#!/usr/bin/env python3
"""Exact boundary regressions; preserves demonstration cases and audit history."""
import pathlib,json,sys,importlib.util,uuid,time,subprocess
import pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def run(path):
 review=module('boundary_review',ROOT/'scripts/staff-control04-review.py');owned,approval=review.owned_configuration(path);info,origin,expiry,record=review.verified_preview(owned,approval)
 env=json.loads((owned/'review-env.json').read_text());db=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'],autocommit=True)
 def sql(q,args=()):
  with db.cursor() as c:c.execute(q,args);return c.fetchall()
 assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==owned/'mysql'
 helper=module('boundary_wf',ROOT/'scripts/ase-adm02-oper09-browser.py');native=module('boundary_login',ROOT/'scripts/ase-advisor-workspace-review.py');wf=helper.wf
 results={'checks':[]}
 def check(name,ok):results['checks'].append({'name':name,'pass':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True);(ROOT/'docs/evidence/staff-control04/boundaries.json').write_text(json.dumps(results,indent=2));assert ok,name
 def good(p,action,body):
  r=wf(p,action,body);assert r['status']==200,(action,r['status']);return r['body']
 with sync_playwright() as pw:
  browser=pw.chromium.launch();p=browser.new_page(ignore_https_errors=True)
  native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
  pending=json.loads((owned/'staff04-pending.json').read_text())['case'];case=good(p,'case',{'case_id':pending});v=good(p,'intentions',{'case_id':pending})
  b={'case_id':pending,'expected_case_version':case['version'],'expected_version':v['version'],'capabilities':['media_review_read'],'operation_id':str(uuid.uuid4())}
  responses=p.evaluate('''async inputs=>{const u='/api/internal/staff-admission/workflow.php';const boot=await(await fetch(u,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'bootstrap',input:{}})})).json();return Promise.all(inputs.map(async input=>{const r=await fetch(u,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':boot.csrf_token},body:JSON.stringify({action:'prepare_intentions',input})});return r.status;}));}''',[b,{**b,'operation_id':str(uuid.uuid4()),'capabilities':[]}])
  check('Concurrent intention edits serialize: one commit and one conflict',sorted(responses)==[200,409])
  v=good(p,'intentions',{'case_id':pending});good(p,'prepare_intentions',{'case_id':pending,'expected_case_version':case['version'],'expected_version':v['version'],'capabilities':['media_review_read'],'operation_id':str(uuid.uuid4())})
  # Preserve Lucia as invited pending for the Director's own simulation.
  inv=good(p,'invitation_status',{'case_id':pending})
  if not inv['invitation']:good(p,'issue',{'case_id':pending,'expected_version':case['version'],'operation_id':str(uuid.uuid4()),'recipient_email':'lucia_staff04@example.test'})
  check('Pending visual candidate has invitation and intentions but no account binding',not good(p,'case',{'case_id':pending})['account_bound'])
  # Fresh simulation on final routing; separate from the earlier recovered demonstration.
  demo=owned/'staff04-simulation-final.json'
  if not demo.exists():
   c=good(p,'prepare',{'operation_id':str(uuid.uuid4()),'recipient_reference':str(uuid.uuid4()),'channel':'ASSISTED','proposed_category':'ADVISOR','given_names':'Candela Ficticia','first_surname':'Revision','second_surname':'','operation_state':'Aguascalientes','operation_city':'Aguascalientes'})
   good(p,'issue',{'case_id':c['case_id'],'expected_version':c['version'],'operation_id':str(uuid.uuid4()),'recipient_email':'candela_staff04@example.test'});demo.write_text(json.dumps({'case':c['case_id']}))
  case_id=json.loads(demo.read_text())['case'];p.reload();p.locator('[data-case="'+case_id+'"]').click();expect(p.locator('#simulate-onboarding')).to_be_visible();p.locator('#simulate-onboarding').click()
  expect(p.locator('#staff-case-status')).to_contain_text('Solicitante ficticio vinculado',timeout=90000)
  job=json.loads((owned/('simulation-'+case_id+'.json')).read_text());check('Fresh simulation completes via visible button with no recovery or identity bypass',job['state']=='REVIEW_REQUIRED')
  before=sql('SELECT COUNT(*) FROM auth_accounts')[0][0];again=good(p,'simulate',{'case_id':case_id});check('Repeated simulation reconciles same job without duplicate account',again['state']=='REVIEW_REQUIRED' and before==sql('SELECT COUNT(*) FROM auth_accounts')[0][0])
  check('Fresh simulation leaves review, admission and grants to explicit Director action',not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(job['account_id'],)) and not sql('SELECT grant_id FROM internal_operator_grants WHERE account_id=%s',(job['account_id'],)))
  check('Simulation request and applicant evidence preserve distinct audit provenance',bool(job.get('audit_event_id')) and job.get('requested_by')=='qa_director' and sql('SELECT actor_account_id FROM auth_staff_document_sets WHERE case_id=%s',(case_id,))[0][0]==job['account_id'])
  p.locator('#close-staff-case').click()
  # Exact activation replay must not re-grant a subsequently revoked permission.
  v=good(p,'intentions',{'case_id':record['case']});activation={'case_id':record['case'],'expected_case_version':v['case_version'],'expected_version':v['version'],'capability':'media_review_approve'}
  def team(operation,cap):
   return p.evaluate('''async b=>{const csrf=await(await fetch('/api/internal/team/csrf.php',{cache:'no-store',referrerPolicy:'same-origin'})).json();const r=await fetch('/api/internal/team/capability.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf.csrf_token},body:JSON.stringify(b)});return {status:r.status,body:await r.json()};}''',{'operation':operation,'target':record['account'],'capability':cap})
  result=team('revoke','media_review_approve');assert result['status']==200,result
  replay=good(p,'activate_intention',activation)
  check('Activation replay does not restore a subsequently revoked grant',replay.get('replayed') and not sql("SELECT grant_id FROM internal_operator_grants WHERE account_id=%s AND capability='media_review_approve' AND status='ACTIVE'",(record['account'],)))
  result=team('grant','media_review_approve');assert result['status']==200,result
  check('Explicit canonical Director writer restores only intended test permission',len(sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE'",(record['account'],)))==2)
  check('Admission precedes all effective grant rows in the synthetic demonstration',sql('SELECT COUNT(*) FROM internal_operator_grants g JOIN auth_staff_admission_completions c ON c.account_id=g.account_id WHERE g.account_id=%s AND g.created_at<c.created_at',(record['account'],))[0][0]==0)
  browser.close()
 db.close();results['result']='PASS';(ROOT/'docs/evidence/staff-control04/boundaries.json').write_text(json.dumps(results,indent=2))
if __name__=='__main__':run(sys.argv[1])
