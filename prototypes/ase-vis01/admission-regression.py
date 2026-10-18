#!/usr/bin/env python3
"""Run the unchanged R2 behavioral assertions with ADM's three static assets allowed."""
import hashlib
import importlib.util
import json
from pathlib import Path
import sys

HERE=Path(__file__).resolve().parent
ROOT=HERE.parents[1]
if len(sys.argv)!=1:
    raise SystemExit('No arguments: evidence is restricted to the ADM-owned directory.')
spec=importlib.util.spec_from_file_location('ase_r2_regression',HERE/'qa.py')
suite=importlib.util.module_from_spec(spec)
spec.loader.exec_module(suite)
suite.ROUTES.update({'/admission.js','/admission.css','/portrait-example.svg'})
out=HERE/'evidence/adm01/r2-regression'
sys.argv=[str(HERE/'qa.py'),'--url','http://127.0.0.1:8140/','--evidence',str(out)]
result=suite.main()
report=json.loads((out/'summary.json').read_text())
report.update(task='ASE-ADM01 R2 behavioral regression',final_visual_approval_scope='ADM and commercial extensions only',assertion_changes='NONE; only three allowlisted static assets added in memory')
for name in ['admission.js','admission.css','portrait-example.svg','admission-qa.py','admission-regression.py']:
    report['source_sha256'][str((HERE/name).relative_to(ROOT))]=hashlib.sha256((HERE/name).read_bytes()).hexdigest()
(out/'summary.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
raise SystemExit(result)
