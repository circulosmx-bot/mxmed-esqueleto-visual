"""DOC-CLOSE02B: Alta canonical lifecycle in an isolated clinical database."""
import json
import os
import subprocess
import uuid
import base64
import struct
import zlib
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
PATIENT = 'p_plan02ux_review'
ROUTE = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents'
VIEW = BASE + '/modules/clinical/ui/viewer.php?doctor_id=1&uuid='
SHOT = Path('/tmp/mxmed-doc-close02b-screenshots')
SHOT.mkdir(exist_ok=True)


def sql(statement):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', statement], text=True).strip()


def check(label, valid):
    if not valid:
        raise AssertionError(label)
    print(label + '=PASS', flush=True)


def q(text):
    return "'" + text.replace("'", "''") + "'"


def count():
    return int(sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='alta_medica'"))


def signature_png():
    def chunk(kind, data):
        return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data) & 0xffffffff)
    width, height = 64, 24
    pixels = b''.join(b'\0' + b''.join(
        (b'\x10\x20\x30\xff' if 8 < x < 55 and abs(y - (7 + x // 8)) <= 1 else b'\xff\xff\xff\xff')
        for x in range(width)) for y in range(height))
    png = (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 6, 0, 0, 0))
           + chunk(b'IDAT', zlib.compress(pixels)) + chunk(b'IEND', b''))
    return 'data:image/png;base64,' + base64.b64encode(png).decode()


def body(marker='QA ALTA'):
    content = {'tipo_alta': 'mejoria', 'motivo_egreso': marker,
               'resumen_evolucion': 'Resumen sintético', 'diagnostico_final': 'Diagnóstico sintético',
               'estado_paciente': 'Estable', 'datos_relevantes': 'Dato sintético',
               'tratamiento': 'Tratamiento sintético', 'cuidados_generales': 'Cuidados sintéticos',
               'signos_alarma': 'Signos sintéticos', 'cita_control': '12 oct 2026 · 10:00',
               'followup_note': 'Control sintético', 'recomendaciones': 'Recomendaciones sintéticas'}
    return {'document_type': 'alta_medica', 'type': 'alta_medica', 'title': 'Alta médica QA',
            'summary': marker, 'event_datetime': '2026-10-10 10:00:00',
            'actor': {'user_id': 'review-user'},
            'context': {'patient_id': PATIENT, 'care_setting': 'consulta'},
            'payload': {'contract_version': 2, 'status': 'draft', 'workflow_step': 3,
                        'alta': {'type': 'mejoria'}, 'report': {'emission_date': '2026-10-10'},
                        'actor_snapshot': {'user_id': 'review-user', 'full_name': 'Dra. QA'},
                        'patient_snapshot': {'full_name': 'Paciente QA', 'age': '36', 'sex': 'Femenino'},
                        'content': content, 'form_snapshot': {'fecha_alta': '2026-10-10', **content},
                        'signatures': {'doctor': None}}}


def save(context, request):
    return context.post(ROUTE, data=request,
                        headers={'Idempotency-Key': 'alta:' + str(uuid.uuid4())})


with sync_playwright() as playwright:
    context = playwright.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    request = body()
    before = count()
    preview_url = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/alta-preview'
    preview = context.post(preview_url, data=request)
    check('B09', preview.status == 200 and count() == before)
    preview_html = preview.json()['data']['html']
    check('B10', all(term in preview_html for term in ['QA ALTA', 'Paciente QA', 'Dra. QA']))
    check('B15', 'final_text' not in request['payload'] and 'rendered_text' not in request['payload'])

    initial_key = 'alta:' + str(uuid.uuid4())
    first = context.post(ROUTE, data=request, headers={'Idempotency-Key': initial_key})
    check('B01', first.status == 201 and first.json()['data']['document']['status'] == 'draft')
    doc_uuid = first.json()['data']['document_id']
    version = int(first.json()['data']['document']['version'])
    check('B02', bool(doc_uuid) and version >= 1 and count() == before + 1)
    replay = context.post(ROUTE, data=request, headers={'Idempotency-Key': initial_key})
    check('B02_IDEMPOTENT_REPLAY', replay.status == 200
          and replay.json()['data']['document_id'] == doc_uuid and count() == before + 1)
    check('B07', sql(f"SELECT status FROM clinical_documents WHERE document_uuid={q(doc_uuid)}") == 'draft')
    request['draft_ref'] = doc_uuid
    request['expected_version'] = version
    request['payload']['workflow_step'] = 4
    second = save(context, request)
    check('B03', second.status == 201 and second.json()['data']['document_id'] == doc_uuid and count() == before + 1)
    next_version = int(second.json()['data']['document']['version'])
    check('B04', next_version == version + 1)
    stale = save(context, request)
    check('B05', stale.status == 409 and count() == before + 1)
    detail = context.get(BASE + f'/api/clinical/index.php/doctors/1/documents/{doc_uuid}')
    restored = detail.json()['data']['document']
    check('B06', detail.status == 200 and restored['version'] == next_version
          and restored['content']['payload']['workflow_step'] == 4
          and restored['content']['payload']['content']['motivo_egreso'] == 'QA ALTA')
    check('B08', restored['status'] == 'draft' and restored['document_id'] == doc_uuid)
    check('B11', preview_html == restored['content']['payload']['alta_snapshot']['html'])
    check('B12', preview_html == json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE document_uuid={q(doc_uuid)}"))['alta_snapshot']['html'])

    request['expected_version'] = next_version
    request['payload']['workflow_step'] = 8
    request['payload']['status'] = 'issued'
    unsigned = save(context, request)
    check('B17_UNSIGNED_NOT_FINAL', unsigned.status == 400
          and sql(f"SELECT status FROM clinical_documents WHERE document_uuid={q(doc_uuid)}") == 'draft')
    request['payload']['signatures']['doctor'] = {'source': 'local_canvas', 'role': 'doctor',
        'signer_name': 'Dra. QA', 'image_data': 'data:image/png;base64,AAAA'}
    malformed = save(context, request)
    check('B17_MALFORMED_IMAGE_NOT_FINAL', malformed.status == 400
          and sql(f"SELECT status FROM clinical_documents WHERE document_uuid={q(doc_uuid)}") == 'draft')
    request['payload']['signatures']['doctor'] = {'source': 'local_canvas', 'role': 'doctor',
        'signer_name': 'Dra. QA', 'image_data': signature_png()}
    final_preview = context.post(preview_url, data=request)
    check('B11_FINAL', final_preview.status == 200 and count() == before + 1)
    final_html = final_preview.json()['data']['html']
    issued = save(context, request)
    check('B17', issued.status == 201 and issued.json()['data']['document_id'] == doc_uuid)
    check('B18', count() == before + 1 and issued.json()['data']['document']['status'] == 'generated')
    issued_doc = issued.json()['data']['document']
    check('B12_FINAL', issued_doc['content']['payload']['alta_snapshot']['html'] == final_html)
    viewer = context.get(VIEW + doc_uuid)
    check('B13', viewer.status == 200 and final_html in viewer.text())
    printable = context.get(VIEW + doc_uuid + '&autoprint=1')
    check('B14', printable.status == 200 and final_html in printable.text() and 'window.print' in printable.text())
    check('B19', issued_doc['document_id'] == doc_uuid)
    check('B20', issued.status == 201 and sql('SELECT status FROM clinical_encounters WHERE encounter_id=1016') == 'open')
    check('B15_PERSISTED', 'final_text' not in issued_doc['content']['payload'])

    legacy_id = str(uuid.uuid4())
    legacy = {'contract_version': 1, 'form_snapshot': {'final_text': 'ALTA HISTÓRICA TEXTO FINAL'},
              'rendered_text': 'ALTA HISTÓRICA TEXTO FINAL',
              'content': {'motivo_egreso': 'ALTA HISTÓRICA MOTIVO'},
              'patient_snapshot': {'full_name': 'Paciente histórico'}}
    sql("INSERT INTO clinical_documents (document_uuid,document_type,title,version,status,patient_id,"
        "care_setting,payload_json,rendered_text,event_datetime,created_at,created_by_user_id) VALUES "
        f"({q(legacy_id)},'alta_medica','Alta histórica',1,'generated',{q(PATIENT)},'consulta',"
        f"{q(json.dumps(legacy, ensure_ascii=False))},'ALTA HISTÓRICA TEXTO FINAL',"
        "'2026-10-10 10:00:00',UTC_TIMESTAMP(),'review-user')")
    historic = context.get(VIEW + legacy_id)
    check('B16', historic.status == 200 and 'ALTA HISTÓRICA TEXTO FINAL' in historic.text())
    check('B21', 'ALTA HISTÓRICA MOTIVO' in historic.text())
    historic_print = context.get(VIEW + legacy_id + '&autoprint=1')
    check('B22', historic_print.status == 200 and 'ALTA HISTÓRICA TEXTO FINAL' in historic_print.text()
          and 'window.print' in historic_print.text())
    legacy_rendered_id = str(uuid.uuid4())
    sql("INSERT INTO clinical_documents (document_uuid,document_type,title,version,status,patient_id,"
        "care_setting,payload_json,rendered_text,event_datetime,created_at,created_by_user_id) VALUES "
        f"({q(legacy_rendered_id)},'alta_medica','Alta histórica texto DB',1,'generated',{q(PATIENT)},"
        "'consulta','{}','ALTA HISTÓRICA RENDERED TEXT','2026-10-10 10:00:00',UTC_TIMESTAMP(),'review-user')")
    check('B16_RENDERED_TEXT_ONLY', 'ALTA HISTÓRICA RENDERED TEXT' in context.get(VIEW + legacy_rendered_id).text())
    unsupported = context.post(ROUTE, data={'document_type': 'alta_medica', 'type': 'alta_medica',
        'payload': {'contract_version': 1}})
    generic = context.post(BASE + '/api/clinical/index.php/documents', data={'document_type': 'alta_medica',
        'context': {'patient_id': PATIENT}, 'payload': {'contract_version': 2}})
    encounter = context.post(BASE + '/api/clinical/index.php/encounters/1016/documents',
        data={'document_type': 'alta_medica', 'payload': {'contract_version': 2}},
        headers={'Idempotency-Key': 'alta:' + str(uuid.uuid4())})
    sql("INSERT INTO patients_patients(patient_id,birthdate) VALUES ('p_alta_legacyqa','1990-01-01')")
    unauthorized_preview = context.post(BASE + '/api/clinical/index.php/doctors/1/patients/p_alta_legacyqa/alta-preview',
        data=request)
    unauthorized_write = context.post(BASE + '/api/clinical/index.php/doctors/1/patients/p_alta_legacyqa/documents',
        data=request, headers={'Idempotency-Key': 'alta:' + str(uuid.uuid4())})
    anonymous = playwright.request.new_context()
    no_session = anonymous.post(preview_url, data=request)
    anonymous.dispose()
    check('ALTA_PATIENT_AND_SESSION_SCOPE', unauthorized_preview.status == 403
          and unauthorized_write.status == 403 and no_session.status in (401, 403))
    legacy_save = context.post(BASE + '/api/clinical-documents.php?action=save',
        data={'type': 'alta_medica', 'context': {'patient_id': 'p_alta_legacyqa'},
              'payload': {'contract_version': 1}})
    check('B20_M6_BOUNDARY', unsupported.status == 422 and generic.status == 422
          and encounter.status == 422 and legacy_save.status == 422
          and legacy_save.json().get('error') == 'ALTA_MEDICA_GENERIC_WRITE_FORBIDDEN')
    context.dispose()

    browser = playwright.chromium.launch(headless=True)
    for width, height in [(1366, 768), (1440, 900)]:
        page = browser.new_page(viewport={'width': width, 'height': height},
            extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
        page.wait_for_function('typeof window.setActivePatientId === "function"')
        page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
        page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
        page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
        page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
        page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
        page.locator('[data-action="documents-open-alta"]').click()
        expect(page.locator('#am_step_1')).to_be_visible()
        page.locator('#am_motivo_egreso').fill('ALTA VISUAL QA')
        page.locator('#am_save').click()
        expect(page.locator('#modalAltaMedica')).not_to_be_visible()
        page.locator('#t-consent .docvis-back').click()
        page.locator('#t-consent .docvis-back').click()
        page.locator('#t-consent .docvis-intents').first.locator('button').nth(1).click()
        draft_card = page.locator('#t-consent .vis06-row').filter(has_text='Alta médica — Mejoría').filter(has_text='Borrador').first
        expect(draft_card).to_be_visible()
        check(f'BROWSER_DRAFT_LIST_{width}', 'Borrador' in draft_card.inner_text())
        current_uuid = page.locator('#t-consent [data-alta-draft="1"]').filter(has_text='ALTA VISUAL QA').first.get_attribute('data-doc-uuid')
        resume_button = draft_card.get_by_role('button', name='Continuar borrador')
        resume_button.focus()
        page.keyboard.press('Enter')
        expect(page.locator('#am_step_1')).to_be_visible()
        check(f'BROWSER_RESUME_{width}', page.locator('#am_motivo_egreso').input_value() == 'ALTA VISUAL QA')
        for step in range(2, 7):
            page.locator('#am_next').click()
            expect(page.locator(f'#am_step_{step}')).to_be_visible()
            if step == 2:
                page.locator('#am_resumen_evolucion').fill('Resumen sintético visual')
                page.locator('#am_diagnostico_final').fill('Diagnóstico sintético visual')
            elif step == 3:
                page.locator('#am_estado_paciente').fill('Estable sintético')
            elif step == 4:
                page.locator('#am_tratamiento').fill('Tratamiento sintético visual')
        expect(page.locator('#am_preview')).to_contain_text('ALTA VISUAL QA')
        unsaved_count = count()
        page.screenshot(path=str(SHOT / f'preview-{width}x{height}.png'))
        check(f'BROWSER_PREVIEW_{width}', page.locator('#am_preview').evaluate('el=>el.scrollWidth<=el.clientWidth')
              and page.locator('#am_preview').get_attribute('aria-label') == 'Vista previa de Alta médica'
              and count() == unsaved_count and not errors)
        page.locator('#am_next').click()
        expect(page.locator('#am_step_7')).to_be_visible()
        canvas = page.locator('#am_signature_canvas')
        canvas.scroll_into_view_if_needed()
        box = canvas.bounding_box()
        page.mouse.move(box['x'] + 35, box['y'] + 45)
        page.mouse.down()
        page.mouse.move(box['x'] + 170, box['y'] + 80, steps=12)
        page.mouse.up()
        page.locator('#am_next').click()
        expect(page.locator('#am_step_8')).to_be_visible()
        expect(page.locator('#am_final_preview')).to_contain_text('ALTA VISUAL QA')
        check(f'BROWSER_FINAL_REVIEW_{width}', page.locator('#am_final_preview').evaluate('el=>el.scrollWidth<=el.clientWidth')
              and page.locator('#am_final_text').count() == 0
              and page.locator('#am_final_preview [contenteditable="true"]').count() == 0)
        page.screenshot(path=str(SHOT / f'final-review-{width}x{height}.png'))
        page.locator('#am_emit').click()
        page.wait_for_function("document.querySelector('#modalAltaMedica')?.classList.contains('show')===false")
        check(f'BROWSER_EMITTED_SAME_UUID_{width}',
              sql(f"SELECT status FROM clinical_documents WHERE document_uuid={q(current_uuid)}") == 'generated'
              and int(sql(f"SELECT COUNT(*) FROM clinical_documents WHERE document_uuid={q(current_uuid)}")) == 1)
        check(f'BROWSER_NO_PAGE_ERROR_{width}', not errors)
        rendered = browser.new_page(viewport={'width': width, 'height': height},
            extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
        rendered.goto(VIEW + current_uuid, wait_until='domcontentloaded')
        expect(rendered.locator('.alta-doc-sheet')).to_be_visible()
        rendered.locator('.alta-doc-sheet').scroll_into_view_if_needed()
        rendered.screenshot(path=str(SHOT / f'viewer-{width}x{height}.png'))
        check(f'BROWSER_VIEWER_{width}', rendered.locator('.alta-doc-sheet').evaluate('el=>el.scrollWidth<=el.clientWidth'))
        rendered.goto(VIEW + current_uuid + '&autoprint=1', wait_until='domcontentloaded')
        expect(rendered.locator('.alta-doc-sheet')).to_be_visible()
        check(f'BROWSER_PRINT_{width}', 'window.print' in rendered.content()
              and rendered.locator('.alta-doc-sheet').count() == 1)
        rendered.close()
        page.close()
    browser.close()
