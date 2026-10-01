"""Disposable ORDPRINT02A writer, read-model, security, and paper-rendering gate."""
import json
import os
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE = os.environ['ORDPRINT_QA_BASE']
DB = os.environ['ORDPRINT_QA_DB']
API = BASE + '/api/clinical/index.php'
OWNER = 'PHPSESSID=ordprint-owner'
OTHER = 'PHPSESSID=ordprint-other'


def sql(query):
    return subprocess.check_output(['mysql', '-N', '-B', '-r', DB, '-e', query], text=True).strip()


def request(method, path, body=None, cookie=OWNER):
    headers = {'Accept': 'application/json'}
    if cookie:
        headers['Cookie'] = cookie
    if body is not None:
        headers.update({'Content-Type': 'application/json', 'Idempotency-Key': str(uuid.uuid4())})
    req = urllib.request.Request(API + path, headers=headers, method=method,
                                 data=None if body is None else json.dumps(body, ensure_ascii=False).encode())
    try:
        with urllib.request.urlopen(req) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as error:
        return error.code, json.load(error)


def pdf_request(order_uuid, doctor='d_ordprint', cookie=OWNER):
    target = '/modules/clinical/ui/portable-order-pdf.php?' + urllib.parse.urlencode(
        {'uuid': order_uuid, 'doctor_id': doctor})
    req = urllib.request.Request(BASE + target, headers={'Cookie': cookie} if cookie else {})
    try:
        with urllib.request.urlopen(req, timeout=40) as response:
            return response.status, response.headers, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.headers, error.read()


def create(items, patient='p_ordprint', encounter=None, indication='Control de rutina', priority='Urgente'):
    path = f'/doctors/d_ordprint/patients/{patient}/documents' if encounter is None else f'/encounters/enc%3A{encounter}/documents'
    body = {'patient_id': patient, 'document_type': 'orders', 'title': 'Orden QA', 'summary': 'Orden QA',
            'event_datetime': '2026-10-01 12:00:00',
            'payload': {'source': 'ordprint02a_qa', 'priority': priority, 'indication': indication,
                        'order_items': items, 'portable_order_snapshot': {'patient': {'name': 'FORGED'}}}}
    status, response = request('POST', path, body)
    assert status == 201 and response['ok'], (status, response)
    document_id = int(response['data']['document_id'])
    return document_id, sql(f'SELECT document_uuid FROM clinical_documents WHERE id={document_id}')


def model(document_uuid, doctor='d_ordprint', cookie=OWNER):
    return request('GET', f'/doctors/{doctor}/portable-orders/{document_uuid}', cookie=cookie)


def copy(source_id, changes=''):
    sql(f"""INSERT INTO clinical_documents
        (document_uuid,document_type,title,version,status,patient_id,appointment_id,encounter_id,encounter_ref_id,
         hospital_stay_id,care_setting,service,payload_json,rendered_text,summary,edited_flag,event_datetime,
         widget_group,printable,created_at,updated_at,generated_at,signed_at,created_by_user_id,updated_by_user_id)
        SELECT UUID(),document_type,title,version,status,patient_id,appointment_id,encounter_id,encounter_ref_id,
         hospital_stay_id,care_setting,service,payload_json,rendered_text,summary,edited_flag,event_datetime,
         widget_group,printable,created_at,updated_at,generated_at,signed_at,created_by_user_id,updated_by_user_id
        FROM clinical_documents WHERE id={source_id}; {changes}
        SELECT id,document_uuid FROM clinical_documents ORDER BY id DESC LIMIT 1""")
    last = sql('SELECT id,document_uuid FROM clinical_documents ORDER BY id DESC LIMIT 1').split('\t')
    return int(last[0]), last[1]


