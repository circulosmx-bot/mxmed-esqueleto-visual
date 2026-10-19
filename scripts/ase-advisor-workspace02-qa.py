#!/usr/bin/env python3
"""Focused WORKSPACE02 rehearsal in new owner-verified disposable infrastructure."""
import argparse,importlib.util,os,pathlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 spec=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
base=module('ws02_base',ROOT/'scripts/ase-advisor-workspace-qa.py').base
checks=module('ws02_checks',ROOT/'scripts/ase-advisor-workspace02-checks.py')
base.EXPECTED_BRANCH='feature/ase-advisor-workspace02-profile-requests'
base.BASE='0dbccc946476f7f5e8e4de000e7f186913069a80'
base.OUT=ROOT/'docs/evidence/ase-advisor-workspace02'
base.EXTRA_PROTECTED.append('mxmed-ase-advisor-workspace01-r2')
base.ROUTER='scripts/ase-advisor-workspace02-router.php'
base.SETUP_HOOK=checks.setup
base.HOOK=checks.run
if __name__=='__main__':
 parser=argparse.ArgumentParser(description='Ensayo aislado WORKSPACE02 con conservación explícita del preview.')
 parser.add_argument('--keep-review',action='store_true',help='Conservar la infraestructura owned hasta su vencimiento de 24 horas o cierre manual')
 args=parser.parse_args()
 if args.keep_review:os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1'
 base.main()
