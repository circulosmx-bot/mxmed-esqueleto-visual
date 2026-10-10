"""DOC-ENCOUNTER04: disposable exact-source Interconsulta browser and API regression."""
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
ROOT = Path(__file__).resolve().parents[3]
SHOTS = Path(os.environ.get('DOC_ENCOUNTER04_SHOTS', '/tmp/doc-encounter04-shots'))
SHOTS.mkdir(parents=True, exist_ok=True)
PATIENT = 'p_plan02ux_review'


def sql(statement):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', statement], text=True).strip()


def check(label, condition):
    if not condition:
        raise AssertionError(label)
    print(f'{label}=PASS', flush=True)


sql("""
INSERT INTO patients_patients (patient_id,birthdate) VALUES ('p_ix_other','1992-01-01');
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
 VALUES (1019,'2','p_plan02ux_review','2026-09-28 09:00:00','open','other-user'),
        (1020,'1','p_ix_other','2026-09-27 09:00:00','open','review-user'),
        (1021,'1','p_plan02ux_review','2026-10-10 10:00:00','open','review-user');
INSERT INTO clinical_encounter_sections
 (encounter_id,section_type,payload_schema_version,payload_json,narrative_text,created_by_user_id,updated_by_user_id)
VALUES (1017,'reason_evolution',1,'{}','Dolor sintético de consulta, revisar motivo de referencia.','review-user','review-user'),
       (1017,'assessment',1,'{}','Valoración sintética de referencia.','review-user','review-user'),
       (1017,'physical_exam',1,'{"systems":{"abdomen":{"state":"ABNORMAL","finding":"Hallazgo sintético"}}}',NULL,'review-user','review-user'),
       (1017,'plan',1,'{}','Plan que no debe ofrecerse para Interconsulta.','review-user','review-user');
INSERT INTO clinical_observations
 (encounter_id,code,value_numeric,unit,effective_at,recorded_at,recorded_by_user_id,source,provenance_json)
 VALUES (1017,'heart_rate',82,'lpm','2026-10-01 09:00:00','2026-10-01 09:00:00','review-user','direct_measurement','{}');
INSERT INTO clinical_documents
 (id,document_uuid,document_type,title,version,status,patient_id,encounter_id,encounter_ref_id,
  care_setting,payload_json,event_datetime,created_at,created_by_user_id)
VALUES (601,'00000000-0000-4000-8000-000000000601','lab_order','Orden exacta de QA',1,'generated',
  'p_plan02ux_review','1017',1017,'consulta','{}','2026-10-01 09:00:00',UTC_TIMESTAMP(),'review-user'),
 (602,'00000000-0000-4000-8000-000000000602','lab_order','Orden sin consulta',1,'generated',
  'p_plan02ux_review',NULL,NULL,'consulta','{}','2026-10-01 09:00:00',UTC_TIMESTAMP(),'review-user'),
 (603,'00000000-0000-4000-8000-000000000603','lab_result','Resultado exacto de QA',1,'generated',
  'p_plan02ux_review',NULL,NULL,'consulta','{"related_order_document_id":601}',
  '2026-10-01 09:00:00',UTC_TIMESTAMP(),'review-user'),
 (604,'00000000-0000-4000-8000-000000000604','lab_result','Resultado sin consulta',1,'generated',
  'p_plan02ux_review',NULL,NULL,'consulta','{}','2026-10-01 09:00:00',UTC_TIMESTAMP(),'review-user');
""")

