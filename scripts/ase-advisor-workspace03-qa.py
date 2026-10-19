#!/usr/bin/env python3
"""WORKSPACE03: fresh owned infrastructure and focused regressions only."""
import importlib.util,pathlib,os
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
base=module('ws03_base',ROOT/'scripts/ase-advisor-workspace02-qa.py').base
checks=module('ws03_checks',ROOT/'scripts/ase-advisor-workspace03-checks.py')
base.EXPECTED_BRANCH='feature/ase-advisor-workspace03-physician-pending-groups'
base.BASE='40b8691f94636909bfb448655939e91387f59fe5'
base.OUT=ROOT/'docs/evidence/ase-advisor-workspace03'
base.EXTRA_PROTECTED.append('mxmed-ase-advisor-workspace02-profile-requests')
base.SETUP_HOOK=checks.setup
base.HOOK=checks.run
if __name__=='__main__':
 import argparse
 parser=argparse.ArgumentParser();parser.add_argument('--keep-review',action='store_true');args=parser.parse_args()
 if args.keep_review:os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1'
 base.main()