items = [
    {'study_category': 'LABORATORIO', 'study_display_name': 'Biometría hemática QA', 'note': 'En ayuno cuando corresponda.'},
    {'study_category': 'CARDIOVASCULAR', 'study_display_name': 'Electrocardiograma QA'},
    {'study_category': 'IMAGEN', 'study_display_name': 'Radiografía de tórax QA'},
]
document_id, document_uuid = create(items)
stored = json.loads(sql(f'SELECT payload_json FROM clinical_documents WHERE id={document_id}'))
assert stored['portable_order_snapshot_version'] == 1
assert stored['portable_order_snapshot']['patient']['name'] == 'Paciente Original QA'
assert stored['portable_order_snapshot']['physician']['name'] == 'Médica Original QA'
assert stored['portable_order_snapshot']['consultorio'] is None
assert 'FORGED' not in json.dumps(stored)
status, projected = model(document_uuid)
assert status == 200 and projected['ok'], (status, projected)
data = projected['data']
assert data['identity_snapshot_source'] == 'ISSUANCE_SNAPSHOT'
assert [row['name'] for row in data['studies']] == [row['study_display_name'] for row in items]
assert data['studies'][0]['note'] == 'En ayuno cuando corresponda.'
assert data['priority'] == 'Urgente' and data['indication'] == 'Control de rutina'
assert not any(key in data for key in ('results', 'tasks', 'prescriptions', 'consultation_narrative'))
assert data['issued_at'] == sql(f'SELECT generated_at FROM clinical_documents WHERE id={document_id}')
print('QA_V2_GENERATED_SNAPSHOT_MIXED_NOTES_PRIORITY_CONTENT=PASS')

encounter = sql("SELECT encounter_id FROM clinical_encounters WHERE patient_id='p_ordprint' LIMIT 1")
enc_id, enc_uuid = create(items[:1], encounter=encounter)
enc_snapshot = json.loads(sql(f'SELECT payload_json FROM clinical_documents WHERE id={enc_id}'))['portable_order_snapshot']
assert enc_snapshot['consultorio']['name'] == 'Consultorio Original QA', enc_snapshot
print('QA_CONSULTATION_CONSULTORIO_SNAPSHOT=PASS')

before_pdf_code, _, before_pdf = pdf_request(document_uuid)
assert before_pdf_code == 200 and before_pdf.startswith(b'%PDF-')
sql("UPDATE profiles_doctors SET display_name='Médica Nueva QA' WHERE doctor_id='d_ordprint'; "
    "UPDATE patients_patients SET display_name='Paciente Nuevo QA' WHERE patient_id='p_ordprint'; "
    "UPDATE consultorios SET titulo='Consultorio Nuevo QA' WHERE doctor_id='d_ordprint'")
status, repeated = model(document_uuid)
assert status == 200 and repeated['data']['patient']['name'] == 'Paciente Original QA'
assert repeated['data']['physician']['name'] == 'Médica Original QA'
status, repeated_consult = model(enc_uuid)
assert status == 200 and repeated_consult['data']['consultorio']['name'] == 'Consultorio Original QA'
after_pdf_code, _, after_pdf = pdf_request(document_uuid)
assert after_pdf_code == 200 and after_pdf.startswith(b'%PDF-')
if os.environ.get('ORDPRINT_QA_ARTIFACTS'):
    snapshot_dir = Path(os.environ['ORDPRINT_QA_ARTIFACTS'])
    snapshot_dir.mkdir(parents=True, exist_ok=True)
    (snapshot_dir / 'snapshot-before.pdf').write_bytes(before_pdf)
    (snapshot_dir / 'snapshot-after.pdf').write_bytes(after_pdf)
print('QA_SNAPSHOT_REPRODUCIBILITY=PASS')

signed_id, signed_uuid = copy(document_id)
sql(f"UPDATE clinical_documents SET status='signed',signed_at=UTC_TIMESTAMP() WHERE id={signed_id}")
status, signed = model(signed_uuid)
assert status == 200 and signed['data']['status'] == 'signed'
assert 'signature' not in signed['data']
print('QA_SIGNED_NO_DIGITAL_SIGNATURE_CLAIM=PASS')

