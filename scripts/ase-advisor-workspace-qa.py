#!/usr/bin/env python3
"""R2 focused workspace checks in a new owned instance; no historical suite."""
import importlib.util,pathlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
base=module('workspace_base',ROOT/'scripts/ase-adm02-oper09-qa.py').base
checks=module('workspace_checks',ROOT/'scripts/ase-advisor-workspace-checks.py')
base.EXPECTED_BRANCH='feature/ase-advisor-workspace01-r2-top-tabs'
base.BASE='5ca9bfa24b081afdc652d7f65443f3b80979f34e'
base.OUT=ROOT/'docs/evidence/ase-advisor-workspace01-r2'
base.EXTRA_PROTECTED.append('mxmed-ase-adm02-oper09')
base.SETUP_HOOK=checks.setup
base.HOOK=checks.run
if __name__=='__main__':base.main()
