#!/usr/bin/env python3
"""One canonical Advisor journey in fresh, owned, loopback-only infrastructure."""
import importlib.util,pathlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
base=module('oper09_base',ROOT/'scripts/ase-adm02-panel08-qa.py').base
checks=module('oper09_checks',ROOT/'scripts/ase-adm02-oper09-checks.py')
base.EXPECTED_BRANCH='feature/ase-adm02-oper09-first-functional-advisor'
base.BASE='f486c42bd5272246a4a6b110d18affba641b29ff'
base.OUT=ROOT/'docs/evidence/ase-adm02-oper09'
base.EXTRA_PROTECTED.append('mxmed-ase-adm02-panel08')
base.SETUP_HOOK=checks.setup
base.HOOK=checks.run
if __name__=='__main__':base.main()