legacy_id, legacy_uuid = copy(document_id)
sql(f"UPDATE clinical_documents SET payload_json=JSON_OBJECT('requested_studies',JSON_ARRAY('Estudio histórico QA')) WHERE id={legacy_id}")
status, legacy = model(legacy_uuid)
assert status == 200 and legacy['data']['identity_snapshot_source'] == 'LEGACY_RECONSTRUCTED', (status, legacy)
assert legacy['data']['studies'][0]['name'] == 'Estudio histórico QA'
assert legacy['data']['physician']['name'] == 'Médica Nueva QA'
assert json.loads(sql(f'SELECT payload_json FROM clinical_documents WHERE id={legacy_id}')) == {'requested_studies': ['Estudio histórico QA']}
print('QA_V1_LEGACY_RECONSTRUCTION_NO_BACKFILL=PASS')

replaced_id, replaced_uuid = copy(document_id)
successor_id, successor_uuid = copy(document_id)
sql(f"INSERT INTO clinical_document_revisions(original_document_id,supersedes_document_id,new_document_id,reason,author_user_id,created_at) "
    f"VALUES({replaced_id},{replaced_id},{successor_id},'QA replacement','u_ordprint',UTC_TIMESTAMP())")
assert model(replaced_uuid)[0] == 409
successor_status, successor = model(successor_uuid)
assert successor_status == 200 and successor['data']['lineage_revision'] == 2
assert successor['data']['document_version'] == 1
void_id, void_uuid = copy(document_id)
sql(f"UPDATE clinical_documents SET status='voided' WHERE id={void_id}")
assert model(void_uuid)[0] == 409
malformed_id, malformed_uuid = copy(document_id)
sql(f"UPDATE clinical_documents SET payload_json=JSON_OBJECT('requested_studies',JSON_ARRAY()) WHERE id={malformed_id}")
assert model(malformed_uuid)[0] == 409
malformed_v2_id, malformed_v2_uuid = copy(document_id)
sql(f"UPDATE clinical_documents SET payload_json=JSON_OBJECT('order_payload_version',2,'requested_studies',JSON_ARRAY('Fallback inseguro')) WHERE id={malformed_v2_id}")
assert model(malformed_v2_uuid)[0] == 409
print('QA_REPLACED_VOIDED_MALFORMED=PASS')

other_id, other_uuid = copy(document_id)
sql(f"UPDATE clinical_documents SET patient_id='p_other' WHERE id={other_id}")
assert model(document_uuid, doctor='d_other', cookie=OTHER)[0] == 404
assert model(other_uuid)[0] == 404
assert model(str(uuid.uuid4()))[0] == 404
assert model(document_uuid, cookie=None)[0] == 401
assert model(document_uuid, doctor='d_other', cookie=OWNER)[0] == 403
print('QA_AUTHORIZATION_UNRELATED_DOCTOR_PATIENT_UUID_NO_SESSION=PASS')

sql("UPDATE profiles_doctors SET professional_license=NULL,specialty_license=NULL WHERE doctor_id='d_ordprint'")
no_credential_id, no_credential_uuid = create(items[:1])
no_credential_status, no_credential = model(no_credential_uuid)
assert no_credential_status == 200 and no_credential['data']['physician']['professional_license'] is None
sql("UPDATE profiles_doctors SET professional_license='QA-123' WHERE doctor_id='d_ordprint'")
print('QA_OPTIONAL_CREDENTIAL_ABSENCE=PASS')

sql("UPDATE profiles_doctors SET display_name=NULL WHERE doctor_id='d_ordprint'")
missing_doctor_id, missing_doctor_uuid = create(items[:1])
assert model(missing_doctor_uuid)[0] == 409
sql("UPDATE profiles_doctors SET display_name='Médica Nueva QA' WHERE doctor_id='d_ordprint'; "
    "UPDATE patients_patients SET display_name='' WHERE patient_id='p_ordprint'")
missing_patient_id, missing_patient_uuid = create(items[:1])
assert model(missing_patient_uuid)[0] == 409
sql("UPDATE patients_patients SET display_name='Paciente Nuevo QA' WHERE patient_id='p_ordprint'")
print('QA_MISSING_REQUIRED_IDENTITIES_FAIL_CLOSED_WITHOUT_BLOCKING_ORDER_WRITER=PASS')

