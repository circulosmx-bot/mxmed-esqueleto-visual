"""DOC-IMP01 physical HTTP proof. Runs only under consultation_flow_r1_disposable_gate.sh."""
import hashlib
import json
import os
import re
import struct
import subprocess
import zlib
from datetime import datetime, timezone
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
ROOT = Path(os.environ['FLOW_R1_QA_ROOT'])
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
assert ROOT.is_dir() and '/flow-r1-qa-' in str(ROOT)
PATH = BASE + '/api/clinical/index.php'
PATIENT = 'p_plan02ux_review'
ROUTE = f'{PATH}/doctors/1/patients/{PATIENT}/documents'
checks = {}


def sql(query):
    return subprocess.check_output(['mysql', '-N', DB, '-e', query], text=True).strip()


def count(table):
    assert table in {'clinical_documents', 'clinical_document_binaries'}
    return int(sql(f'SELECT COUNT(*) FROM {table}'))


def check(name, condition):
    assert condition, name
    checks[name] = 'PASS'
    print(f'PASS {name}', flush=True)


def pdf_bytes():
    objects = [
        b'<< /Type /Catalog /Pages 2 0 R >>',
        b'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >>',
        b'<< /Length 0 >>\nstream\n\nendstream',
    ]
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
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 16, 16, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b''))


PDF = pdf_bytes()
PNG = png_bytes()
NOW = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')


def attachment(api, key, title, data=PDF, kind='pdf', patient=PATIENT, extra=None):
    fields = {
        'document_type': kind, 'title': title, 'event_datetime': NOW,
        'payload': json.dumps({'source': 'documents_longitudinal_attachment'}),
        'file': {'name': 'qa.pdf' if kind == 'pdf' else 'qa.png',
                 'mimeType': 'application/pdf' if kind == 'pdf' else 'image/png', 'buffer': data},
    }
    if kind == 'image':
        fields['media_tag_key'] = 'clinical_attachment'
    if extra:
        fields.update(extra)
    return api.post(f'{PATH}/doctors/1/patients/{patient}/documents', multipart=fields,
                    headers={'Idempotency-Key': key})


def uuid_of(response):
    data = response.json()['data']
    return data.get('document_uuid') or data.get('new_document_uuid') or data.get('document_id')


def row(uuid):
    return sql("SELECT CONCAT_WS('|',document_type,patient_id,COALESCE(encounter_id,'NULL'),"
               "COALESCE(encounter_ref_id,'NULL')) FROM clinical_documents WHERE document_uuid='" + uuid + "'")


def list_items(api):
    response = api.get(ROUTE + '?limit=200')
    check('T09 list HTTP', response.status == 200)
    return response.json()['data']['items']


