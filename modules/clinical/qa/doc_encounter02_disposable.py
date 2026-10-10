"""DOC-ENCOUNTER02: exact-source Nota regression on consultation_flow_r1_disposable_gate.sh."""
import json
import os
import socket
import subprocess
import time
import uuid
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
SHOTS = Path(os.environ.get('DOC_ENCOUNTER02_SHOTS', '/tmp/doc-encounter02-shots'))
SHOTS.mkdir(parents=True, exist_ok=True)
PATIENT = 'p_plan02ux_review'


def sql(statement):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', statement], text=True).strip()


def check(label, ok):
    if not ok:
        raise AssertionError(label)
    print(f'{label}=PASS', flush=True)


sql("""
INSERT INTO patients_patients (patient_id,birthdate) VALUES ('p_note_other','1992-01-01');
INSERT INTO clinical_encounters (encounter_id,doctor_id,patient_id,encounter_dt,status,opened_by_user_id)
VALUES (1019,'2','p_plan02ux_review','2026-09-28 09:00:00','open','other-user'),
       (1020,'1','p_note_other','2026-09-27 09:00:00','open','review-user');
UPDATE clinical_encounters SET status='closed',closed_at=UTC_TIMESTAMP(),closed_by_user_id='review-user'
 WHERE encounter_id=1016;
INSERT INTO clinical_encounters (encounter_id,doctor_id,patient_id,encounter_dt,status,opened_by_user_id)
 VALUES (1017,'1','p_plan02ux_review','2026-09-30 09:00:00','open','review-user');
UPDATE clinical_encounters SET status='closed',closed_at=UTC_TIMESTAMP(),closed_by_user_id='review-user'
 WHERE encounter_id=1017;
INSERT INTO clinical_encounters (encounter_id,doctor_id,patient_id,encounter_dt,status,opened_by_user_id)
 VALUES (1018,'1','p_plan02ux_review','2026-09-29 09:00:00','open','review-user');
UPDATE clinical_encounters SET status='voided',voided_at=UTC_TIMESTAMP(),voided_by_user_id='review-user',void_reason='QA'
 WHERE encounter_id=1018;
INSERT INTO clinical_encounters (encounter_id,doctor_id,patient_id,encounter_dt,status,opened_by_user_id)
 VALUES (1021,'1','p_plan02ux_review','2026-10-10 10:00:00','open','review-user');
INSERT INTO clinical_encounter_sections
 (encounter_id,section_type,payload_schema_version,payload_json,narrative_text,created_by_user_id,updated_by_user_id)
VALUES
 (1016,'reason_evolution',1,'{}','Dolor abdominal sintético de 24 horas.','review-user','review-user'),
 (1016,'assessment',1,'{}','Impresión sintética de QA.','review-user','review-user'),
 (1016,'plan',1,'{}','Plan sintético de QA.','review-user','review-user'),
 (1016,'physical_exam',1,'{"systems":{"abdomen":{"state":"ABNORMAL","finding":"Sensibilidad sintética"},"skin":{"state":"NORMAL"},"general":{"state":"NOT_REVIEWED"}}}',NULL,'review-user','review-user');
INSERT INTO clinical_observations
 (encounter_id,code,value_numeric,unit,effective_at,recorded_at,recorded_by_user_id,source,provenance_json)
VALUES (1016,'heart_rate',82,'lpm','2026-10-01 09:00:00','2026-10-01 09:00:00','review-user','direct_measurement','{}'),
       (1017,'heart_rate',91,'lpm','2026-09-30 09:00:00','2026-09-30 09:00:00','review-user','direct_measurement','{}');
INSERT INTO clinical_observations
 (encounter_id,code,value_numeric,unit,effective_at,recorded_at,recorded_by_user_id,source,provenance_json,
  invalidated_at,invalidated_by_user_id,invalidation_reason)
VALUES (1016,'temperature',39,'C','2026-10-01 09:00:00','2026-10-01 09:00:00','review-user','direct_measurement','{}',
        '2026-10-01 10:00:00','review-user','Valor inválido sintético');
INSERT INTO clinical_documents
 (document_uuid,document_type,title,version,status,patient_id,encounter_id,encounter_ref_id,
  care_setting,payload_json,event_datetime,created_at,created_by_user_id)
VALUES
 ('00000000-0000-4000-8000-000000000601','lab_order','Biometría QA vinculada',1,'generated',
  'p_plan02ux_review','1016',1016,'consulta','{}','2026-10-01 09:00:00',UTC_TIMESTAMP(),'review-user'),
 ('00000000-0000-4000-8000-000000000602','lab_order','Orden paciente sin vínculo',1,'generated',
  'p_plan02ux_review',NULL,NULL,'consulta','{}','2026-10-01 09:00:00',UTC_TIMESTAMP(),'review-user'),
 ('00000000-0000-4000-8000-000000000603','lab_order','Borrador vinculado no emitido',1,'draft',
  'p_plan02ux_review','1016',1016,'consulta','{}','2026-10-01 09:00:00',UTC_TIMESTAMP(),'review-user');
""")

