"""DOC-VIS01 browser and HTTP proof under consultation_flow_r1_disposable_gate.sh."""
import json
import os
import re
import struct
import subprocess
import zlib
from email.parser import BytesParser
from pathlib import Path
from urllib.parse import urlsplit

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
OUT = Path(os.environ['FLOW_R1_ARTIFACTS'])
OUT.mkdir(parents=True, exist_ok=True)
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
PATIENT = 'p_plan02ux_review'
API = '/api/clinical/index.php'
checks = {}


def check(name, condition):
    assert condition, name
    checks[name] = 'PASS'
    print('PASS ' + name, flush=True)


def sql(query):
    return subprocess.check_output(['mysql', '-N', DB, '-e', query], text=True).strip()


def pdf_bytes():
    objects = [b'<< /Type /Catalog /Pages 2 0 R >>',
               b'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
               b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >>',
               b'<< /Length 0 >>\nstream\n\nendstream']
    out = bytearray(b'%PDF-1.4\n')
    offsets = [0]
    for index, obj in enumerate(objects, 1):
        offsets.append(len(out))
        out += f'{index} 0 obj\n'.encode() + obj + b'\nendobj\n'
    xref = len(out)
    out += f'xref\n0 {len(offsets)}\n0000000000 65535 f \n'.encode()
    for offset in offsets[1:]:
        out += f'{offset:010d} 00000 n \n'.encode()
    out += f'trailer\n<< /Size {len(offsets)} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n'.encode()
    return bytes(out)


def png_bytes():
    def chunk(tag, data):
        return struct.pack('>I', len(data)) + tag + data + struct.pack('>I', zlib.crc32(tag + data) & 0xffffffff)
    raw = b''.join(b'\x00' + b'\x00\x90\xb0' * 16 for _ in range(16))
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 16, 16, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b'')


PDF, PNG = pdf_bytes(), png_bytes()
fixtures = {'docvis.pdf': PDF, 'docvis.png': PNG}
private_reads = []
# The generic disposable gate omits this empty legacy history table. The
# production schema already owns it; this fixture exists only in the QA DB.
sql('CREATE TABLE IF NOT EXISTS clinical_record_entries (entry_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, '
    'patient_id VARCHAR(64), note_type VARCHAR(64), status VARCHAR(32), payload_json LONGTEXT, '
    'subjective LONGTEXT, objective LONGTEXT, assessment LONGTEXT, plan LONGTEXT, created_at DATETIME)')


