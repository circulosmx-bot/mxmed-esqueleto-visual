#!/usr/bin/env python3
"""Focused UI grants and cumulative revocation, current-session server probes.
Never writes grants, staff, admission or original records through SQL.
"""
import pathlib,sys,json,importlib.util,uuid,time,subprocess,os
import pymysql
from playwright.sync_api import sync_playwright,expect
os.umask(0o077)
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(n,p):
 s=importlib.util.spec_from_file_location(n,p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
native=module('fg_native',ROOT/'scripts/ase-advisor-workspace-review.py');wf=module('fg_wf',ROOT/'scripts/ase-adm02-oper09-browser.py');req=module('fg_req',ROOT/'scripts/ase-advisor-workspace02-browser.py');media=req.previous
O=pathlib.Path(sys.argv[1]).resolve();assert O.parent==pathlib.Path('/private/tmp') and O.name.startswith('mxmed-ase-adm02imp01-')
E=json.loads((O/'review-env.json').read_text());origin=json.loads((O/'workspace-runtime.json').read_text())['origin'];OUT=O/'full-grants-evidence';OUT.mkdir(exist_ok=True)
DB=pymysql.connect(unix_socket=str(O/'mysql.sock'),user='ase_fixture',password=E['ASE_INT02A_FIXTURE_PASSWORD'],database=E['MXMED_DB_NAME'],autocommit=True)
def sql(q,args=()):
 assert q.lstrip().upper().startswith(('SELECT','SHOW')), 'read-only QA SQL'
 with DB.cursor() as c:c.execute(q,args);return c.fetchall()
assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==O/'mysql'
MATRIX=json.loads((ROOT/'docs/evidence/advisor-full-grants-qa01/capability-matrix.json').read_text());CAPS=[v['id'] for v in MATRIX['capabilities'] if v['connected_in_review']]
checks=[]
def check(n,ok,**details):
 checks.append(dict(name=n,pass_=bool(ok),**details));(OUT/'checks.json').write_text(json.dumps(checks,indent=2));print(('PASS ' if ok else 'FAIL ')+n,flush=True);assert ok,n
password=(O/'applicant-password').read_text().strip();dp=(O/'fixture-password').read_text().strip()
def cli(action):
 x=subprocess.run(['php','scripts/ase-advisor-workspace02-fixture.php',action],cwd=ROOT,env=E,input='{}',text=True,capture_output=True)
 if x.returncode:(O/'full-cli-error.log').write_text(x.stderr);raise RuntimeError('fixture failed')
 return json.loads(x.stdout)
def good(p,action,body=None):
 r=wf.wf(p,action,body or {});assert r['status']==200,(action,r['status'],r['body']);return r['body']
def admitted(p,key):
 path=O/('full-'+key+'.json')
 if path.exists():return json.loads(path.read_text())
 email='full_grants_'+key+'@example.test';c=good(p,'prepare',dict(operation_id=str(uuid.uuid4()),recipient_reference=str(uuid.uuid4()),channel='ASSISTED',proposed_category='ADVISOR',given_names='Asesor Sintético',first_surname='Revocación' if key=='test' else 'Demostración',second_surname='QA01',operation_state='Aguascalientes',operation_city='Calvillo'))
 good(p,'issue',dict(case_id=c['case_id'],expected_version=c['version'],operation_id=str(uuid.uuid4()),recipient_email=email));good(p,'simulate',{'case_id':c['case_id']})
 deadline=time.time()+100
 while time.time()<deadline:
  state=good(p,'simulation_status',{'case_id':c['case_id']})
  if state['state']=='REVIEW_REQUIRED':break
  p.wait_for_timeout(500)
 assert state['state']=='REVIEW_REQUIRED',state
 for action in ['READ','ACK','ACCEPT','ADMIT']:
  case=good(p,'case',{'case_id':c['case_id']});v=good(p,'view',{'case_id':c['case_id']})
  auth=good(p,'authorize',dict(case_id=c['case_id'],set_id=v['current_set_id'],action=action,reason='Verificación sintética explícita QA01',expected_version=case['version'],expected_review_id=v['review']['review_id'] if v['review'] else None))
  body={'intent_id':auth['intent_id']}
  if action=='ADMIT':body['case_id']=c['case_id']
  good(p,{'READ':'evidence','ACK':'acknowledge','ACCEPT':'decide','ADMIT':'admit'}[action],body)
 account=sql('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s',(c['case_id'],))[0][0]
 record=dict(account=account,email=email,case=c['case_id']);path.write_text(json.dumps(record));return record

def select(d,r):
 d.goto(origin+native.DIRECTOR)
 d.wait_for_timeout(700)
 probe=get(d,'/api/internal/team/read.php?category=ADVISOR&page=1&page_size=20')
 if probe['status']!=200:
  (OUT/'director-read-failure.json').write_text(json.dumps(probe,indent=2));print('DIRECTOR_READ_STATUS',probe['status'],flush=True)
 expect(d.locator('#filter-advisors')).to_be_enabled(timeout=30000);d.locator('#filter-advisors').click();expect(d.locator('#directory-content')).to_have_attribute('aria-busy','false');d.locator('[data-member="'+r['account']+'"]').click();expect(d.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
def grants(d,r,desired):
 select(d,r)
 for family in ['requests','media','operators','profiles']:
  if not d.locator('#use-'+family).is_visible():d.locator('[data-expand="use-'+family+'"]').click()
 changed=False
 for cap in CAPS:
  box=d.locator('[data-capability="'+cap+'"]');expect(box).to_be_enabled()
  if box.is_checked()!=(cap in desired):box.set_checked(cap in desired);changed=True
 if changed:d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=30000)
 select(d,r)
 actual={x[0] for x in sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE' AND revoked_at IS NULL",(r['account'],))}
 check('UI batch persisted/reloaded '+r['email']+' '+str(len(desired)),actual==set(desired))
 return actual

def get(p,path):return media.read(p,path)
def status(p,path):return p.evaluate('async path=>(await fetch(path,{cache:"no-store"})).status',path)
def admin(p,mode='admin_summary',ref='mxmed-local-development:1'):return get(p,'/api/internal/workspace/requests.php?mode='+mode+'&profile_ref='+ref)
def choose(p,name,id):
 if p.locator('#physician-workspace').is_visible():p.locator('#search-toggle').click()
 p.locator('#physician-search').fill(name);p.locator('#physician-search-form').evaluate('(f)=>f.requestSubmit()');expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000);p.locator('#discovery-list [data-doctor-id="'+id+'"]').first.click();expect(p.locator('#physician-workspace')).to_be_visible()
def enroll(r):
 (O/'oper09-advisor.json').write_text(json.dumps(r))
 if not sql("SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id=%s AND status='ACTIVE'",(r['account'],)):assert cli('enroll_advisor').get('ok',True)

def current_tab(p,label):
 selector={'Imágenes':'#tab-images','Solicitudes':'#tab-requests','Historial':'#tab-history'}[label]
 expect(p.locator(selector)).to_be_enabled(timeout=20000)
 if not p.locator(selector).is_visible():
  p.locator('#more-toggle').click();p.locator('#more-menu [data-tab="'+selector[5:]+'"]').click()
 else:p.locator(selector).click()
media.tab=current_tab

def item(p,sid):
 expect(p.locator('#tab-images')).to_be_enabled(timeout=20000)
 p.locator('#tab-images').click()
 p.locator('#media-items [data-submission-id="'+sid+'"]').click()
 expect(p.locator('#review-image')).to_be_visible()
 expect(p.locator('#review-image')).to_have_js_property('complete',True)

def main():
 with sync_playwright() as pw:
  b=pw.chromium.launch(headless=False);d=b.new_page(ignore_https_errors=True,viewport={'width':1440,'height':900});native.login(d,origin,'qa_director@example.test',dp,native.DIRECTOR)
  test=admitted(d,'test');demo=admitted(d,'demo')
  for r in [test,demo]:
   check('Verified admission, ACTIVE Advisor, separate Identity '+r['email'],sql('SELECT governance_class,status FROM internal_staff WHERE account_id=%s',(r['account'],))[0]==('ADVISOR','ACTIVE') and sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(r['case'],))[0][0]==1)
   config=json.loads((O/'administration-source.json').read_text());config['staff'][r['account']]={k:['1'] for k in ['physician_administrative_read','physician_locations_read','physician_operator_support_read']};(O/'administration-source.json').write_text(json.dumps(config))
  # MFA enrollment uses canonical authentication; independently authenticate UI afterward.
  native.login(d,origin,'qa_director@example.test',dp,native.DIRECTOR)
  if not (OUT/'fully-granted.json').exists():
   for r in [test,demo]:grants(d,r,CAPS)
   (OUT/'fully-granted.json').write_text(json.dumps({'test':test,'demo':demo,'grants':CAPS,'not_connected':[x['id'] for x in MATRIX['capabilities'] if x['classification']=='A' and not x['connected_in_review']]},indent=2))
  for r in [test,demo]:enroll(r)
  a=b.new_page(ignore_https_errors=True,viewport={'width':1440,'height':900});native.login(a,origin,test['email'],password,'/internal/staff-workspace/index.php');expect(a.locator('#staff-role')).to_have_text('Asesor')
  run_consumers(a,test)
  a.screenshot(path=str(OUT/('chromium-resumed.png' if (OUT/'revocation.json').exists() else 'chromium-fully-granted.png')),full_page=True)
  revoke(d,a,test)
  # Full demonstration is separate; its grants were never consumed by the sequence.
  select(d,demo);d.screenshot(path=str(OUT/'director-full-demo.png'),full_page=True)
  w=pw.webkit.launch();wp=w.new_page(ignore_https_errors=True,viewport={'width':1366,'height':768});native.login(wp,origin,demo['email'],password,'/internal/staff-workspace/index.php');choose(wp,'Leticia','mxmed-local-development:1');wp.locator('#tab-offices').click();expect(wp.locator('#offices-content article')).to_have_count(3);check('WebKit full demo current grants and scoped locations',admin(wp,'admin_operators')['status']==200);wp.screenshot(path=str(OUT/'webkit-full-demo.png'),full_page=True);w.close()
  (O/'oper09-advisor.json').write_text(json.dumps(demo));(OUT/'complete-connected.json').write_text(json.dumps({'connected':14,'canonical_A':17,'result':'PARTIAL_CANONICAL_CONNECTIVITY_AND_SHARED_READER_CONTRACT'},indent=2));b.close()