with sync_playwright() as pw:
    browser = pw.webkit.launch(headless=True)
    context = browser.new_context(viewport={'width': 1366, 'height': 768},
                                  extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    api = context.request
    url = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/nota-encounters'
    rows = api.get(url)
    check('E01_E02_LIST_AUTHORIZED', rows.status == 200 and
          set(x['encounter_id'] for x in rows.json()['data']) == {1016, 1017, 1021})
    projection = api.get(url + '/1016')
    check('E07_EXACT_PROJECTION', projection.status == 200)
    data = projection.json()['data']
    kinds = {x['source_type'] for x in data['candidates']}
    check('E08_E11_E12_SOURCES', {'reason_evolution','vital_observation','physical_exam','assessment','plan','diagnostic_order'} <= kinds
          and any(x['source_type'] == 'reason_evolution' and x['text'] == 'Dolor abdominal sintético de 24 horas.' for x in data['candidates'])
          and any(x['source_type'] == 'assessment' and x['text'] == 'Impresión sintética de QA.' for x in data['candidates'])
          and any(x['source_type'] == 'plan' and x['text'] == 'Plan sintético de QA.' for x in data['candidates']))
    check('E09_INVALIDATED_EXCLUDED', not any('temperature' in x['text'] or '91.' in x['text'] for x in data['candidates']))
    check('E10_STORED_EXAM_ONLY', any('Sensibilidad sintética' in x['text'] and 'No revisado' not in x['text'] for x in data['candidates']))
    check('E13_EXACT_ORDER_ONLY', any('Biometría QA vinculada' in x['text'] for x in data['candidates'])
          and not any('Orden paciente sin vínculo' in x['text'] or 'Borrador vinculado' in x['text']
                      for x in data['candidates']))
    check('E14_NO_FOLLOWUP', 'follow_up' not in kinds)
    check('E38_CLOSED', api.get(url + '/1016').status == 200)
    check('OPEN_SELECTABLE', api.get(url + '/1021').json()['data']['encounter']['status'] == 'open')
    check('E35_E36_E37_E40_AUTH', api.get(url + '/1018').status == 409
          and api.get(url + '/1019').status == 404 and api.get(url + '/1020').status == 404
          and api.get(url + '/999999').status == 404
          and pw.request.new_context().get(url + '/1016').status == 401)
    # Exercise the actual route with the M6 master gate disabled against the same disposable DB.
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', 0))
        gate_port = probe.getsockname()[1]
    gate_env = dict(os.environ, MXMED_DB_HOST='localhost', MXMED_DB_NAME=DB,
                    MXMED_DB_USER='root', MXMED_DB_PASS='',
                    MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1='0',
                    MXMED_CLINICAL_M6_COHORT_MODE='allowlist',
                    MXMED_CLINICAL_M6_COHORT_PAIRS='1|p_plan02ux_review')
    with open(os.devnull, 'wb') as gate_log:
        gate_server = subprocess.Popen(['php', '-d', f"session.save_path={os.environ['FLOW_R1_QA_ROOT']}/sessions",
                                        '-S', f'127.0.0.1:{gate_port}', '-t', str(Path(__file__).resolve().parents[3])],
                                       env=gate_env, stdout=gate_log, stderr=gate_log)
        try:
            gate_url = f'http://127.0.0.1:{gate_port}/api/clinical/index.php/doctors/1/patients/{PATIENT}/nota-encounters/1016'
            gate_response = None
            for _ in range(30):
                try:
                    gate_response = api.get(gate_url, timeout=1000)
                    break
                except Exception:
                    time.sleep(0.1)
            check('E39_M6_CANONICAL_READ_GATE', gate_response is not None and
                  gate_response.status == 409 and gate_response.json()['error'] == 'NOTA_M6_CANONICAL_REQUIRED')
        finally:
            gate_server.terminate()
            gate_server.wait(timeout=5)

    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-nota"]').click()
    expect(page.locator('#modalNotaMedica')).to_be_visible()
    check('E04_UNLINKED_ENTRY', 'Sin consulta vinculada' in page.locator('#nm_source_summary').inner_text())
    page.screenshot(path=str(SHOTS / '1366x768-unlinked.png'))
    page.locator('#nm_source_choose').click()
    expect(page.locator('#nm_source_options input')).to_have_count(3)
    check('E02_NO_AUTO_SELECT', page.locator('#nm_source_options input:checked').count() == 0)
    page.locator('#nm_source_options input[value="1016"]').check()
    page.locator('#nm_source_confirm').click()
    expect(page.locator('#nm_source_summary')).to_contain_text('Cerrada')
    check('E15_LINK_DOES_NOT_IMPORT', page.locator('#nm_motivo_consulta').input_value() == '')
    page.screenshot(path=str(SHOTS / '1366x768-linked-closed.png'))
    page.locator('#nm_source_import').click()
    expect(page.locator('#nm_source_candidates input')).to_have_count(len(data['candidates']))
    page.screenshot(path=str(SHOTS / '1366x768-import-review.png'))
    reason_index = next(i for i, item in enumerate(data['candidates']) if item['source_type'] == 'reason_evolution')
    page.locator(f'#nm_source_candidates input[data-nota-candidate="{reason_index}"]').check()
    page.locator('#nm_source_apply').click()
    check('E15_E16_SELECTED_ONLY', 'Dolor abdominal sintético' in page.locator('#nm_motivo_consulta').input_value()
          and page.locator('#nm_signos_vitales').input_value() == ''
          and page.locator('#nm_estudios_sugeridos').input_value() == '')
    page.locator('#nm_source_import').click()
    expect(page.locator('#nm_source_import_panel')).to_be_visible()
    for index, candidate in enumerate(data['candidates']):
        if candidate['source_type'] in ('vital_observation','physical_exam','assessment','plan','diagnostic_order'):
            page.locator(f'#nm_source_candidates input[data-nota-candidate="{index}"]').check()
    page.locator('#nm_source_apply').click()
    check('E16_E20_MULTI_IMPORT', 'Dolor abdominal sintético' in page.locator('#nm_motivo_consulta').input_value()
          and 'Sensibilidad sintética' in page.locator('#nm_exploracion_fisica').input_value()
          and 'heart_rate' in page.locator('#nm_signos_vitales').input_value()
          and 'Biometría QA' in page.locator('#nm_estudios_sugeridos').input_value()
          and page.locator('#nm_seguimiento').input_value() == '')
    page.locator('#nm_motivo_consulta').fill('Edición médica conservada')
    with page.expect_response(lambda response: response.url.endswith('/nota-encounters/1016')):
        page.locator('#nm_source_import').click()
    expect(page.locator('#nm_source_import_panel')).to_be_visible()
    reason_index = next(i for i, item in enumerate(data['candidates']) if item['source_type'] == 'reason_evolution')
    page.locator(f'#nm_source_candidates input[data-nota-candidate="{reason_index}"]').check()
    page.locator('#nm_source_apply').click()
    expect(page.locator('#nm_source_conflict')).to_be_visible()
    check('E17_E20_NO_PARTIAL_CHANGE', page.locator('#nm_motivo_consulta').input_value() == 'Edición médica conservada')
    page.screenshot(path=str(SHOTS / '1366x768-replace-confirmation.png'))
    page.locator('#nm_source_replace_cancel').click()
    check('E18_CANCEL_PRESERVES', page.locator('#nm_motivo_consulta').input_value() == 'Edición médica conservada')
    page.locator('#nm_source_apply').click()
    page.locator('#nm_source_replace_confirm').click()
    check('E19_REPLACE', 'Dolor abdominal sintético' in page.locator('#nm_motivo_consulta').input_value())
    page.screenshot(path=str(SHOTS / '1366x768-populated-nota.png'))
    check('VIEWPORT_1366X768', page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'))
    page.locator('#nm_save').click()
    expect(page.locator('#modalNotaMedica')).to_be_hidden()
    stored = sql("SELECT CONCAT_WS('|',document_uuid,encounter_ref_id,JSON_EXTRACT(payload_json,'$.encounter_source.encounter_id'),JSON_LENGTH(JSON_EXTRACT(payload_json,'$.encounter_imports'))) FROM clinical_documents WHERE document_type='nota_medica' ORDER BY id DESC LIMIT 1")
    print('STORED=' + stored, flush=True)
    check('E05_E21_E22_PERSISTED', '|1016|1016|' in stored and int(stored.split('|')[-1]) >= 6)
    check('NO_JS_ERRORS', not errors)

    # The original snapshot survives a later edit to the source encounter.
    sql("UPDATE clinical_encounter_sections SET narrative_text='Fuente modificada después de importar',"
        " row_version=row_version+1,updated_at=UTC_TIMESTAMP()"
        " WHERE encounter_id=1016 AND section_type='reason_evolution'")
    draft = page.locator('[data-nota-draft="1"]').first
    expect(draft).to_be_attached()
    page.evaluate("document.querySelector('[data-nota-draft=\"1\"]').click()")
    expect(page.locator('#modalNotaMedica')).to_be_visible()
    check('E06_E24_E25_RESUME_SNAPSHOT', 'Dolor abdominal sintético' in page.locator('#nm_motivo_consulta').input_value()
          and 'Fuente modificada' not in page.locator('#nm_motivo_consulta').input_value()
          and 'enc:1016' in page.locator('#nm_source_summary').text_content())
    check('E23_PROVENANCE_NOT_PRINTED', 'section:1016' not in page.locator('#nm_preview_frame').inner_text())
    page.screenshot(path=str(SHOTS / '1366x768-resumed-draft.png'))
    for _ in range(5):
        page.locator('#nm_prev').click()
    expect(page.locator('#nm_step_1')).to_be_visible()
    page.locator('#nm_source_choose').click()
    page.locator('#nm_source_options input[value="1021"]').check()
    page.once('dialog', lambda dialog: dialog.accept())
    page.locator('#nm_source_confirm').click()
    expect(page.locator('#nm_source_summary')).to_contain_text('enc:1021')
    check('E26_CHANGE_SOURCE_PRESERVES_TEXT', 'Dolor abdominal sintético' in page.locator('#nm_motivo_consulta').input_value())
    page.locator('#nm_save').click()
    expect(page.locator('#modalNotaMedica')).to_be_hidden()
    check('E26_OLD_PROVENANCE_REMAINS', sql("SELECT CONCAT_WS('|',encounter_ref_id,"
          "JSON_EXTRACT(payload_json,'$.encounter_imports[0].encounter_id')) FROM clinical_documents "
          "WHERE document_type='nota_medica' ORDER BY id DESC LIMIT 1") == '1021|1016')
    page.evaluate("document.querySelector('[data-nota-draft=\"1\"]').click()")
    for _ in range(5):
        page.locator('#nm_prev').click()
    page.once('dialog', lambda dialog: dialog.accept())
    page.locator('#nm_source_unlink').click()
    check('E27_UNLINK_PRESERVES_TEXT', 'Dolor abdominal sintético' in page.locator('#nm_motivo_consulta').input_value())
    page.locator('#nm_save').click()
    expect(page.locator('#modalNotaMedica')).to_be_hidden()
    check('E27_UNLINK_PERSISTED_WITH_HISTORY', sql("SELECT CONCAT_WS('|',IFNULL(encounter_ref_id,'NULL'),"
          "JSON_EXTRACT(payload_json,'$.encounter_imports[0].encounter_id')) FROM clinical_documents "
          "WHERE document_type='nota_medica' ORDER BY id DESC LIMIT 1") == 'NULL|1016')
    qr_draft_uuid = sql("SELECT document_uuid FROM clinical_documents WHERE document_type='nota_medica' ORDER BY id DESC LIMIT 1")
    qr_version = int(sql(f"SELECT version FROM clinical_documents WHERE document_uuid='{qr_draft_uuid}'"))
    qr_create = api.post(BASE + '/api/clinical/index.php/nota-qr-sessions',
                         data={'document_uuid': qr_draft_uuid,
                               'document_version': qr_version, 'role': 'doctor'})
    check('E33_QR_SESSION_REGRESSION', qr_create.status == 201 and
          qr_create.json()['data']['status'] == 'pending')
    qr_token = qr_create.json()['data']['token']
    qr_mobile = api.get(BASE + f'/api/clinical/index.php/nota-qr-sessions/{qr_token}/mobile-context')
    check('E33_QR_EXACT_DRAFT_REVIEW', qr_mobile.status == 200 and
          'Dolor abdominal sintético' in qr_mobile.json()['data']['review_html'])
    api.post(BASE + f'/api/clinical/index.php/nota-qr-sessions/{qr_token}/cancel')

    page.evaluate("window.dispatchEvent(new CustomEvent('mxmed:open-nota-from-encounter',"
                  "{detail:{patient_id:'p_plan02ux_review',encounter_key:'enc:1021'}}))")
    expect(page.locator('#modalNotaMedica')).to_be_visible()
    expect(page.locator('#nm_source_options input[value="1021"]')).to_be_checked()
    check('E03_EXPLICIT_CONTEXT_REQUIRES_CONFIRMATION', page.locator('#nm_source_summary').text_content() == 'Sin consulta vinculada'
          and page.locator('#nm_motivo_consulta').input_value() == '')
    page.locator('#modalNotaMedica .btn-close').click()

    page.locator('[data-action="documents-open-nota"]').click()
    expect(page.locator('#modalNotaMedica')).to_be_visible()
    page.locator('#nm_source_choose').click()
    page.locator('#nm_source_options input[value="1016"]').check()
    page.locator('#nm_source_confirm').click()
    page.locator('#nm_motivo_consulta').fill('Motivo aprobado para emisión QA')
    page.locator('#nm_next').click()
    page.locator('#nm_padecimiento_actual').fill('Padecimiento inicial antes de importar')
    page.locator('#nm_next').click()
    page.locator('#nm_exploracion_fisica').fill('Exploración aprobada QA')
    page.locator('#nm_next').click()
    page.locator('#nm_impresion_diagnostica').fill('Impresión aprobada QA')
    page.locator('#nm_next').click()
    page.locator('#nm_tratamiento_indicaciones').fill('Plan aprobado QA')
    page.locator('#nm_next').click()
    expect(page.locator('#nm_preview_frame')).to_contain_text('Motivo aprobado para emisión QA')
    check('E30_PREVIEW_CANONICAL', 'encounter_source' not in page.locator('#nm_preview_frame').inner_text())
    page.locator('#nm_preview_continue').click()
    expect(page.locator('#nm_signature_canvas')).to_be_visible()
    canvas = page.locator('#nm_signature_canvas')
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 30, box['y'] + 35)
    page.mouse.down()
    page.mouse.move(box['x'] + 160, box['y'] + 85, steps=12)
    page.mouse.up()
    page.locator('#nm_next').click()
    expect(page.locator('#nm_step_7')).to_be_visible()
    check('E28_SIGNATURE_VALID_BEFORE_IMPORT', 'Firma médica aplicada' in page.locator('#nm_signature_status').inner_text())
    for _ in range(6):
        page.locator('#nm_prev').click()
    expect(page.locator('#nm_step_1')).to_be_visible()
    with page.expect_response(lambda response: response.url.endswith('/nota-encounters/1016')):
        page.locator('#nm_source_import').click()
    source_rows = api.get(url + '/1016').json()['data']['candidates']
    reason_index = next(i for i, item in enumerate(source_rows) if item['source_type'] == 'reason_evolution')
    page.locator(f'#nm_source_candidates input[data-nota-candidate="{reason_index}"]').check()
    page.locator(f'#nm_source_candidates select[data-nota-destination="{reason_index}"]').select_option('padecimiento_actual')
    page.locator('#nm_source_apply').click()
    expect(page.locator('#nm_source_conflict')).to_be_visible()
    page.locator('#nm_source_replace_confirm').click()
    check('E29_IMPORT_STALES_SIGNATURE', 'aplicada a esta versión' not in page.locator('#nm_signature_status').inner_text().lower()
          and 'Fuente modificada' in page.locator('#nm_padecimiento_actual').input_value())
    page.locator('#nm_save').click()
    expect(page.locator('#modalNotaMedica')).to_be_hidden()
    check('E29_STALE_SIGNATURE_NOT_PERSISTED', sql("SELECT JSON_EXTRACT(payload_json,'$.signatures.doctor') "
          "FROM clinical_documents WHERE document_type='nota_medica' ORDER BY id DESC LIMIT 1") == 'null')
    signed_draft_uuid = sql("SELECT document_uuid FROM clinical_documents WHERE document_type='nota_medica' ORDER BY id DESC LIMIT 1")
    page.evaluate("uuid=>document.querySelector(`[data-nota-draft=\"1\"][data-doc-uuid=\"${uuid}\"]`).click()", signed_draft_uuid)
    expect(page.locator('#modalNotaMedica')).to_be_visible()
    expect(page.locator('#nm_preview_frame')).to_contain_text('Fuente modificada después de importar')
    expect(page.locator('#nm_preview_continue')).to_be_visible()
    page.locator('#nm_preview_continue').click()
    expect(page.locator('#nm_signature_canvas')).to_be_visible()
    canvas = page.locator('#nm_signature_canvas')
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 35, box['y'] + 35)
    page.mouse.down()
    page.mouse.move(box['x'] + 180, box['y'] + 88, steps=12)
    page.mouse.up()
    page.locator('#nm_next').click()
    expect(page.locator('#nm_step_7')).to_be_visible()
    check('E31_FINAL_REVIEW_APPROVED_COPY', 'Fuente modificada después de importar' in page.locator('#nm_final_preview_frame').inner_text())
    page.locator('#nm_emit').click()
    page.wait_for_timeout(700)
    emitted = sql("SELECT CONCAT_WS('|',status,encounter_ref_id,"
                  "JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.content.padecimiento_actual'))) "
                  f"FROM clinical_documents WHERE document_uuid='{signed_draft_uuid}'")
    check('E31_EMITTED_EXACT_CONTENT', emitted.startswith('generated|1016|Fuente modificada después de importar'))
    doc_response = api.get(BASE + f'/api/clinical/index.php/doctors/1/documents/{signed_draft_uuid}')
    check('E32_EXACT_VIEWER_SOURCE', doc_response.status == 200)
    viewer = context.new_page()
    viewer_response = viewer.goto(BASE + f'/modules/clinical/ui/viewer.php?uuid={signed_draft_uuid}&doctor_id=1',
                                  wait_until='domcontentloaded')
    check('E32_VIEWER_HTTP', viewer_response.status == 200)
    expect(viewer.locator('.document-sheet')).to_be_visible()
    check('E32_VIEWER_EXACT_APPROVED_CONTENT', 'Fuente modificada después de importar' in
          viewer.locator('.document-sheet').inner_text() and
          'section:1016' not in viewer.locator('.document-sheet').inner_text())
    check('E32_PRINT_ACTION', viewer.locator('[data-role="viewer-print"]').is_visible() and
          'window.print()' in viewer.content())
    printable = api.get(BASE + f'/modules/clinical/ui/viewer.php?uuid={signed_draft_uuid}&doctor_id=1&autoprint=1')
    check('E32_PRINT_EXACT_CONTENT', printable.status == 200 and
          'Fuente modificada después de importar' in printable.text() and
          'section:1016' not in printable.text())
    check('E34_PROFESSIONAL_HEADER', viewer.locator('.document-sheet .clinical-doc-head').count() == 1)
    viewer.close()
    page.locator('#modalDocumentPostEmission [data-post-emission="close"]').click()
    expect(page.locator('#modalDocumentPostEmission')).to_be_hidden()

    page.set_viewport_size({'width': 1440, 'height': 900})
    page.locator('[data-action="documents-open-nota"]').click()
    expect(page.locator('#modalNotaMedica')).to_be_visible()
    page.locator('#nm_source_choose').click()
    page.locator('#nm_source_options input[value="1021"]').check()
    page.locator('#nm_source_confirm').click()
    expect(page.locator('#nm_source_summary')).to_contain_text('Abierta')
    page.screenshot(path=str(SHOTS / '1440x900-linked-open.png'))
    check('VIEWPORT_1440X900', page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'))
    page.locator('#nm_source_import').click()
    expect(page.locator('#nm_source_import_panel')).to_be_visible()
    page.screenshot(path=str(SHOTS / '1440x900-import-review.png'))
    check('ACCESSIBILITY_SOURCE_CONTROL', page.locator('#nm_source_title').is_visible()
          and page.locator('#nm_source_options').get_attribute('role') == 'radiogroup'
          and page.locator('#nm_source_import_panel').get_attribute('aria-label') == 'Revisar información de la consulta')
    page.locator('#nm_source_import_cancel').click()
    page.locator('#nm_motivo_consulta').fill('Nota independiente de QA para encabezado')
    page.locator('#nm_next').click()
    page.locator('#nm_padecimiento_actual').fill('Padecimiento sintético')
    page.locator('#nm_next').click()
    page.locator('#nm_exploracion_fisica').fill('Exploración sintética')
    page.locator('#nm_next').click()
    page.locator('#nm_impresion_diagnostica').fill('Impresión sintética')
    page.locator('#nm_next').click()
    page.locator('#nm_tratamiento_indicaciones').fill('Plan sintético')
    page.locator('input[name="nm_professional_header"][value="hidden"]').check()
    page.locator('#nm_next').click()
    check('E34_HIDDEN_HEADER_REGRESSION', page.locator('#nm_preview_frame .clinical-doc-head').count() == 0
          and 'Nota independiente de QA para encabezado' in page.locator('#nm_preview_frame').inner_text())
    check('NO_JS_ERRORS_FINAL', not errors)
    browser.close()
