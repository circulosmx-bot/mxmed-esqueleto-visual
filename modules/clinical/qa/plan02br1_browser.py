"""Real disposable Director QA. No mocked success; see PLAN02BR1_REVIEW.md."""
import json, os, uuid
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

OUT = Path(os.environ['PLAN02BR1_ARTIFACTS']); OUT.mkdir(parents=True, exist_ok=True)
BASE = 'http://127.0.0.1:18143'
URL = BASE + '/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide'

def boot(browser):
    ctx = browser.new_context(viewport={'width':1440,'height':900}, timezone_id='America/Mexico_City')
    page = ctx.new_page(); page.set_default_timeout(20000)
    page.on('pageerror', lambda e: (_ for _ in ()).throw(AssertionError(str(e))))
    page.goto(URL, wait_until='commit'); ready(page)
    return ctx, page

def ready(page):
    page.wait_for_function('document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId==="1015"', timeout=55000)
    page.wait_for_timeout(1200); page.locator('[data-m7-section="plan"]').click()

def saved(page):
    return page.evaluate('JSON.parse(sessionStorage.getItem("mxmed-plan02b:1:p_plan02_review:1015"))')

def count(page, n):
    badge=page.locator('[data-plan02b-count]')
    if n: expect(badge).to_have_text(str(n)); expect(badge).to_be_visible()
    else: expect(badge).not_to_be_visible()

def order(page,title):
    page.locator('[data-ns="orders"]').click(); page.locator('[data-order-field="title"]').fill(title)
    page.locator('[data-order-field="summary"]').fill('Indicación sintética de revisión')
    page.locator('[data-modal-add]').click(); expect(page.locator('.plan02b-modal')).to_have_count(0)

def appointment(page,date=None):
    page.locator('[data-ns="appointment"]').click(); page.locator('[name="ns-mode"][value="new"]').check()
    expect(page.locator('[data-ns-location] option[value="1"]')).to_be_attached()
    if date: page.locator('[data-ns-date]').fill(date); page.locator('[data-ns-date]').press('Tab')
    else:
        page.locator('[data-ns-days]').fill('10')
        assert page.locator('[data-ns-date]').input_value()=='2026-10-05'
    assert 'PLAN02' in page.locator('.plan02b-patient').inner_text()
    page.locator('[data-ns-location]').select_option('1'); page.locator('[data-ns="availability"]').click()
    expect(page.locator('[data-ns-slot]').first).to_be_visible()
    assert page.locator('[data-ns-slot][aria-pressed=true]').count()==0
    page.locator('[data-ns-slot]').first.click(); expect(page.locator('[data-slot-selection]')).to_contain_text('Aún no reservado')
    page.screenshot(path=str(OUT/'appointment-modal.png')); page.locator('[data-modal-add]').click()

def followup(page,title):
    page.locator('[data-ns="followup"]').click(); page.locator('[data-ns-title]').fill(title)
    page.locator('[data-ns-link]').select_option('yes'); expect(page.locator('[data-ns-due]')).not_to_be_visible()
    page.screenshot(path=str(OUT/'followup-modal.png')); page.locator('[data-modal-add]').click()

def collector(page):
    page.locator('[data-m7-section="documents"]').click(); expect(page.locator('[data-plan02b-collector]')).to_be_visible()
    expect(page.locator('.plan02b-leave')).to_have_count(0)

def capture(page, writes):
    def receive(r):
        if r.request.method=='POST' and (r.url.endswith('/documents') or r.url.endswith('/appointments') or '/longitudinal/tasks' in r.url):
            writes.append({'url':r.url,'payload':r.request.post_data_json,'key':r.request.headers.get('idempotency-key'),'body':r.json()})
    page.on('response', receive)

def confirm(page):
    page.locator('[data-ns="confirm"]').click(); expect(page.locator('[data-ns="confirm"]')).to_have_text('Confirmar acciones',timeout=30000)

