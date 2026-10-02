"""OR03 physician UI regression with an in-memory, disposable REL01 projection."""
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:18148/'
V1 = '00000000-0000-4000-8000-000000000101'
V2 = '00000000-0000-4000-8000-000000000102'
IDS = [f'00000000-0000-4000-8000-{n:012d}' for n in (11, 12, 13, 21, 22)]


def study(name, item_id, coverage):
    return {'order_item_id': item_id, 'study_display_name': name,
            'study_category': 'LABORATORIO', 'coverage_state': coverage}


v1_items = [study('Estudio A de V1', IDS[0], 'RESULT_AVAILABLE'),
            study('Estudio B de V1', IDS[1], 'RESULT_AVAILABLE'),
            study('Estudio C de V1', IDS[2], 'RESULT_AVAILABLE')]
v2_items = [study('Estudio A de V2', IDS[3], 'RESULT_AVAILABLE'),
            study('Estudio B de V2', IDS[4], 'NO_RESULT')]
versions = [
    {'id': 101, 'document_uuid': V1, 'version': 1, 'title': 'Orden V1',
     'created_at': '2026-09-29 10:00:00', 'has_private_binary': 0,
     'order_payload_version': 2, 'order_items': v1_items,
     'coverage_state': 'ALL_ITEMS_HAVE_RESULTS'},
    {'id': 102, 'document_uuid': V2, 'version': 2, 'title': 'Orden V2',
     'created_at': '2026-09-30 10:00:00', 'has_private_binary': 0,
     'order_payload_version': 2, 'order_items': v2_items,
     'coverage_state': 'PARTIAL_RESULTS'},
]
order = {**versions[1], 'document_type': 'lab_order', 'status': 'generated',
         'generated_at': None, 'chronology_at': '2026-09-30 10:00:00',
         'source_scope': 'PATIENT', 'has_successor': 0,
         'result_count': 3, 'versions': versions}


def result(number, title, source, item_ids, relationship):
    historical = source == 101
    return {'id': number, 'document_uuid': f'00000000-0000-4000-8000-{number:012d}',
            'document_type': 'lab_result', 'title': title, 'status': 'generated',
            'created_at': '2026-10-01 10:00:00', 'chronology_at': '2026-10-01 10:00:00',
            'result_source_order_document_id': source,
            'result_source_order_document_uuid': V1 if historical else V2,
            'result_source_order_version': 1 if historical else 2,
            'order_lineage_head_document_id': 102,
            'order_lineage_head_document_uuid': V2,
            'order_lineage_head_version': 2,
            'result_order_relationship': relationship,
            'related_order_document_id': 102,
            'related_order_item_ids': item_ids,
            'has_private_binary': 1, 'versions': []}


results = [result(201, 'Resultado A', 101, [IDS[0]], 'PREDECESSOR_VERSION'),
           result(202, 'Resultado B y C', 101, IDS[1:3], 'PREDECESSOR_VERSION'),
           result(203, 'Resultado V2', 102, [IDS[3]], 'DIRECT_CURRENT_VERSION')]
standalone = {**result(204, 'Resultado sin orden', 102, [], None),
              'result_source_order_document_id': None,
              'result_source_order_document_uuid': None,
              'result_source_order_version': None,
              'order_lineage_head_document_id': None,
              'order_lineage_head_document_uuid': None,
              'order_lineage_head_version': None,
              'related_order_document_id': None, 'result_origin': 'sin_orden'}


