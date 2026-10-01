"""TAX03C HTTP writer and reader checks against the disposable DB in tax03c_disposable_gate.sh."""
import json
import os
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = os.environ['TAX03C_QA_BASE'] + '/api/clinical/index.php'
DB = os.environ['TAX03C_QA_DB']
COOKIE = 'PHPSESSID=tax03c-owner'
DOCTOR = 'd_tax03c'
OPEN = 'p_tax03c_open'
PLAIN = 'p_tax03c_plain'
ENCOUNTER = int(os.environ['TAX03C_QA_ENCOUNTER'])


def sql(query):
    return subprocess.check_output(['mysql', '-N', '-B', '-r', DB, '-e', query], text=True).strip()


def request(method, path, data=None, key=None):
    headers = {'Cookie': COOKIE, 'Accept': 'application/json'}
    if data is not None:
        headers['Content-Type'] = 'application/json'
    if key is not None:
        headers['Idempotency-Key'] = key
    req = urllib.request.Request(BASE + path, data=None if data is None else json.dumps(data, ensure_ascii=False).encode(),
                                 headers=headers, method=method)
    try:
        with urllib.request.urlopen(req) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as error:
        return error.code, json.load(error)


def catalog(search='', category=''):
    query = urllib.parse.urlencode({'search': search, 'category': category})
    status, body = request('GET', f'/doctors/{DOCTOR}/study-types?{query}')
    assert status == 200 and body['ok'], (status, body)
    return body['data']


cbc = next(row for row in catalog('CBC', 'LABORATORIO')['items'] if row['study_type_key'] == 'cbc')
glucose = next(row for row in catalog('Glucosa', 'LABORATORIO')['items'] if row['study_type_key'] == 'glucose')
image = next(row for row in catalog('RX Tórax', 'IMAGEN')['items'] if row['study_type_key'] == 'rx_chest')
assert cbc['study_type_key'] == 'cbc' and glucose['study_type_key'] == 'glucose'
assert image['study_type_key'] == 'rx_chest'
alias = catalog('Mesa inclinada', 'CARDIOVASCULAR')['items'][0]
assert alias['study_type_key'] == 'tilt_table'
assert not catalog('Estudio imposible QA')['items']
facets = catalog()['categories']
assert len(facets) == 13 and any(row['category_key'] == 'DENTAL' for row in facets)
print('QA_CATALOG_SEARCH_ALIAS_FILTER_FACETS=PASS')


def canonical(row):
    return {'study_type_id': row['study_type_id'], 'study_type_key': row['study_type_key']}


def create(path, kind, items, name, key=None, extra=None):
    payload = {'source': 'tax03c_qa', 'priority': 'Urgente', 'indication': 'Indicación de prueba',
               'order_area': 'Estudios diagnósticos', 'order_items': items}
    body = {'document_type': kind, 'patient_id': OPEN if 'p_tax03c_open' in path else PLAIN,
            'title': name, 'summary': 'QA orden', 'event_datetime': '2026-09-30 12:00:00', 'payload': payload}
    if extra:
        body.update(extra)
    return request('POST', path, body, key or str(uuid.uuid4()))


open_path = f'/doctors/{DOCTOR}/patients/{OPEN}/documents'
plain_path = f'/doctors/{DOCTOR}/patients/{PLAIN}/documents'
enc_path = f'/encounters/{urllib.parse.quote("enc:" + str(ENCOUNTER), safe="")}/documents'

