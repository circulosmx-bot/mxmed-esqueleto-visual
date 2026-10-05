"""LAB-CAT05C authenticated integration gate. Requires an isolated disposable DB/server."""
import json
import os
import pathlib
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = os.environ['LAB05C_BASE']
DB = os.environ['LAB05C_DB']
ROOT = pathlib.Path(os.environ['LAB05C_ROOT'])
REPO = pathlib.Path(__file__).resolve().parents[3]
assert DB.startswith('labcat05c_qa_') and ROOT.name.startswith('labcat05c-qa-')
PATH = '/api/clinical/index.php/doctors/d_labcat05c_order/patients/p_labcat05c_order'
COOKIE = 'PHPSESSID=labcat05c-owner'

def sql(query):
    return subprocess.check_output(['mysql', '-N', '-B', DB, '-e', query], text=True).strip()

def call(path, body=None, key=None):
    headers = {'Accept': 'application/json', 'Cookie': COOKIE}
    data = None
    if body is not None:
        data = json.dumps(body, ensure_ascii=False).encode()
        headers.update({'Content-Type': 'application/json', 'Idempotency-Key': key or body['order_composition_batch_uuid']})
    request = urllib.request.Request(BASE + path, data=data, headers=headers)
    try:
        with urllib.request.urlopen(request, timeout=45) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as response:
        return response.code, json.load(response)

def order(items, apps=None):
    result = {'order_routing_group_key': 'CLINICAL_LAB', 'priority': 'Rutinaria',
              'indication': 'QA LAB-CAT05C desechable', 'order_items': items}
    if apps is not None:
        result['lab_preset_applications'] = [{'preset_key': key, 'preset_version': 1} for key in apps]
    return result

def batch(items, apps=None):
    return {'order_composition_batch_uuid': str(uuid.uuid4()), 'order_routing_version': 1,
            'orders': [order(items, apps)]}

def issue(items, apps=None):
    body = batch(items, apps)
    code, response = call(PATH + '/orders/batch', body)
    assert code == 201, (code, response)
    document = response['data']['orders'][0]
    payload = json.loads(sql('SELECT payload_json FROM clinical_documents WHERE id=' + str(int(document['document_id']))))
    assert document['document_type'] == 'lab_order'
    return body, response, document, payload

def preset(name):
    return issue([], [name])

def pdf(document, label, required, forbidden=()):
    query = urllib.parse.urlencode({'uuid': document['document_uuid'], 'doctor_id': 'd_labcat05c_order'})
    request = urllib.request.Request(BASE + '/modules/clinical/ui/portable-order-pdf.php?' + query,
                                     headers={'Cookie': COOKIE})
    with urllib.request.urlopen(request, timeout=45) as response:
        data = response.read()
        assert response.status == 200 and response.headers.get_content_type() == 'application/pdf'
    assert data.startswith(b'%PDF-') and b'%%EOF' in data[-1024:]
    path = ROOT / (label + '.pdf')
    path.write_bytes(data)
    result = subprocess.check_output(['swift', '-e',
        'import PDFKit; import Foundation; let d=PDFDocument(url: URL(fileURLWithPath: CommandLine.arguments[1]))!; print("PAGES="+String(d.pageCount)); print(d.string ?? "")',
        str(path)], text=True)
    assert 'PAGES=1' in result and all(term in result for term in required), (label, result)
    assert all(term not in result for term in forbidden), (label, result)
    print('QA_PDF_' + label.upper() + '=PASS', flush=True)

def linked_result(document, item_ids, label):
    boundary = 'lab05c' + uuid.uuid4().hex
    body = {'patient_id': 'p_labcat05c_order', 'document_type': 'lab_result',
            'title': 'Resultado QA ' + label, 'event_datetime': '2026-10-04 12:00:00',
            'provenance': 'Laboratorio QA', 'payload': {
                'source': 'res02a_linked_result', 'related_order_document_uuid': document['document_uuid'],
                'related_order_item_ids': item_ids, 'provenance': 'Laboratorio QA'}}
    parts = []
    for key, value in body.items():
        if isinstance(value, dict):
            value = json.dumps(value, ensure_ascii=False)
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="qa.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode() + b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    request = urllib.request.Request(BASE + PATH + '/documents', data=b''.join(parts), headers={
        'Accept': 'application/json', 'Cookie': COOKIE,
        'Idempotency-Key': str(uuid.uuid4()), 'Content-Type': 'multipart/form-data; boundary=' + boundary})
    try:
        with urllib.request.urlopen(request, timeout=45) as response:
            assert response.status == 201
            created = json.load(response)['data']
    except urllib.error.HTTPError as error:
        raise AssertionError((error.code, error.read().decode(errors='replace')[:1000])) from error
    stored = json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='" + created['document_uuid'] + "'"))
    assert stored['related_order_document_uuid'] == document['document_uuid']
    assert stored['related_order_item_ids'] == item_ids
    return stored