def page_request(target, cookie=OWNER):
    req = urllib.request.Request(BASE + target, headers={'Cookie': cookie} if cookie else {})
    try:
        with urllib.request.urlopen(req) as response:
            return response.status, response.headers, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.headers, error.read().decode()


page_path = '/modules/clinical/ui/portable-order.php?' + urllib.parse.urlencode({'uuid': document_uuid, 'doctor_id': 'd_ordprint'})
status, headers, html = page_request(page_path)
assert status == 200 and 'no-store' in headers.get('Cache-Control', '')
assert 'Biometría hemática QA' in html and 'Firma del médico' in html
assert '03/02/1985' in html and '02/02/1985' not in html
assert 'FORGED' not in html and 'Paciente Nuevo QA' not in html
assert 'México Médico · MXMED' not in html and '<div class="brand">México Médico</div>' in html
assert 'Referencia MXMED:' in html and 'Versión documental' not in html
assert document_uuid not in html and 'Copia histórica reconstruida' not in html
assert not any(value in html for value in ('related_order_item_ids', 'clinical_patient_tasks', 'Descargar PDF', 'QR'))
legacy_path = '/modules/clinical/ui/portable-order.php?' + urllib.parse.urlencode({'uuid': legacy_uuid, 'doctor_id': 'd_ordprint'})
assert 'Copia histórica reconstruida' in page_request(legacy_path)[2]
assert page_request(page_path, None)[0] == 403
assert page_request('/modules/clinical/ui/portable-order.php?' + urllib.parse.urlencode({'uuid': void_uuid, 'doctor_id': 'd_ordprint'}))[0] == 403
print('QA_DEDICATED_RENDERER_SECURITY_AND_LEGACY_NOTICE=PASS')

for order_uuid in (document_uuid, signed_uuid, legacy_uuid):
    code, headers, body = pdf_request(order_uuid)
    assert code == 200 and headers.get_content_type() == 'application/pdf', (code, headers, body[:120])
    assert body.startswith(b'%PDF-') and body.rstrip().endswith(b'%%EOF')
    assert int(headers['Content-Length']) == len(body)
    assert headers['Content-Disposition'].startswith('attachment; filename="Orden-estudios-')
    assert order_uuid not in headers['Content-Disposition']
assert pdf_request(document_uuid, doctor='d_other', cookie=OTHER)[0] == 403
assert pdf_request(other_uuid)[0] == 403
assert pdf_request(document_uuid, cookie=None)[0] == 403
assert pdf_request(str(uuid.uuid4()))[0] == 403
for denied_uuid in (replaced_uuid, void_uuid, malformed_uuid, malformed_v2_uuid,
                    missing_doctor_uuid, missing_patient_uuid):
    assert pdf_request(denied_uuid)[0] == 403, denied_uuid
print('QA_PDF_AUTH_GENERATED_SIGNED_LEGACY_AND_FAIL_CLOSED_STATES=PASS')

ten_id, ten_uuid = create([{'study_category': 'LABORATORIO', 'study_display_name': f'Estudio de control {i}'} for i in range(10)])
many_id, many_uuid = create([{'study_category': 'LABORATORIO', 'study_display_name': f'Estudio extenso {i} ' + ('Descripción clínica ' * 8),
                             'note': ('Nota particular para este estudio. ' * 12)} for i in range(55)],
                            indication='Indicación clínica extensa. ' * 80)
