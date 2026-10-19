"""Reuse canonical synthetic demonstration preparation; execute only PANEL08 focused checks."""
import importlib.util

def module(name,path):
 spec=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m

def run(r,check,races):
 fixture=module('panel08_fixture',r['ROOT']/'scripts/ase-adm02-uxflow03-checks.py')
 def focused(*args):
  module('panel08_browser',r['ROOT']/'scripts/ase-adm02-panel08-browser.py').run(*args)
 fixture.browser=focused
 fixture.run(r,check,races)
