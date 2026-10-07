"""CONS-PREV02-R1 disposable browser and canonical-writer proof."""
import base64
import json
import os
import re
import subprocess
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
ROOT = Path(os.environ['FLOW_R1_QA_ROOT'])
OUT = Path(os.environ.get('CONS_PREV02_SCREENSHOTS', '/tmp/cons-prev02-screenshots'))
OUT.mkdir(parents=True, exist_ok=True)
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
with (Path(__file__).resolve().parents[1] / 'db/migrations/2026_10_06_29_consent_templates.sql').open('rb') as migration:
    subprocess.run(['mysql', DB], stdin=migration, check=True)


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', DB, '-e', query], text=True).strip()


def check(code, condition):
    assert condition, code
    print(f'{code}=PASS', flush=True)


def count_docs():
    return int(sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado'"))


def capture(page, name):
    for width, height in [(1366, 768), (1440, 900)]:
        page.set_viewport_size({'width': width, 'height': height})
        page.screenshot(path=str(OUT / f'{name}-{width}x{height}.png'))
        check(f'{name}_{width}x{height}_FIT', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    page.set_viewport_size({'width': 1440, 'height': 900})


def draw_signature(page):
    canvas = page.locator('#ci_signature_canvas')
    canvas.scroll_into_view_if_needed()
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 25, box['y'] + 25)
    page.mouse.down()
    page.mouse.move(box['x'] + 105, box['y'] + 55, steps=8)
    page.mouse.up()


with sync_playwright() as pw:
    api = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    browser = pw.webkit.launch()
    page = browser.new_page(viewport={'width': 1440, 'height': 900},
                            extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    errors = []
    document_posts = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.on('request', lambda req: document_posts.append(req.post_data)
            if req.method == 'POST' and '/patients/p_plan02ux_review/documents' in req.url else None)
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    expect(page.locator('#p-expediente')).to_have_attribute('data-patient-id', 'p_plan02ux_review')
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()

    def open_blank():
        page.locator('[data-action="documents-open-consent"]').click()
        expect(page.locator('#modalConsentTemplateFlow')).to_be_visible()
        page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').click()
        expect(page.locator('#modalConsentimientoInformado')).to_be_visible()

    open_blank()
    check('T02', count_docs() == 0 and not page.locator('#ci_review_html').get_attribute('data-uuid'))
    check('DIRECT_EMIT_HIDDEN', page.locator('#ci_emit').is_hidden())
    check('CAPTURE_MODES', page.locator('#ci_mode_guided').is_visible()
          and page.locator('#ci_mode_full').inner_text() == 'Captura completa')
    page.locator('#ci_next').click()
    page.locator('#ci_title').fill('Procedimiento de prueba')
    page.locator('#ci_procedimiento').fill('Descripción original')
    page.locator('#ci_confirm_informed').check()
    page.evaluate("document.querySelector('#ci_emit').click()")
    check('DIRECT_EMIT_GUARD', count_docs() == 0 and len(document_posts) == 0
          and 'Revísalo nuevamente' in page.locator('#ci_wizard_notice').inner_text())
    page.locator('#ci_preview').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    check('REVIEW_CLEARS_OLD_WARNING', page.locator('#ci_wizard_notice').is_hidden())
    check('PRE_REVIEW_CONFIRMATION_CLEARED', not page.locator('#ci_confirm_informed').is_checked())
    check('T01', 'Descripción original' in page.locator('#ci_review_html').inner_text())
    check('T03', count_docs() == 0 and len(document_posts) == 0)
    check('T04', 'CONSENTIMIENTO INFORMADO' in page.locator('#ci_review_html').inner_text()
          and 'Declaración legal integrada' in page.locator('#ci_review_html').inner_text()
          and page.locator('#ci_review_html article').count() == 1)
    capture(page, 'A-content-preview')
    page.locator('#ci_review_edit_button').click()
    expect(page.locator('#ci_review_edit')).to_be_visible()
    protected = ('patient_id','patient_name','doctor_name','appointment_id','encounter_key',
                 'firmante_nombre','testigo_1_nombre','signature','identity_files','rendered_text')
    check('T11', page.locator('#ci_review_edit [contenteditable]').count() == 0
          and all(page.locator(f'#ci_review_edit [data-consent-review-key="{key}"]').count() == 0
                  for key in protected))
    values = {
        'title': 'Procedimiento corregido', 'procedimiento': 'Técnica revisada QA',
        'objetivo': 'Objetivo clínico QA', 'riesgos': 'Riesgos generales QA',
        'risk_comunes': 'Equimosis QA', 'risk_poco_frecuentes': 'Infección QA',
        'risk_raros_graves': 'Complicación grave QA',
        'beneficios_esperados': 'Beneficio QA', 'alternativas': 'Alternativa QA',
        'consecuencias_no_aceptar': 'Consecuencia QA'
    }
    for key, value in values.items():
        page.locator(f'#ci_review_edit_{key}').fill(value)
    capture(page, 'B-structured-edit')
    page.locator('#ci_review_apply').click()
    expect(page.locator('#ci_review_edit')).to_be_hidden()
    html_text = page.locator('#ci_review_html').inner_text()
    for code, key in [('T05','title'),('T06','procedimiento'),('T07','objetivo'),('T08','risk_comunes'),
                      ('T09','beneficios_esperados'),('T10','alternativas')]:
        check(code, values[key] in html_text and page.locator('#ci_' + ('title' if key == 'title' else key if key != 'risk_comunes' else 'risk_common')).input_value() == values[key])
    check('STRUCTURED_RISKS_ALL_LEVELS', all(values[key] in html_text
          for key in ('risk_comunes','risk_poco_frecuentes','risk_raros_graves')))

    # Save-as-template reads the same state.form edited from the preview.
    page.locator('#ci_save_as_template').click()
    expect(page.locator('#ci_save_template_overlay')).to_be_visible()
    page.locator('#ci_save_template_name').fill('Plantilla revisión QA')
    page.locator('#ci_save_template_confirm').click()
    expect(page.locator('#ci_save_template_status')).to_have_text('Plantilla guardada')
    check('T23', sql("SELECT JSON_UNQUOTE(JSON_EXTRACT(content_json,'$.title')) FROM clinical_consent_templates WHERE template_name='Plantilla revisión QA'") == values['title'])

    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    check('T12', page.locator('#ci_signature_canvas').is_visible()
          and page.locator('#ci_review_confirm_informed').is_visible())
    capture(page, 'C-signatures')
    page.locator('#ci_review_confirm_informed').check()
    draw_signature(page)
    page.locator('#ci_signatures_continue').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    expect(page.locator('#ci_review_heading')).to_have_text('Vista previa · Revisión final')
    check('T13', page.locator('#ci_review_html').inner_text().count('Paciente / responsable') == 1)
    check('T14', page.locator('#ci_review_html article').count() == 1
          and 'Técnica revisada QA' in page.locator('#ci_review_html').inner_text())
    capture(page, 'D-final-review')

    page.locator('#ci_review_back_edit').click()
    page.locator('#ci_procedimiento').fill('Técnica revisada tras firma QA')
    check('T15', not page.locator('#ci_confirm_informed').is_checked()
          and page.locator('#ci_signature_status').inner_text() == 'Sin firma')
    page.locator('#ci_preview').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    check('T16', not page.locator('#ci_review_confirm_informed').is_checked())
    page.locator('#ci_review_confirm_informed').check()
    draw_signature(page)
    page.locator('#ci_signatures_continue').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    expect(page.locator('#ci_review_heading')).to_have_text('Vista previa · Revisión final')

    # Attachment changes revoke the final review. An unsaved file is labeled pending.
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lN8AAAAASUVORK5CYII=')
    page.locator('#ci_identity_files').set_input_files({'name':'identidad-qa.png','mimeType':'image/png','buffer':png})
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    before_posts = len(document_posts)
    page.evaluate("document.querySelector('#ci_emit').click()")
    check('T17', len(document_posts) == before_posts and 'Revísalo nuevamente' in page.locator('#ci_wizard_notice').inner_text())
    capture(page, 'E-stale-review-warning')
    page.locator('#ci_signatures_continue').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    expect(page.locator('#ci_review_heading')).to_have_text('Vista previa · Revisión final')
    check('PENDING_ATTACHMENT', 'identidad-qa.png' in page.locator('#ci_review_attachments').inner_text()
          and 'pendiente de guardar' in page.locator('#ci_review_attachments').inner_text()
          and 'identidad-qa.png' not in page.locator('#ci_review_html').inner_html())
    capture(page, 'G-pending-attachment')
    page.locator('#ci_review_back_signatures').click()
    page.locator('#ci_identity_files').set_input_files([])
    page.locator('#ci_signatures_continue').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    expect(page.locator('#ci_review_heading')).to_have_text('Vista previa · Revisión final')
    page.locator('#ci_review_back_signatures').click()
    page.locator('#ci_review_firmante_nombre').fill('Firmante corregido QA')
    page.evaluate("document.querySelector('#ci_emit').click()")
    check('T19', len(document_posts) == before_posts and count_docs() == 0)
    page.locator('#ci_signatures_continue').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    expect(page.locator('#ci_review_heading')).to_have_text('Vista previa · Revisión final')
    reviewed_html = page.locator('#ci_review_html').inner_html()
    with page.expect_response(lambda response: response.request.method == 'POST'
                              and '/patients/p_plan02ux_review/documents' in response.url) as emitted:
        page.locator('#ci_emit').click()
    check('T18', emitted.value.status in (200, 201))
    page.wait_for_function("document.querySelector('#ci_action_feedback')?.textContent?.includes('Consentimiento emitido')", timeout=20000)
    expect(page.locator('#modalConsentimientoInformado')).to_be_hidden()
    capture(page, 'F-emission-confirmation')
    posted_html = json.loads(document_posts[-1])['payload']['frozen_snapshot']['html']
    stored_html = sql("SELECT JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.frozen_snapshot.html')) FROM clinical_documents WHERE document_type='consentimiento_informado' LIMIT 1")
    browser_html = page.evaluate("html=>{const el=document.createElement('div');el.innerHTML=html;return el.innerHTML}", posted_html)
    check('T20', count_docs() == 1 and stored_html == posted_html and reviewed_html == browser_html)

    # A personal template remains available and can still create a stable draft.
    page.locator('[data-action="documents-open-consent"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    row = page.locator('#ci_tpl_selector_list .ci-template-row').filter(has_text='Plantilla revisión QA')
    expect(row).to_be_visible()
    row.locator('[data-tpl-action="use"]').click()
    expect(page.locator('#modalConsentimientoInformado')).to_be_visible()
    check('T24', page.locator('#ci_title').input_value() == values['title'])
    page.locator('#ci_next').click()
    page.locator('#ci_save').click()
    page.wait_for_function("document.querySelector('#ci_action_feedback')?.textContent?.includes('Borrador guardado')", timeout=20000)
    check('T21', int(sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft'")) == 1)
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentDraftPrompt')).to_be_visible()
    page.locator('#modalConsentDraftPrompt [data-draft-ref]').first.click()
    expect(page.locator('#modalConsentimientoInformado')).to_be_visible()
    check('T22', page.locator('#ci_title').input_value() == values['title'] and page.locator('#ci_emit').is_hidden())
    draft_uuid = sql("SELECT document_uuid FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft' LIMIT 1")
    page.locator('#ci_mode_full').click()
    expect(page.locator('#ci_full_view')).to_be_visible()
    page.locator('#ci_preview').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    check('FULL_CAPTURE_PREVIEW', draft_uuid == sql("SELECT document_uuid FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft' LIMIT 1")
          and count_docs() == 2 and page.locator('#ci_emit').is_hidden())
    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    page.locator('#ci_review_confirm_informed').check()
    page.locator('#ci_signatures_continue').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    with page.expect_response(lambda response: response.request.method == 'POST'
                              and '/patients/p_plan02ux_review/documents' in response.url) as promoted:
        page.locator('#ci_emit').click()
    check('DRAFT_TO_GENERATED_SAME_UUID', promoted.value.status in (200, 201)
          and int(sql(f"SELECT COUNT(*) FROM clinical_documents WHERE document_uuid='{draft_uuid}' AND status='generated'")) == 1
          and count_docs() == 2)
    legacy = api.post(BASE + '/api/clinical-documents.php?action=save', data={'context':{'patient_id':'p_plan02ux_review'}})
    check('T25', legacy.status == 409 and legacy.json().get('error') == 'M6_LEGACY_WRITE_BLOCKED')
    check('NO_BROWSER_ERRORS', errors == [])
    orphan_count = int(sql("SELECT COUNT(*) FROM clinical_binary_uploads WHERE storage_state IN ('ORPHANED','RECONCILIATION_REQUIRED')"))
    private_files = sum(1 for path in (ROOT / 'private').rglob('*') if path.is_file()) if (ROOT / 'private').exists() else 0
    check('NO_ORPHAN_ATTACHMENTS', orphan_count == 0)
    print('DOCUMENT_ROWS_CREATED_BY_PREVIEW=0', flush=True)
    print(f'ORPHAN_ATTACHMENT_COUNT={orphan_count}', flush=True)
    print(f'PRIVATE_QA_FILE_COUNT_BEFORE_CLEANUP={private_files}', flush=True)
    print(f'QA_DB={DB}', flush=True)
    print('QA_ROOT=' + str(ROOT), flush=True)
    print('QA_PORT=' + BASE.rsplit(':', 1)[-1], flush=True)
    print('SCREENSHOTS=' + str(OUT), flush=True)
    browser.close()