assert model(ten_uuid)[0] == 200 and model(many_uuid)[0] == 200

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    context.add_cookies([{'name': 'PHPSESSID', 'value': 'ordprint-owner', 'url': BASE}])
    page = context.new_page()
    for width, height in ((1440, 810), (1440, 900), (1366, 768), (390, 844)):
        page.set_viewport_size({'width': width, 'height': height})
        page.goto(BASE + page_path)
        assert page.locator('.paper').is_visible()
        assert page.locator('.toolbar button').is_visible()
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), (width, height)
    print('QA_DESKTOP_AND_MOBILE_ENTRY=PASS')
    for identity, order_uuid, expected_min in [('one', enc_uuid, 1), ('ten', ten_uuid, 1), ('many', many_uuid, 2)]:
        target = '/modules/clinical/ui/portable-order.php?' + urllib.parse.urlencode({'uuid': order_uuid, 'doctor_id': 'd_ordprint'})
        page.goto(BASE + target)
        assert page.locator('.study').count() == {'one': 1, 'ten': 10, 'many': 55}[identity]
        pdf = page.pdf(prefer_css_page_size=True, print_background=False)
        if os.environ.get('ORDPRINT_QA_ARTIFACTS'):
            artifact_dir = Path(os.environ['ORDPRINT_QA_ARTIFACTS'])
            artifact_dir.mkdir(parents=True, exist_ok=True)
            (artifact_dir / f'{identity}.pdf').write_bytes(pdf)
            if identity == 'one':
                page.screenshot(path=str(artifact_dir / 'one-screen.png'), full_page=True)
        pages = len(re.findall(rb'/Type\s*/Page\b', pdf))
        assert pages >= expected_min and len(pdf) > 1000, (identity, pages, len(pdf))
        pdf_code, pdf_headers, pdf_body = pdf_request(order_uuid)
        assert pdf_code == 200 and pdf_headers.get_content_type() == 'application/pdf'
        pdf_pages = len(re.findall(rb'/Type\s*/Page\b', pdf_body))
        assert pdf_pages >= expected_min and abs(pdf_pages - pages) <= 1, (identity, pages, pdf_pages)
        if os.environ.get('ORDPRINT_QA_ARTIFACTS'):
            (artifact_dir / f'{identity}-download.pdf').write_bytes(pdf_body)
        if identity == 'many':
            assert page.locator('.study').last.is_visible()
            assert page.locator('.signature').is_visible()
            assert page.locator('.reference').is_visible()
        print(f'QA_{identity.upper()}_STUDY_PAPER_AND_DOWNLOAD=PASS print_pages={pages} pdf_pages={pdf_pages}')
    entry = context.new_page()
    entry.route(BASE + '/', lambda route: route.fulfill(status=200, content_type='text/html', body='<html><body></body></html>'))
    entry.goto(BASE + '/')
    entry.set_content('<div id="t-estudios"><div data-est-open-modal></div><div data-est-order-block></div><div class="est-orders-list"></div></div>')
    entry.evaluate("window.mxmedStore={doctor_id:'d_ordprint'};window.resolveDoctorId=()=> 'd_ordprint';window.bootstrap={Modal:class {constructor(el){this.el=el}show(){this.el.style.display='block'}static getOrCreateInstance(el){return new this(el)}}};window.__portableOpened='';window.__portableDownloaded='';window.open=(url)=>{window.__portableOpened=url;};HTMLAnchorElement.prototype.click=function(){window.__portableDownloaded=this.href;}")
    entry.add_script_tag(path=str(Path(__file__).resolve().parents[3] / 'assets/js/app.js'))
    assert entry.evaluate('typeof window.mxmedOpenDiagnosticDocumentDetail') == 'function'
    entry.evaluate('(uuid)=>window.mxmedOpenDiagnosticDocumentDetail(uuid)', enc_uuid)
    assert not entry.locator('[data-est-order-print-disabled]').is_disabled()
    assert not entry.locator('[data-est-order-pdf-disabled]').is_disabled()
    entry.locator('[data-est-order-print-disabled]').click()
    assert '/modules/clinical/ui/portable-order.php?' in entry.evaluate('window.__portableOpened')
    assert enc_uuid in entry.evaluate('window.__portableOpened')
    entry.locator('[data-est-order-pdf-disabled]').click()
    assert '/modules/clinical/ui/portable-order-pdf.php?' in entry.evaluate('window.__portableDownloaded')
    assert enc_uuid in entry.evaluate('window.__portableDownloaded')
    entry.evaluate('(uuid)=>window.mxmedOpenDiagnosticDocumentDetail(uuid)', void_uuid)
    assert entry.locator('[data-est-order-print-disabled]').is_disabled()
    assert entry.locator('[data-est-order-pdf-disabled]').is_disabled()
    print('QA_CONSULTATION_SHARED_PRINT_AND_PDF_ENTRIES_AND_VOIDED_DENIAL=PASS')
    browser.close()
