import json
import os
import subprocess
import urllib.error
import urllib.request

base = os.environ['LON05A_HTTP_BASE']
db = os.environ['LON05A_HTTP_DB']


def request(path, method='GET', body=None, key=None, session='lon05a-a'):
    headers = {'Accept': 'application/json', 'Cookie': 'PHPSESSID=' + session}
    payload = None if body is None else json.dumps(body).encode()
    if payload is not None:
        headers['Content-Type'] = 'application/json'
    if key:
        headers['Idempotency-Key'] = key
    req = urllib.request.Request(base + path, data=payload, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as error:
        return error.code, json.load(error)


def check(value, name):
    if not value:
        raise AssertionError(name)
    print('PASS', name)


def count(table):
    result = subprocess.run(['mysql', '-N', db, '-e', f'SELECT COUNT(*) FROM {table}'], capture_output=True, text=True, check=True)
    return int(result.stdout.strip())


status, data = request('/medications')
check(status == 200 and data['data']['knowledge_state'] == 'UNREVIEWED' and data['data']['list_version'] == 1, 'HTTP empty state remains unknown')
check(count('clinical_patient_medications') == 0 and count('clinical_documents') == 2, 'prescriptions do not auto-create medication')
status, data = request('/medications/patient-reported', 'POST', {'medication_name': 'HTTP patient report'}, 'http-report')
check(status == 200 and data['data']['item']['state'] == 'REPORTED_BY_PATIENT', 'HTTP patient-reported create')
item = data['data']['item']['medication_id']
status, data = request('/medications/from-prescription', 'POST', {'medication_name': 'HTTP Rx', 'source_document_id': 301, 'initial_state': 'PRESCRIBED_NOT_CONFIRMED_ACTIVE'}, 'http-rx')
check(status == 200 and data['data']['item']['state'] == 'PRESCRIBED_NOT_CONFIRMED_ACTIVE', 'HTTP explicit prescription evidence')
status, data = request('/medications/from-prescription', 'POST', {'medication_name': 'Foreign', 'source_document_id': 302, 'initial_state': 'PRESCRIBED_NOT_CONFIRMED_ACTIVE'}, 'http-foreign')
check(status == 409 and data['error'] == 'FOREIGN_SOURCE', 'HTTP foreign prescription rejected')
status, data = request(f'/medications/{item}/confirm-active', 'POST', {'expected_version': 1, 'reason': 'Explicit review'}, 'http-confirm')
check(status == 200 and data['data']['item']['state'] == 'ACTIVE_CONFIRMED', 'HTTP explicit confirmation')
status, data = request(f'/medications/{item}/history')
check(status == 200 and [e['operation'] for e in data['data']['events']] == ['PATIENT_REPORTED_CREATE', 'CONFIRM_ACTIVE'], 'HTTP audit read')
status, data = request('/medication-reconciliations')
version = data['data']['list_version']
body = {'expected_list_version': version, 'decisions': [{'action': 'ADD', 'medication_name': 'HTTP reconciliation add'}]}
status, data = request('/medication-reconciliations', 'POST', body, 'http-reconcile')
check(status == 200 and data['data']['list_version'] == version + 1, 'HTTP reconciliation command')
reconciliation_id = data['data']['reconciliation_id']
status, data = request(f'/medication-reconciliations/{reconciliation_id}')
check(status == 200 and len(data['data']['items']) == 1, 'HTTP reconciliation history')
status, data = request('/medication-reconciliations', 'POST', body, 'http-stale', session='lon05a-b')
check(status == 409 and data['error'] == 'STALE_LIST_VERSION', 'HTTP second session stale list conflict')
status, data = request(f'/medications/{item}/discontinue', 'POST', {'expected_version': 1, 'reason': 'Stale dose'}, 'http-stale-row', session='lon05a-b')
check(status == 409 and data['error'] == 'STALE_VERSION', 'HTTP second session stale episode conflict')
status, data = request('/medications', session='lon05a-foreign')
check(status == 404, 'HTTP foreign doctor read denied')
status, data = request('/medications', 'POST', {'medication_name': 'Foreign write'}, 'http-foreign-write', session='lon05a-foreign')
check(status == 404 and count('clinical_patient_medications') == 3, 'HTTP foreign doctor write denied')
