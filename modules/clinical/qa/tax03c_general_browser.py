"""General Orders/Results CTA integration with the shared TAX03C composer."""
import json
from pathlib import Path
from urllib.parse import urlsplit

from playwright.sync_api import expect, sync_playwright

root = Path(__file__).resolve().parents[3]
study = {'study_type_id': 12, 'study_type_key': 'glucose', 'display_name_es': 'Glucosa',
         'category_key': 'LABORATORIO', 'category_label_es': 'Laboratorio'}
writes = []
created = []


def handler(route):
    request = route.request
    path = urlsplit(request.url).path
    if '/study-types' in path:
        data = {'items': [study], 'has_more': False, 'categories': [
            {'category_key': 'LABORATORIO', 'label_es': 'Laboratorio', 'active_count': 1}]}
        status = 200
    elif request.method == 'POST' and path.endswith('/documents'):
        writes.append({'path': path, 'body': request.post_data_json, 'key': request.headers.get('idempotency-key')})
        data = {'document_id': 41, 'document_uuid': '00000000-0000-4000-8000-000000000041'}
        created.append(True)
        status = 201
    elif 'orders_results_mode=1' in request.url:
        orders = [] if not created else [{'kind': 'ORDER', 'order': {
            'id': 41, 'document_uuid': '00000000-0000-4000-8000-000000000041',
            'document_type': 'lab_order', 'title': 'Glucosa', 'status': 'generated',
            'chronology_at': '2026-09-30 12:00:00', 'source_scope': 'PATIENT',
            'order_payload_version': 2, 'order_items': [{'study_display_name': 'Glucosa', 'study_category': 'LABORATORIO'}],
            'requested_studies': ['Glucosa'], 'versions': []}, 'result_count': 0, 'results': []}]
        data = {'items': orders, 'has_more': False, 'cursor_next': None}
        status = 200
    elif '/encounters/active' in path:
        data = {'doctor_id': 'd_tax03c'}
        status = 200
    else:
        data = {'items': []}
        status = 200
    route.fulfill(status=status, content_type='application/json', body=json.dumps({'ok': True, 'data': data}))


with sync_playwright() as playwright:
    browser = playwright.chromium.launch()
    page = browser.new_page(viewport={'width': 1366, 'height': 768})
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.route('http://127.0.0.1:38999/', lambda route: route.fulfill(status=200, content_type='text/html', body='<html><body></body></html>'))
    page.route('**/api/clinical/index.php/**', handler)
    page.goto('http://127.0.0.1:38999/')
    page.set_content('<div id="p-expediente" data-patient-id="p_tax03c"><div id="t-estudios" class="active"></div><div id="t-consent"></div><div id="t-tratamiento"></div></div>')
    page.add_style_tag(path=str(root / 'assets/css/expediente-paciente-visual-normalization.css'))
    page.add_script_tag(path=str(root / 'assets/js/clinical/tax03c-study-composer.js'))
    page.add_script_tag(path=str(root / 'assets/js/clinical/vis06-modules.js'))
    create = page.locator('#t-estudios .vis06-create')
    expect(create).to_be_visible()
    create.click()
    expect(page.locator('.tax03c-dialog')).to_be_visible()
    assert page.locator('[data-est-section="solicitar"]:visible').count() == 0
    page.locator('[data-tax03c-search]').fill('Glucosa')
    expect(page.locator('[data-tax03c-id="12"]')).to_be_visible()
    page.locator('[data-tax03c-id="12"]').click()
    page.locator('[data-tax03c-indication]').fill('Control de laboratorio')
    page.locator('[data-tax03c-submit]').click()
    expect(page.locator('.tax03c-dialog')).to_have_count(0)
    assert len(writes) == 1, writes
    assert writes[0]['path'].endswith('/doctors/d_tax03c/patients/p_tax03c/documents')
    assert writes[0]['body']['payload']['order_items'] == [{'study_type_id': 12, 'study_type_key': 'glucose'}]
    assert writes[0]['body']['document_type'] == 'lab_order'
    assert writes[0]['key']
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(1)
    page.locator('#t-estudios .vis06-index-card').click()
    expect(page.locator('#t-estudios .vis06-study-list li')).to_contain_text('Glucosa')
    expect(page.locator('#t-estudios .vis06-study-category')).to_have_text('Laboratorio')
    assert not errors, errors
    print('QA_GENERAL_SINGLE_CTA_PATIENT_V2_SAVE_REFRESH=PASS')
    browser.close()