def full(browser):
    ctx,page=boot(browser);writes=[];capture(page,writes);report={}
    bar=page.locator('[data-plan02b]');bar.scroll_into_view_if_needed();height=bar.bounding_box()['height']
    ys=[b['y'] for b in page.locator('.plan02b-action-bar button').evaluate_all('(xs)=>xs.map(x=>({y:x.getBoundingClientRect().y}))')];assert max(ys)-min(ys)<2
    page.screenshot(path=str(OUT/'compact-plan.png'));original=page.locator('[data-m7-editor-text]').input_value()
    for kind in ['orders','appointment','followup']:
        button=page.locator('[data-ns="'+kind+'"]');button.click();page.locator('[data-modal-cancel]').click();count(page,0)
        assert button.evaluate('(e)=>document.activeElement===e')
    assert not writes and not page.evaluate('window.mxmedPlanNextSteps.hasPending()')
    order(page,'PLAN02BR1 laboratorio A');order(page,'PLAN02BR1 imagen B');count(page,2)
    collector(page);first=page.locator('[data-prepared]').first;first.locator('[data-review]').click()
    page.locator('[data-order-field="title"]').fill('PLAN02BR1 laboratorio A revisada');page.screenshot(path=str(OUT/'order-modal.png'))
    page.locator('[data-modal-add]').click();assert first.locator('[data-review]').evaluate('(e)=>e===document.activeElement');count(page,2);accepted=saved(page)['orders'][0]
    first.locator('[data-review]').click();page.locator('[data-order-field="title"]').fill('DO NOT KEEP')
    page.once('dialog',lambda d:d.dismiss());page.keyboard.press('Escape');expect(page.locator('.plan02b-modal')).to_be_visible()
    page.locator('[data-modal-cancel]').click();assert saved(page)['orders'][0]==accepted
    page.locator('[data-m7-section="plan"]').click();appointment(page);followup(page,'PLAN02BR1 Revisar resultados');count(page,4)
    assert bar.bounding_box()['height']==height
    assert page.locator('[data-m7-editor-text]').input_value()==original
    assert page.locator('[data-lon06b-m7-follow-up]').is_hidden()
    for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
        page.set_viewport_size({'width':w,'height':h});bar.scroll_into_view_if_needed();assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
        page.screenshot(path=str(OUT/f'plan-{w}.png'))
        for kind in ['orders','appointment','followup']:
            page.locator('[data-ns="'+kind+'"]') .click();modal=page.locator('.plan02b-modal');box=modal.bounding_box();assert box['width']<=w and box['height']<=h
            assert modal.evaluate('(e)=>e.scrollWidth<=e.clientWidth');page.screenshot(path=str(OUT/f'{kind}-{w}.png'))
            # Native modal focus remains contained when tabbing across controls.
            for _ in range(16):page.keyboard.press('Tab');assert page.evaluate('!!document.activeElement.closest(".plan02b-modal")')
            page.locator('[data-modal-cancel]').click()
        collector(page);count(page,4);expect(page.locator('[data-prepared]')).to_have_count(4);assert page.evaluate('document.documentElement.scrollWidth<=innerWidth');page.screenshot(path=str(OUT/f'collector-{w}.png'));page.locator('[data-m7-section="plan"]').click();report[str(w)]='PASS'
    page.set_viewport_size({'width':1440,'height':900});assert not writes
    # Shared footer navigation preserves preparations too.
    page.locator('[data-vis04-next]').click();expect(page.locator('[data-plan02b-collector]')).to_be_visible();page.locator('[data-vis04-prev]').click();expect(bar).to_be_visible();count(page,4)
    page.locator('[data-m7-section="finalize"]').click();count(page,4);assert not writes
    page.locator('[data-m7-finalize]').click();expect(page.locator('.plan02b-leave')).to_be_visible();page.locator('[data-stay]').click();count(page,4)
    page.locator('[data-m7-section="plan"]').click();before=saved(page)
    page.once('dialog',lambda d:d.accept());page.reload(wait_until='commit');ready(page);assert saved(page)==before;count(page,4)
    collector(page);expect(page.locator('[data-m7-order-form]')).to_be_visible();expect(page.locator('[data-m7-doc-upload-form]')).to_be_visible();expect(page.locator('[data-m7-result-form]')).to_be_visible()
    confirm(page);expect(page.locator('[data-ns-message]')).to_contain_text('Próximos pasos registrados');count(page,4);assert len(writes)==4,writes
    s=saved(page);assert all(a['state']=='SUCCESS' for a in s['orders']+[s['appointment'],s['followup']]);aid=s['appointment']['result']['appointment_id'];tid=s['followup']['result']['item']['task_id']
    appt=page.request.get(BASE+'/api/agenda/index.php/appointments/'+aid).json()['data'];assert appt['status']=='tentative' and appt['patient_id']=='p_plan02_review'
    task=page.request.get(BASE+'/api/clinical/index.php/patients/p_plan02_review/longitudinal/tasks/'+str(tid)).json()['data']['item'];assert task['appointment_id']==aid and task['due_at'] is None and task['source_encounter_id']==1015
    docs=page.request.get(BASE+'/api/clinical/index.php/doctors/1/patients/p_plan02_review/documents?limit=200').json()['data']['items'];assert all(any(d['document_uuid']==o['result']['document_uuid'] and str(d['encounter_ref_id'])=='1015' for d in docs) for o in s['orders'])
    assert page.locator('#m7-workspace [data-m7-body]').get_attribute('data-encounter-state')=='open'
    expect(page.locator('[data-remove]')).to_have_count(0);page.screenshot(path=str(OUT/'collector-success.png'))
    report.update(writes=writes,state=s,appointment=appt,task=task);(OUT/'full-results.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));print('PASS full flow + modals + four widths + restoration + shared navigation + finalization guard',flush=True);ctx.close()

if __name__=='__main__':
    with sync_playwright() as p:
        browser=p.chromium.launch(headless=True);full(browser);browser.close()
