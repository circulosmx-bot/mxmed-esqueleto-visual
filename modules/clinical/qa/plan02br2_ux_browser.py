"""Read/preparation-only UX review; canonical writes stay in the stress fixture."""
from plan02br2_browser import *
import pymysql
UX=BASE+'/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide'
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True);ctx=browser.new_context(viewport={'width':1440,'height':900},timezone_id='America/Mexico_City');page=ctx.new_page();page.set_default_timeout(20000);writes=[];capture(page,writes)
    page.goto(UX,wait_until='commit');page.wait_for_function('document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId==="1016"',timeout=55000);page.wait_for_timeout(1200);page.locator('[data-m7-section="plan"]').click();expect(page.locator('[data-m7-editor-text]')).to_have_value('Solicitar estudios de control y revisar los resultados en la próxima consulta.');report={}
    assert not any(x in page.locator('#m7-workspace').inner_text() for x in ['PLAN02BR1','PLAN02B OPTIONAL','recovery'])
    for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
        page.set_viewport_size({'width':w,'height':h});page.locator('[data-plan02b]').scroll_into_view_if_needed();assert page.evaluate('document.documentElement.scrollWidth<=innerWidth');page.screenshot(path=str(OUT/f'ux-plan-{w}.png'))
        for kind in ['orders','appointment','followup']:
            opener=page.locator('[data-ns="'+kind+'"]');opener.click();modal=page.locator('.plan02b-modal')
            if kind=='appointment':
                expect(page.locator('.plan02b-patient')).to_have_text('Paciente: Elena Rivera Demostración');report['patient_copy']=page.locator('.plan02b-patient').inner_text()
                page.locator('[name="ns-mode"][value="new"]').check();expect(page.locator('[data-ns-location] option[value="1"]')).to_be_attached();page.locator('[data-ns-date]').fill('2026-11-02');page.locator('[data-ns-date]').press('Tab');page.locator('[data-ns-location]').select_option('1');page.locator('[data-ns="availability"]').click();expect(page.locator('[data-ns-slot]').first).to_be_visible();page.locator('[data-ns-slot]').first.click();expect(page.locator('.plan02b-modal-content [role=status]')).to_have_text('Horario seleccionado. Aún no reservado. Se reservará al confirmar las acciones.');assert 'Elige un horario disponible' not in modal.inner_text()
                assert page.locator('[data-ns-slot][aria-pressed=true]').evaluate('(e)=>getComputedStyle(e).backgroundColor')=='rgb(0, 115, 143)'
            if kind=='orders':page.locator('[data-order-field="title"]').fill('Estudios de control')
            if kind=='followup':page.locator('[data-ns-title]').fill('Revisar resultados de estudios')
            assert page.locator('[data-modal-add]').evaluate('(e)=>getComputedStyle(e).backgroundColor')=='rgb(0, 115, 143)'
            assert modal.evaluate('(e)=>e.scrollWidth<=e.clientWidth') and modal.bounding_box()['height']<=h
            page.screenshot(path=str(OUT/f'ux-{kind}-{w}.png'));page.locator('[data-modal-cancel]').click();assert opener.evaluate('(e)=>e===document.activeElement');count(page,0)
        collector(page);count(page,0);assert page.locator('[data-plan02b-collector]').bounding_box()['height']<85;page.screenshot(path=str(OUT/f'ux-empty-step6-{w}.png'));page.locator('[data-m7-section="plan"]').click();report[str(w)]='PASS'
    label=page.evaluate('window.mxmedStore.patientLabelById.p_plan02ux_review')
    for unsafe in ['Paciente','p_plan02ux_review','']:
        page.evaluate('(v)=>window.mxmedStore.patientLabelById.p_plan02ux_review=v',unsafe);page.locator('[data-ns="appointment"]').click();expect(page.locator('.plan02b-patient')).to_have_count(0);page.locator('[data-modal-cancel]').click()
    page.evaluate('(v)=>window.mxmedStore.patientLabelById.p_plan02ux_review=v',label)
    assert not writes;assert not page.evaluate('window.mxmedPlanNextSteps.hasPending()')
    # Confirm the context remains clean after visual review; no hidden stress records.
    c=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',database='mxmed_director_review_lon07c').cursor()
    for table in ['clinical_documents','clinical_patient_tasks','agenda_appointments']:
        c.execute('SELECT COUNT(*) FROM '+table+' WHERE patient_id=%s',('p_plan02ux_review',));assert c.fetchone()[0]==0
    report['no_domain_writes']=True;report['url']=UX;(OUT/'ux-results.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));print('PASS clean UX context, actual patient, scoped teal, four widths, safe cancel, zero domain writes',flush=True);ctx.close();browser.close()