with sync_playwright() as playwright:
    browser = playwright.webkit.launch()
    api = playwright.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    page.on('dialog', lambda dialog: dialog.accept())
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))

    def proxy(route):
        request = route.request
        url = urlsplit(request.url)
        target = BASE + url.path + ('?' + url.query if url.query else '')
        if request.method == 'POST' and re.fullmatch(r'/api/clinical/index.php/doctors/1/patients/[^/]+/documents', url.path):
            # Playwright's WebKit route omits file bytes from post_data_buffer.
            # Rebuild only that QA upload from the selected fixture and retain
            # every text field and the key emitted by the real browser UI.
            raw = b'Content-Type: ' + request.headers['content-type'].encode() + b'\r\nMIME-Version: 1.0\r\n\r\n' + request.post_data_buffer
            parts = BytesParser().parsebytes(raw)
            fields = {}
            for part in parts.get_payload():
                name = part.get_param('name', header='content-disposition')
                if name == 'file':
                    filename = part.get_filename()
                    assert filename in fixtures
                    fields[name] = {'name': filename, 'mimeType': part.get_content_type(), 'buffer': fixtures[filename]}
                else:
                    fields[name] = part.get_payload(decode=True).decode()
            check('UI canonical multipart fields', set(fields) >= {'file', 'document_type', 'title', 'event_datetime', 'payload'}
                  and json.loads(fields['payload']) == {'source': 'documents_longitudinal_attachment'}
                  and 'encounter_id' not in fields and 'encounter_key' not in fields)
            response = api.post(target, multipart=fields, headers={'Idempotency-Key': request.headers['idempotency-key']})
        else:
            try:
                response = api.fetch(target, method=request.method, data=request.post_data_buffer,
                                     headers={k: v for k, v in request.headers.items()
                                              if k in ['content-type', 'accept', 'idempotency-key']}, timeout=10000)
            except Exception:
                route.abort()
                return
        if '/binary/ORIGINAL' in url.path:
            private_reads.append(response.status)
        route.fulfill(response=response)

    page.route('**/api/clinical/index.php/**', proxy)
    page.goto('http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_placeholders=clean', wait_until='domcontentloaded')
    expect(page.locator('#p-expediente')).to_have_attribute('data-patient-id', PATIENT, timeout=20000)
    page.wait_for_timeout(2500)
    return_to_file = page.get_by_role('button', name='Volver al expediente')
    if return_to_file.is_visible():
        return_to_file.click()
    tab = page.locator('.vis01-primary-navigation [data-bs-target="#t-consent"]')
    tab.click()
    module = page.locator('#t-consent .vis06-module')
    expect(page.locator('#t-consent')).to_have_class(re.compile(r'\bactive\b'))
    expect(module).to_have_attribute('data-doc-mode', 'home')
    check('V01', page.locator('#t-consent .docvis-intents').first.is_visible()
          and not page.locator('#t-consent .vis06-controls').is_visible()
          and not page.locator('#docs_catalog_panel').is_visible())

    def capture(state):
        for width, height in [(1440, 900), (1366, 768)]:
            page.set_viewport_size({'width': width, 'height': height})
            page.screenshot(path=str(OUT / f'{state}-{width}x{height}-webkit.png'), full_page=True)
            check(f'{state} horizontal fit {width}x{height}', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))

    capture('home')
    page.locator('#t-consent .docvis-intents').first.locator('button').first.focus()
    page.keyboard.press('Enter')
    check('V02', module.get_attribute('data-doc-mode') == 'create_attach')
    capture('create-attach')
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.focus()
    page.keyboard.press('Space')
    check('V03', module.get_attribute('data-doc-mode') == 'catalog'
          and page.locator('#docs_catalog_panel').is_visible()
          and not page.locator('#ci_list').is_visible()
          and page.locator('#docs_catalog_panel .docs-launcher-row:not(.d-none)').count() == 10)
    capture('catalog')
    page.locator('[data-action="documents-open-certificado"]').click()
    expect(page.locator('#modalCertificadoMedico')).to_have_class(re.compile(r'\bshow\b'))
    check('V04', True)
    page.locator('#modalCertificadoMedico .btn-close').first.click()
    page.locator('[data-doc-code="documento_libre"]').click()
    expect(page.locator('#modalDocumentosPlaceholder')).to_have_class(re.compile(r'\bshow\b'))
    check('V05', page.locator('#modalDocumentosPlaceholder').get_by_text('Documento libre').count() > 0)
    page.locator('#modalDocumentosPlaceholder .btn-close').first.click()
    page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').nth(1).click()
    check('attach mode', module.get_attribute('data-doc-mode') == 'attach')
    capture('attach')

    def upload(title, filename, mime):
        page.locator('#t-consent .docvis-attach input[name="title"]').fill(title)
        page.locator('#t-consent .docvis-attach input[name="file"]').set_input_files(
            {'name': filename, 'mimeType': mime, 'buffer': fixtures[filename]})
        page.locator('#t-consent .docvis-attach button[type="submit"]').click()
        expect(page.locator('#t-consent .docvis-success')).to_be_visible(timeout=15000)
        check(title + ' success', 'adjuntado' in page.locator('#t-consent .docvis-attach-status').inner_text().lower())
        return sql("SELECT CONCAT_WS('|',document_uuid,COALESCE(encounter_id,'NULL'),COALESCE(encounter_ref_id,'NULL')) "
                   "FROM clinical_documents WHERE title='" + title + "' ORDER BY id DESC LIMIT 1").split('|')

    open_uuid, encounter_id, encounter_ref = upload('DOCVIS open PDF', 'docvis.pdf', 'application/pdf')
    check('V08', (encounter_id, encounter_ref) == ('NULL', 'NULL'))
    consultation = api.post(f'{BASE}{API}/encounters/enc%3A1016/documents',
        multipart={'document_type': 'pdf', 'title': 'DOCVIS consultation direct',
                   'event_datetime': '2026-10-06 12:00:00',
                   'payload': json.dumps({'source': 'm7_ws04'}),
                   'file': {'name': 'docvis.pdf', 'mimeType': 'application/pdf', 'buffer': PDF}},
        headers={'Idempotency-Key': 'doc-vis01-consultation-direct'})
    check('consultation direct setup', consultation.status == 201)
    direct_uuid = consultation.json()['data']['document_uuid']
    check('consultation direct context', page.locator('[data-m7-body]').first.get_attribute('data-encounter-key') == 'enc:1016')
    read_count = len(private_reads)
    page.evaluate("(uuid) => window.dispatchEvent(new CustomEvent('mxmed:review-document', {detail:{encounterKey:'enc:1016',document:{document_uuid:uuid,has_private_binary:1}}}))", direct_uuid)
    page.wait_for_timeout(400)
    check('V13', len(private_reads) > read_count and private_reads[-1] == 200
          and module.get_attribute('data-doc-mode') == 'attach')
    capture('success')
    page.locator('#t-consent .docvis-success').get_by_role('button', name='Ver documentos').click()
    expect(page.locator('#t-consent .vis06-row').filter(has_text='DOCVIS open PDF')).to_be_visible(timeout=15000)
    check('V09', module.get_attribute('data-doc-mode') == 'consult')
    capture('consult')
    card = page.locator('#t-consent .vis06-row').filter(has_text='DOCVIS open PDF')
    card.get_by_role('button', name='Ver detalle').click()
    check('V11', page.locator('#t-consent .vis06-detail').is_visible())
    page.locator('#t-consent .vis06-detail').get_by_role('button', name='Cerrar detalle').click()
    card.get_by_role('button', name='Abrir archivo').click()
    page.wait_for_timeout(300)
    check('V10', 200 in private_reads)
    replacement = api.post(f'{BASE}{API}/documents/{open_uuid}/amendments',
        multipart={'reason': 'DOCVIS QA correction',
                   'replacement': json.dumps({'document_type': 'pdf', 'title': 'DOCVIS corrected',
                                              'event_datetime': '2026-10-06 12:00:00',
                                              'payload': {'source': 'm7_ws04_replacement'}}),
                   'file': {'name': 'docvis.pdf', 'mimeType': 'application/pdf', 'buffer': PDF}},
        headers={'Idempotency-Key': 'doc-vis01-replacement'})
    check('replacement setup', replacement.status == 201)
    page.locator('#t-consent .vis06-controls').get_by_role('button', name='Actualizar').click()
    card = page.locator('#t-consent .vis06-row').filter(has_text='DOCVIS open PDF')
    expect(card.get_by_role('button', name='Ver historial')).to_be_visible(timeout=15000)
    card.get_by_role('button', name='Ver historial').click()
    check('V12', page.locator('#t-consent .vis06-detail .vis06-version').count() == 2)

    sql("UPDATE clinical_encounters SET status='closed',closed_at=UTC_TIMESTAMP(),closed_by_user_id='review-user' WHERE encounter_id=1016")
    page.locator('.vis01-primary-navigation [data-bs-target="#t-estudios"]').click()
    check('V16', page.locator('#t-estudios .vis06-module').is_visible())
    tab.click()
    check('reentry home', module.get_attribute('data-doc-mode') == 'home')
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').nth(1).click()
    no_open_pdf = upload('DOCVIS no-open PDF', 'docvis.pdf', 'application/pdf')
    check('V06', no_open_pdf[1:] == ['NULL', 'NULL'])
    page.locator('#t-consent .docvis-success').get_by_role('button', name='Adjuntar otro archivo').click()
    no_open_image = upload('DOCVIS no-open image', 'docvis.png', 'image/png')
    check('V07', no_open_image[1:] == ['NULL', 'NULL'])
    page.locator('.vis01-primary-navigation [data-bs-target="#t-historial-atencion"]').click()
    previous = page.locator('#lon01-history .lon01-card.is-closed').first
    expect(previous).to_be_visible(timeout=15000)
    previous.click()
    expect(page.locator('#lon01-detail')).to_contain_text('DOCVIS consultation direct', timeout=15000)
    read_count = len(private_reads)
    page.locator('#lon01-detail').get_by_role('button', name='Abrir archivo privado').first.click()
    page.wait_for_timeout(400)
    check('V14', len(private_reads) > read_count and private_reads[-1] == 200
          and module.get_attribute('data-doc-mode') == 'attach')
    tab.click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').nth(1).click()
    page.locator('#t-consent .docvis-attach input[name="title"]').fill('Unsaved patient switch')
    page.evaluate("document.querySelector('#p-expediente').dataset.patientId='p_docvis_other'")
    expect(module).to_have_attribute('data-doc-mode', 'home')
    check('V15', not page.locator('#t-consent .docvis-attach input[name="title"]').input_value()
          and page.locator('#t-consent .vis06-detail').is_hidden())
    check('accessibility', not errors and page.locator('#t-consent .docvis-intents button').count() == 4
          and page.locator('#t-consent .docvis-attach input[name="file"]').get_attribute('accept') is not None)
    print('DOC_VIS01_QA=' + json.dumps({'checks': checks, 'page_errors': errors,
          'document_rows': sql('SELECT COUNT(*) FROM clinical_documents'),
          'binary_rows': sql('SELECT COUNT(*) FROM clinical_document_binaries')}, sort_keys=True), flush=True)
    browser.close()