assert sql('SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1') == '280'
assert sql("SELECT COUNT(*) FROM clinical_study_types WHERE study_type_key IN ('panel_quimica_6','arterial_blood_gas') AND is_active=1") == '2'
print('QA_EXACT_TWO_CANONICAL_IDENTITIES=PASS', flush=True)
route_file = 'modules/clinical/catalog/study_order_routing_v1.json'
before_routes = json.loads(subprocess.check_output(['git', 'show', 'HEAD:' + route_file], cwd=REPO, text=True))['studies']
after_routes = json.loads((REPO / route_file).read_text())['studies']
assert {key: value for key, value in after_routes.items() if key not in ('panel_quimica_6', 'arterial_blood_gas')} == before_routes
assert after_routes['panel_quimica_6'] == after_routes['arterial_blood_gas'] == 'CLINICAL_LAB'
for key, group in [('cyto_pap', 'PATHOLOGY_CYTOLOGY'), ('rx_chest', 'GENERAL_IMAGING'),
                   ('ecg_12lead', 'CARDIOVASCULAR_DIAGNOSTICS'), ('anoscopy_base', 'DIGESTIVE_ENDOSCOPY'),
                   ('dental_panoramic_xray', 'DENTAL_DIAGNOSTICS')]:
    assert after_routes[key] == group and sql("SELECT is_active FROM clinical_study_types WHERE study_type_key='" + key + "'") == '1'
print('QA_OTHER_FAMILY_ROUTING_REGRESSION=PASS', flush=True)

for query, key in [('QS6', 'panel_quimica_6'), ('quimica sanguinea', 'panel_quimica_6'),
                   ('GSA', 'arterial_blood_gas'), ('ABG', 'arterial_blood_gas'), ('gas arterial', 'arterial_blood_gas')]:
    code, response = call('/api/clinical/index.php/doctors/d_labcat05c_order/study-types?search=' + urllib.parse.quote(query))
    assert code == 200 and key in [item['study_type_key'] for item in response['data']['items']], (query, response)
print('QA_CANONICAL_SEARCH=PASS', flush=True)

_, _, qs6, qs6_payload = issue([{'study_type_key': 'panel_quimica_6'}])
assert len(qs6_payload['order_items']) == 1
assert [c['semantic_key'] for c in qs6_payload['order_items'][0]['lab_panel_definition']['components']] == [
    'glucose', 'urea', 'creatinine', 'uric_acid', 'chol_total', 'triglycerides']
assert qs6_payload['order_items'][0]['lab_panel_definition']['panel_definition_version'] == 1
assert qs6_payload['order_items'][0]['specimen_collection_requirements']['specimen_type_key'] == 'SERUM'
print('QA_QS6_SINGLE_PANEL_SNAPSHOT=PASS', flush=True)

gas_docs = []
for context in [{'version': 1, 'mode': 'ROOM_AIR'},
                {'version': 1, 'mode': 'SUPPLEMENTAL_OXYGEN', 'fio2_percent': 35, 'delivery_device': 'NASAL_CANNULA'},
                {'version': 1, 'mode': 'UNKNOWN'}]:
    _, _, doc, payload = issue([{'study_type_key': 'arterial_blood_gas', 'lab_arterial_oxygen_context': context}])
    item = payload['order_items'][0]
    assert len(payload['order_items']) == 1 and item['specimen_collection_requirements']['specimen_type_key'] == 'ARTERIAL_BLOOD'
    assert item['lab_arterial_oxygen_context'] == context and len(item['lab_panel_definition']['components']) == 6
    gas_docs.append(doc)
print('QA_GAS_ROOM_AIR_SUPPLEMENTAL_UNKNOWN=PASS', flush=True)

