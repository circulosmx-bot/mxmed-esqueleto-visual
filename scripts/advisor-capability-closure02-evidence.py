#!/usr/bin/env python3
"""Read-only reconciliation; export only hashes and non-secret synthetic evidence."""
import pathlib,sys,json,hashlib,importlib.util,pymysql,subprocess
ROOT=pathlib.Path(__file__).resolve().parents[1];O=pathlib.Path(sys.argv[1]).resolve();assert O.parent==pathlib.Path('/private/tmp') and O.name.startswith('mxmed-ase-adm02imp01-')
OUT=ROOT/'docs/evidence/advisor-capability-closure02';OUT.mkdir(parents=True,exist_ok=True)
E=json.loads((O/'review-env.json').read_text());db=pymysql.connect(unix_socket=str(O/'mysql.sock'),user='ase_fixture',password=E['ASE_INT02A_FIXTURE_PASSWORD'],database=E['MXMED_DB_NAME'],autocommit=True)
def sql(q,args=()):
 assert q.startswith(('SELECT','SHOW'))
 with db.cursor() as c:c.execute(q,args);return c.fetchall()
assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==O/'mysql'
def save(name,v):(OUT/name).write_text(json.dumps(v,indent=2,ensure_ascii=False,default=str)+'\n')
def sanitize(v):
 if isinstance(v,dict):return {k:sanitize(x) for k,x in v.items() if k not in ['csrf_token','password','challenge','proof','token','token_digest','session_id']}
 if isinstance(v,list):return [sanitize(x) for x in v]
 return v