mixed = [canonical(cbc), {'study_category': 'DENTAL', 'study_display_name': 'CBCT dental QA', 'note': 'Especialidad odontológica'}, canonical(image)]
key = str(uuid.uuid4())
status, created = create(open_path, 'orders', mixed, 'TAX03C mixed', key)
assert status == 201 and created['ok'], (status, created)
doc_id = int(created['data']['document_id'])
stored = json.loads(sql(f'SELECT payload_json FROM clinical_documents WHERE id={doc_id}'))
assert stored['order_payload_version'] == 2 and len(stored['order_items']) == 3, stored
assert [row['sequence'] for row in stored['order_items']] == [1, 2, 3]
assert [row['study_category'] for row in stored['order_items']] == ['LABORATORIO', 'DENTAL', 'IMAGEN']
assert stored['order_items'][0]['study_type_id'] == cbc['study_type_id']
assert stored['order_items'][0]['study_display_name'] == cbc['display_name_es']
assert stored['order_items'][1]['study_type_id'] is None and stored['order_items'][1]['note'] == 'Especialidad odontológica'
assert stored['priority'] == 'Urgente' and stored['indication'] == 'Indicación de prueba'
assert all(uuid.UUID(row['order_item_id']) for row in stored['order_items'])
assert sql(f'SELECT CONCAT(COALESCE(encounter_ref_id,"NULL"),"|",COALESCE(encounter_id,"NULL"),"|",COALESCE(appointment_id,"NULL")) FROM clinical_documents WHERE id={doc_id}') == 'NULL|NULL|NULL'
status, replay = create(open_path, 'orders', mixed, 'TAX03C mixed', key)
assert status == 200 and replay['ok'] and replay['meta']['idempotency_replay'] is True
assert int(replay['data']['document_id']) == doc_id
print('QA_GENERAL_WITH_OPEN_MIXED_CANONICAL_CUSTOM_IDEMPOTENCY=PASS')

status, lab = create(plain_path, 'lab_order', [canonical(cbc), canonical(glucose)], 'TAX03C lab')
assert status == 201 and lab['ok'], (status, lab)
lab_id = int(lab['data']['document_id'])
assert sql(f'SELECT CONCAT(document_type,"|",COALESCE(encounter_ref_id,"NULL")) FROM clinical_documents WHERE id={lab_id}') == 'lab_order|NULL'
status, imaging = create(plain_path, 'imaging_order', [canonical(image)], 'TAX03C imaging')
assert status == 201 and imaging['ok'], (status, imaging)
print('QA_GENERAL_WITHOUT_OPEN_ALL_LAB_ALL_IMAGING=PASS')

status, encounter = create(enc_path, 'orders', [canonical(cbc), canonical(image)], 'TAX03C consultation',
                           extra={'patient_id': OPEN})
assert status == 201 and encounter['ok'], (status, encounter)
enc_id = int(encounter['data']['document_id'])
assert sql(f'SELECT encounter_ref_id FROM clinical_documents WHERE id={enc_id}') == str(ENCOUNTER)
assert sql("SELECT COUNT(*) FROM clinical_encounters WHERE patient_id='p_tax03c_open'") == '1'
print('QA_CONSULTATION_ENCOUNTER_SCOPE_NO_DUPLICATE=PASS')

status, forbidden = create(open_path, 'orders', [canonical(cbc)], 'TAX03C forbidden', extra={'encounter_id': str(ENCOUNTER)})
assert status == 400 and not forbidden['ok'], (status, forbidden)
status, wrong_doctor = create(open_path, 'orders', [canonical(cbc)], 'TAX03C wrong doctor', extra={'doctor_id': 'someone_else'})
assert status == 400 and not wrong_doctor['ok'], (status, wrong_doctor)
status, unknown = create(open_path, 'orders', [{'study_type_id': 999999999}], 'TAX03C unknown')
assert status == 400 and not unknown['ok'], (status, unknown)
assert sql('SELECT COUNT(*) FROM clinical_study_types') == '183'
print('QA_SCOPE_FORGERY_AND_UNKNOWN_CATALOG_REJECTED_NO_CATALOG_MUTATION=PASS')

status, projection = request('GET', open_path + '?orders_results_mode=1&filter=orders&search=TAX03C%20mixed')
assert status == 200 and projection['ok'], (status, projection)
row = next(row['order'] for row in projection['data']['items'] if row['order']['id'] == doc_id)
assert row['order_payload_version'] == 2 and len(row['order_items']) == 3, row
print('QA_OR02B_V2_PROJECTION=PASS')
