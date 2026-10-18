#!/usr/bin/env python3
"""IMP03 hook on fresh owned IMP01/02 regression infrastructure, never persistent targets."""
import importlib.util,pathlib,hashlib,json
ROOT=pathlib.Path(__file__).resolve().parents[1]
def load(name,path):
    spec=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
if __name__=='__main__':
    qa=load('foundation','scripts/ase-adm02-foundation-qa.py')
    invitations=load('invitations','scripts/ase-adm02-invitation-checks.py')
    media=load('media','scripts/ase-adm02-media-checks.py')
    qa.EXPECTED_BRANCH='feature/ase-adm02-private-staff-media'
    qa.BASE='27e0da02a4daf15d3c1a32ab5590e2527e835b52'
    qa.OUT=ROOT/'docs/evidence/ase-adm02-imp03'
    qa.EXTRA_PROTECTED+=['mxmed-ase-adm02-staff-case-foundation','mxmed-ase-adm02-staff-invitation-binding']
    qa.ROUTER='scripts/ase-adm02-invitation-router.php'
    def hook(r,check,races):
        invitations.run(r,check,races)
        media.run(r,check,races)
    qa.HOOK=hook
    qa.main()
    p=qa.OUT/'qa.json';r=json.loads(p.read_text())
    files=[*ROOT.glob('modules/identity/StaffMedia/*.php'),*ROOT.glob('scripts/ase-adm02-media-*.py'),ROOT/'modules/identity/tests/StaffMediaFixture.php',ROOT/'modules/identity/db/migrations/2026_10_18_06_staff_private_media.sql',ROOT/'modules/platform/tests/StaffMediaAuditPolicyTest.php']
    r['source_sha256'].update({str(f.relative_to(ROOT)):hashlib.sha256(f.read_bytes()).hexdigest() for f in files})
    r['verification_scope']='201 IMP01/IMP02 regression checks + private media canonical CLI foundation; no media HTTP or browser QA'
    p.write_text(json.dumps(r,indent=2)+'\n')
