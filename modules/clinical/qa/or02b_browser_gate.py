"""OR02B UI contract smoke with mocked read responses; no clinical writes."""
import json
from pathlib import Path
from urllib.parse import parse_qs, urlsplit
from playwright.sync_api import sync_playwright, expect

root = Path(__file__).resolve().parents[3]
calls = []

def doc(id, title, kind):
    return {'id': id, 'document_uuid': f'00000000-0000-4000-8000-{id:012d}',
            'document_type': kind, 'title': title, 'summary': '', 'version': 1,
            'status': 'generated', 'lineage_root_id': id, 'has_private_binary': 0,
            'chronology_at': '2026-09-30 12:00:00', 'event_datetime': '2026-09-01 00:00:00',
            'source_scope': 'PATIENT', 'versions': [], 'related_order_document_id': 1}

results = [doc(i, f'Resultado {i}', 'lab_result') for i in (2, 3, 4)]
order = {'kind': 'ORDER', 'order': doc(1, 'Orden con tres resultados', 'lab_order'),
         'result_count': 3, 'results': results}
standalone = {'kind': 'STANDALONE_RESULT', 'result': doc(5, 'Resultado independiente', 'result')}
response = lambda data: {'ok': True, 'data': data, 'error': None, 'message': 'ok'}

with sync_playwright() as playwright:
    browser = playwright.chromium.launch()
    page = browser.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))

    def route_handler(route):
        url = route.request.url
        calls.append(url)
        query = parse_qs(urlsplit(url).query)
        if '/encounters/active' in url:
            data = {'doctor_id': 'd_or02b'}
        elif 'orders_results_mode=1' in url:
            if query.get('filter') == ['results']:
                items = [{'kind': 'RESULT', 'result': results[0]}, standalone]
            elif query.get('search') == ['independiente']:
                items = [standalone]
            elif 'cursor' in query:
                items = [standalone]
            else:
                items = [order]
            data = {'items': items, 'has_more': items == [order],
                    'cursor_next': 'eyJhdCI6IjIwMjYtMDktMzAgMTI6MDA6MDAiLCJpZCI6MX0=' if items == [order] else None,
                    'order_types': ['order','orders','lab_order','imaging_order','orden_estudio'],
                    'result_types': ['lab_result','lab_pdf','imaging_result','external_result','external_report','result']}
        elif '/documents/' in url:
            data = {'content': {'payload': {'text': 'Detalle autorizado'}}}
        else:
            data = {'items': []}
        route.fulfill(status=200, content_type='application/json', body=json.dumps(response(data)))

    page.route('**/api/clinical/index.php/**', route_handler)
    page.route('http://mxmed.test/', lambda route: route.fulfill(status=200,content_type='text/html',body='<html><body></body></html>'))
    page.goto('http://mxmed.test/')
    page.set_content('<div id="p-expediente" data-patient-id="p_or02b"><div id="t-estudios"></div><div id="t-consent"></div><div id="t-tratamiento"></div></div>')
    page.add_script_tag(path=str(root / 'assets/js/clinical/vis06-modules.js'))
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(1)
    page.locator('#t-estudios .vis06-index-card').click()
    expect(page.locator('#t-estudios .vis06-result-entry')).to_have_count(3)
    page.locator('#t-estudios .vis06-result-entry', has_text='Resultado 4').click()
    expect(page.locator('#t-estudios .vis06-projected-intro h4')).to_have_text('Resultado 4')
    page.locator('#t-estudios .vis06-controls input').fill('independiente')
    expect(page.locator('#t-estudios .vis06-index-copy strong')).to_have_text('Resultado independiente')
    assert any('search=independiente' in call for call in calls)
    page.locator('#t-estudios .vis06-controls input').fill('')
    page.locator('#t-estudios .vis06-segments button[data-filter="results"]').click()
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(2)
    assert any('filter=results' in call for call in calls)
    page.locator('#t-estudios .vis06-segments button[data-filter="all"]').click()
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(1)
    page.locator('#t-estudios .vis06-list button', has_text='Mostrar más').click()
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(2)
    page.locator('#t-estudios .vis06-refresh').click()
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(1)
    assert not errors, errors
    print('QA_UI_MULTIPLE_RESULTS=PASS; QA_UI_SEARCH_FILTER_PAGINATION_REFRESH=PASS; QA_UI_JS_ERRORS=NONE')
    browser.close()
