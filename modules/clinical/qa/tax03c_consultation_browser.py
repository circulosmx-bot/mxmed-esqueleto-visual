"""Browser integration of TAX03C with Plan preparation and Step 7 handoff."""
import json
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

from playwright.sync_api import expect, sync_playwright

root = Path(__file__).resolve().parents[3]
rows = [
    {'study_type_id': 1, 'study_type_key': 'glucose', 'display_name_es': 'Glucosa', 'category_key': 'LABORATORIO', 'category_label_es': 'Laboratorio'},
    {'study_type_id': 2, 'study_type_key': 'rx_chest', 'display_name_es': 'RX Tórax', 'category_key': 'IMAGEN', 'category_label_es': 'Imagenología'},
]
writes = []
task_writes = []


def handler(route):
    request = route.request
    path = urlsplit(request.url).path
    if '/study-types' in path:
        query = parse_qs(urlsplit(request.url).query)
        search = query.get('search', [''])[0].lower()
        items = [row for row in rows if search in row['display_name_es'].lower()]
        data = {'items': items, 'has_more': False, 'categories': [
            {'category_key': 'LABORATORIO', 'label_es': 'Laboratorio', 'active_count': 1},
            {'category_key': 'IMAGEN', 'label_es': 'Imagenología', 'active_count': 1}]}
        status = 200
    elif request.method == 'POST' and path.endswith('/documents'):
        writes.append({'path': path, 'body': request.post_data_json, 'key': request.headers.get('idempotency-key')})
        data = {'document_id': 41, 'document_uuid': '00000000-0000-4000-8000-000000000041'}
        status = 201
    elif request.method == 'POST' and path.endswith('/longitudinal/tasks'):
        task_writes.append({'path': path, 'body': request.post_data_json})
        data = {'item': {'task_id': 51}}
        status = 201
    else:
        data = {'items': []}
        status = 200
    route.fulfill(status=status, content_type='application/json', body=json.dumps({'ok': True, 'data': data}))


with sync_playwright() as playwright:
    browser = playwright.chromium.launch()
    page = browser.new_page(viewport={'width': 1440, 'height': 810})
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.route('http://127.0.0.1:38999/', lambda route: route.fulfill(status=200, content_type='text/html', body='<html><body></body></html>'))
    page.route('**/api/clinical/index.php/**', handler)
    page.goto('http://127.0.0.1:38999/')
    page.set_content('''<div id="p-expediente" data-patient-id="p_tax03c"></div>
        <div id="m7-workspace"><div data-m7-body data-encounter-id="77" data-encounter-key="enc:77" data-encounter-state="open" data-plan02b-section="plan"></div></div>
        <section data-plan02b></section><span data-plan02b-count></span><section data-plan02b-collector></section>
        <div data-plan-confirm-slot></div><button data-m7-finalize></button>
        <div data-review-documents hidden><div data-review-document-list></div></div>
        <button data-review-plan></button><button data-review-docs></button><button data-m7-terminal-refresh></button>
        <div class="m7-workspace-sections"><button data-m7-section="plan"></button><button data-m7-section="documents"></button></div>''')
    page.evaluate("window.mxmedStore={doctor_id:'d_tax03c',patientLabelById:{p_tax03c:'QA patient'}}")
    page.add_style_tag(path=str(root / 'assets/css/expediente-paciente-visual-normalization.css'))
    page.add_script_tag(path=str(root / 'assets/js/clinical/tax03c-study-composer.js'))
    page.add_script_tag(path=str(root / 'assets/js/clinical/plan02b-next-steps.js'))
    assert page.evaluate('!!window.mxmedPlanNextSteps'), errors
    page.locator('[data-plan02b] [data-ns="orders"]').click()
    assert page.locator('[data-tax03c-host]').count(), {'errors': errors, 'root_hidden': page.locator('[data-plan02b]').is_hidden()}
    expect(page.locator('[data-tax03c-host]')).to_be_visible()
    for width, height in ((1440, 810), (1440, 900), (1366, 768), (390, 844)):
        page.set_viewport_size({'width': width, 'height': height})
        modal = page.locator('.plan02b-modal')
        box = modal.bounding_box()
        assert box['width'] <= width and box['height'] <= height, (width, height, box)
        assert modal.evaluate('(element) => element.scrollWidth <= element.clientWidth'), (width, height)
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (width, height)
    page.set_viewport_size({'width': 1440, 'height': 810})
    search = page.locator('[data-tax03c-search]')
    search.fill('Glucosa')
    expect(page.locator('[data-tax03c-id="1"]')).to_be_visible()
    page.locator('[data-tax03c-id="1"]').click()
    search.fill('RX Tórax')
    expect(page.locator('[data-tax03c-id="2"]')).to_be_visible()
    page.locator('[data-tax03c-id="2"]').click()
    page.locator('[data-tax03c-priority]').select_option('Urgente')
    page.locator('[data-tax03c-indication]').fill('Control mixto de prueba')
    page.locator('[data-order-review="mode"]').select_option('days')
    page.locator('[data-order-review="days"]').fill('10')
    page.locator('[data-modal-add]').click()
    assert page.locator('.plan02b-modal').count() == 0, {'errors': errors, 'message': page.locator('[data-modal-error]').inner_text(), 'selected': page.locator('[data-tax03c-selected]').inner_text()}
    expect(page.locator('.plan02b-modal')).to_have_count(0)
    state = page.evaluate('JSON.parse(sessionStorage.getItem("mxmed-plan02b:d_tax03c:p_tax03c:77"))')
    assert len(state['orders']) == 1 and len(state['orders'][0]['items']) == 2, state
    assert len(state['orderReviews']) == 1 and state['orderReviews'][0]['derivedFromOrder'] == state['orders'][0]['id']
    assert state['orders'][0]['priority'] == 'Urgente' and state['orders'][0]['indication'] == 'Control mixto de prueba'
    assert not writes and not task_writes
    print('QA_CONSULTATION_PREPARATION_ONLY=PASS')

    page.locator('[data-m7-body]').evaluate("element => element.dataset.plan02bSection='finalize'")
    expect(page.locator('[data-ns="confirm"]')).to_be_visible()
    page.locator('[data-ns="confirm"]').click()
    page.wait_for_function('!window.mxmedPlanNextSteps.isBusy()')
    assert len(writes) == 1, writes
    assert writes[0]['path'].endswith('/encounters/enc%3A77/documents'), writes
    assert writes[0]['body']['document_type'] == 'orders'
    assert writes[0]['body']['payload']['order_items'] == [
        {'study_type_id': 1, 'study_type_key': 'glucose'},
        {'study_type_id': 2, 'study_type_key': 'rx_chest'},
    ]
    assert writes[0]['body']['payload']['priority'] == 'Urgente'
    assert writes[0]['body']['payload']['indication'] == 'Control mixto de prueba'
    assert writes[0]['key']
    assert len(task_writes) == 1 and task_writes[0]['body']['source_encounter_id'] == 77
    assert not errors, errors
    print('QA_CONSULTATION_STEP7_V2_ENCOUNTER_HANDOFF=PASS')
    browser.close()
