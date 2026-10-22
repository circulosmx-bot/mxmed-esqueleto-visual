#!/usr/bin/env python3
"""Actual protected requests, no source or original writes; full demo stays granted."""
import pathlib,importlib.util,json,subprocess,base64
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];s=importlib.util.spec_from_file_location('fg',ROOT/'scripts/advisor-full-grants-qa01-browser.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
x=subprocess.run(['php','scripts/advisor-full-grants-qa01-scope-fixture.php'],cwd=ROOT,env=m.E,input='{}',text=True,capture_output=True)
if x.returncode:(m.O/'boundary-private-error.log').write_text(x.stderr);raise RuntimeError('owned scope fixture failed')
selfs=json.loads((m.O/'full-self-origins.json').read_text());demo=json.loads((m.O/'full-demo.json').read_text());f=json.loads((m.O/'workspace02-fixture.json').read_text());media=json.loads((m.O/'workspace-media.json').read_text());logo=next(x['submission_id'] for x in media['items'] if x['owner_id']=='workspace_bruno' and x['purpose']=='PHYSICIAN_PERSONAL_LOGO');checks=[]
def check(n,status,expected):
 checks.append(dict(name=n,status=status,expected=expected,passed=status==expected));(m.OUT/'boundaries.json').write_text(json.dumps(checks,indent=2));print(('PASS ' if status==expected else 'FAIL ')+n,flush=True);assert status==expected
with sync_playwright() as pw:
 b=pw.chromium.launch();a=b.new_page(ignore_https_errors=True);m.native.login(a,m.origin,demo['email'],m.password,'/internal/staff-workspace/index.php')
 csrf=m.get(a,'/api/internal/workspace/media.php?mode=options&doctor_id=workspace_bruno')['body']['csrf']
 for mode in ['approve','replacement','improve']:
  check('Wrong physician '+mode,m.media.post(a,'/api/internal/workspace/media.php?mode='+mode+'&doctor_id=workspace_aurora',dict(submission_id=logo,csrf=csrf,reason_code='OTHER',feedback='Negative synthetic binding'))['status'],404)
 code=a.evaluate('''async ([id,csrf,data])=>{let f=new FormData();f.append('submission_id',id);f.append('csrf',csrf);f.append('corrected',new Blob([Uint8Array.from(atob(data),x=>x.charCodeAt(0))],{type:'image/png'}),'qa.png');return (await fetch('/api/internal/workspace/media.php?mode=corrected&doctor_id=workspace_aurora',{method:'POST',body:f})).status;}''',[logo,csrf,base64.b64encode((m.O/'synthetic.png').read_bytes()).decode()]);check('Wrong physician corrected upload',code,404)
 file=m.sql('SELECT file_id FROM auth_physician_evidence_files WHERE request_id=%s LIMIT 1',(f['new_ready']['evidence_request_id'],))[0][0]
 check('Cross-case evidence file',m.status(a,'/api/internal/workspace/requests.php?mode=evidence&request_id='+f['claim_ready']['application_id']+'&file_id='+file),403)
 for kind,rid in selfs.items():
  check('Own applicant request cannot be reviewed '+kind,m.get(a,'/api/internal/workspace/requests.php?mode=read&request_id='+rid)['status'],403)
  check('Own applicant evidence cannot be reviewed '+kind,m.status(a,'/api/internal/workspace/requests.php?mode=evidence&request_id='+rid+'&file_id='+file),403)
  check('Own applicant decision cannot begin '+kind,m.req.command(a,'begin',dict(request_id=rid,intake_version=1,review_version=0,revision_token='0'*64,decision='REJECTED',reason='Intento sintético de autorrevisión',email=demo['email'],password=m.password))['status'],403)
 # Validate MFA remains mandatory with otherwise authorized current facts.
 rid=f['information']['application_id'];r=m.req.snapshot(a,rid)['request'];begin=m.req.command(a,'begin',dict(request_id=rid,intake_version=r['intake_version'],review_version=r['review_version'],revision_token=r['revision_token'],decision='NEEDS_INFORMATION',reason='Prueba negativa sintética de MFA; sin decisión',email=demo['email'],password=m.password));assert begin['status']==200
 ch=begin['body']['data'];check('Invalid MFA cannot verify current challenge',m.req.command(a,'verify',dict(case_id=ch['case_id'],challenge_id=ch['challenge_id'],challenge=ch['challenge'],code='invalid'))['status'],403)
 check('No decision without valid MFA proof',m.req.command(a,'decide',dict(case_id=ch['case_id'],proof_id='missing',proof='missing',confirmed=True))['status'],403)
 a.locator('#staff-logout').click();a.wait_for_url('**/public/identity/acceso.php*');m.native.login(a,m.origin,demo['email'],m.password,'/internal/staff-workspace/index.php');check('Demo legitimate logout and relogin',m.admin(a)['status'],200)
 m.choose(a,'Aurora','workspace_aurora');m.item(a,next(x['submission_id'] for x in media['items'] if x['owner_id']=='workspace_aurora' and x['purpose']=='PHYSICIAN_PERSONAL_LOGO'));a.screenshot(path=str(m.OUT/'chromium-full-demo.png'),full_page=True);b.close()
