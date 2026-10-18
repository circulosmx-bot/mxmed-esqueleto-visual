#!/usr/bin/env python3
"""Targeted UI/lifecycle integration in the existing owned disposable harness."""
import importlib.util,pathlib
root=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
    spec=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
base=module('owned_staff',root/'scripts/ase-adm02-workflow-qa.py')
checks=module('uxfunc_checks',root/'scripts/ase-adm02-uxfunc01-checks.py')
base.EXPECTED_BRANCH='feature/ase-adm02-uxfunc01'
base.BASE='66056af75a70de8e1821ac3064d48d629bc76bfb'
base.OUT=root/'docs/evidence/ase-adm02-uxfunc01'
base.EXTRA_PROTECTED.append('mxmed-ase-adm02-functional-consolidation')
base.REVIEW_SECONDS=86400
base.HOOK=checks.run
if __name__=='__main__':base.main()
