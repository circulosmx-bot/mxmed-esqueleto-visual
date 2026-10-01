import json
import os
import subprocess
import urllib.error
import urllib.request
import uuid

fixture = json.load(open(os.environ['TAX03B_AMEND_FIXTURE']))
base = os.environ['TAX03B_AMEND_BASE'] + '/api/clinical/index.php/documents/' + fixture['result']['uuid'] + '/amendments'
original = fixture['result']['payload']
main_id = fixture['main']['payload']['order_items'][0]['order_item_id']
other_id = fixture['other']['payload']['order_items'][0]['order_item_id']


def amend(payload):
    request = urllib.request.Request(
        base,
        data=json.dumps({'reason': 'QA correction', 'replacement': {'payload': payload}}).encode(),
        headers={
            'Cookie': 'PHPSESSID=tax03b-amend',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Idempotency-Key': str(uuid.uuid4()),
        },
        method='POST',
    )
    try:
        with urllib.request.urlopen(request) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as error:
        return error.code, json.load(error)


for name, payload in [
    ('other_order_item', {**original, 'related_order_item_ids': [other_id]}),
    ('unknown_item', {**original, 'related_order_item_ids': [str(uuid.uuid4())]}),
    ('different_order', {**original,
        'related_order_document_id': str(fixture['other']['id']),
        'related_order_document_uuid': fixture['other']['uuid']}),
]:
    status, body = amend(payload)
    assert status in (400, 409) and not body['ok'], (name, status, body)
    print('QA_HTTP_REJECT_' + name.upper() + '=PASS')

status, body = amend({**original, 'result_summary': 'Resultado corregido'})
assert status == 201 and body['ok'], (status, body)
assert body['data']['new_document_id'] != fixture['result']['id'], body
new_id = int(body['data']['new_document_id'])
payload_raw = subprocess.check_output(['mysql', '-N', os.environ['TAX03B_AMEND_DB'], '-e',
    f'SELECT payload_json FROM clinical_documents WHERE id={new_id}'])
stored = json.loads(payload_raw)
assert stored['related_order_item_ids'] == [main_id], stored
assert stored['related_order_document_id'] == str(fixture['main']['id']), stored
assert stored['related_order_document_uuid'] == fixture['main']['uuid'], stored
assert stored['result_summary'] == 'Resultado corregido', stored
print('QA_HTTP_RESULT_AMENDMENT=PASS')
print('QA_HTTP_RESULT_ITEM_ID_PRESERVED=PASS')
