"""DOC-ENCOUNTER03: exact-source Informe regression on consultation_flow_r1_disposable_gate.sh."""
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
SHOTS = Path(os.environ.get('DOC_ENCOUNTER03_SHOTS', '/tmp/doc-encounter03-shots'))
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
    url = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/informe-encounters'
    rows_response = api.get(url)
    check('I01_I02_I03_I06_I07_I08_I09', rows_response.status == 200 and
          set(x['encounter_id'] for x in rows_response.json()['data']) == {1016, 1017, 1021})
    projection_response = api.get(url + '/1016')
    check('I11_I12_I13_I14_I44', projection_response.status == 200)
    projection = projection_response.json()['data']
    candidates = projection['candidates']
    mapped = {(x['source_type'], tuple(x['destinations'])) for x in candidates}
    check('I11_I14_MAP', ('reason_evolution', ('clinical_summary',)) in mapped and
          ('physical_exam', ('findings',)) in mapped and
          ('vital_observation', ('findings',)) in mapped and
          ('assessment', ('diagnostic_impression',)) in mapped and
          ('plan', ('plan',)) in mapped and
          not any(x['source_type'] == 'diagnostic_order' for x in candidates))
    check('I39_I40_I41_I42', pw.request.new_context().get(url + '/1016').status == 401 and
          api.get(url + '/1018').status == 409 and
          api.get(url + '/1019').status == 404 and
          api.get(url + '/1020').status == 404)
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
            gate_url = f'http://127.0.0.1:{gate_port}/api/clinical/index.php/doctors/1/patients/{PATIENT}/informe-encounters/1016'
            gate_response = None
            for _ in range(30):
                try:
                    gate_response = api.get(gate_url, timeout=1000)
                    break
                except Exception:
                    time.sleep(0.1)
            check('I43_M6_READ_GATE', gate_response is not None and gate_response.status == 409)
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
    page.locator('[data-action="documents-open-informe"]').click()
    expect(page.locator('#modalInformeMedico')).to_be_visible()
    check('I01_I06_I15_I16_I17', 'Sin consulta vinculada' in page.locator('#im_source_summary').inner_text()
          and page.locator('#im_reason').input_value() == ''
          and page.locator('#im_relevant_history').input_value() == ''
          and page.locator('#im_prognosis').input_value() == '')
    page.screenshot(path=str(SHOTS / '1366x768-unlinked.png'))
    page.locator('#im_source_choose').click()
    expect(page.locator('#im_source_options input')).to_have_count(3)
    check('I03_NO_GUESS', page.locator('#im_source_options input:checked').count() == 0)
    page.locator('#im_source_options input[value="1016"]').check()
    page.locator('#im_source_confirm').click()
    expect(page.locator('#im_source_summary')).to_contain_text('Cerrada')
    check('I02_I08_I18_NO_AUTO_IMPORT', page.locator('#im_clinical_summary').input_value() == '')
    page.screenshot(path=str(SHOTS / '1366x768-linked-closed.png'))
    page.locator('#im_source_import').click()
    expect(page.locator('#im_source_candidates input')).to_have_count(len(candidates))
    page.screenshot(path=str(SHOTS / '1366x768-import-review.png'))
    idx = {kind: next(i for i,c in enumerate(candidates) if c['source_type'] == kind)
           for kind in ['reason_evolution','physical_exam','vital_observation','assessment','plan']}
    for kind in ['reason_evolution','physical_exam','assessment','plan']:
        page.locator(f'#im_source_candidates input[data-informe-candidate="{idx[kind]}"]').check()
    page.locator('#im_source_apply').click()
    expect(page.locator('#im_step_2')).to_be_visible()
    check('I11_I14_I18', 'Dolor abdominal sintético' in page.locator('#im_clinical_summary').input_value()
          and 'Sensibilidad sintética' in page.locator('#im_findings').input_value()
          and 'Impresión sintética' in page.locator('#im_diagnostic_impression').input_value()
          and 'Plan sintético' in page.locator('#im_plan').input_value()
          and page.locator('#im_reason').input_value() == ''
          and page.locator('#im_relevant_history').input_value() == '')
    page.screenshot(path=str(SHOTS / '1366x768-imported-fields.png'))
    page.locator('#im_clinical_summary').fill('Edición médica conservada')
    page.locator('#im_prev').click()
    with page.expect_response(lambda response: response.url.endswith('/informe-encounters/1016')):
        page.locator('#im_source_import').click()
    page.locator(f'#im_source_candidates input[data-informe-candidate="{idx["reason_evolution"]}"]').check()
    page.locator('#im_source_apply').click()
    expect(page.locator('#im_source_conflict')).to_be_visible()
    check('I19_I20', page.locator('#im_clinical_summary').input_value() == 'Edición médica conservada')
    page.screenshot(path=str(SHOTS / '1366x768-replace-confirmation.png'))
    page.locator('#im_source_replace_cancel').click()
    check('I19_CANCEL', page.locator('#im_clinical_summary').input_value() == 'Edición médica conservada')
    page.locator('#im_source_apply').click()
    page.locator('#im_source_replace_confirm').click()
    check('I19_REPLACE', 'Dolor abdominal sintético' in page.locator('#im_clinical_summary').input_value())
    page.locator('#im_prev').click()
    with page.expect_response(lambda response: response.url.endswith('/informe-encounters/1016')):
        page.locator('#im_source_import').click()
    page.locator(f'#im_source_candidates input[data-informe-candidate="{idx["physical_exam"]}"]').check()
    page.locator(f'#im_source_candidates input[data-informe-candidate="{idx["vital_observation"]}"]').check()
    page.locator('#im_source_apply').click()
    check('SINGLE_SOURCE_CONFLICT', 'una sola fuente' in page.locator('#im_notice').inner_text())
    page.locator('#im_source_import_cancel').click()
    check('1366X768_NO_HORIZONTAL_OVERFLOW', page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'))
    page.locator('#im_reason').fill('Informe sintético de revisión')
    page.locator('#im_next').click()
    expect(page.locator('#im_step_2')).to_be_visible()
    page.locator('#im_next').click()
    expect(page.locator('#im_step_4')).to_be_visible()
    expect(page.locator('#im_preview')).to_contain_text('Dolor abdominal sintético')
    check('I25_I31_PREVIEW', 'section:1016' not in page.locator('#im_preview').inner_text())
    with page.expect_request(lambda request: request.method == 'POST' and request.url.endswith('/documents')) as save_request:
        page.locator('#im_next').click()
    captured_body = save_request.value.post_data_json
    expect(page.locator('#im_step_3')).to_be_visible()
    forged = json.loads(json.dumps(captured_body))
    forged['payload']['encounter_imports'][0]['imported_snapshot'] = 'Texto de fuente falsificado'
    forged_response = api.post(save_request.value.url, data=forged,
                               headers={'Idempotency-Key': 'informe:' + str(uuid.uuid4())})
    check('SERVER_CANONICAL_IMPORT_REJECTS_FORGERY', forged_response.status >= 400)
    stored = sql("SELECT CONCAT_WS('|',document_uuid,IFNULL(encounter_ref_id,'NULL'),"
                 "IFNULL(encounter_id,'NULL'),JSON_EXTRACT(payload_json,'$.encounter_source.encounter_id'),"
                 "JSON_LENGTH(JSON_EXTRACT(payload_json,'$.encounter_imports'))) "
                 "FROM clinical_documents WHERE document_type='informe_medico' ORDER BY id DESC LIMIT 1")
    print('STORED=' + stored, flush=True)
    check('I04_I23_I24_EXACT_PERSISTENCE', '|1016|1016|1016|' in stored and int(stored.split('|')[-1]) >= 5)
    draft_uuid = stored.split('|')[0]
    page.locator('#im_save').click()
    expect(page.locator('#modalInformeMedico')).to_be_hidden()
    page.wait_for_timeout(400)
    sql("UPDATE clinical_encounter_sections SET narrative_text='Fuente cambiada tras importar',"
        "row_version=row_version+1,updated_at=UTC_TIMESTAMP() "
        "WHERE encounter_id=1016 AND section_type='reason_evolution'")
    page.evaluate("uuid=>document.querySelector(`[data-informe-draft=\"1\"][data-doc-uuid=\"${uuid}\"]`).click()", draft_uuid)
    expect(page.locator('#modalInformeMedico')).to_be_visible()
    check('I10_I21_I22_I28_RESUME_SNAPSHOT', 'Dolor abdominal sintético' in page.locator('#im_clinical_summary').input_value()
          and 'Fuente cambiada' not in page.locator('#im_clinical_summary').input_value()
          and 'enc:1016' in page.locator('#im_source_summary').inner_text())
    page.screenshot(path=str(SHOTS / '1366x768-resumed-draft.png'))
    for _ in range(2):
        if page.locator('#im_prev').is_enabled(): page.locator('#im_prev').click()
    expect(page.locator('#im_step_1')).to_be_visible()
    page.locator('#im_source_choose').click()
    page.locator('#im_source_options input[value="1021"]').check()
    page.once('dialog', lambda dialog: dialog.accept())
    page.locator('#im_source_confirm').click()
    check('I27_SOURCE_CHANGE_RETAINS_COPY', 'Dolor abdominal sintético' in page.locator('#im_clinical_summary').input_value())
    page.locator('#im_next').click()
    page.locator('#im_next').click()
    expect(page.locator('#im_preview')).to_contain_text('Dolor abdominal sintético')
    page.locator('#im_next').click()
    page.locator('#im_save').click()
    expect(page.locator('#modalInformeMedico')).to_be_hidden()
    changed = sql(f"SELECT CONCAT_WS('|',encounter_ref_id,JSON_EXTRACT(payload_json,'$.encounter_imports[0].encounter_id')) FROM clinical_documents WHERE document_uuid='{draft_uuid}'")
    check('I27_PRIOR_PROVENANCE_TRUTH', changed == '1021|1016')
    page.wait_for_timeout(400)
    page.evaluate("uuid=>document.querySelector(`[data-informe-draft=\"1\"][data-doc-uuid=\"${uuid}\"]`).click()", draft_uuid)
    expect(page.locator('#modalInformeMedico')).to_be_visible()
    for _ in range(2):
        if page.locator('#im_prev').is_enabled(): page.locator('#im_prev').click()
    page.once('dialog', lambda dialog: dialog.accept())
    page.locator('#im_source_unlink').click()
    check('I26_UNLINK_RETAINS_COPY', 'Dolor abdominal sintético' in page.locator('#im_clinical_summary').input_value())
    page.locator('#im_next').click()
    page.locator('#im_next').click()
    expect(page.locator('#im_preview')).to_contain_text('Dolor abdominal sintético')
    page.locator('#im_next').click()
    page.locator('#im_save').click()
    expect(page.locator('#modalInformeMedico')).to_be_hidden()
    unlinked = sql(f"SELECT CONCAT_WS('|',IFNULL(encounter_ref_id,'NULL'),JSON_EXTRACT(payload_json,'$.encounter_imports[0].encounter_id')) FROM clinical_documents WHERE document_uuid='{draft_uuid}'")
    check('I26_UNLINK_PERSISTS_PROVENANCE', unlinked == 'NULL|1016')
    check('I37_CANONICAL_DRAFT', sql(f"SELECT status FROM clinical_documents WHERE document_uuid='{draft_uuid}'") == 'draft')
    qr_version = int(sql(f"SELECT version FROM clinical_documents WHERE document_uuid='{draft_uuid}'"))
    qr_create = api.post(BASE + '/api/clinical/index.php/informe-qr-sessions',
                         data={'document_uuid': draft_uuid, 'document_version': qr_version, 'role': 'doctor'})
    check('I35_QR_SESSION_REGRESSION', qr_create.status == 201 and qr_create.json()['data']['status'] == 'pending')
    qr_token = qr_create.json()['data']['token']
    qr_mobile = api.get(BASE + f'/api/clinical/index.php/informe-qr-sessions/{qr_token}/mobile-context')
    check('I35_QR_REVIEW_SNAPSHOT', qr_mobile.status == 200 and
          'Dolor abdominal sintético' in qr_mobile.json()['data']['review_html'])
    api.post(BASE + f'/api/clinical/index.php/informe-qr-sessions/{qr_token}/cancel')
    page.wait_for_timeout(400)
    page.set_viewport_size({'width': 1440, 'height': 900})
    page.locator('[data-action="documents-open-informe"]').click()
    expect(page.locator('#modalInformeMedico')).to_be_visible()
    check('1440X900_UNLINKED', 'Sin consulta vinculada' in page.locator('#im_source_summary').inner_text())
    page.screenshot(path=str(SHOTS / '1440x900-unlinked.png'))
    page.locator('#im_source_choose').click()
    page.locator('#im_source_options input[value="1021"]').check()
    page.locator('#im_source_confirm').click()
    expect(page.locator('#im_source_summary')).to_contain_text('Abierta')
    page.screenshot(path=str(SHOTS / '1440x900-linked-open.png'))
    page.locator('#im_source_import').click()
    expect(page.locator('#im_source_import_panel')).to_be_visible()
    page.screenshot(path=str(SHOTS / '1440x900-import-review.png'))
    check('1440X900_NO_HORIZONTAL_OVERFLOW', page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'))
    check('ACCESSIBILITY_QA', page.locator('#im_source_title').is_visible()
          and page.locator('#im_source_options').get_attribute('role') == 'radiogroup'
          and page.locator('#im_source_import_panel').get_attribute('aria-label') == 'Revisar información de la consulta')
    check('NO_NESTED_SCROLL_TRAP', page.evaluate("""() => { const p=document.querySelector('#im_source_import_panel');
      const style=getComputedStyle(p); return !['auto','scroll'].includes(style.overflowY); }"""))
    page.locator('#modalInformeMedico .btn-close').click()
    expect(page.locator('#modalInformeMedico')).to_be_hidden()
    page.wait_for_timeout(400)
    page.evaluate("window.dispatchEvent(new CustomEvent('mxmed:open-informe-from-encounter',"
                  "{detail:{patient_id:'p_plan02ux_review',encounter_key:'enc:1021'}}))")
    page.wait_for_timeout(150)
    if page.locator('#modalDocSessionRecovery').is_visible():
        page.locator('#doc_session_recovery_discard_btn').click()
    expect(page.locator('#modalInformeMedico')).to_be_visible()
    expect(page.locator('#im_source_options input[value="1021"]')).to_be_checked()
    check('I05_CONTEXT_PRESELECT_REQUIRES_CONFIRMATION', page.locator('#im_source_summary').inner_text() == 'Sin consulta vinculada'
          and page.locator('#im_clinical_summary').input_value() == '')
    page.locator('#modalInformeMedico .btn-close').click()
    expect(page.locator('#modalInformeMedico')).to_be_hidden()
    page.wait_for_timeout(400)
    page.evaluate("uuid=>document.querySelector(`[data-informe-draft=\"1\"][data-doc-uuid=\"${uuid}\"]`).click()", draft_uuid)
    expect(page.locator('#im_preview')).to_contain_text('Dolor abdominal sintético')
    page.locator('#im_next').click()
    expect(page.locator('#im_step_3')).to_be_visible()
    canvas = page.locator('#im_signature_canvas')
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 30, box['y'] + 40)
    page.mouse.down()
    page.mouse.move(box['x'] + 180, box['y'] + 90, steps=12)
    page.mouse.up()
    page.locator('#im_next').click()
    expect(page.locator('#im_step_4')).to_contain_text('Dolor abdominal sintético')
    expect(page.locator('#im_step_label')).to_contain_text('Revisión final')
    check('I29_SIGNED_BEFORE_IMPORT', 'Revisión final' in page.locator('#im_step_label').inner_text() and not page.locator('#im_notice').inner_text().strip())
    for _ in range(4): page.locator('#im_prev').click()
    expect(page.locator('#im_step_1')).to_be_visible()
    page.locator('#im_source_choose').click()
    page.locator('#im_source_options input[value="1016"]').check()
    page.locator('#im_source_confirm').click()
    with page.expect_response(lambda response: response.url.endswith('/informe-encounters/1016')):
        page.locator('#im_source_import').click()
    updated_rows = api.get(url + '/1016').json()['data']['candidates']
    reason_index = next(i for i, item in enumerate(updated_rows) if item['source_type'] == 'reason_evolution')
    page.locator(f'#im_source_candidates input[data-informe-candidate="{reason_index}"]').check()
    page.locator('#im_source_apply').click()
    expect(page.locator('#im_source_conflict')).to_be_visible()
    page.locator('#im_source_replace_confirm').click()
    check('I30_IMPORT_STALES_SIGNATURE', 'aplicada a esta versión' not in page.locator('#im_signature_status').inner_text().lower()
          and 'Fuente cambiada tras importar' in page.locator('#im_clinical_summary').input_value())
    page.locator('#im_next').click()
    page.locator('#im_next').click()
    expect(page.locator('#im_preview')).to_contain_text('Fuente cambiada tras importar')
    page.locator('#im_next').click()
    expect(page.locator('#im_step_3')).to_be_visible()
    page.locator('#im_next').click()
    check('I30_STALE_CANNOT_FINALIZE', page.locator('#im_step_3').is_visible()
          and 'firma' in page.locator('#im_notice').inner_text().lower())
    page.locator('#im_signature_clear').click()
    canvas = page.locator('#im_signature_canvas')
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 40, box['y'] + 45)
    page.mouse.down()
    page.mouse.move(box['x'] + 210, box['y'] + 85, steps=12)
    page.mouse.up()
    page.locator('#im_next').click()
    expect(page.locator('#im_step_4')).to_contain_text('Fuente cambiada tras importar')
    check('I32_FINAL_REVIEW_PARITY', 'section:1016' not in page.locator('#im_step_4').inner_text())
    page.locator('#im_emit').click()
    expect(page.locator('#modalInformeMedico')).to_be_hidden()
    expect(page.locator('#modalDocumentPostEmission')).to_be_visible()
    check('I38_POST_EMISSION_MODAL', page.locator('#modalDocumentPostEmission').is_visible())
    emitted = sql(f"SELECT CONCAT_WS('|',status,encounter_ref_id,JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.content.clinical_summary'))) FROM clinical_documents WHERE document_uuid='{draft_uuid}'")
    check('I32_EMISSION', emitted.startswith('generated|1016|Fuente cambiada tras importar'))
    viewer = context.new_page()
    response = viewer.goto(BASE + f'/modules/clinical/ui/viewer.php?uuid={draft_uuid}&doctor_id=1', wait_until='domcontentloaded')
    check('I33_VIEWER', response.status == 200 and 'Fuente cambiada tras importar' in viewer.locator('.document-sheet').inner_text()
          and 'section:1016' not in viewer.locator('.document-sheet').inner_text())
    printable = api.get(BASE + f'/modules/clinical/ui/viewer.php?uuid={draft_uuid}&doctor_id=1&autoprint=1')
    check('I34_PRINT', printable.status == 200 and 'Fuente cambiada tras importar' in printable.text()
          and 'section:1016' not in printable.text())
    check('I36_HEADER', viewer.locator('.document-sheet .clinical-doc-head').count() == 1)
    viewer.close()
    check('NO_JS_ERRORS', not errors)
    browser.close()
