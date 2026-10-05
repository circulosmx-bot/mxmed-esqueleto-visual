"""Authenticated cross-family composition, portable order and result QA on a disposable DB."""
import copy, html, json, os, subprocess, tempfile, urllib.error, urllib.parse, urllib.request, uuid
from pathlib import Path

BASE = os.environ['STUDY_COVERAGE02_QA_BASE']; DB = os.environ['STUDY_COVERAGE02_QA_DB']
assert DB.startswith('studycoverage02_qa_')
ROOT = Path(__file__).resolve().parents[3]
ROUTING = json.loads((ROOT/'modules/clinical/catalog/study_order_routing_v1.json').read_text())
DOCTOR = 'd_studycoverage02'; PATIENT = 'p_studycoverage02'; COOKIE = 'PHPSESSID=studycoverage02-owner'
API = f'/api/clinical/index.php/doctors/{DOCTOR}/patients/{PATIENT}'

def sql(query):
    return subprocess.check_output(['mysql', '-N', '-B', '-r', DB, '-e', query], text=True).strip()

def request(path, body=None, key=None, content_type='application/json'):
    headers = {'Cookie': COOKIE, 'Accept': 'application/json'}
    data = None
    if body is not None:
        data = json.dumps(body, ensure_ascii=False).encode()
        headers.update({'Content-Type': content_type, 'Idempotency-Key': key or body.get('order_composition_batch_uuid', str(uuid.uuid4()))})
    try:
        with urllib.request.urlopen(urllib.request.Request(BASE+path, data=data, headers=headers), timeout=120) as response:
            return response.status, response.headers.get_content_type(), response.read()
    except urllib.error.HTTPError as response:
        return response.code, response.headers.get_content_type(), response.read()

def batch(orders):
    return {'order_composition_batch_uuid': str(uuid.uuid4()), 'order_routing_version': ROUTING['version'], 'orders': orders}

def order(items):
    group = ROUTING['studies'][items[0]['study_type_key']]
    assert all(ROUTING['studies'][item['study_type_key']] == group for item in items)
    return {'order_routing_group_key': group, 'priority': 'Rutinaria', 'indication': 'Cierre QA entre familias', 'order_items': items}

def issue(body):
    status, mime, raw = request(API+'/orders/batch', body)
    return status, json.loads(raw) if mime == 'application/json' else raw

def saved(doc):
    return json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(doc['document_id'])}"))

def counts():
    return tuple(int(sql(f'SELECT COUNT(*) FROM {table}')) for table in ('clinical_documents', 'clinical_idempotency_requests'))

def upload_result(doc, item_id, title, document_type):
    fields = {'patient_id': PATIENT, 'document_type': document_type, 'title': title, 'event_datetime': '2026-10-05 12:00:00', 'provenance': 'QA desechable', 'payload': {'source': 'res02a_linked_result', 'related_order_document_uuid': doc['document_uuid'], 'related_order_item_ids': [item_id], 'provenance': 'QA desechable'}}
    boundary = 'qa'+uuid.uuid4().hex; parts = []
    for name, value in fields.items():
        if isinstance(value, (dict, list)): value = json.dumps(value, ensure_ascii=False)
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="result.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()+b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    headers = {'Cookie': COOKIE, 'Accept': 'application/json', 'Content-Type': 'multipart/form-data; boundary='+boundary, 'Idempotency-Key': str(uuid.uuid4())}
    try:
        with urllib.request.urlopen(urllib.request.Request(BASE+API+'/documents', data=b''.join(parts), headers=headers), timeout=90) as response:
            assert response.status == 201
            created = json.load(response)['data']
    except urllib.error.HTTPError as error:
        raise AssertionError((document_type, title, error.code, error.read().decode())) from error
    stored = json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(created['document_id'])}"))
    assert stored['related_order_document_uuid'] == doc['document_uuid'] and stored['related_order_item_ids'] == [item_id], stored

def portable(doc, expected):
    query = urllib.parse.urlencode({'uuid': doc['document_uuid'], 'doctor_id': DOCTOR})
    status, _, raw = request('/modules/clinical/ui/portable-order.php?'+query)
    assert status == 200, (status, raw[:150])
    markup = html.unescape(raw.decode())
    assert all(label in markup for label in expected), (expected, markup[-1400:])
    status, mime, raw = request('/modules/clinical/ui/portable-order-pdf.php?'+query)
    assert status == 200 and mime == 'application/pdf' and raw.startswith(b'%PDF-') and b'%%EOF' in raw[-1024:], (status, mime, raw[:100])
    with tempfile.TemporaryDirectory(prefix='studycoverage02-pdf-') as directory:
        pdf = Path(directory)/'order.pdf'; pdf.write_bytes(raw)
        text = subprocess.check_output(['swift', '-e', 'import Foundation; import PDFKit; let d=PDFDocument(url: URL(fileURLWithPath: CommandLine.arguments.last!))!; print(d.string ?? "")', str(pdf)], text=True)
        assert all(label in text for label in expected), (expected, text[:1200])
        assert not any(token in text for token in ('HIGH_RESOLUTION', 'MAXILLARY', 'MANDIBULAR', 'JOINT_STANDARD', 'PATHOLOGY_CYTOLOGY', 'preset_qs3', 'preset_hepatic'))

