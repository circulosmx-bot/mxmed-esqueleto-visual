"""Browser check of existing approved staff shell and real HTTP onboarding controls."""
import pathlib,json,time,uuid
from playwright.sync_api import sync_playwright,expect

def run(r,check):
    owned=r['owned'];root=r['ROOT'];origin=r['origin'];sql=r['sql'];tokens=r['tokens']
    shots=root/'docs/evidence/ase-adm02-functional/screens';shots.mkdir(parents=True,exist_ok=True)
    def context(browser,actor,w=1366,h=900):
        c=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h})
        c.add_cookies([{'name':'__Host-mxmed_session','value':tokens[actor],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);return c,c.new_page()
    with sync_playwright() as pw:
        for engine in ['chromium','webkit']:
            browser=getattr(pw,engine).launch()
            for w,h in [(1366,900),(820,1180),(390,844)]:
                c,page=context(browser,'director',w,h);page.goto(origin+'/internal/media-review/index.php?section=equipo')
                expect(page.locator('#add-member')).to_be_enabled()
                page.locator('#add-member').click();expect(page.locator('#wf-given_names')).to_be_focused()
                check(f'{engine} {w} approved shell plus functional entry, no horizontal overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth+1'))
                page.screenshot(path=str(shots/f'{engine}-{w}-director.png'),full_page=False,animations='disabled',timeout=60000);c.close()
            browser.close()
        browser=pw.chromium.launch();dc,d=context(browser,'director');ac,a=context(browser,'outsider')
        d.goto(origin+'/internal/media-review/index.php?section=equipo');expect(d.locator('#add-member')).to_be_enabled();d.locator('#add-member').click();expect(d.locator('#wf-director')).to_be_visible()
        d.locator('#wf-given_names').fill('Solicitante UI Ficticio');d.locator('#wf-first_surname').fill('Ensayo');d.locator('#wf-new').click()
        expect(d.locator('#wf-status')).to_contain_text('Solicitud preparada')
        cid=d.locator('#wf-cases').input_value();d.locator('#wf-email').fill('qa_outsider@example.test');d.locator('#wf-invite').click();expect(d.locator('#wf-status')).to_contain_text('Invitación confirmada')
        messages=[json.loads(p.read_text()) for p in owned.glob('invitation-*.json')];m=next(x for x in messages if x['case_id']==cid)
        a.goto(origin+'/staff-admission.php');expect(a.locator('#wf-applicant')).to_be_visible()
        a.get_by_text('Aceptar invitación',exact=True).click();a.locator('#wf-inv-case').fill(cid);a.locator('#wf-inv-id').fill(m['invitation_id']);a.locator('#wf-token').fill(m['token']);a.locator('#wf-accept-invite').click()
        expect(a.locator('#wf-status')).to_have_text('Solicitud consultada.');expect(a.locator('#wf-given_names')).to_have_value('Solicitante UI Ficticio')
        a.locator('#wf-confirm').click();expect(a.locator('#wf-status')).to_have_text('Solicitud consultada.')
        # Wait for canonical version instead of assuming the button completed synchronously.
        for _ in range(100):
            if sql('SELECT state FROM auth_staff_admission_cases WHERE case_id=%s',(cid,))[0][0]=='PERSON_CONFIRMED':break
            a.wait_for_timeout(100)
        a.locator('#wf-front').set_input_files(str(owned/'synthetic.png'));a.locator('#wf-back').set_input_files(str(owned/'synthetic.png'));a.locator('#wf-add-document').click()
        expect(a.locator('#wf-documents li')).to_have_count(1);a.locator('#wf-consent').check();a.locator('#wf-submit').click();expect(a.locator('#wf-documents li')).to_have_count(0)
        check('Browser applicant invitation, confirmation, two-side upload and submission persist canonically',sql('SELECT COUNT(*) FROM auth_staff_document_sets WHERE case_id=%s',(cid,))[0][0]==1)
        check('Browser Director controls absent for applicant',a.locator('#wf-review').is_hidden())
        a.screenshot(path=str(shots/'chromium-applicant-submitted.png'),full_page=False,animations='disabled',timeout=60000)
        dc.close();ac.close();browser.close()