for invalid in [
    {'study_type_key': 'arterial_blood_gas', 'lab_arterial_oxygen_context': {'version': 1, 'mode': 'SUPPLEMENTAL_OXYGEN', 'fio2_percent': 21}},
    {'study_type_key': 'arterial_blood_gas', 'lab_arterial_oxygen_context': {'version': 1, 'mode': 'SUPPLEMENTAL_OXYGEN', 'fio2_percent': 101}},
    {'study_type_key': 'arterial_blood_gas', 'lab_arterial_oxygen_context': {'version': 1, 'mode': 'SUPPLEMENTAL_OXYGEN'}},
    {'study_type_key': 'arterial_blood_gas', 'lab_arterial_oxygen_context': {'version': 1, 'mode': 'BAD'}},
    {'study_type_key': 'arterial_blood_gas', 'lab_arterial_oxygen_context': {'version': 1, 'mode': 'ROOM_AIR', 'foo': 1}},
    {'study_type_key': 'arterial_blood_gas', 'specimen_collection_requirements': {'specimen_type_key': 'SERUM'}},
]:
    before = sql('SELECT COUNT(*) FROM clinical_documents')
    code, response = call(PATH + '/orders/batch', batch([invalid]))
    assert code == 422 and sql('SELECT COUNT(*) FROM clinical_documents') == before, (invalid, code, response)
print('QA_INVALID_GAS_ZERO_WRITES=PASS', flush=True)

renal = 'preset_qs3_renal_v1'
lipids = 'preset_qs3_lipids_v1'
hepatic = 'preset_hepatic_basic_v1'
expected = {renal: ['glucose', 'urea', 'creatinine'],
            lipids: ['glucose', 'chol_total', 'triglycerides'],
            hepatic: ['ast', 'alt', 'alp', 'ggt', 'total_protein', 'albumin', 'bilirubin_total', 'bilirubin_direct']}
preset_docs = {}
for key, keys in expected.items():
    body, response, doc, payload = preset(key)
    assert [item['study_type_key'] for item in payload['order_items']] == keys
    assert [component['order_item_id'] for component in payload['lab_preset_provenance'][0]['components']] == [item['order_item_id'] for item in payload['order_items']]
    assert payload['lab_preset_provenance'][0]['preset_key'] == key
    assert all(item['study_type_key'] not in expected for item in payload['order_items'])
    preset_docs[key] = doc
    code, replay = call(PATH + '/orders/batch', body)
    assert code == 200 and replay['data'] == response['data']
print('QA_THREE_PRESETS_EXPANSION_PROVENANCE_IDEMPOTENCY=PASS', flush=True)

cases = [
    ([{'study_type_key': 'glucose'}], [renal], expected[renal]),
    ([{'study_type_key': key} for key in expected[renal]], [renal], expected[renal]),
    ([], [renal, renal], expected[renal]),
    ([], [renal, lipids], ['glucose', 'urea', 'creatinine', 'chol_total', 'triglycerides']),
    ([{'study_type_key': 'alt'}], [hepatic], ['alt'] + [key for key in expected[hepatic] if key != 'alt']),
]
for items, apps, keys in cases:
    _, _, _, payload = issue(items, apps)
    assert [item['study_type_key'] for item in payload['order_items']] == keys
    assert len({item['study_type_id'] for item in payload['order_items']}) == len(keys)
print('QA_PRESET_DEDUPE_CASES=PASS', flush=True)

pdf(qs6, 'qs6', ['Química sanguínea de 6 elementos', 'Incluye: Glucosa, Urea, Creatinina'])
pdf(gas_docs[1], 'gas', ['Gasometría arterial', 'Oxígeno suplementario', 'FiO₂: 35%'], ['SUPPLEMENTAL_OXYGEN'])
pdf(preset_docs[renal], 'preset', ['Glucosa', 'Urea', 'Creatinina'], ['Preset QS3', 'preset_qs3_renal_v1'])
assert len(qs6_payload['order_items']) == 1 and len(gas_docs) == 3
linked_result(qs6, [qs6_payload['order_items'][0]['order_item_id']], 'QS6')
gas_payload = json.loads(sql('SELECT payload_json FROM clinical_documents WHERE id=' + str(int(gas_docs[1]['document_id']))))
linked_result(gas_docs[1], [gas_payload['order_items'][0]['order_item_id']], 'gas')
renal_payload = json.loads(sql('SELECT payload_json FROM clinical_documents WHERE id=' + str(int(preset_docs[renal]['document_id']))))
linked_result(preset_docs[renal], [renal_payload['order_items'][0]['order_item_id']], 'preset component')
print('QA_EXACT_RESULT_ITEM_LINKAGE=PASS', flush=True)
print('LAB_CAT05C_DISPOSABLE_HTTP_GATE=PASS', flush=True)