with sync_playwright() as playwright:
    api = playwright.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    anon = playwright.request.new_context()
    sql("UPDATE clinical_encounters SET status='closed',closed_at=UTC_TIMESTAMP(),"
        "closed_by_user_id='review-user' WHERE encounter_id=1016")
    check('T01 no open encounter', int(sql("SELECT COUNT(*) FROM clinical_encounters WHERE status='open'")) == 0)

    first = attachment(api, 'doc-imp01-t01', 'Longitudinal PDF')
    check('T01 PDF HTTP', first.status == 201)
    pdf_uuid = uuid_of(first)
    check('T01 patient-only row', row(pdf_uuid) == f'pdf|{PATIENT}|NULL|NULL')
    check('T01 private manifest', count('clinical_document_binaries') == 1)

    image = attachment(api, 'doc-imp01-t02', 'Longitudinal image', PNG, 'image')
    check('T02 image HTTP', image.status == 201)
    image_uuid = uuid_of(image)
    check('T02 image row', row(image_uuid) == f'image|{PATIENT}|NULL|NULL')
    check('T02 image listed', any(i['document_uuid'] == image_uuid for i in list_items(api)))

    sql("INSERT INTO clinical_encounters (encounter_id,doctor_id,patient_id,encounter_dt,status,opened_by_user_id) "
        f"VALUES (1017,'1','{PATIENT}',UTC_TIMESTAMP(),'open','review-user')")
    third = attachment(api, 'doc-imp01-t03', 'Patient file with open encounter')
    check('T03 open encounter does not auto-link', third.status == 201 and
          row(uuid_of(third)) == f'pdf|{PATIENT}|NULL|NULL')
    before_replay = (count('clinical_documents'), count('clinical_document_binaries'))
    replay = attachment(api, 'doc-imp01-t03', 'Patient file with open encounter')
    check('T04 replay identity and counts', replay.status == 200 and uuid_of(replay) == uuid_of(third)
          and replay.json()['meta']['idempotency_replay'] is True
          and before_replay == (count('clinical_documents'), count('clinical_document_binaries')))
    conflict = attachment(api, 'doc-imp01-t03', 'Changed title')
    check('T05 idempotency conflict', conflict.status == 409 and
          before_replay == (count('clinical_documents'), count('clinical_document_binaries')))

    sql("INSERT INTO patients_patients VALUES ('p_doc_imp01_foreign','1991-01-01')")
    foreign = attachment(api, 'doc-imp01-t06', 'Foreign patient', patient='p_doc_imp01_foreign')
    check('T06 foreign patient rejected', foreign.status == 403 and
          before_replay == (count('clinical_documents'), count('clinical_document_binaries')))
    anonymous = attachment(anon, 'doc-imp01-t06-anon', 'Anonymous patient file')
    check('T06 anonymous upload rejected', anonymous.status == 401 and
          before_replay == (count('clinical_documents'), count('clinical_document_binaries')))
    invalid = attachment(api, 'doc-imp01-t07', 'Invalid content', b'not a PDF')
    check('T07 invalid MIME rejected', invalid.status == 400 and
          before_replay == (count('clinical_documents'), count('clinical_document_binaries')))
    forged = attachment(api, 'doc-imp01-t07-context', 'Foreign encounter', extra={'encounter_key': 'enc:1017'})
    check('T07 explicit context rejected', forged.status == 400 and
          before_replay == (count('clinical_documents'), count('clinical_document_binaries')))

    original = api.get(f'{PATH}/documents/{pdf_uuid}/binary/ORIGINAL')
    check('T08 authorized private read', original.status == 200 and original.body() == PDF)
    check('T08 unauthorized private read', anon.get(f'{PATH}/documents/{pdf_uuid}/binary/ORIGINAL').status == 401)
    sha, length, key = sql("SELECT b.sha256,b.byte_length,b.storage_key FROM clinical_document_binaries b "
                           "JOIN clinical_documents d ON d.id=b.document_id WHERE d.document_uuid='" + pdf_uuid + "'").split('\t')
    check('T08 manifest/hash/length', hashlib.sha256(original.body()).hexdigest() == sha and
          len(original.body()) == int(length) and (ROOT / 'private' / key).read_bytes() == PDF)
    check('T09 patient list', any(i['document_uuid'] == pdf_uuid for i in list_items(api)))

    replacement_fields = {'reason': 'Corrección documental QA',
        'replacement': json.dumps({'document_type': 'pdf', 'title': 'Longitudinal PDF corrected',
                                   'event_datetime': NOW, 'payload': {'source': 'm7_ws04_replacement'}}),
        'file': {'name': 'replacement.pdf', 'mimeType': 'application/pdf', 'buffer': PDF}}
    replaced = api.post(f'{PATH}/documents/{pdf_uuid}/amendments', multipart=replacement_fields,
                        headers={'Idempotency-Key': 'doc-imp01-t10'})
    check('T10 replacement HTTP', replaced.status == 201)
    items = list_items(api)
    original_row = next(i for i in items if i['document_uuid'] == pdf_uuid)
    family = [i for i in items if str(i['lineage_root_id']) == str(original_row['id'])]
    check('T10 original and successor preserved', len(family) == 2 and int(original_row['has_successor']) == 1
          and all(i['encounter_ref_id'] is None for i in family))
    check('T11 history projection compatible', len(family) == 2 and
          len([i for i in family if int(i['has_successor']) == 0]) == 1)

    encounter_fields = {'document_type': 'pdf', 'title': 'Consultation PDF', 'event_datetime': NOW,
        'payload': json.dumps({'source': 'm7_ws04'}),
        'file': {'name': 'consultation.pdf', 'mimeType': 'application/pdf', 'buffer': PDF}}
    encounter_url = f'{PATH}/encounters/enc%3A1017/documents'
    encounter = api.post(encounter_url, multipart=encounter_fields,
                         headers={'Idempotency-Key': 'doc-imp01-t12'})
    check('T12 consultation attachment', encounter.status == 201 and
          row(uuid_of(encounter)) == f'pdf|{PATIENT}|1017|1017')
    encounter_replay = api.post(encounter_url, multipart=encounter_fields,
                                headers={'Idempotency-Key': 'doc-imp01-t12'})
    check('T12 consultation idempotency', encounter_replay.status == 200 and
          uuid_of(encounter_replay) == uuid_of(encounter))
    encounter_replaced = api.post(f'{PATH}/documents/{uuid_of(encounter)}/amendments',
                                  multipart=replacement_fields,
                                  headers={'Idempotency-Key': 'doc-imp01-t12-amendment'})
    check('T12 consultation replacement', encounter_replaced.status == 201 and
          row(uuid_of(encounter_replaced)) == f'pdf|{PATIENT}|1017|1017')
    encounter_items = list_items(api)
    encounter_original = next(i for i in encounter_items if i['document_uuid'] == uuid_of(encounter))
    encounter_family = [i for i in encounter_items
                        if str(i['lineage_root_id']) == str(encounter_original['id'])]
    check('T12 consultation lineage preserved', len(encounter_family) == 2 and
          int(encounter_original['has_successor']) == 1 and
          all(str(i['encounter_ref_id']) == '1017' for i in encounter_family))
    sql("UPDATE clinical_encounters SET status='closed',closed_at=UTC_TIMESTAMP(),"
        "closed_by_user_id='review-user' WHERE encounter_id=1017")
    before_closed = (count('clinical_documents'), count('clinical_document_binaries'))
    closed = api.post(encounter_url, multipart=encounter_fields,
                      headers={'Idempotency-Key': 'doc-imp01-t13'})
    check('T13 closed encounter rejected', closed.status >= 400 and
          before_closed == (count('clinical_documents'), count('clinical_document_binaries')))

    mobile = api.get(f'{PATH}/mobile-capture-sessions/classifications')
    check('T14 mobile capture contract', mobile.status == 200 and mobile.json()['ok'] is True and
          anon.get(f'{PATH}/mobile-capture-sessions/classifications').status == 401)
    orphan = int(sql('SELECT COUNT(*) FROM clinical_document_binaries b LEFT JOIN clinical_documents d '
                     'ON d.id=b.document_id WHERE d.id IS NULL'))
    check('zero orphan binary rows', orphan == 0)
    print('DOC_IMP01_PHYSICAL_QA=' + json.dumps({'checks': checks,
          'document_rows': count('clinical_documents'), 'binary_rows': count('clinical_document_binaries'),
          'orphan_binary_rows': orphan}, sort_keys=True), flush=True)
