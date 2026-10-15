#!/usr/bin/env python3
"""Compatibility entry: OP02's rejected standalone UI was retired by OP-UI03.
All destructive UI QA now targets the separate disposable UI03 schema only.
"""
import pathlib,runpy,sys
if '--reset-between-cases' in sys.argv:sys.argv.remove('--reset-between-cases')
runpy.run_path(str(pathlib.Path(__file__).with_name('op-ui03-browser-qa.py')),run_name='__main__')
