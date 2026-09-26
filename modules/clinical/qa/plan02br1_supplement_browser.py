"""Navigation, dependency removal, patient scope and preserved Step 6 tools."""
from plan02br1_browser import *

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True);ctx,page=boot(browser);writes=[];capture(page,writes);report={}
    collector(page);count(page,0);page.locator('[data-m7-doc-refresh]').click();expect(page.locator('[data-m7-encounter-documents]')).to_contain_text('PLAN02');count(page,0)
    page.locator('[data-m7-section="plan"]').click();page.locator('[data-ns="followup"]').click();page.locator('[data-ns-title]').fill('R1 independent deadline');page.locator('[data-ns-due]').fill('2026-10-04T12:00');page.locator('[data-modal-add]').click();count(page,1)
    page.locator('[data-ns="appointment"]').click();expect(page.locator('[data-ns-existing] option').nth(1)).to_be_attached();value=page.locator('[data-ns-existing] option').nth(1).get_attribute('value');page.locator('[data-ns-existing]').select_option(value);page.locator('[data-modal-add]').click();assert not saved(page)['followup']['link'];count(page,2)
    page.locator('[data-ns="followup"]').click();page.locator('[data-ns-link]').select_option('yes');assert page.locator('[data-ns-due]').input_value()=='2026-10-04T12:00';expect(page.locator('[data-ns-due]')).to_be_visible();page.locator('[data-modal-add]').click();count(page,2)
    collector(page);s=saved(page);page.locator('[data-remove="'+s['appointment']['id']+'"]').click();expect(page.locator('[data-ns-message]')).to_contain_text('depende');count(page,2);assert saved(page)['followup']['link']
    page.locator('[data-remove="'+s['followup']['id']+'"]').click();page.locator('[data-remove="'+s['appointment']['id']+'"]').click();count(page,0);assert not writes;report['explicit_dependency_removal']='PASS'
    page.locator('[data-m7-section="plan"]').click();page.locator('[data-ns="orders"]').click();page.locator('[data-order-field="title"]').fill('R1 double acceptance')
    page.locator('[data-modal-add]').evaluate('(e)=>{e.click();e.click()}');count(page,1);assert len(saved(page)['orders'])==1;report['double_accept']='PASS'
    # A real M7 section save on internal navigation must not execute the preparation.
    editor=page.locator('[data-m7-editor-text]');original=editor.input_value();draft=original+'\nPLAN02BR1 safe-save QA';editor.fill(draft);page.locator('[data-vis04-next]').click();expect(page.locator('[data-plan02b-collector]')).to_be_visible();assert not writes;count(page,1)
    detail=page.request.get(BASE+'/api/clinical/index.php/encounters/enc%3A1015').json()['data'];assert detail['sections']['plan']['narrative_text']==draft
    page.locator('[data-vis04-prev]').click();assert editor.input_value()==draft;editor.fill(original);page.locator('[data-m7-editor-save]').click();expect(page.locator('[data-m7-editor-save]')).to_be_disabled();report['narrative_safe_save']='PASS'
    s=saved(page);page.once('dialog',lambda d:d.accept());page.goto(BASE+'/index.html?review_encounter=open&qa_tools=hide',wait_until='commit');page.wait_for_function('document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId==="1001"',timeout=55000);page.wait_for_timeout(1200);page.locator('[data-m7-section="plan"]').click();count(page,0);assert page.locator('#p-expediente').get_attribute('data-patient-id')=='review-patient'
    page.goto(URL,wait_until='commit');ready(page);count(page,1);assert saved(page)==s;report['patient_context_isolation']='PASS'
    collector(page);page.locator('[data-remove]').click();count(page,0)
    # Existing Step 6 writer remains usable and is not counted as a Plan preparation.
    page.locator('[data-m7-order-title]').fill('PLAN02BR1 existing Step 6 tool');page.locator('[data-m7-order-summary]').fill('Synthetic regression');page.locator('[data-m7-order-form] button[type=submit]').click();expect(page.locator('[data-m7-encounter-documents]')).to_contain_text('PLAN02BR1 existing Step 6 tool');count(page,0);assert len(writes)==1 and writes[0]['body']['ok'];report['existing_step6_writer']='PASS'
    for date,n in [('2026-09-28',6),('2026-09-26',5),('2026-09-27',0)]:assert len(page.request.get(BASE+'/api/agenda/index.php/availability?doctor_id=1&consultorio_id=1&date='+date).json()['data']['slots'])==n
    report['baseline_and_override']='PASS';(OUT/'supplement-results.json').write_text(json.dumps(report,indent=2));print(report,flush=True);ctx.close();browser.close()
