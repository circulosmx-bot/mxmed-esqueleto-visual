#!/usr/bin/env python3
"""Fresh owned review; reuse schema setup, not historical QA or persistent fixtures."""
import importlib.util,pathlib,json,hashlib,subprocess,os,time
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(n,p):
 s=importlib.util.spec_from_file_location(n,p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
previous=module('support_previous',ROOT/'scripts/staff-workspace-vis01-qa.py');base=previous.base
base.EXPECTED_BRANCH='feature/mxmed-advisor-operator-support01';base.BASE='46f8960d7e567d9a90bbba3a1d836926e9caabd8';base.OUT=ROOT/'docs/evidence/advisor-operator-support01';base.REVIEW_SECONDS=86400
base.EXTRA_PROTECTED+=['mxmed-staff-workspace-vis01','mxmed-staff-grants-e2e01']
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
 for table in ['auth_profile_operator_invitations','auth_profile_operator_grant_revisions','auth_profile_operator_grants','auth_profile_operator_presentations']:
  r['sql'](f"GRANT SELECT ON `{r['database']}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
 p=r['owned']/'workflow-approval.json';a=json.loads(p.read_text())
 for pattern in ['modules/identity/operators/*.php','assets/js/staff-workspace.js','modules/identity/Team/ReadService.php','scripts/advisor-operator-support01*']:
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
 for script in ['advisor-operator-support01-browser.py','advisor-operator-support01-supplement.py']:
  subprocess.run(['python3',str(ROOT/'scripts'/script),str(o)],cwd=ROOT,check=True)
 for name in ['checks.json','supplement.json']:
  for item in json.loads((o/'support-evidence'/name).read_text()):check(item['name'],item['pass'])
base.SETUP_HOOK=setup;base.HOOK=run
if __name__=='__main__':os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1';base.main()
