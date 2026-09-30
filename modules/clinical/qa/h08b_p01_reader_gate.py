"""Disposable HTTP proof of H08B-P01's opt-in document timeline projection."""
import json
import os
import subprocess
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from urllib.error import HTTPError

base = os.environ['H08BP01_QA_BASE']
db = os.environ['H08BP01_QA_DB']
route = base + '/api/clinical/index.php?route=doctors/d_a/patients/p_a/documents'

def sql(statement):
    subprocess.run(['mysql', db], input=statement, text=True, check=True, capture_output=True)

def read(path=route, **params):
    url = path + ('&' if '?' in path else '?') + urlencode(params)
    try:
        response = urlopen(Request(url, headers={'Cookie': 'PHPSESSID=h08bp01-qa'}))
    except HTTPError as error:
        response = error
    return response.status, json.loads(response.read())

def check(name, condition):
    assert condition, name
    print('PASS ' + name, flush=True)

def document(id, kind, created, generated=None, event='2026-01-01 00:00:00', patient='p_a', encounter=None, encounter_ref=None, appointment=None, hospital=None, payload=None):
    def q(value):
        return 'NULL' if value is None else "'" + str(value).replace("'", "''") + "'"
    return '(' + ','.join([str(id), q(f'doc-{id}'), q(f'Synthetic {kind} {id}'), q(kind), q('Synthetic descriptor'),
        q(event), '1', q(json.dumps(payload or {})), q(encounter), 'NULL' if encounter_ref is None else str(encounter_ref),
        q(appointment), q(hospital), q('generated'), q(patient), q(created), q(generated)]) + ')'

rows = [
    document(1,'prescription','2026-09-27 12:00:00','2026-09-28 12:00:00',event='2050-01-01 00:00:00',payload={'prescription':{'items':[{'medicamento':'Synthetic A'}]}}),
    document(2,'prescription','2026-09-27 00:00:00','2026-09-27 12:00:00',encounter_ref=101),
    document(3,'lab_order','2026-09-26 12:00:00',None,event='2049-01-01 00:00:00'),
    document(4,'imaging_order','2026-09-25 12:00:00','2026-09-25 12:00:00',encounter='legacy-101'),
    document(5,'lab_order','2026-09-24 12:00:00',None,appointment='appt-101'),
    document(6,'prescription','2026-09-23 12:00:00','2026-09-23 12:00:00'),
    document(7,'prescription','2026-09-24 12:00:00','2026-09-24 12:00:00',payload={'prescription':{'items':[{},{}]}}),
    document(8,'lab_order','2026-09-25 12:00:00','2026-09-25 12:00:00'),
    document(9,'lab_order','2026-09-25 12:00:00','2026-09-25 12:00:00'),
    document(10,'lab_result','2026-09-25 12:00:00','2026-09-25 12:00:00'),
    document(11,'prescription','2026-09-25 12:00:00','2026-09-25 12:00:00',patient='p_b'),
    document(222,'lab_order','2026-09-22 12:00:00',hospital='stay-101'),
    document(223,'prescription','1000-01-01 00:00:00',event='2040-01-01 00:00:00'),
]
rows += [document(id,'prescription',f'2026-01-01 00:{(id//60)%60:02d}:{id%60:02d}') for id in range(12,222)]
sql('INSERT INTO clinical_documents VALUES ' + ','.join(rows) + ';'
    'INSERT INTO clinical_document_revisions VALUES (1,6,6,7);')

default_status, default = read(limit=200)
check('default list shape, cap and event_datetime ordering', default_status == 200
    and set(default['data']) == {'items'} and len(default['data']['items']) == 200
    and default['data']['items'][0]['id'] == 1
    and 'timeline_at' not in default['data']['items'][0]
    and 'created_at' not in default['data']['items'][0])
check('default page size and type filter unchanged', len(read()[1]['data']['items']) == 30
    and all(row['document_type'] == 'lab_order' for row in read(document_type='lab_order')[1]['data']['items']))

status, first = read(timeline_mode=1, limit=50)
check('opt-in mode and UTC clock metadata', status == 200 and first['meta']['timeline_mode'] is True
    and first['meta']['timeline_timezone'] == 'UTC' and len(first['data']['items']) == 50)

items = []
cursor = None
while True:
    status, page = read(timeline_mode=1, limit=50, **({'cursor': cursor} if cursor else {}))
    check('timeline page read', status == 200)
    items += page['data']['items']
    if not page['data']['has_more']:
        check('last page has no cursor', page['data']['cursor_next'] is None)
        break
    cursor = page['data']['cursor_next']

by_id = {item['id']: item for item in items}
check('over 200 rows are discoverable without duplicates or skips', len(items) == 219
    and len(by_id) == len(items) and set(range(12,222)).issubset(by_id))
check('safe timestamp descending with stable id tie break',
    [(x['timeline_at'],x['id']) for x in items] == sorted([(x['timeline_at'],x['id']) for x in items], reverse=True)
    and items.index(by_id[9]) < items.index(by_id[8]))
check('standalone prescription issuance and minimal metadata', by_id[1]['timeline_at'] == '2026-09-28 12:00:00'
    and by_id[1]['timeline_at_source'] == 'generated_at' and by_id[1]['timeline_scope'] == 'PATIENT'
    and by_id[1]['timeline_eligible'] is True and by_id[1]['prescription_item_count'] == 1
    and 'payload_json' not in by_id[1])
check('encounter prescription excluded from standalone classification', by_id[2]['encounter_ref_id'] == 101
    and by_id[2]['timeline_scope'] == 'ENCOUNTER' and by_id[2]['timeline_eligible'] is False)
check('standalone order uses creation fallback, not event_datetime', by_id[3]['timeline_at'] == '2026-09-26 12:00:00'
    and by_id[3]['timeline_at_source'] == 'created_at' and by_id[3]['timeline_scope'] == 'PATIENT')
check('legacy encounter and appointment associations visible', by_id[4]['encounter_id'] == 'legacy-101'
    and by_id[4]['timeline_scope'] == 'ENCOUNTER' and by_id[5]['appointment_id'] == 'appt-101'
    and by_id[5]['timeline_scope'] == 'APPOINTMENT' and by_id[5]['timeline_eligible'] is False)
check('hospital stay cannot be classified as patient-level', by_id[222]['hospital_stay_id'] == 'stay-101'
    and by_id[222]['timeline_scope'] == 'HOSPITAL_STAY' and by_id[222]['timeline_eligible'] is False)
check('logical revision collapsed to latest document', 6 not in by_id and by_id[7]['lineage_root_id'] == 6
    and by_id[7]['prescription_item_count'] == 2 and by_id[7]['has_successor'] == 0)
check('unsupported types, other patient and unsafe clock hidden', 10 not in by_id and 11 not in by_id and 223 not in by_id)
check('doctor authorization preserved', read(path=base+'/api/clinical/index.php?route=doctors/d_b/patients/p_a/documents',timeline_mode=1)[0] == 403)
try:
    anonymous = urlopen(route + '&timeline_mode=1')
except HTTPError as error:
    anonymous = error
check('anonymous timeline read denied', anonymous.status == 401)
check('invalid cursor and oversized page rejected', read(timeline_mode=1,cursor='invalid')[0] == 400
    and read(timeline_mode=1,limit=201)[0] == 400)
sql('ALTER TABLE clinical_documents DROP COLUMN appointment_id;')
check('missing association column fails closed only in timeline mode', read(timeline_mode=1)[0] == 503
    and read()[0] == 200)
