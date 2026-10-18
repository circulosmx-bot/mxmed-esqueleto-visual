#!/usr/bin/env python3
"""Read/draft-only responsive and applicant smoke against an owned UXFLOW03 preview."""
import pathlib,json,os,sys,re,time,hashlib
from playwright.sync_api import sync_playwright,expect
root=pathlib.Path(__file__).resolve().parents[1];owned=pathlib.Path(sys.argv[1]).resolve()
assert re.fullmatch(r'/private/tmp/mxmed-ase-adm02imp01-[A-Za-z0-9_]+',str(owned))
assert owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
info=json.loads((owned/'review-info.json').read_text());approval=json.loads((owned/'workflow-approval.json').read_text())
assert pathlib.Path(approval['root']).resolve()==root and time.time()<min(info['expiry_unix'],approval['expires'])
assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
records=json.loads((owned/'uxfunc02-records.json').read_text());tokens=json.loads((owned/'sessions.json').read_text());origin=info['url'].split('/internal/')[0];checks=[]
shots=root/'docs/evidence/ase-adm02-uxflow03/screens'
def check(name,value):
    assert value,name;checks.append({'name':name,'pass':True});print('PASS '+name,flush=True)
with sync_playwright() as pw:
    b=pw.chromium.launch();c=b.new_context(ignore_https_errors=True);c.add_cookies([{'name':'__Host-mxmed_session','value':tokens['director'],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=c.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)));p.goto(info['url']);expect(p.locator('#add-member')).to_be_enabled()
    for w,h in [(1440,900),(1366,768),(390,844)]:
        p.set_viewport_size({'width':w,'height':h});p.locator('#add-member').click();expect(p.locator('#onboarding')).to_be_visible();p.locator('#new-given_names').click();p.locator('#new-given_names').fill('Borrador sin guardar');p.locator('#new-first_surname').fill('Ficticio');p.locator('#new-operation_city').click();p.locator('#new-operation_city').fill('Ciudad de prueba');p.locator('#new-email').click();p.locator('#new-email').fill('borrador@example.test');p.locator('#new-channel').select_option('REMOTE_PREAUTHORIZED');p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso 2');p.locator('#previous-onboarding').click();expect(p.locator('#new-given_names')).to_have_value('Borrador sin guardar')
        check('Persona fields/navigation reachable at '+str(w),p.locator('#onboarding').evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'));p.screenshot(path=str(shots/f'persona-{w}.png'));p.once('dialog',lambda d:d.accept());p.locator('#cancel-onboarding').click()
        if w==390:p.locator('#show-directory').click()
        p.locator('#staff-status').select_option('PENDING');p.locator('[data-case="'+records['fernanda']['case']+'"]').click();expect(p.locator('#staff-case')).to_be_visible();check('Contextual case fits '+str(w),p.locator('#staff-case').evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'));p.screenshot(path=str(shots/f'case-{w}.png'))
        p.locator('[data-review-action="CORRECTIONS"]').click();expect(p.locator('#staff-reason-label')).to_be_visible();check('Contextual credentials fit '+str(w),p.locator('#staff-stepup').evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'));p.screenshot(path=str(shots/f'stepup-{w}.png'));p.locator('#cancel-staff-stepup').click();p.locator('#close-staff-case').click();p.locator('#staff-status').select_option('ACTIVE')
    ac=b.new_context(ignore_https_errors=True,viewport={'width':390,'height':844});ac.add_cookies([{'name':'__Host-mxmed_session','value':tokens['fernanda'],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);a=ac.new_page();a.on('pageerror',lambda e:errors.append(str(e)));a.goto(origin+'/staff-admission.php');expect(a.locator('#wf-applicant')).to_be_visible();a.locator('#wf-cases').select_option(records['fernanda']['case']);expect(a.locator('#wf-given_names')).to_have_value('Fernanda Solicitud');check('Applicant self-service remains connected without Director review controls',a.locator('#wf-review,#wf-reauth-email,#wf-action').count()==0)
    check('No exceptions in separated Director/applicant controllers',not errors);b.close()
(root/'docs/evidence/ase-adm02-uxflow03/smoke.json').write_text(json.dumps({'result':'PASS','checks':checks,'count':len(checks),'production_activation':False},indent=2)+'\n')
