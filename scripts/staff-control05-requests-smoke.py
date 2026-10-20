#!/usr/bin/env python3
"""Reuse the existing focused Requests smoke on this correction's owned instance."""
import pathlib,sys,importlib.util
p=pathlib.Path(__file__).with_name('staff-control04-requests-smoke.py')
s=importlib.util.spec_from_file_location('requests_smoke',p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
if __name__=='__main__':m.run(sys.argv[1],'staff-control05-review.py','staff-control05')
