#!/usr/bin/env python3
"""Targeted UI/lifecycle integration in the existing owned disposable harness."""
import importlib.util,pathlib
root=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
    spec=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
base=module('owned_staff',root/'scripts/ase-adm02-workflow-qa.py')
checks=module('uxfunc_checks',root/'scripts/ase-adm02-panel05-checks.py')
base.EXPECTED_BRANCH='feature/ase-adm02-panel05-interaction-fixes'
base.BASE='b356b06e5a7650fafaea82d08ac396611b3d4b09'
base.OUT=root/'docs/evidence/ase-adm02-panel05'
base.EXTRA_PROTECTED.append('mxmed-ase-adm02-functional-consolidation')
base.EXTRA_PROTECTED.append('mxmed-ase-adm02-uxfunc01')
base.SEED_STAFF_ACTORS=['director','master','master2']
base.EXTRA_PROTECTED.append('mxmed-ase-adm02-uxfunc02')
base.EXTRA_PROTECTED.append('mxmed-ase-adm02-uxflow03')
base.EXTRA_PROTECTED.append('mxmed-ase-adm02-panel04')
base.REVIEW_SECONDS=86400
base.HOOK=checks.run
if __name__=='__main__':base.main()