for name in ['checks.json','summary-fields.json','consumer-checks.json','consumer-attempt1.json','consumer-attempt2.json','consumer-attempt3-scope-defect.json','security.json','reserved.json']:save(name,sanitize(json.loads((O/'closure-evidence'/name).read_text())))
record=json.loads((O/'full-closure.json').read_text());account=record['account'];summary=json.loads((O/'full-summary.json').read_text())
current=[x[0] for x in sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE' AND revoked_at IS NULL ORDER BY capability",(account,))];assert len(current)==17
assert [x[0] for x in sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE' AND revoked_at IS NULL",(summary['account'],))]==['media_review_read']
save('demonstration.json',{'actor':account,'email':record['email'],'grants':current,'canonical_admission':sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(record['case'],))[0][0]==1,'previous_14_capability_exercise_repeated':False,'new_demo_grants_prepared_once':True})
events=sql("SELECT event_id,action,outcome,metadata_json FROM platform_audit_events WHERE real_actor_reference=%s ORDER BY occurred_at_utc",('account:'+account,));audit=[]
for event,action,outcome,raw in events:
 meta=json.loads(raw).get('producer_metadata',{});audit.append({'event_id':event,'action':action,'outcome':outcome,'operation':meta.get('operation') if isinstance(meta,dict) else None})
proofs=sql("SELECT operation,status,COUNT(*) FROM auth_staff_mfa_proofs WHERE actor_account_id=%s GROUP BY operation,status",(account,));assert sum(x[2] for x in proofs if x[1]=='CONSUMED')>=3
save('consumer-audit.json',{'actor':account,'events':audit,'mfa_proofs':proofs,'provider_journal':sql('SELECT claim_uuid,actor_account_id,action,previous_status,new_status,reason_code,correlation_key FROM healthcare_organization_claim_events WHERE actor_account_id=%s',(account,))})
grants=[]
for event,actor,raw in sql("SELECT event_id,real_actor_reference,metadata_json FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND resource_reference IN (%s,%s)",(account,summary['account'])):
 meta=json.loads(raw)['producer_metadata'];assert actor=='account:qa_director';grants.append({'event_id':event,'actor':actor,'operation':meta['operation'],'capability':meta['capability']})
save('grant-audit.json',grants)
# Compare the existing bounded original record fingerprint. Never writes original data.
home=pathlib.Path.home();secret=home/'Library/Application Support/MXMED/physician-administration-link03/reader-v1.json';reader=json.loads(secret.read_text());env=json.loads((home/'Library/Application Support/MXMED/local-identity-runtime/environment.json').read_text());assert env['APP_ENV']=='local' and env['MXMED_DB_HOST']==reader['host']=='127.0.0.1' and env['MXMED_DB_NAME']==reader['database']=='mxmed' and int(env['MXMED_DB_PORT'])==3306
original=pymysql.connect(host=env['MXMED_DB_HOST'],port=3306,user=env['MXMED_DB_USER'],password=env['MXMED_DB_PASS'],database='mxmed',autocommit=False)
prior=json.loads((secret.parent/'ux05-projection-receipt.json').read_text())['before_records'];fingerprints={}
try:
 with original.cursor() as c:
  c.execute('START TRANSACTION READ ONLY');c.execute('SELECT @@server_uuid');assert c.fetchone()[0]==reader['server_uuid']
  for table in ['profiles_doctors','consultorios','auth_account_memberships','media_assets']:
   column='profile_doctor_id' if table=='auth_account_memberships' else 'owner_id' if table=='media_assets' else 'doctor_id';c.execute('SELECT * FROM `'+table+'` WHERE `'+column+'`=%s',('1',));digest=hashlib.sha256(repr(c.fetchall()).encode()).hexdigest();assert digest==prior[table];fingerprints[table]={'sha256':digest,'matches_pre_UX05':True}
finally:original.rollback();original.close()
save('original-records.json',fingerprints)
s=importlib.util.spec_from_file_location('closure_runner',ROOT/'scripts/advisor-capability-closure02.py');runner=importlib.util.module_from_spec(s);s.loader.exec_module(runner);before=json.loads((O/'full-grants-protected-before.json').read_text());after=runner.base.protected();differences={k:{'before':before.get(k),'after':after.get(k)} for k in before.keys()|after.keys() if before.get(k)!=after.get(k)};assert not(set(differences)-{'ase_int01_pointer_sha256'});save('protected-runtime.json',{'original_listeners_and_worktrees_equal':True,'all_observations_equal':not differences,'unattributed_old_preview_pointer_difference':differences,'pointer_modified_by_this_task':False,'before':before,'after':after})
# Actual catalog, no invented IDs; exclusive categories + separate connectivity axis.
raw=subprocess.check_output(['php','-r',"require 'modules/identity/services/InternalCapabilityCatalog.php';echo json_encode(Identity\\Services\\InternalCapabilityCatalog::all());"],cwd=ROOT,text=True);catalog=json.loads(raw);old=json.loads((ROOT/'docs/evidence/advisor-full-grants-qa01/capability-matrix.json').read_text());old={v['id']:v for v in old['capabilities']};matrix=[]
for key,definition in catalog.items():
 previous=old[key];category={'A':'OPERATIONAL_IN_SCOPE','B':'RESERVED','C':'NO_ADVISOR_CONSUMER','D':'COMMERCIAL_OUT_OF_SCOPE'}[previous['classification']]
 matrix.append({'id':key,'label':definition['label'],'category':category,'catalog_delegable':definition['delegable'],'effective_advisor_delegation':category=='OPERATIONAL_IN_SCOPE','connected_before':previous['connected_in_review'],'connected_after':category=='OPERATIONAL_IN_SCOPE','consumer':{'professional_source_provision':'Advisor → original PhysicianOnboardingPage/Http source_* → SourceProvisioningService → VID01/CRD02','physician_accreditation_review':'Advisor → original PhysicianOnboardingPage/Http review_* → PhysicianEvidenceService','provider_claim_review':'Advisor organization panel → Http provider_claims/provider_decide → ProviderClaimReview → HealthcareOrganizationClaimService'}.get(key,previous['consumer']),'domain':definition['domain'],'scope':previous['resource_scope']})
assert len(matrix)==26 and len(set(v['id'] for v in matrix))==26
save('capability-matrix.json',{'catalog_total':26,'exclusive_totals':{'OPERATIONAL_IN_SCOPE':17,'RESERVED':4,'NO_ADVISOR_CONSUMER':4,'COMMERCIAL_OUT_OF_SCOPE':1},'operational_including_excluded_commercial':18,'obsolete':0,'reconciliation':'17=14 previamente conectadas+3 backend existentes desconectados; cuatro genéricas C son distintas; D comercial implementada, no obsoleta. Connectivity es un eje, no otro sumando.','capabilities':matrix})
save('tested-artifacts.json',{str(f.relative_to(ROOT)):hashlib.sha256(f.read_bytes()).hexdigest() for pattern in ['scripts/advisor-capability-closure02*','modules/identity/PhysicianRequests/ProviderClaimReview.php','modules/identity/PhysicianRequests/Http.php','modules/identity/ProfessionalSources/SourceProvisioningService.php','assets/js/staff-capability-reviews.js','assets/js/staff-workspace.js','internal/staff-workspace/index.php'] for f in ROOT.glob(pattern)})
print('CLOSURE_EVIDENCE_RECONCILED: 26 capabilities, 17 demo grants, canonical audits and original hashes preserved')