def run_consumers(a,record):
 if (OUT/'consumers.json').exists():return
 fixtures=json.loads((O/'workspace-media.json').read_text());f=json.loads((O/'workspace02-fixture.json').read_text())
 def sid(doc,purpose,n=0):return sorted(x['submission_id'] for x in fixtures['items'] if x['owner_id']==doc and x['purpose']==purpose)[n]
 photo=sid('workspace_aurora','DOCTOR_PROFILE_PHOTO');gallery=sid('workspace_aurora','DOCTOR_GALLERY');replacement=sid('workspace_aurora','DOCTOR_GALLERY',1);logo=sid('workspace_bruno','PHYSICIAN_PERSONAL_LOGO')
 choose(a,'Bruno','workspace_bruno');item(a,logo)
 check('media_review_read: actual REVIEW image and exact resource',a.locator('#review-image').evaluate('(i)=>i.complete&&i.naturalWidth>0'))
 with a.expect_download() as dl:a.locator('#download-original').click()
 check('media_review_source_download: protected download',dl.value.failure() is None)
 if not sql("SELECT event_id FROM platform_audit_events WHERE action='MEDIA_LOGO_IMPROVEMENT_ACCEPTED' AND resource_reference=%s",(logo,)):
  a.locator('#generate-improvement').click();expect(a.locator('#proposal-panel')).to_be_visible(timeout=25000);a.locator('#accept-improvement').click();expect(a.locator('#proposal-panel')).not_to_be_visible()
 check('media_review_improve: actual derivative accepted',bool(sql("SELECT event_id FROM platform_audit_events WHERE action='MEDIA_LOGO_IMPROVEMENT_ACCEPTED' AND resource_reference=%s",(logo,))))
 choose(a,'Aurora','workspace_aurora');item(a,gallery)
 if not sql("SELECT file_id FROM media_review_files WHERE submission_id=%s AND role='CORRECTED'",(gallery,)):
  a.locator('#corrected-file').set_input_files(str(O/'synthetic.png'));a.locator('#save-corrected').click();expect(a.locator('#corrected-file')).to_have_value('')
 check('media_review_corrected_upload: corrected asset persists',len(sql("SELECT file_id FROM media_review_files WHERE submission_id=%s AND role='CORRECTED'",(gallery,)))==1)
 if sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(replacement,))[0][0]=='PENDING_REVIEW':
  item(a,replacement);a.locator('#request-replacement').click();a.locator('#replacement-reason').select_option('QUALITY_INSUFFICIENT');a.locator('#replacement-feedback').fill('Geometría sintética de prueba, sustituir imagen.');a.locator('#send-replacement').click();expect(a.locator('#media-items [data-submission-id="'+replacement+'"]')).not_to_be_visible()
 check('media_review_request_replacement: NEEDS_WORK persisted',sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(replacement,))[0][0]=='NEEDS_WORK')
 if sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(photo,))[0][0]=='PENDING_REVIEW':
  item(a,photo);a.once('dialog',lambda x:x.accept());a.locator('#approve-image').click();expect(a.locator('#media-items [data-submission-id="'+photo+'"]')).not_to_be_visible()
 check('media_review_approve: synthetic published derivative',sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(photo,))[0][0]=='APPROVED')
 for key in ['claim_approve','new_approve']:
  rid=f[key]['application_id'];r=req.snapshot(a,rid)['request'];req.open_request(a,rid)
  check('physician_request_read '+key,r['request_id']==rid)
  if r['state']!='APPROVED':
   req.inspect_evidence(a);check('physician_request_evidence_read '+key,True)
   a.locator('#approve-request').click();a.locator('#request-reason').fill('Aprobación del expediente geométrico sintético QA01');a.locator('#request-password').fill(password);a.locator('#request-mfa').fill(req.fresh_mfa(a,O,sql,record['account']));a.locator('#request-confirmation').check();a.locator('#confirm-request-decision').click();expect(a.locator('#request-status')).to_have_text('Aprobada',timeout=30000)
  check(('physician_claim_resolve' if key=='claim_approve' else 'physician_new_profile_resolve')+': actual canonical decision',req.snapshot(a,rid)['request']['state']=='APPROVED')
 pending=get(a,'/api/internal/workspace/requests.php?mode=pending');check('physician_pending_summary_read: current extended summary',pending['status']==200 and pending['body']['data']['summary_authorized'])
 choose(a,'Leticia','mxmed-local-development:1');check('physician_administrative_read: qualified original context',admin(a)['status']==200)
 a.locator('#tab-offices').click();expect(a.locator('#offices-content article')).to_have_count(3);check('physician_locations_read: three administrative locations',admin(a,'admin_locations')['status']==200)
 a.locator('#tab-operators').click();expect(a.locator('#operators-content')).to_contain_text('Colaborador');check('physician_operator_support_read: original read-only relation',admin(a,'admin_operators')['status']==200)
 for mode in ['admin_summary','admin_locations','admin_operators']:
  for bad in ['1','mxmed-local-development:2','unknown:1']:check(mode+' denied outside explicit scope '+bad,admin(a,mode,bad)['status']==403)
 # Wrong physician binding and no-work profile are not disclosure paths.
 check('Media no-work physician excluded',get(a,'/api/internal/workspace/read.php?doctor_id=workspace_out_of_scope')['status']==404)
 for mode in ['review','source']:check('Media '+mode+' exact binding denies wrong physician',status(a,'/api/internal/workspace/media.php?mode='+mode+'&doctor_id=workspace_aurora&submission_id='+logo)==404)
 check('Requests fabricated resource denied',get(a,'/api/internal/workspace/requests.php?mode=read&request_id=00000000-0000-4000-8000-000000000099')['status'] in [403,404])
 check('Pending out-of-scope resource denied',get(a,'/api/internal/workspace/requests.php?mode=pending&doctor_id=workspace_out_of_scope')['status']==403)
 check('Advisor cannot access Director staff directory',status(a,'/api/internal/team/read.php')==403)
 (OUT/'consumers.json').write_text(json.dumps({'exercised':CAPS,'mutations':'owned synthetic only','admin_source':'original read-only allowlist id1','outside_scope':'admin all3, Media read/source binding, Requests unknown, pending outside; mutation-specific negatives still pending'},indent=2))

