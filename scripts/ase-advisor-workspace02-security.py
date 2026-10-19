#!/usr/bin/env python3
"""Focused canonical concurrency/security rehearsal, only in a verified owned preview.

No HTTP exposure or original DB selector. Canonical fixture workers hold every
credential/proof privately in the owned root. Results contain assertions only.
"""
import argparse,datetime,hashlib,json,os,pathlib,re,subprocess,time
import pymysql

ROOT=pathlib.Path(__file__).resolve().parents[1]
WORKER='scripts/ase-advisor-workspace02-security-worker.php'

def main():
 parser=argparse.ArgumentParser();parser.add_argument('--owned',required=True)
 parser.add_argument('--skip-secondary',action='store_true',help='Defer the separate qa_master reviewer check while C4 uses that actor')
 parser.add_argument('--only-new-race',action='store_true',help='Run only the deterministic two-reviewer new-application race')
 parser.add_argument('--new-race-attempt',type=int,choices=range(1,10),default=1)
 args=parser.parse_args();owned=pathlib.Path(args.owned).resolve()
 if not re.fullmatch(r'/private/tmp/mxmed-ase-adm02imp01-[a-zA-Z0-9_]+',str(owned)):raise RuntimeError('owned_root_required')
 if owned.stat().st_uid!=os.geteuid() or owned.stat().st_mode&0o777!=0o700:raise RuntimeError('owned_permissions_required')
 approval=json.loads((owned/'workflow-approval.json').read_text());env=json.loads((owned/'review-env.json').read_text())
 if approval['root']!=str(ROOT) or approval['expires']<=time.time() or approval['database']!=env['MXMED_DB_NAME']:raise RuntimeError('owned_approval_required')
 if pathlib.Path(env['ASE_INT02A_OWNED']).resolve()!=owned:raise RuntimeError('owned_environment_required')
 if subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()!='feature/ase-advisor-workspace02-profile-requests':raise RuntimeError('isolated_branch_required')
 db=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=env['MXMED_DB_NAME'],autocommit=True)
 def sql(query,params=()):
  with db.cursor() as c:c.execute(query,params);return c.fetchall()
 if pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()!=owned/'mysql':raise RuntimeError('owned_database_required')
 isolation=sql('SELECT @@transaction_isolation')[0][0]
 source_hash=hashlib.sha256((ROOT/'modules/identity/PhysicianRequests/RequestService.php').read_bytes()).hexdigest()
 results=[];run_status='IN_PROGRESS';checkpoint=owned/'workspace02-security-checkpoint.json'
 completed=json.loads(checkpoint.read_text()) if checkpoint.exists() else {}
 output=ROOT/'docs/evidence/ase-advisor-workspace02/security-result.json';output.parent.mkdir(parents=True,exist_ok=True)
 def write():
  if owned.is_dir():checkpoint.write_text(json.dumps(completed));checkpoint.chmod(0o600)
  output.write_text(json.dumps({'scope':'OWNED_SYNTHETIC_ONLY','status':run_status,'database_isolation':isolation,'request_service_sha256':source_hash,'only_new_race':args.only_new_race,'new_race_attempt':args.new_race_attempt,'secondary_reviewer_deferred':args.skip_secondary,'completed_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'checks':results,'pass':run_status=='COMPLETE' and all(r['pass'] for r in results)},indent=2)+'\n')
 def check(name,ok):
  results.append({'name':name,'pass':bool(ok)});write();print(('PASS ' if ok else 'FAIL ')+name,flush=True)
  if not ok:raise AssertionError(name)
 def run(action,body=None,allow_error=False):
  p=subprocess.run(['php',WORKER,action],cwd=ROOT,env=env,input=json.dumps(body or {}),capture_output=True,text=True,timeout=80)
  try:v=json.loads(p.stdout)
  except Exception:
   if owned.is_dir():(owned/'workspace02-security-worker-output.log').write_text(p.stdout+'\n'+p.stderr)
   raise RuntimeError('security_worker_output_invalid' if owned.is_dir() else 'owned_environment_removed')
  if not v.get('ok') and not allow_error:raise RuntimeError('security_worker_failed:'+str(v.get('error','unknown')))
  return v
 def data(action,body=None):return run(action,body)['data']
 def prepare(key,secondary=False):return data('prepare',{'key':key,'secondary':secondary})
 def inspect(key):return data('inspect',{'key':key})
 def once(key,fn):
  if key in completed:
   for item in completed[key]:results.append(item);print('RECORDED '+item['name'],flush=True)
   write();return
  start=len(results);fn();completed[key]=results[start:];write()
 def concurrent(keys,hold_materialization=False):
  barrier=owned/'workspace02-security-release'
  if barrier.exists():barrier.unlink()
  blocker=None;waiters=0
  if hold_materialization:
   blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=env['MXMED_DB_NAME'],autocommit=False)
   with blocker.cursor() as c:c.execute("SELECT lock_name FROM auth_physician_profile_request_locks WHERE lock_name='profile_materialization' FOR UPDATE");assert c.fetchone()==('profile_materialization',)
  processes=[]
  for key in keys:
   p=subprocess.Popen(['php',WORKER,'decide'],cwd=ROOT,env=env,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
   p.stdin.write(json.dumps({'key':key,'release_file':str(barrier)}));p.stdin.close();p.stdin=None;processes.append(p)
  time.sleep(.5);barrier.touch(mode=0o600)
  responses=[]
  try:
   if blocker:
    deadline=time.monotonic()+8
    while time.monotonic()<deadline:
     waiters=sql("SELECT COUNT(DISTINCT w.REQUESTING_ENGINE_TRANSACTION_ID) FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE WHERE l.OBJECT_SCHEMA=%s AND l.OBJECT_NAME='auth_physician_profile_request_locks'",(env['MXMED_DB_NAME'],))[0][0]
     if waiters==2:break
     time.sleep(.05)
    blocker.commit();blocker.close();blocker=None
   for p in processes:
    out,err=p.communicate(timeout=45)
    try:responses.append(json.loads(out))
    except Exception:
     if owned.is_dir():(owned/'workspace02-security-worker-output.log').write_text(out+'\n'+err)
     raise RuntimeError('concurrent_worker_output_invalid' if owned.is_dir() else 'owned_environment_removed')
  finally:
   if blocker:blocker.rollback();blocker.close()
   barrier.unlink(missing_ok=True)
  if hold_materialization:check('Two independent reviewer transactions reach the materialization mutex before either commits',waiters==2)
  return responses
 def double():
  prepare('security_double');responses=concurrent(['security_double','security_double']);r=inspect('security_double')
  check('Two independent workers consume one proof as one canonical decision and one replay receipt',all(v.get('ok') for v in responses) and sorted(v['data']['replayed'] for v in responses)==[False,True])
  check('Double submission creates one hidden profile link and one approval event',len(r['decisions'])==1 and r['profile_links']==1 and r['operations'].count('REQUEST_APPROVED')==1 and r['operations'].count('PROFILE_MATERIALIZED')==1)
 def revoked():
  prepare('security_revoked');data('revoke_grant')
  try:v=run('decide',{'key':'security_revoked'},True);r=inspect_sql('security_revoked')
  finally:data('restore_grant')
  check('Grant revoked after verified MFA denies effect and leaves no decision or profile',not v.get('ok') and 'capability_denied' in v.get('error','') and r['decisions']==0 and r['profiles']==0)
 def inspect_sql(key):
  rec=json.loads((owned/'workspace02-fixture.json').read_text())[key];origin=rec['application_id'];profile='pr_'+origin.replace('-','')
  return {'decisions':sql('SELECT COUNT(*) FROM auth_physician_profile_request_decisions WHERE origin_id=%s',(origin,))[0][0],
          'profiles':sql('SELECT COUNT(*) FROM profiles_doctors WHERE doctor_id=%s',(profile,))[0][0],
          'links':sql('SELECT COUNT(*) FROM auth_physician_profile_request_profiles WHERE origin_id=%s',(origin,))[0][0]}
 def stale():
  prepare('security_stale');data('revoke_source');v=run('decide',{'key':'security_stale'},True);r=inspect_sql('security_stale')
  check('Canonical source revocation after MFA invalidates request facts and prevents approval',not v.get('ok') and 'conflict' in v.get('error','') and r['decisions']==0)
 def evidence_version():
  prepare('evidence_stale');data('reject_evidence');v=run('decide',{'key':'evidence_stale'},True);r=inspect_sql('evidence_stale')
  check('A canonical C4 evidence decision changes version and invalidates an already-verified request proof',not v.get('ok') and 'conflict' in v.get('error','') and r['decisions']==0)
 def audit():
  prepare('security_audit');records=json.loads((owned/'workspace02-fixture.json').read_text());origin=records['security_audit']['application_id']
  job=json.loads((owned/'workspace02-security-job-security_audit.json').read_text());trigger='ws02_security_audit_failure'
  if sql('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=%s AND TRIGGER_NAME=%s',(env['MXMED_DB_NAME'],trigger))[0][0]:raise RuntimeError('ambiguous_security_trigger')
  sql("CREATE TRIGGER ws02_security_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW BEGIN IF NEW.resource_reference=%s AND NEW.action='PHYSICIAN_PROFILE_REQUEST_CHANGED' AND JSON_UNQUOTE(JSON_EXTRACT(NEW.metadata_json,'$.producer_metadata.operation'))='REQUEST_APPROVED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='workspace02_owned_audit_failure'; END IF; END",(origin,))
  try:
   v=run('decide',{'key':'security_audit'},True);r=inspect_sql('security_audit')
   proof=sql('SELECT status FROM auth_staff_mfa_proofs WHERE proof_id=%s',(job['proof_id'],))[0][0]
   case=sql('SELECT status FROM auth_governance_cases WHERE case_id=%s',(job['case_id'],))[0][0]
   check('Canonical audit insertion failure rolls back decision, materialized profile, link, proof and case',not v.get('ok') and r=={'decisions':0,'profiles':0,'links':0} and proof=='VERIFIED' and case=='OPEN')
  finally:sql('DROP TRIGGER ws02_security_audit_failure')
  receipt=data('result',{'key':'security_audit'});check('After failed audit the authoritative result confirms NOT_COMMITTED without retrying mutation',receipt=={'committed':False,'status':'NOT_COMMITTED'})
 def race():
  profile_sql="SELECT display_name,professional_license,specialty_primary,profile_status,is_public_candidate,plan_code FROM profiles_doctors WHERE doctor_id='workspace02_race'"
  before=sql(profile_sql)
  prepare('race_a');prepare('race_b');responses=concurrent(['race_a','race_b'])
  summaries=[inspect('race_a'),inspect('race_b')];approved=sum(sum(d['state']=='APPROVED' for d in r['decisions']) for r in summaries)
  check('Competing claims from independent workers produce exactly one approval',approved==1 and sum(v.get('ok',False) for v in responses)==1)
  proof_states=[]
  for key in ['race_a','race_b']:
   job=json.loads((owned/('workspace02-security-job-'+key+'.json')).read_text())
   proof_states.append(sql('SELECT status FROM auth_staff_mfa_proofs WHERE proof_id=%s',(job['proof_id'],))[0][0])
  check('Competing claim loser keeps an unconsumed proof and produces no approval event',sorted(proof_states)==['CONSUMED','VERIFIED'] and sum(r['operations'].count('REQUEST_APPROVED') for r in summaries)==1)
  check('Competing claim winner preserves the existing profile and does not activate ownership',before==sql(profile_sql) and sum(r['profile_links'] for r in summaries)==0 and sql("SELECT COUNT(*) FROM auth_account_memberships WHERE profile_doctor_id='workspace02_race' AND role_code='owner' AND status='active'")[0][0]==0)
 def two_reviewers():
  prepare('security_tworeview');r=data('secondary_begin');check('A different reviewer cannot open a competing active request case',r['denied'])
  v=data('decide',{'key':'security_tworeview'});r=inspect('security_tworeview');check('Original reviewer commits one decision after competing reviewer denial',v['state']=='APPROVED' and len(r['decisions'])==1)
 def new_race():
  prefix='security_new_race'+str(args.new_race_attempt);keys=[prefix+'_a',prefix+'_b']
  prepare(keys[0]);prepare(keys[1],True)
  jobs=[json.loads((owned/('workspace02-security-job-'+key+'.json')).read_text()) for key in keys]
  applicants=sql('SELECT COUNT(DISTINCT account_id) FROM auth_physician_enrollment_origins WHERE origin_id IN (%s,%s)',(jobs[0]['origin_id'],jobs[1]['origin_id']))[0][0]
  check('Duplicate-new race uses separate applications, accounts and authenticated reviewers',jobs[0]['origin_id']!=jobs[1]['origin_id'] and jobs[0]['actor_id']!=jobs[1]['actor_id'] and applicants==2)
  responses=concurrent(keys,True);summaries=[inspect(key) for key in keys]
  count=sql('SELECT COUNT(*) FROM profiles_doctors WHERE professional_license=%s',('SYNTHETIC-WS02-NEW-RACE-'+str(args.new_race_attempt),))[0][0]
  approved=sum(sum(d['state']=='APPROVED' for d in r['decisions']) for r in summaries)
  check('Two applications with one declared professional license materialize exactly one profile and approval',count==1 and approved==1 and sum(r['profile_links'] for r in summaries)==1 and sum(v.get('ok',False) for v in responses)==1)
  loser=[v for v in responses if not v.get('ok')]
  check('Competing new-application loser receives conflict and no duplicate approval event',len(loser)==1 and loser[0].get('error') in ['physician_request_revision_conflict','physician_request_approval_conflict'] and sum(r['operations'].count('REQUEST_APPROVED') for r in summaries)==1)
 def gates():
  check('Unprivileged staff cannot resolve a physician request',data('unauthorized')['denied'])
  check('Request capability does not authorize the general initial-owner gate',data('general_owner_gate')['denied'])
  value=data('owned_conflict');check('An already-owned profile cannot be approved or transferred by the request workflow',value['denied'] and not value['approval_available'])
 try:
  data('setup',{'new_race_attempt':args.new_race_attempt});check('Dedicated synthetic reviewer has canonical exact grants and legitimate MFA enrollment',True)
  if not args.only_new_race:
   for key,fn in [('gates',gates),('double',double),('revoked',revoked),('stale',stale),('audit',audit),('race',race)]:once(key,fn)
   if not args.skip_secondary:once('two_reviewers',two_reviewers);once('evidence_version',evidence_version)
  if not args.skip_secondary:once('new_race'+str(args.new_race_attempt),new_race)
  run_status='COMPLETE'
 except BaseException:
  run_status='FAILED';raise
 finally:write();db.close()
 print('SECURITY_RESULT='+str(output),flush=True)

if __name__=='__main__':main()
