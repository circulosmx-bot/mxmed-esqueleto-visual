"""OR04 study navigation against disposable, in-memory OR02B responses."""
import os
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:18148/'
V1, V2 = '00000000-0000-4000-8000-000000000301', '00000000-0000-4000-8000-000000000302'
IDS = [f'00000000-0000-4000-8000-{number:012d}' for number in (311, 312, 313, 321)]


def item(name, item_id, state, note=None, custom=False):
    return {'order_item_id': item_id, 'study_display_name': name, 'study_category': 'LABORATORIO',
            'study_type_id': None if custom else 1, 'study_type_key': None if custom else 'lab',
            'note': note, 'coverage_state': state}


def result(number, title, source, ids):
    historical = source == 301
    return {'id': number, 'document_uuid': f'00000000-0000-4000-8000-{number:012d}',
            'title': title, 'document_type': 'lab_result', 'status': 'generated',
            'created_at': '2026-10-01 10:00:00', 'chronology_at': '2026-10-01 10:00:00',
            'related_order_item_ids': ids, 'related_order_document_id': 302,
            'result_source_order_document_id': source,
            'result_source_order_document_uuid': V1 if historical else V2,
            'result_source_order_version': 1 if historical else 2,
            'order_lineage_head_document_id': 302, 'order_lineage_head_document_uuid': V2,
            'order_lineage_head_version': 2,
            'result_order_relationship': 'PREDECESSOR_VERSION' if historical else 'DIRECT_CURRENT_VERSION',
            'has_private_binary': 0, 'versions': []}


R_A = result(401, 'Resultado A', 302, [IDS[0]])
R_C = result(402, 'Resultado C', 302, [IDS[2]])
R_AB = result(403, 'Resultado A y B', 302, IDS[:2])
R_A2 = result(404, 'Segundo resultado A', 302, [IDS[0]])
R_WHOLE = result(405, 'Resultado general', 302, None)
R_OLD = result(406, 'Resultado histórico', 301, [IDS[3]])
CASES = {
    'none': ([], ['NO_RESULT'] * 3, 'NO_RESULTS'),
    'partial': ([R_A, R_C], ['RESULT_AVAILABLE', 'NO_RESULT', 'RESULT_AVAILABLE'], 'PARTIAL_RESULTS'),
    'multi': ([R_AB], ['RESULT_AVAILABLE', 'RESULT_AVAILABLE', 'NO_RESULT'], 'PARTIAL_RESULTS'),
    'multiple': ([R_A, R_A2], ['RESULT_AVAILABLE', 'NO_RESULT', 'NO_RESULT'], 'PARTIAL_RESULTS'),
    'whole': ([R_WHOLE], ['UNKNOWN'] * 3, 'UNKNOWN_COVERAGE'),
    'complete': ([R_AB, R_C], ['RESULT_AVAILABLE'] * 3, 'ALL_ITEMS_HAVE_RESULTS'),
    'predecessor': ([R_OLD], ['NO_RESULT'] * 3, 'NO_RESULTS'),
    'legacy': ([], [], 'UNKNOWN_LEGACY'),
}