with sync_playwright() as pw:
    browser = pw.webkit.launch(headless=True)
    context = browser.new_context(viewport={'width': 1366, 'height': 768},
                                  extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    api = context.request
    url = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/interconsulta-encounters'
    rows = api.get(url)
    check('X01_X02_X07_X08_X09_X41', rows.status == 200 and
          set(item['encounter_id'] for item in rows.json()['data']) == {1016, 1017, 1021})
    projection_response = api.get(url + '/1017')
    check('X11_X12_X13_PROJECTION', projection_response.status == 200)
    projection = projection_response.json()['data']
    candidates = projection['candidates']
    kinds = {item['source_type'] for item in candidates}
    check('X11_X12_X13_X14_X15_X16_X17',
          {'reason_evolution','assessment','physical_exam','vital_observation',
           'diagnostic_order','diagnostic_result'} <= kinds and 'plan' not in kinds and
          not any('Orden sin consulta' in item['text'] or 'Resultado sin consulta' in item['text']
                  for item in candidates) and
          set(dest for item in candidates for dest in item['destinations']) == {'reason','summary','studies'})
    check('X41_X42_X43_X44_X46',
          pw.request.new_context().get(url + '/1017').status == 401 and
          api.get(url + '/1020').status == 404 and api.get(url + '/1019').status == 404 and
          api.get(url + '/1018').status == 409 and api.get(url + '/1017').status == 200)
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', 0))
        port = probe.getsockname()[1]
    env = dict(os.environ, MXMED_DB_HOST='localhost', MXMED_DB_NAME=DB, MXMED_DB_USER='root',
               MXMED_DB_PASS='', MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1='0',
               MXMED_CLINICAL_M6_COHORT_MODE='allowlist',
               MXMED_CLINICAL_M6_COHORT_PAIRS='1|p_plan02ux_review')
    with open(os.devnull, 'wb') as log:
        server = subprocess.Popen(['php', '-d', f"session.save_path={os.environ['FLOW_R1_QA_ROOT']}/sessions",
                                   '-S', f'127.0.0.1:{port}', '-t', str(ROOT)],
                                  env=env, stdout=log, stderr=log)
        try:
            for _ in range(30):
                try:
                    gated = api.get(f'http://127.0.0.1:{port}/api/clinical/index.php/doctors/1/patients/{PATIENT}/interconsulta-encounters/1017', timeout=1000)
                    break
                except Exception:
                    time.sleep(0.1)
            check('X45_M6_READ_GATE', gated.status == 409)
        finally:
            server.terminate()
            server.wait(timeout=5)
    page = context.new_page()
    def capture_both(name):
        page.screenshot(path=str(SHOTS / f'1366x768-{name}.png'))
        page.set_viewport_size({'width': 1440, 'height': 900})
        page.screenshot(path=str(SHOTS / f'1440x900-{name}.png'))
        page.set_viewport_size({'width': 1366, 'height': 768})
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-interconsulta"]').click()
    expect(page.locator('#modalInterconsulta')).to_be_visible()
    check('X01_X04_X06', page.locator('#ix_source_summary').inner_text() == 'Sin consulta vinculada'
          and page.locator('#ix_reason').input_value() == '')
    capture_both('unlinked')
    page.locator('#ix_source_choose').click()
    expect(page.locator('#ix_source_options input')).to_have_count(3)
    check('X04_NO_LATEST', page.locator('#ix_source_options input:checked').count() == 0)
    page.locator('#ix_source_options input[value="1017"]').check()
    page.locator('#ix_source_confirm').click()
    expect(page.locator('#ix_source_summary')).to_contain_text('Cerrada')
    check('X02_X08_NO_AUTO_IMPORT', page.locator('#ix_reason').input_value() == '')
    capture_both('linked-closed')
    page.locator('#ix_doctor_name').fill('Dra. Destino QA')
    with page.expect_response(lambda response: response.url.endswith('/interconsulta-encounters/1017')):
        page.locator('#ix_source_import').click()
    expect(page.locator('#ix_source_candidates input')).to_have_count(len(candidates))
    capture_both('import-review')
    idx = {kind: next(i for i,c in enumerate(candidates) if c['source_type'] == kind)
           for kind in ['reason_evolution','assessment','physical_exam','diagnostic_order','diagnostic_result']}
    for kind in ['reason_evolution','assessment','diagnostic_order']:
        page.locator(f'#ix_source_candidates input[data-interconsulta-candidate="{idx[kind]}"]').check()
    page.locator('#ix_source_apply').click()
    expect(page.locator('#ix_step_2')).to_be_visible()
    check('X18_X14_X15_X16_X17',
          'Dolor sintético' in page.locator('#ix_reason').input_value() and
          'Valoración sintética' in page.locator('#ix_summary').input_value() and
          'Orden exacta' in page.locator('#ix_studies').input_value() and
          all(page.locator('#ix_' + key).input_value() == '' for key in ['background','request','comments','specialty','service','facility','city','contact']))
    capture_both('imported')
    page.locator('#ix_reason').fill('Edición médica conservada')
    page.locator('#ix_prev').click()
    with page.expect_response(lambda response: response.url.endswith('/interconsulta-encounters/1017')):
        page.locator('#ix_source_import').click()
    page.locator(f'#ix_source_candidates input[data-interconsulta-candidate="{idx["reason_evolution"]}"]').check()
    page.locator('#ix_source_apply').click()
    expect(page.locator('#ix_source_conflict')).to_be_visible()
    check('X19_X20', page.locator('#ix_reason').input_value() == 'Edición médica conservada')
    capture_both('replace')
    page.locator('#ix_source_replace_cancel').click()
    check('X20_CANCEL', page.locator('#ix_reason').input_value() == 'Edición médica conservada')
    page.locator('#ix_source_apply').click()
    page.locator('#ix_source_replace_confirm').click()
    check('X21_REPLACE', 'Dolor sintético' in page.locator('#ix_reason').input_value())
    page.locator('#ix_prev').click()
    with page.expect_response(lambda response: response.url.endswith('/interconsulta-encounters/1017')):
        page.locator('#ix_source_import').click()
    for kind in ['assessment','physical_exam']:
        page.locator(f'#ix_source_candidates input[data-interconsulta-candidate="{idx[kind]}"]').check()
    page.locator('#ix_source_apply').click()
    check('X22_SINGLE_SOURCE', 'una sola fuente' in page.locator('#ix_notice').inner_text())
    page.locator('#ix_source_import_cancel').click()
    with page.expect_response(lambda response: response.url.endswith('/interconsulta-encounters/1017')):
        page.locator('#ix_source_import').click()
    page.locator(f'#ix_source_candidates input[data-interconsulta-candidate="{idx["diagnostic_result"]}"]').check()
    page.locator('#ix_source_apply').click()
    expect(page.locator('#ix_source_conflict')).to_be_visible()
    page.locator('#ix_source_replace_confirm').click()
    check('X13_RESULT_REFERENCE_ONLY', 'Resultado exacto de QA' in page.locator('#ix_studies').input_value()
          and 'Orden sin consulta' not in page.locator('#ix_studies').input_value())
    while not page.locator('#ix_step_1').is_visible():
        page.locator('#ix_prev').click()
    page.locator('#ix_source_choose').click()
    page.locator('#ix_source_options input[value="1016"]').check()
    page.once('dialog', lambda dialog: dialog.accept())
    page.locator('#ix_source_confirm').click()
    expect(page.locator('#ix_source_summary')).to_contain_text('enc:1016')
    check('X28_SOURCE_CHANGE', 'Dolor sintético' in page.locator('#ix_reason').input_value())
    page.once('dialog', lambda dialog: dialog.accept())
    page.locator('#ix_source_unlink').click()
    check('X29_UNLINK', 'Dolor sintético' in page.locator('#ix_reason').input_value()
          and page.locator('#ix_source_summary').inner_text() == 'Sin consulta vinculada')
    page.locator('#ix_source_choose').click()
    page.locator('#ix_source_options input[value="1017"]').check()
    page.locator('#ix_source_confirm').click()
    expect(page.locator('#ix_source_summary')).to_contain_text('enc:1017')
    with page.expect_response(lambda response: response.request.method == 'POST' and response.url.endswith('/documents')) as save_response:
        page.locator('#ix_save').click()
    check('DRAFT_SAVE_RESPONSE', save_response.value.status == 201)
    forged = json.loads(json.dumps(save_response.value.request.post_data_json))
    forged['payload']['encounter_imports'][0]['imported_snapshot'] = 'Referencia falsificada'
    rejected = api.post(save_response.value.request.url, data=forged,
                        headers={'Idempotency-Key': 'interconsulta:' + str(uuid.uuid4())})
    check('SERVER_CANONICAL_IMPORT_REJECTS_FORGERY', rejected.status >= 400)
    expect(page.locator('#modalInterconsulta')).to_be_hidden()
    stored = sql("SELECT CONCAT_WS('|',document_uuid,IFNULL(encounter_ref_id,'NULL'),"
                 "IFNULL(encounter_id,'NULL'),JSON_EXTRACT(payload_json,'$.encounter_source.encounter_id'),"
                 "JSON_LENGTH(JSON_EXTRACT(payload_json,'$.encounter_imports'))) "
                 "FROM clinical_documents WHERE document_type='interconsulta' ORDER BY id DESC LIMIT 1")
    print('STORED=' + stored, flush=True)
    check('X03_X24_X25', '|1017|1017|1017|' in stored and int(stored.split('|')[-1]) >= 3)
    draft_uuid = stored.split('|')[0]
    page.wait_for_timeout(350)
    page.evaluate("uuid=>document.querySelector(`[data-interconsulta-draft=\"1\"][data-doc-uuid=\"${uuid}\"]`).click()", draft_uuid)
    expect(page.locator('#modalInterconsulta')).to_be_visible()
    check('X10_X23_X27_X30', 'enc:1017' in page.locator('#ix_source_summary').inner_text()
          and 'Dolor sintético' in page.locator('#ix_reason').input_value()
          and page.locator('#ix_doctor_name').input_value() == 'Dra. Destino QA')
    while not page.locator('#ix_step_1').is_visible():
        page.locator('#ix_prev').click()
    page.screenshot(path=str(SHOTS / '1366x768-resumed.png'))
    page.locator('#ix_source_choose').click()
    page.locator('#ix_source_options input[value="1021"]').check()
    page.once('dialog', lambda dialog: dialog.accept())
    page.locator('#ix_source_confirm').click()
    expect(page.locator('#ix_source_summary')).to_contain_text('Abierta')
    page.screenshot(path=str(SHOTS / '1366x768-linked-open.png'))
    page.locator('#ix_save').click()
    expect(page.locator('#modalInterconsulta')).to_be_hidden()
    changed = sql(f"SELECT CONCAT_WS('|',encounter_ref_id,JSON_EXTRACT(payload_json,'$.encounter_imports[0].encounter_id')) FROM clinical_documents WHERE document_uuid='{draft_uuid}'")
    check('X28_PRIOR_PROVENANCE_TRUTH', changed == '1021|1017')
    page.wait_for_timeout(350)
    page.evaluate("uuid=>document.querySelector(`[data-interconsulta-draft=\"1\"][data-doc-uuid=\"${uuid}\"]`).click()", draft_uuid)
    expect(page.locator('#modalInterconsulta')).to_be_visible()
    while not page.locator('#ix_step_1').is_visible():
        page.locator('#ix_prev').click()
    page.once('dialog', lambda dialog: dialog.accept())
    page.locator('#ix_source_unlink').click()
    check('X29_UNLINK_RETAINS_TEXT', page.locator('#ix_source_summary').inner_text() == 'Sin consulta vinculada'
          and 'Dolor sintético' in page.locator('#ix_reason').input_value())
    page.locator('#ix_save').click()
    expect(page.locator('#modalInterconsulta')).to_be_hidden()
    unlinked = sql(f"SELECT CONCAT_WS('|',IFNULL(encounter_ref_id,'NULL'),JSON_EXTRACT(payload_json,'$.encounter_imports[0].encounter_id')) FROM clinical_documents WHERE document_uuid='{draft_uuid}'")
    check('X29_UNLINK_PERSISTS_PROVENANCE', unlinked == 'NULL|1017')
    qr_version = int(sql(f"SELECT version FROM clinical_documents WHERE document_uuid='{draft_uuid}'"))
    qr_create = api.post(BASE + '/api/clinical/index.php/interconsulta-qr-sessions',
                         data={'document_uuid': draft_uuid, 'document_version': qr_version, 'role': 'doctor'})
    check('X37_QR_REGRESSION', qr_create.status == 201 and qr_create.json()['data']['status'] == 'pending')
    qr_token = qr_create.json()['data']['token']
    qr_mobile = api.get(BASE + f'/api/clinical/index.php/interconsulta-qr-sessions/{qr_token}/mobile-context')
    check('X37_QR_REVIEW', qr_mobile.status == 200 and
          'Dolor sintético' in qr_mobile.json()['data']['review_html'])
    api.post(BASE + f'/api/clinical/index.php/interconsulta-qr-sessions/{qr_token}/cancel')
    page.wait_for_timeout(350)
    page.evaluate("uuid=>document.querySelector(`[data-interconsulta-draft=\"1\"][data-doc-uuid=\"${uuid}\"]`).click()", draft_uuid)
    expect(page.locator('#modalInterconsulta')).to_be_visible()
    while not page.locator('#ix_step_1').is_visible():
        page.locator('#ix_prev').click()
    page.locator('#ix_source_choose').click()
    page.locator('#ix_source_options input[value="1017"]').check()
    page.locator('#ix_source_confirm').click()
    expect(page.locator('#ix_source_summary')).to_contain_text('enc:1017')
    page.screenshot(path=str(SHOTS / '1366x768-relinked.png'))
    page.set_viewport_size({'width': 1440, 'height': 900})
    check('1366X768_1440X900_NO_HORIZONTAL_OVERFLOW',
          page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'))
    page.screenshot(path=str(SHOTS / '1440x900-resumed.png'))
    page.locator('#ix_source_import').click()
    expect(page.locator('#ix_source_import_panel')).to_be_visible()
    page.screenshot(path=str(SHOTS / '1440x900-import-review.png'))
    check('ACCESSIBILITY_QA', page.locator('#ix_source_title').is_visible()
          and page.locator('#ix_source_options').get_attribute('role') == 'radiogroup'
          and page.locator('#ix_source_import_panel').get_attribute('aria-label') == 'Revisar información de la consulta')
    check('NO_NESTED_SCROLL_TRAP', page.evaluate("""() => {
      const style=getComputedStyle(document.querySelector('#ix_source_import_panel'));
      return !['auto','scroll'].includes(style.overflowY);
    }"""))
    page.locator('#ix_source_import_cancel').click()
    page.locator('#ix_next').click()
    expect(page.locator('#ix_step_2')).to_be_visible()
    page.locator('#ix_next').click()
    expect(page.locator('#ix_step_3')).to_be_visible()
    page.locator('#ix_next').click()
    expect(page.locator('#ix_step_4')).to_be_visible()
    page.locator('#ix_request').fill('Solicito valoración sintética del especialista.')
    page.locator('#ix_next').click()
    expect(page.locator('#ix_step_5')).to_be_visible()
    page.locator('#ix_next').click()
    expect(page.locator('#ix_step_6')).to_be_visible()
    expect(page.locator('#ix_preview')).to_contain_text('Dolor sintético')
    check('X31_X33_PREVIEW', 'encounter_source' not in page.locator('#ix_preview').inner_text()
          and 'section:1017' not in page.locator('#ix_preview').inner_text())
    page.screenshot(path=str(SHOTS / '1440x900-preview.png'))
    page.locator('#ix_next').click()
    expect(page.locator('#ix_step_7')).to_be_visible()
    canvas = page.locator('#ix_signature_canvas')
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 30, box['y'] + 40)
    page.mouse.down()
    page.mouse.move(box['x'] + 170, box['y'] + 90, steps=12)
    page.mouse.up()
    expect(page.locator('#ix_signature_status')).to_contain_text('aplicada')
    page.locator('#ix_next').click()
    expect(page.locator('#ix_step_8')).to_be_visible()
    expect(page.locator('#ix_final_preview')).to_contain_text('Dolor sintético')
    check('X34_FINAL_REVIEW', 'Dolor sintético' in page.locator('#ix_final_preview').inner_text())
    sql("UPDATE clinical_encounter_sections SET narrative_text='Fuente nueva, requiere revisión',"
        "row_version=row_version+1,updated_at=UTC_TIMESTAMP() "
        "WHERE encounter_id=1017 AND section_type='reason_evolution'")
    check('X23_NO_LIVE_UPDATE', 'Dolor sintético' in page.locator('#ix_final_preview').inner_text()
          and 'Fuente nueva' not in page.locator('#ix_final_preview').inner_text())
    while not page.locator('#ix_step_1').is_visible():
        page.locator('#ix_prev').click()
    with page.expect_response(lambda response: response.url.endswith('/interconsulta-encounters/1017')):
        page.locator('#ix_source_import').click()
    updated = api.get(url + '/1017').json()['data']['candidates']
    reason_index = next(i for i,item in enumerate(updated) if item['source_type'] == 'reason_evolution')
    page.locator(f'#ix_source_candidates input[data-interconsulta-candidate="{reason_index}"]').check()
    page.locator('#ix_source_apply').click()
    expect(page.locator('#ix_source_conflict')).to_be_visible()
    page.locator('#ix_source_replace_confirm').click()
    check('X32_IMPORT_STALES_SIGNATURE', 'Fuente nueva' in page.locator('#ix_reason').input_value()
          and 'aplicada a esta versión' not in page.locator('#ix_signature_status').inner_text().lower())
    for step in [3,4,5,6]:
        page.locator('#ix_next').click()
        expect(page.locator(f'#ix_step_{step}')).to_be_visible()
    expect(page.locator('#ix_preview')).to_contain_text('Fuente nueva')
    page.locator('#ix_next').click()
    expect(page.locator('#ix_step_7')).to_be_visible()
    page.locator('#ix_next').click()
    check('X32_STALE_CANNOT_FINALIZE', page.locator('#ix_step_7').is_visible()
          and 'firma' in page.locator('#ix_notice').inner_text().lower())
    page.locator('#ix_signature_clear').click()
    canvas = page.locator('#ix_signature_canvas')
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 40, box['y'] + 45)
    page.mouse.down()
    page.mouse.move(box['x'] + 200, box['y'] + 85, steps=12)
    page.mouse.up()
    expect(page.locator('#ix_signature_status')).to_contain_text('aplicada')
    page.locator('#ix_next').click()
    expect(page.locator('#ix_step_8')).to_be_visible()
    expect(page.locator('#ix_final_preview')).to_contain_text('Fuente nueva')
    check('X34_FINAL_PARITY', 'Fuente nueva' in page.locator('#ix_final_preview').inner_text()
          and 'section:1017' not in page.locator('#ix_final_preview').inner_text())
    page.locator('#ix_emit').click()
    expect(page.locator('#modalInterconsulta')).to_be_hidden()
    expect(page.locator('#modalDocumentPostEmission')).to_be_visible()
    emitted = sql(f"SELECT CONCAT_WS('|',status,encounter_ref_id,JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.content.reason'))) FROM clinical_documents WHERE document_uuid='{draft_uuid}'")
    check('X40_POST_EMISSION', emitted.startswith('generated|1017|Fuente nueva'))
    viewer = context.new_page()
    response = viewer.goto(BASE + f'/modules/clinical/ui/viewer.php?uuid={draft_uuid}&doctor_id=1', wait_until='domcontentloaded')
    check('X35_VIEWER', response.status == 200 and 'Fuente nueva' in viewer.locator('.document-sheet').inner_text()
          and 'encounter_source' not in viewer.locator('.document-sheet').inner_text())
    printed = api.get(BASE + f'/modules/clinical/ui/viewer.php?uuid={draft_uuid}&doctor_id=1&autoprint=1')
    check('X36_PRINT', printed.status == 200 and 'Fuente nueva' in printed.text()
          and 'section:1017' not in printed.text())
    check('X38_HEADER', viewer.locator('.document-sheet .clinical-doc-head').count() == 1)
    viewer.close()
    page.locator('#modalDocumentPostEmission [data-post-emission="close"]').click()
    expect(page.locator('#modalDocumentPostEmission')).to_be_hidden()
    page.evaluate("window.dispatchEvent(new CustomEvent('mxmed:open-interconsulta-from-encounter',"
                  "{detail:{patient_id:'p_plan02ux_review',encounter_key:'enc:1021'}}))")
    if page.locator('#modalDocSessionRecovery').is_visible():
        page.locator('#doc_session_recovery_discard_btn').click()
    expect(page.locator('#modalInterconsulta')).to_be_visible()
    expect(page.locator('#ix_source_options input[value="1021"]')).to_be_checked()
    check('X05_CONSULTA_ENTRY_CONFIRMATION', page.locator('#ix_source_summary').inner_text() == 'Sin consulta vinculada'
          and page.locator('#ix_reason').input_value() == '')
    page.screenshot(path=str(SHOTS / '1440x900-consulta-entry-preselect.png'))
    page.locator('#ix_source_confirm').click()
    expect(page.locator('#ix_source_summary')).to_contain_text('Abierta')
    page.screenshot(path=str(SHOTS / '1440x900-linked-open.png'))
    check('NO_JS_ERRORS', not errors)
    browser.close()
