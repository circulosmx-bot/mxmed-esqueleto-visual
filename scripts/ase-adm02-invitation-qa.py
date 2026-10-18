#!/usr/bin/env python3
"""Reuse the owned fresh-instance harness and all IMP01 regression checks.
The harness's private ase-adm02imp01 namespace/CLI guard remains unchanged.
An additional hook installs IMP02 only in that same verified new datadir.
"""
import importlib.util, pathlib, hashlib, json
ROOT=pathlib.Path(__file__).resolve().parents[1]
def load(name,path):
    spec=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
if __name__=='__main__':
    qa=load('foundation','scripts/ase-adm02-foundation-qa.py')
    tests=load('invitation','scripts/ase-adm02-invitation-checks.py')
    qa.EXPECTED_BRANCH='feature/ase-adm02-staff-invitation-binding'
    qa.BASE='2330be886bc984d8ce89c5952113d540bdf1f3cb'
    qa.OUT=ROOT/'docs/evidence/ase-adm02-imp02'
    qa.EXTRA_PROTECTED.append('mxmed-ase-adm02-staff-case-foundation')
    qa.ROUTER='scripts/ase-adm02-invitation-router.php'
    qa.HOOK=tests.run
    qa.main()
    p=qa.OUT/'qa.json';r=json.loads(p.read_text())
    sources=[*ROOT.glob('modules/identity/StaffAdmission/*.php'),*ROOT.glob('api/internal/staff-admission/*.php'),*ROOT.glob('scripts/ase-adm02-invitation*'),ROOT/'modules/identity/tests/StaffInvitationFixture.php',ROOT/'modules/identity/db/migrations/2026_10_18_05_staff_admission_invitations.sql']
    r['source_sha256'].update({str(f.relative_to(ROOT)):hashlib.sha256(f.read_bytes()).hexdigest() for f in sources})
    r['verification_scope']='IMP01 HTTP regression + IMP02 canonical CLI E2E; invitation HTTP positive unavailable pending production policy'
    p.write_text(json.dumps(r,indent=2)+'\n')