def projection(case):
    results, states, coverage = CASES[case]
    studies = [item('Estudio A', IDS[0], states[0], 'En ayuno', False),
               item('Estudio B', IDS[1], states[1], None, True),
               item('Estudio C', IDS[2], states[2]) ] if states else []
    versions = [{'id': 301, 'document_uuid': V1, 'version': 1, 'title': 'Orden V1',
                 'created_at': '2026-09-29 10:00:00', 'has_private_binary': 0,
                 'order_payload_version': 2,
                 'order_items': [item('Estudio histórico', IDS[3], 'RESULT_AVAILABLE')]},
                {'id': 302, 'document_uuid': V2, 'version': 2, 'title': 'Orden V2',
                 'created_at': '2026-09-30 10:00:00', 'has_private_binary': 0,
                 'order_payload_version': 2, 'order_items': studies}]
    order = {**versions[1], 'document_type': 'lab_order', 'status': 'generated',
             'generated_at': None, 'chronology_at': '2026-09-30 10:00:00',
             'source_scope': 'PATIENT', 'coverage_state': coverage, 'has_successor': 0,
             'requested_studies': ['Estudio legado'] if case == 'legacy' else [],
             'versions': versions}
    if case == 'legacy':
        order['order_payload_version'] = 1
        order['order_items'] = []
    return {'ok': True, 'data': {'items': [{'kind': 'ORDER', 'order': order,
                                          'result_count': len(results), 'results': results}],
                                 'has_more': False, 'cursor_next': None,
                                 'order_types': ['lab_order'], 'result_types': ['lab_result']}}


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for case in CASES:
        sizes = [(1440, 900), (1366, 768), (390, 844)] if case == 'partial' else [(1440, 900)]
        for width, height in sizes:
            state = {'case': case, 'posts': 0}
            page = browser.new_page(viewport={'width': width, 'height': height})
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))

            def intercept(route):
                url = route.request.url
                if 'orders_results_mode=1' in url:
                    route.fulfill(json=projection(state['case']))
                elif route.request.method == 'POST' and '/patients/review-patient/documents' in url:
                    state['posts'] += 1
                    state['case'] = 'partial'
                    route.fulfill(json={'ok': True, 'data': {'document_id': 999}})
                elif url.endswith('/documents/' + V2):
                    route.fulfill(json={'ok': True, 'data': {'document': {
                        'document_id': V2, 'document_db_id': 302, 'document_type': 'lab_order',
                        'title': 'Orden V2', 'status': 'generated',
                        'context': {'patient_id': 'review-patient', 'encounter_id': None},
                        'content': {'payload': {'order_payload_version': 2,
                                                'order_items': projection(state['case'])['data']['items'][0]['order']['order_items']}}}}})
                elif url.endswith('/documents/' + V1):
                    route.fulfill(json={'ok': True, 'data': {'document': {
                        'document_id': V1, 'document_db_id': 301, 'title': 'Orden V1',
                        'content': {'payload': {'order_payload_version': 2,
                                                'order_items': [item('Estudio histórico', IDS[3], 'RESULT_AVAILABLE')]}}}}})
                elif '/documents/' in url:
                    route.fulfill(json={'ok': True, 'data': {'document': {'content': {'payload': {}}}}})
                else:
                    route.continue_()

            page.route('**/api/clinical/index.php/doctors/1/**', intercept)
            page.goto(BASE + '?qa_tools=hide', wait_until='domcontentloaded')
            page.wait_for_timeout(850)
            page.locator('[data-bs-target="#t-estudios"]').first.click()
            pane = page.locator('#t-estudios')
            if case == 'complete':
                pane.get_by_role('button', name='Ver resultados e historial', exact=False).click()
                pane.locator('.vis06-segments [data-filter="complete"]').click()
            else:
                pane.get_by_role('button', name='Revisar órdenes pendientes', exact=False).click()
            pane.locator('.vis06-index-card').first.wait_for()
            pane.locator('.vis06-index-card').first.click()
            detail = pane.locator('.vis06-detail')
            rows = detail.locator('.vis06-study-row')
            if case == 'legacy':
                assert rows.count() == 0 and 'Estudio legado' in detail.inner_text()
            else:
                assert rows.count() == 3
                assert 'Laboratorio' in rows.first.inner_text()
                assert 'En ayuno' in rows.first.inner_text()
                assert 'Personalizado' in rows.nth(1).inner_text()
                assert 'Sin nota' not in detail.inner_text()
                assert len(detail.locator('.vis06-result-entry').all()) == len(CASES[case][0])
                if case in ('none', 'predecessor'):
                    assert all('Sin resultado' in row.inner_text() and row.locator('button').count() == 0 for row in rows.all())
                if case == 'partial':
                    assert rows.nth(0).get_by_role('button', name='Ver resultado', exact=False).count() == 1
                    assert rows.nth(1).locator('button').count() == 0
                    assert rows.nth(2).get_by_role('button', name='Ver resultado', exact=False).count() == 1
                    rows.nth(0).get_by_role('button', name='Ver resultado', exact=False).click()
                    assert 'Resultado A' in detail.inner_text()
                    detail.get_by_role('button', name='Volver a la orden').click()
                    assert rows.count() == 3 and 'Estudio A' in rows.first.inner_text()
                if case == 'multi':
                    assert rows.nth(0).locator('button').count() == 1
                    assert rows.nth(1).locator('button').count() == 1
                    rows.nth(1).get_by_role('button', name='Ver resultado', exact=False).click()
                    assert 'Resultado A y B' in detail.inner_text()
                if case == 'multiple':
                    action = rows.first.get_by_role('button', name='Ver 2 resultados de Estudio A')
                    assert action.get_attribute('aria-expanded') == 'false'
                    action.focus()
                    page.keyboard.press('Enter')
                    assert action.get_attribute('aria-expanded') == 'true'
                    assert rows.first.locator('.vis06-study-result-link').count() == 2
                    rows.first.get_by_role('button', name='Ver resultado Segundo resultado A de Estudio A').click()
                    assert 'Segundo resultado A' in detail.inner_text()
                if case == 'whole':
                    assert all(row.locator('button').count() == 0 for row in rows.all())
                    assert 'Cobertura no especificada' in rows.first.inner_text()
                    assert 'Resultado general' in detail.inner_text()
                if case == 'complete':
                    assert 'Todos los estudios tienen resultado' in detail.inner_text()
                    assert all('Resultado disponible' in row.inner_text() and row.locator('button').count() == 1 for row in rows.all())
                if case == 'predecessor':
                    detail.locator('.vis06-result-entry').first.click()
                    assert 'RESULTADO DE UNA VERSIÓN ANTERIOR' in detail.inner_text()
                    assert 'Estudio histórico' in detail.inner_text()
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
            assert not errors, errors
            if case == 'partial' and os.getenv('OR04_SCREENSHOTS') == '1':
                page.screenshot(path=f'/tmp/or04-partial-{width}x{height}.png')
            print(f'QA_OR04_{case}_{width}x{height}=PASS')
            page.close()
    state = {'case': 'none', 'posts': 0}
    page = browser.new_page(viewport={'width': 1440, 'height': 900})

    def registration(route):
        url = route.request.url
        if 'orders_results_mode=1' in url:
            route.fulfill(json=projection(state['case']))
        elif route.request.method == 'POST' and '/patients/review-patient/documents' in url:
            assert route.request.headers.get('idempotency-key')
            state['posts'] += 1
            state['case'] = 'partial'
            route.fulfill(json={'ok': True, 'data': {'document_id': 999}})
        elif url.endswith('/documents/' + V2):
            route.fulfill(json={'ok': True, 'data': {'document': {
                'document_id': V2, 'document_db_id': 302, 'document_type': 'lab_order',
                'title': 'Orden V2', 'status': 'generated',
                'context': {'patient_id': 'review-patient', 'encounter_id': None},
                'content': {'payload': {'order_payload_version': 2,
                                        'order_items': projection(state['case'])['data']['items'][0]['order']['order_items']}}}}})
        elif '/documents/' in url:
            route.fulfill(json={'ok': True, 'data': {'document': {'content': {'payload': {}}}}})
        else:
            route.continue_()

    page.route('**/api/clinical/index.php/doctors/1/**', registration)
    page.goto(BASE + '?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_timeout(850)
    page.locator('[data-bs-target="#t-estudios"]').first.click()
    pane = page.locator('#t-estudios')
    pane.get_by_role('button', name='Revisar órdenes pendientes', exact=False).click()
    pane.locator('.vis06-index-card').first.wait_for()
    pane.locator('.vis06-index-card').first.click()
    pane.locator('.vis06-detail').get_by_role('button', name='REGISTRAR RESULTADO').click()
    dialog = page.locator('#res02a-linked-result')
    dialog.locator('input[data-item]').first.check()
    dialog.locator('[data-provenance]').fill('Laboratorio de prueba')
    dialog.locator('[data-file]').set_input_files({'name': 'resultado.pdf', 'mimeType': 'application/pdf',
                                                  'buffer': b'%PDF-1.4\n% QA\n'})
    dialog.locator('[data-save]').click()
    page.wait_for_function("document.querySelector('#t-estudios .vis06-study-row')?.textContent.includes('Resultado disponible')")
    assert state['posts'] == 1
    assert pane.locator('.vis06-detail .vis06-study-row').first.get_by_role('button', name='Ver resultado', exact=False).count() == 1
    print('QA_OR04_REGISTER_RESULT_REFRESH=PASS')
    page.close()
    browser.close()
