#!/usr/bin/env python3
"""STAFF-CONTROL04 targeted checks in new disposable infrastructure."""
import importlib.util,pathlib,os
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
base=module('staff04_base',ROOT/'scripts/ase-advisor-workspace03-qa.py').base
checks=module('staff04_checks',ROOT/'scripts/staff-control04-checks.py')
base.EXPECTED_BRANCH='feature/mxmed-staff-control04-cross-surface'
base.BASE='8d0dfef72d45253fc9827e22a1f451f6abd3c981'
base.OUT=ROOT/'docs/evidence/staff-control04'
base.EXTRA_PROTECTED.append('mxmed-ase-advisor-workspace03-physician-pending-groups')
base.ROUTER='scripts/staff-control04-router.php'
base.SETUP_HOOK=checks.setup
base.HOOK=checks.run
if __name__=='__main__':
 import argparse
 p=argparse.ArgumentParser();p.add_argument('--keep-review',action='store_true');a=p.parse_args()
 if a.keep_review:os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1'
 base.main()
