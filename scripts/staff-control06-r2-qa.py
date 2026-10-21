#!/usr/bin/env python3
"""Director layout verification in a new owned synthetic composition."""
import importlib.util,pathlib,os,json,hashlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
previous=module('staff06_previous',ROOT/'scripts/staff-control06-qa.py')
base=previous.base
base.EXPECTED_BRANCH='feature/mxmed-staff-control06-r2-director-layout'
base.BASE='cbc92d964240a595e2804b4134fbe290cf02e226'
base.OUT=ROOT/'docs/evidence/staff-control06-r2'
base.EXTRA_PROTECTED.append('mxmed-staff-control06-director-ux')
def setup(r,check):
 previous.setup(r,check);r['env']['MXMED_STAFF_CONTROL06_R2']='1'
 p=r['owned']/'workflow-approval.json';a=json.loads(p.read_text())
 for name in ['partials/identity/internal-team.php','partials/identity/staff-workflow.php']:
  a['source_hashes'][name]=hashlib.sha256((ROOT/name).read_bytes()).hexdigest()
 p.write_text(json.dumps(a))
def run(r,check,races):
 r.update(OUT=base.OUT,BROWSER='scripts/staff-control06-r2-browser.py');previous.checks.run(r,check,races)
base.SETUP_HOOK=setup;base.HOOK=run
if __name__=='__main__':
 import argparse
 p=argparse.ArgumentParser();p.add_argument('--keep-review',action='store_true');args=p.parse_args()
 if args.keep_review:os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1'
 base.main()
