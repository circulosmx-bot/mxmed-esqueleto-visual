#!/usr/bin/env python3
"""Viewer-only verification using the existing owned synthetic composition."""
import importlib.util,pathlib,os,json,hashlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
previous=module('doc_previous',ROOT/'scripts/staff-control07-qa.py');base=previous.base
base.EXPECTED_BRANCH='feature/mxmed-staff-doc-vis01';base.BASE='486124b33bdc8179ffc58f5f4eaf0d06d99463b3';base.OUT=ROOT/'docs/evidence/staff-doc-vis01'
base.EXTRA_PROTECTED.append('mxmed-staff-control07-stable-navigation')
def setup(r,check):
 previous.setup(r,check);r['env']['MXMED_STAFF_DOC_VIS01']='1'
 p=r['owned']/'workflow-approval.json';a=json.loads(p.read_text())
 for name in ['assets/js/staff-evidence-viewer.js',*[str(f.relative_to(ROOT)) for f in ROOT.glob('scripts/staff-doc-vis01*')]]:a['source_hashes'][name]=hashlib.sha256((ROOT/name).read_bytes()).hexdigest()
 p.write_text(json.dumps(a))
def run(r,check,races):
 r.update(OUT=base.OUT,BROWSER='scripts/staff-doc-vis01-browser.py');previous.checks.run(r,check,races)
base.SETUP_HOOK=setup;base.HOOK=run
if __name__=='__main__':
 import argparse
 p=argparse.ArgumentParser();p.add_argument('--keep-review',action='store_true');args=p.parse_args()
 if args.keep_review:os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1'
 base.main()