arch = lambda value: {'contract_version': 2, 'location_type': 'ARCH_LOCATION', 'selection_mode': 'ARCH', 'dentition_mode': 'PERMANENT', 'arch_key': value}
cases = [
    ([{'study_type_key': 'panel_quimica_6'}, {'study_type_key': 'arterial_blood_gas'}], 'Química sanguínea', 'lab_result'),
    ([{'study_type_key': 'histopath_biopsy', 'pathology_order_parameters': {'version': 1, 'specimens': [{'material_key': 'TISSUE', 'anatomic_site_text': 'Sitio QA'}]}}], 'Biopsia', 'lab_result'),
    ([{'study_type_key': 'rx_hip', 'imaging_order_parameters': {'version': 1, 'laterality': 'RIGHT', 'xray_view_preset': 'JOINT_STANDARD'}}], 'Radiografía de cadera', 'imaging_result'),
    ([{'study_type_key': 'dental_occlusal_xray', 'dental_location': arch('MAXILLARY')}, {'study_type_key': 'dental_occlusal_xray', 'dental_location': arch('MANDIBULAR')}], 'Radiografía oclusal', 'imaging_result'),
    ([{'study_type_key': 'colposcopy_diagnostic'}], 'Colposcopia diagnóstica', 'lab_result'),
    ([{'study_type_key': 'esophageal_manometry', 'functional_order_parameters': {'version': 1, 'gi_technique': 'HIGH_RESOLUTION'}}], 'Manometría esofágica', 'lab_result'),
]
assert sql('SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1') == '289'
for items, _, _ in cases:
    assert all(item['study_type_key'] in ROUTING['studies'] for item in items)
composition = batch([order(items) for items, _, _ in cases])
before = counts()
invalid = copy.deepcopy(composition); invalid['order_composition_batch_uuid'] = str(uuid.uuid4()); invalid['orders'][-1]['order_items'][0]['functional_order_parameters']['gi_technique'] = 'UNSUPPORTED'
status, response = issue(invalid)
assert status == 422 and counts() == before, (status, response, before, counts())
print('QA_INVALID_MIXED_COMPOSITION_ZERO_WRITES=PASS', flush=True)
trigger = """DELIMITER $$
CREATE TRIGGER studycoverage02_fail BEFORE INSERT ON clinical_documents FOR EACH ROW BEGIN
IF JSON_UNQUOTE(JSON_EXTRACT(NEW.payload_json,'$.order_routing_group_key'))='GENERAL_IMAGING' THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='DISPOSABLE_FORCED_FAILURE';
END IF;
END$$
DELIMITER ;
"""
subprocess.run(['mysql', DB], input=trigger, text=True, check=True)
forced = batch([order([{'study_type_key': 'glucose'}]), order([{'study_type_key': 'rx_chest'}])])
before = counts(); status, _ = issue(forced)
assert status == 500 and counts() == before, (status, before, counts())
sql('DROP TRIGGER studycoverage02_fail')
status, _ = issue(forced)
assert status == 201
print('QA_FORCED_SECOND_ORDER_FAILURE_ROLLS_BACK_BATCH=PASS', flush=True)
status, response = issue(composition)
assert status == 201 and len(response['data']['orders']) == len(cases), (status, response)
docs = response['data']['orders']; snaps = [saved(doc) for doc in docs]
assert len({doc['document_id'] for doc in docs}) == 6
assert {snapshot['order_composition_batch_uuid'] for snapshot in snaps} == {composition['order_composition_batch_uuid']}
all_item_ids = [item['order_item_id'] for snapshot in snaps for item in snapshot['order_items']]
assert len(all_item_ids) == len(set(all_item_ids)) == 8
assert [snapshot['order_routing_group_key'] for snapshot in snaps] == [entry['order_routing_group_key'] for entry in composition['orders']]
assert snaps[3]['order_items'][0]['dental_location']['arch_key'] == 'MAXILLARY' and snaps[3]['order_items'][1]['dental_location']['arch_key'] == 'MANDIBULAR'
assert snaps[1]['order_items'][0]['pathology_order_parameters']['version'] == 1 and snaps[1]['order_items'][0]['pathology_order_parameters_label']
assert snaps[2]['order_items'][0]['imaging_order_parameters']['version'] == 1 and snaps[2]['order_items'][0]['imaging_order_parameters_label']
assert snaps[5]['order_items'][0]['functional_order_parameters']['version'] == 1 and snaps[5]['order_items'][0]['functional_order_parameters_label']
print('QA_SIX_GROUP_COMPOSITION_AND_PARAMETER_SNAPSHOTS=PASS', flush=True)
mid = counts(); status, replay = issue(composition)
assert status == 200 and replay['data'] == response['data'] and counts() == mid, (status, replay, mid, counts())
print('QA_IDEMPOTENT_REPLAY=PASS', flush=True)
duplicate = copy.deepcopy(composition); duplicate['order_composition_batch_uuid'] = str(uuid.uuid4()); duplicate['orders'][3]['order_items'][1]['dental_location'] = arch('MAXILLARY')
mid = counts(); status, _ = issue(duplicate)
assert status == 422 and counts() == mid
print('QA_DENTAL_LOCATION_AWARE_DEDUPE=PASS', flush=True)
for doc, snapshot, (items, label, result_type) in zip(docs, snaps, cases):
    portable(doc, [label])
    upload_result(doc, snapshot['order_items'][0]['order_item_id'], 'Resultado QA '+label, result_type)
upload_result(docs[3], snaps[3]['order_items'][1]['order_item_id'], 'Resultado QA mandibular', 'imaging_result')
print('QA_SIX_PORTABLE_HTML_PDF_AND_SEVEN_EXACT_RESULTS=PASS', flush=True)
