#!/usr/bin/env python3
"""Targeted correction on the existing owner-verified disposable harness."""
import importlib.util,pathlib,os,json,hashlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
base=module('staff06_base',ROOT/'scripts/staff-control04-qa.py').base
checks=module('staff06_existing_checks',ROOT/'scripts/staff-control04-checks.py')
base.EXPECTED_BRANCH='feature/mxmed-staff-control06-director-ux'
base.BASE='ab6b78349c26b9f80906065dedfdb1210449726f'
base.OUT=ROOT/'docs/evidence/staff-control06'
base.EXTRA_PROTECTED.extend(['mxmed-staff-control04-cross-surface','mxmed-staff-control05-admission-simplification'])
def setup(r,check):
 r['OUT']=base.OUT;checks.setup(r,check);r['env']['MXMED_STAFF_CONTROL06']='1'
 p=r['owned']/'workflow-approval.json';a=json.loads(p.read_text())
 for f in [*ROOT.glob('scripts/staff-control06*'),ROOT/'assets/js/team-selects.js',ROOT/'assets/css/internal-team.css']:a['source_hashes'][str(f.relative_to(ROOT))]=hashlib.sha256(f.read_bytes()).hexdigest()
 p.write_text(json.dumps(a))
def run(r,check,races):
 r.update(OUT=base.OUT,BROWSER='scripts/staff-control06-browser.py');checks.run(r,check,races)
base.SETUP_HOOK=setup;base.HOOK=run
if __name__=='__main__':
 import argparse
 p=argparse.ArgumentParser();p.add_argument('--keep-review',action='store_true');args=p.parse_args()
 if args.keep_review:os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1'
 base.main()
