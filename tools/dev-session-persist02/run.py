"""Focused staff lifecycle QA. Fresh owned SQL; no existing fixture reset."""
import importlib.util,pathlib,json,os,subprocess,sys
ROOT=pathlib.Path(__file__).resolve().parents[2]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
base=module('persist02_base',ROOT/'scripts/ase-adm02-workflow-qa.py')
base.BASE='0145432fec761283110fde5853ec3d5e483783cc';base.EXPECTED_BRANCH='feature/mxmed-dev-session-persist02';base.ROUTER='scripts/ase-adm02-workflow-router.php'
base.OUT=pathlib.Path.home()/'Library/Application Support/MXMED/dev-session-persist02';base.OUT.mkdir(parents=True,exist_ok=True,mode=0o700)
base.EXTRA_PROTECTED.extend(['mxmed-dev-session-persist01','mxmed-advisor-capability-closure02'])
def setup(r,check):
 module('persist02_workspace',ROOT/'scripts/ase-advisor-workspace-checks.py').setup(r,check)
 r['env']['MXMED_DEV_PERSISTENT_COOKIE']='1'
 # Explicit persistence only on the new owned instance. No CONFIG on existing services.
 from resp import command
 command(r['valkey_port'],'CONFIG','SET','appendonly','yes');command(r['valkey_port'],'CONFIG','SET','appendfsync','always')
 (r['owned']/'oper09-advisor.json').write_text(json.dumps({'email':'qa_target@example.test','account':'qa_target','name':'Asesor sintético'}))
 # Current source expects this additive review metadata, even without invoking admission.
 r['source_sql']((ROOT/'modules/identity/db/migrations/2026_10_21_02_staff_session_review.sql').read_text())
 check('New owned persistence configured; canonical TTL untouched',True)
def run(r,check,races):
 r['OUT']=base.OUT
 module('persist02_browser',ROOT/'tools/dev-session-persist02/staff.py').run(r,check,base.processes)
base.SETUP_HOOK=setup;base.HOOK=run
if __name__=='__main__':base.main()
