#!/usr/bin/env python3
"""Focused document lifecycle verification in a fresh owned synthetic composition."""
import importlib.util,pathlib,os,json,hashlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
previous=module('doc_previous',ROOT/'scripts/staff-doc-lifecycle03-qa.py');base=previous.base
base.EXPECTED_BRANCH='feature/mxmed-staff-auth-ux08';base.BASE='cd2a9ae940fa3aaa6e312bfd8294892d19bb7f04';base.OUT=ROOT/'docs/evidence/staff-auth-ux08'
base.EXTRA_PROTECTED.append('mxmed-staff-doc-lifecycle03')
def setup(r,check):
 previous.setup(r,check);r['env']['MXMED_STAFF_DOC_VIS02']='1'
 r['source_sql']((ROOT/'modules/identity/db/migrations/2026_10_21_02_staff_session_review.sql').read_text())
 p=r['owned']/'workflow-approval.json';a=json.loads(p.read_text())
 for name in ['modules/identity/db/migrations/2026_10_21_02_staff_session_review.sql','assets/js/staff-applicant.js','partials/identity/staff-applicant.php',*[str(f.relative_to(ROOT)) for f in ROOT.glob('scripts/staff-auth-ux08*')]]:a['source_hashes'][name]=hashlib.sha256((ROOT/name).read_bytes()).hexdigest()
 p.write_text(json.dumps(a))
def run(r,check,races):
 r.update(OUT=base.OUT,BROWSER='scripts/staff-auth-ux08-browser.py');module('lifecycle_checks',ROOT/'scripts/staff-control04-checks.py').run(r,check,races)
base.SETUP_HOOK=setup;base.HOOK=run
if __name__=='__main__':
 import argparse
 p=argparse.ArgumentParser();p.add_argument('--keep-review',action='store_true');args=p.parse_args()
 if args.keep_review:os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1'
 base.main()
