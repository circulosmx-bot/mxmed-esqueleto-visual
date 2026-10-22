#!/usr/bin/env python3
"""Fresh owned review; reuse schema setup, not historical QA or persistent fixtures."""
import importlib.util,pathlib,json,hashlib,subprocess,os,time
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(n,p):
 s=importlib.util.spec_from_file_location(n,p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
previous=module('support_previous',ROOT/'scripts/staff-workspace-vis01-qa.py');base=previous.base
base.EXPECTED_BRANCH='feature/mxmed-cross-surface-link03';base.BASE='7b3675d15931e559227d67decb20f1c82a7f1b46';base.OUT=pathlib.Path('/tmp/mxmed-link03-runner-evidence');base.REVIEW_SECONDS=86400
base.REVIEW_REPORT_CONTEXT={'real_personal_data_used':True,'original_source_read':'restricted administrative projections only','real_database_connections':'restricted local reader; no medical DML','production_activation':False}
base.EXTRA_PROTECTED+=['mxmed-cross-surface-link02','mxmed-advisor-operator-support01'];base.EXTRA_PROTECTED+=['mxmed-staff-workspace-vis01','mxmed-staff-grants-e2e01']
def setup(r,check):
 previous.setup(r,check)
 for f in ['2026_10_15_02_profile_operator_foundation.sql','2026_10_15_04_operator_presentations.sql']:
  ddl=(ROOT/'modules/identity/db/migrations'/f).read_text()
  # Adapt the rehearsal guard only to this freshly verified owned schema.
  ddl=ddl.replace("DATABASE()='mxmed_gate4d_preview_opimp07'","DATABASE()='"+r['database']+"'")
  if 'CREATE TRIGGER' in ddl:
   before,trigger=ddl.split('CREATE TRIGGER',1);ddl=before+'DELIMITER $$\nCREATE TRIGGER'+trigger.rstrip().removesuffix(';')+'$$\nDELIMITER ;\n'
  r['source_sql'](ddl)
 fixture_db='mxmed_op01_'+__import__('secrets').token_hex(5)
 r['sql'](f'CREATE DATABASE `{fixture_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
 r['sql'](f"GRANT ALL ON `{fixture_db}`.* TO 'ase_fixture'@'localhost'")
 r['env']['SUPPORT_FIXTURE_DB']=fixture_db
 r['env']['MXMED_LINK03_REVIEW']='1'
 secret=pathlib.Path(os.environ['MXMED_LINK03_SECRET_FILE']).resolve();v=json.loads(secret.read_text());assert v['stage']=='ready'
 (r['owned']/'administration-source.json').write_text(json.dumps({'secret_file':str(secret),'origin':v['origin'],'expires':time.time()+86400,'staff':{'qa_target':{'physician_administrative_read':v['ids'],'physician_operator_support_read':v['ids']}}}))
 for table in ['auth_profile_operator_invitations','auth_profile_operator_grant_revisions','auth_profile_operator_grants','auth_profile_operator_presentations']:
  r['sql'](f"GRANT SELECT ON `{r['database']}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
 p=r['owned']/'workflow-approval.json';a=json.loads(p.read_text())
 for pattern in ['modules/identity/operators/*.php','assets/js/staff-workspace.js','modules/identity/Team/ReadService.php','scripts/advisor-operator-support01*','scripts/cross-surface-link03*','modules/identity/PhysicianAdministration/*.php','modules/identity/PhysicianRequests/Http.php','scripts/ase-advisor-workspace02-runtime.php']:
  for f in ROOT.glob(pattern):a['source_hashes'][str(f.relative_to(ROOT))]=hashlib.sha256(f.read_bytes()).hexdigest()
 p.write_text(json.dumps(a))
def run(r,check,races):
 o=r['owned'];env=r['env']
 def cli(f,args=[]):
  x=subprocess.run(['php',f,*args],cwd=ROOT,env=env,input='{}',text=True,capture_output=True)
  if x.returncode:(o/'support-fixture-error.log').write_text(x.stderr);raise RuntimeError('owned_fixture_failed')
  return json.loads(x.stdout)
 cli('modules/identity/tests/StaffWorkflowFixture.php',['enroll'])
 (o/'workspace-media.json').write_text(json.dumps(cli('scripts/ase-advisor-workspace-fixture.php')))
 (o/'workspace-runtime.json').write_text(json.dumps({'origin':r['origin'],'expiry_unix':r['review_deadline']}))
 (o/'workspace-head').write_text(subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip())
 (o/'review-info.json').write_text(json.dumps({'url':r['origin']+'/internal/media-review/index.php?section=equipo','expiry_unix':r['review_deadline'],'owner_pid':os.getpid()}))
 (o/'review-env.json').write_text(json.dumps(env));(o/'support-ready').write_text('ready')
 (o/'oper09-advisor.json').write_text(json.dumps({'account':'qa_target','email':'qa_target@example.test','fixture':'Independent support QA; not Irene'}))
 (o/'applicant-password').write_bytes((o/'fixture-password').read_bytes())
 cli('scripts/advisor-operator-support01-fixture.php')
 print('SUPPORT_REVIEW_READY',o,r['origin'],flush=True)
 print('LINK03_REVIEW_READY',o,r['origin'],flush=True)
 # Run only this integration's focused checks, not historical browser suites.
 subprocess.run(['php',str(ROOT/'scripts/cross-surface-link03-scale.php'),str(o)],cwd=ROOT,check=True)
 subprocess.run(['python3',str(ROOT/'scripts/cross-surface-link03-browser.py'),str(o)],cwd=ROOT,check=True)
 subprocess.run(['python3',str(ROOT/'scripts/cross-surface-link03-supplement.py'),str(o)],cwd=ROOT,check=True)
 (o/'link03-qa-complete').write_text(json.dumps({'pass':True}))
 check('LINK03 focused QA completed',True)
base.SETUP_HOOK=setup;base.HOOK=run
if __name__=='__main__':os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1';base.main()