def projection(filter_name):
    if filter_name == 'results':
        items = [{'kind': 'RESULT', 'result': row} for row in results]
        items.append({'kind': 'STANDALONE_RESULT', 'result': standalone})
    elif filter_name == 'orders':
        items = [{'kind': 'ORDER', 'order': order, 'result_count': 3, 'results': results}]
    else:
        items = [{'kind': 'ORDER', 'order': order, 'result_count': 3, 'results': results},
                 {'kind': 'STANDALONE_RESULT', 'result': standalone}]
    return {'ok': True, 'data': {'items': items, 'has_more': False, 'cursor_next': None,
                                 'order_types': ['lab_order'], 'result_types': ['lab_result']}}


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height in [(1440, 900), (1366, 768), (390, 844)]:
        page = browser.new_page(viewport={'width': width, 'height': height})

        def intercept(route):
            url = route.request.url
            if 'orders_results_mode=1' in url:
                from urllib.parse import parse_qs, urlparse
                name = parse_qs(urlparse(url).query).get('filter', ['all'])[0]
                route.fulfill(json=projection(name))
            elif url.endswith('/documents/' + V1) or url.endswith('/documents/' + V2):
                version = versions[0] if url.endswith(V1) else versions[1]
                route.fulfill(json={'ok': True, 'data': {'document': {
                    'document_db_id': version['id'], 'document_id': version['document_uuid'],
                    'title': version['title'], 'content': {'payload': {
                        'order_payload_version': 2, 'order_items': version['order_items']}}}}})
            else:
                route.continue_()

        page.route('**/api/clinical/index.php/doctors/1/**', intercept)
        page.goto(BASE + '?qa_tools=hide', wait_until='domcontentloaded')
        page.wait_for_timeout(1000)
        page.locator('[data-bs-target="#t-estudios"]').first.click()
        pane = page.locator('#t-estudios')
        pane.get_by_role('button', name='Revisar órdenes pendientes', exact=False).click()
        pane.locator('.vis06-index-card').first.wait_for()
        pane.locator('.vis06-index-card').first.click()
        detail = pane.locator('.vis06-detail')
        assert 'Algunos estudios tienen resultado' in detail.inner_text()
        assert 'Estudio B de V2' in detail.inner_text()
        assert detail.locator('.vis06-result-entry').count() == 3
        assert detail.locator('.vis06-result-entry .vis06-historical-marker').count() == 2
        detail.locator('.vis06-result-entry').first.click()
        text = detail.inner_text()
        assert 'RESULTADO DE UNA VERSIÓN ANTERIOR' in text
        assert 'Versión 1' in text and 'Versión 2' in text
        assert 'Estudio A de V1' in text and 'Estudio A de V2' not in text
        detail.get_by_role('button', name='Ver orden donde se solicitó').click()
        source_dialog = page.locator('.vis06-source-dialog')
        source_dialog.wait_for()
        assert 'Orden V1' in source_dialog.inner_text()
        assert 'Estudio A de V1' in source_dialog.inner_text()
        assert 'Estudio A de V2' not in source_dialog.inner_text()
        source_dialog.get_by_role('button', name='Cerrar').last.click()
        if width == 390:
            detail.get_by_role('button', name='Volver a la lista').click()
        pane.locator('.vis06-index-card').first.click()
        detail.locator('.vis06-result-entry').nth(1).click()
        assert 'Estudio B de V1' in detail.inner_text() and 'Estudio C de V1' in detail.inner_text()
        if width == 390:
            detail.get_by_role('button', name='Volver a la lista').click()
        pane.get_by_role('button', name='Volver a opciones').first.click()
        pane.get_by_role('button', name='Ver resultados e historial', exact=False).click()
        pane.locator('.vis06-index-card').nth(2).wait_for()
        pane.locator('.vis06-index-card').nth(2).click()
        detail.get_by_text('Estudio A de V2').wait_for()
        direct = detail.inner_text()
        assert 'Corresponde a esta orden' in direct and 'Estudio A de V2' in direct, direct
        assert 'RESULTADO DE UNA VERSIÓN ANTERIOR' not in direct
        if width == 390:
            detail.get_by_role('button', name='Volver a la lista').click()
        pane.locator('.vis06-index-card').nth(3).click()
        assert 'Sin orden previa' in detail.inner_text()
        assert 'RESULTADO DE UNA VERSIÓN ANTERIOR' not in detail.inner_text()
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        print(f'QA_OR03_{width}x{height}=PASS')
        page.close()
    browser.close()