def revoke(d,a,record):
 order=['media_review_source_download','media_review_corrected_upload','media_review_improve','media_review_request_replacement','media_review_approve','physician_claim_resolve','physician_new_profile_resolve','physician_request_evidence_read','physician_pending_summary_read','physician_operator_support_read','physician_locations_read','physician_administrative_read','physician_request_read','media_review_read']
 path=OUT/'revocation.json';results=json.loads(path.read_text()) if path.exists() else []
 f=json.loads((O/'workspace02-fixture.json').read_text());fixtures=json.loads((O/'workspace-media.json').read_text());logo=next(x['submission_id'] for x in fixtures['items'] if x['owner_id']=='workspace_bruno' and x['purpose']=='PHYSICIAN_PERSONAL_LOGO')
 initial_cookies=a.context.cookies();before_session=next(x['value'] for x in initial_cookies if x['name']=='__Host-mxmed_session')
 remaining=set(CAPS)-{r['capability'] for r in results}
 for old in results:
  if old['capability']=='physician_operator_support_read' and not old['ui_restricted']:
   choose(a,'Leticia','mxmed-local-development:1');a.locator('#tab-operators').click();expect(a.locator('#restricted-panel')).to_be_visible();old['ui_restricted']=True;old['ui_verification']='Restricted section, not disabled navigation tab; checked after harness resume';path.write_text(json.dumps(results,indent=2))
 for cap in order:
  if cap not in remaining:continue
  remaining.remove(cap);grants(d,record,remaining)
  a.reload();expect(a.locator('#staff-role')).to_have_text('Asesor')
  endpoint='/api/internal/workspace/media.php?doctor_id=workspace_bruno&'
  ui=True;detail={}
  if cap.startswith('media_'):
   if cap=='media_review_read':code=status(a,endpoint+'mode=review&submission_id='+logo);ui=not get(a,'/api/internal/workspace/read.php')['body']['data']['physicians']
   else:
    choose(a,'Bruno','workspace_bruno');item(a,logo)
    controls={'media_review_source_download':'#download-original','media_review_corrected_upload':'#save-corrected','media_review_improve':'#generate-improvement','media_review_request_replacement':'#request-replacement','media_review_approve':'#approve-image'}
    ui=not a.locator(controls[cap]).is_visible() or a.locator(controls[cap]).is_disabled()
    if cap=='media_review_source_download':code=status(a,endpoint+'mode=source&submission_id='+logo)
    else:
     opt=get(a,endpoint+'mode=options')['body'];mode={'media_review_corrected_upload':'corrected','media_review_improve':'improve','media_review_request_replacement':'replacement','media_review_approve':'approve'}[cap]
     if mode=='corrected':
      code=a.evaluate('''async ([url,id,csrf])=>{let f=new FormData();f.append('submission_id',id);f.append('csrf',csrf);f.append('corrected',new Blob(['synthetic'],{type:'image/png'}),'qa.png');return (await fetch(url,{method:'POST',body:f})).status}''',[endpoint+'mode=corrected',logo,opt['csrf']])
     else:code=media.post(a,endpoint+'mode='+mode,{'submission_id':logo,'csrf':opt['csrf'],'reason_code':'OTHER','feedback':'Prueba sintética revocada'})['status']
  elif cap in ['physician_claim_resolve','physician_new_profile_resolve']:
   rid=f['claim_ready' if cap=='physician_claim_resolve' else 'new_ready']['application_id'];snap=req.snapshot(a,rid)['request'];req.open_request(a,rid);ui=not a.locator('#approve-request').is_visible()
   code=req.command(a,'begin',dict(request_id=rid,intake_version=snap['intake_version'],review_version=snap['review_version'],revision_token=snap['revision_token'],decision='REJECTED',reason='Intento revocado sintético',email=record['email'],password=password))['status']
  elif cap=='physician_request_evidence_read':
   rid=f['claim_ready']['application_id'];req.open_request(a,rid);ui=not a.locator('#request-evidence button').count()
   fid=sql('SELECT file_id FROM auth_physician_evidence_files WHERE request_id=%s LIMIT 1',(f['claim_ready']['evidence_request_id'],))[0][0];code=status(a,'/api/internal/workspace/requests.php?mode=evidence&request_id='+rid+'&file_id='+fid)
  elif cap=='physician_pending_summary_read':
   response=get(a,'/api/internal/workspace/requests.php?mode=pending');code=response['status'];ui=response['body']['data']['summary_authorized'] is False;detail={'semantic':'shared reader remains authorized by remaining grants; extended summary removed','literal_http_denial':False}
  elif cap=='physician_request_read':
   code=get(a,'/api/internal/workspace/requests.php?mode=read&request_id='+f['claim_ready']['application_id'])['status'];ui='physician_request_read' not in req.snapshot(a)['capabilities']
  else:
   mode={'physician_operator_support_read':'admin_operators','physician_locations_read':'admin_locations','physician_administrative_read':'admin_summary'}[cap];code=admin(a,mode)['status']
   if cap!='physician_administrative_read':
    choose(a,'Leticia','mxmed-local-development:1');tab='#tab-operators' if cap=='physician_operator_support_read' else '#tab-offices';expect(a.locator(tab)).to_be_attached();a.locator(tab).click();expect(a.locator('#restricted-panel')).to_be_visible();ui=a.locator('#restricted-message').is_visible()
   else:
    a.locator('#physician-search').fill('Leticia');a.locator('#physician-search-form').evaluate('(f)=>f.requestSubmit()');expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false');ui=a.locator('[data-doctor-id="mxmed-local-development:1"]').count()==0
  same=next(x['value'] for x in a.context.cookies() if x['name']=='__Host-mxmed_session')==before_session
  unrelated={}
  if 'physician_administrative_read' in remaining:unrelated['admin_summary']=admin(a)['status']==200
  if 'physician_locations_read' in remaining:unrelated['admin_locations']=admin(a,'admin_locations')['status']==200
  if 'physician_operator_support_read' in remaining:unrelated['admin_operators']=admin(a,'admin_operators')['status']==200
  if 'media_review_read' in remaining:unrelated['media']=status(a,endpoint+'mode=review&submission_id='+logo)==200
  if 'physician_request_read' in remaining:unrelated['requests']=get(a,'/api/internal/workspace/requests.php?mode=read&request_id='+f['claim_ready']['application_id'])['status']==200
  # Audit events are selected by exact actor/target; payload checked offline below.
  audits=sql("SELECT event_id FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND real_actor_reference='account:qa_director' AND resource_reference=%s AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))='revoke' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.capability'))=%s ORDER BY occurred_at_utc DESC LIMIT 1",(record['account'],cap))
  result=dict(capability=cap,http_status=code,ui_restricted=ui,same_identity_session=same,unrelated=unrelated,audit_event=audits[0][0] if audits else None,remaining=sorted(remaining),**detail)
  results.append(result);path.write_text(json.dumps(results,indent=2))
  check('Cumulative revoke '+cap,ui and same and all(unrelated.values()) and bool(audits) and (code==403 or cap=='physician_pending_summary_read' and code==200),http_status=code)
 (OUT/'progressively-revoked.json').write_text(json.dumps({'account':record['account'],'remaining':sorted(remaining),'steps':len(results),'no_regrant':True},indent=2))

if __name__=='__main__':main()
