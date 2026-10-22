#!/usr/bin/env python3
"""New disposable closure review. Reuses setup only, never QA01's 14-grant exercise."""
import importlib.util,pathlib,json,os,hashlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('closure_previous',ROOT/'scripts/advisor-full-grants-qa01.py');previous=importlib.util.module_from_spec(s);s.loader.exec_module(previous);base=previous.base
base.EXPECTED_BRANCH='feature/mxmed-advisor-capability-closure02';base.BASE='2ef08171641ea86c3069ab9e9c3eab0ff229fe7e';base.OUT=pathlib.Path('/tmp/mxmed-closure02-setup');base.ROUTER='scripts/advisor-capability-closure02-router.php';base.EXTRA_PROTECTED.append('mxmed-advisor-full-grants-qa01')
def setup(r,check):
 previous.setup(r,check)
 source=r['source_sql'];sql=r['sql'];db=r['database'];o=r['owned']
 # The current schema already has the superset governance CHECK. Install the
 # unchanged source objects after the historical CHECK, preserving newer operations.
 ddl=(ROOT/'modules/identity/db/migrations/2026_10_17_07_professional_source_provisioning.sql').read_text();source('CREATE TABLE '+ddl.split('CREATE TABLE ',1)[1])
 group=(ROOT/'modules/agenda/db/medical_groups_schema.sql').read_text();source(group.split('CREATE TABLE IF NOT EXISTS `medical_group_memberships`')[0])
 source((ROOT/'modules/agenda/db/migrations/2026_10_01_05_healthcare_organization_claims.sql').read_text())
 tables=['auth_professional_source_requests','auth_professional_source_files','auth_professional_source_reads','auth_professional_source_operations','auth_professional_source_journal','auth_physician_evidence_requests','auth_physician_evidence_files','auth_physician_evidence_reads','auth_physician_evidence_reviews','auth_physician_enrollment_resolutions','auth_physician_enrollment_handoffs','auth_physician_issuance_workflows','auth_account_phone_verifications','healthcare_organization_claims','healthcare_organization_claim_events']
 for table in tables:
  if sql('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s',(db,table))[0][0]:sql(f"GRANT SELECT,INSERT,UPDATE ON `{db}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
 for table in ['profiles_verified_identities','profiles_doctor_credentials']:sql(f"GRANT INSERT ON `{db}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
 sql(f"GRANT SELECT ON `{db}`.medical_groups TO 'ase_writer'@'127.0.0.1'")
 r['env']['C4_UI_QA_SMS']=str(o/'closure-inert-sms.json')
 (o/'closure-scope.json').write_text(json.dumps({'workflows':['professional_source_provisioning','enrollment_review','c4_evidence'],'accounts':[],'profiles':[],'staff':{},'provider_claims':[]}))
 p=o/'workflow-approval.json';a=json.loads(p.read_text())
 for pattern in ['scripts/advisor-capability-closure02*','assets/js/staff-capability-reviews.js','assets/js/staff-workspace.js','scripts/advisor-full-grants-qa01-review.py','modules/identity/PhysicianRequests/*.php','modules/identity/ProfessionalSources/*.php','internal/staff-workspace/index.php']:
  for f in ROOT.glob(pattern):a['source_hashes'][str(f.relative_to(ROOT))]=hashlib.sha256(f.read_bytes()).hexdigest()
 p.write_text(json.dumps(a))
 check('Closure02 additional canonical schema restricted to new owned server',True)
base.SETUP_HOOK=setup;base.HOOK=previous.run
if __name__=='__main__':os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1';base.main()
