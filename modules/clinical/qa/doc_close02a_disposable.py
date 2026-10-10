"""DOC-CLOSE02A: HTTP write boundary and historical reads in a disposable DB."""
import json
import os
import subprocess
import uuid

from playwright.sync_api import sync_playwright


BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
PATIENT = 'p_close02_legacy'


def sql(statement):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', statement], text=True).strip()


def check(label, condition):
    if not condition:
        raise AssertionError(label)
    print(f'{label}=PASS', flush=True)


sql("INSERT INTO patients_patients (patient_id,birthdate) VALUES ('p_close02_legacy','1990-01-01');"
    "INSERT INTO patients_doctor_links (doctor_id,patient_id,status) "
    "VALUES ('1','p_close02_legacy','active');"
    "INSERT INTO clinical_encounters "
    "(encounter_id,doctor_id,patient_id,encounter_dt,status,opened_by_user_id) "
    "VALUES (1022,'1','p_close02_legacy','2026-10-10 10:00:00','open','review-user')")

with sync_playwright() as pw:
    context = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    baseline = int(sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type IN "
                       "('informe_medico','interconsulta')"))
    for doc_type, prefix in [('informe_medico', 'INFORME'), ('interconsulta', 'INTERCONSULTA')]:
        for patient in [PATIENT, 'p_plan02ux_review']:
            route = BASE + f'/api/clinical/index.php/doctors/1/patients/{patient}/documents'
            for version in [None, 0, 1, 3, 2.5, '2', '2invalid']:
                payload = {'contract_version': version} if version is not None else {}
                response = context.post(route, data={'document_type': doc_type,
                    'type': doc_type, 'payload': payload})
                body = response.json()
                check(f'{prefix}_UNSUPPORTED_{patient}_{version}', response.status == 422
                      and body['error']['code'] == f'{prefix}_CONTRACT_VERSION_UNSUPPORTED')
            supported = context.post(route, data={'document_type': doc_type,
                'type': doc_type, 'payload': {'contract_version': 2}})
            check(f'{prefix}_CURRENT_ROUTES_CANONICAL_{patient}', supported.status == 400
                  and supported.json()['message'] == f'{prefix}_INTENT_INVALID')
        generic_url = BASE + '/api/clinical/index.php/documents'
        for version in [1, 2]:
            response = context.post(generic_url, data={'document_type': doc_type,
                'context': {'patient_id': PATIENT}, 'payload': {'contract_version': version}})
            check(f'{prefix}_DIRECT_GENERIC_{version}', response.status == 422
                  and response.json()['error']['code'] == f'{prefix if prefix != "INFORME" else "INFORME_MEDICO"}_GENERIC_WRITE_FORBIDDEN')
        upload = context.post(generic_url, multipart={'document_type': doc_type,
            'payload': json.dumps({'contract_version': 1})})
        check(f'{prefix}_DIRECT_GENERIC_MULTIPART', upload.status == 422
              and upload.json()['error']['code'].endswith('_GENERIC_WRITE_FORBIDDEN'))
        for encounter_id in [1016, 1022]:
            encounter_route = BASE + f'/api/clinical/index.php/encounters/{encounter_id}/documents'
            response = context.post(encounter_route, data={'document_type': doc_type,
                'payload': {'contract_version': 1}},
                headers={'Idempotency-Key': f'{prefix.lower()}:{uuid.uuid4()}'})
            check(f'{prefix}_ENCOUNTER_GENERIC_{encounter_id}', response.status == 422
                  and response.json()['error']['code'].endswith('_GENERIC_WRITE_FORBIDDEN'))
    check('NO_UNSUPPORTED_ROWS_CREATED', int(sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type IN "
          "('informe_medico','interconsulta')")) == baseline)

    for doc_type, prefix in [('informe_medico', 'INFORME'), ('interconsulta', 'INTERCONSULTA')]:
        route = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents'
        payload = {'contract_version': 2, 'status': 'draft',
                   'actor_snapshot': {'user_id': 'review-user', 'full_name': 'Dra. QA'},
                   'patient_snapshot': {'full_name': 'Paciente QA'},
                   'report': {'emission_date': '2026-10-10'},
                   'content': {'reason': 'Caso sintético', 'summary': 'Caso sintético',
                               'request': 'Valoración sintética'},
                   'recipient': {'mode': 'doctor', 'source': 'manual', 'doctor_name': 'Dr. QA'}}
        body = {'document_type': doc_type, 'type': doc_type, 'title': 'Caso DOC-CLOSE02A',
                'actor': {'user_id': 'review-user'}, 'context': {'patient_id': PATIENT},
                'payload': payload}
        headers = {'Idempotency-Key': f'{prefix.lower()}:{uuid.uuid4()}'}
        saved = context.post(route, data=body, headers=headers)
        check(f'{prefix}_CANONICAL_DRAFT_SAVE', saved.status == 201)
        draft_uuid = saved.json()['data']['document_id']
        version = int(sql(f"SELECT version FROM clinical_documents WHERE document_uuid='{draft_uuid}'"))
        body['draft_ref'] = draft_uuid
        body['expected_version'] = version
        body['payload']['status'] = 'issued'
        missing = context.post(route, data=body,
            headers={'Idempotency-Key': f'{prefix.lower()}:{uuid.uuid4()}'})
        check(f'{prefix}_MISSING_SIGNATURE_REJECTED', missing.status == 400
              and missing.json()['message'] == f'{prefix}_PHYSICIAN_SIGNATURE_REQUIRED')
        body['payload']['signatures'] = {'doctor': {'source': 'local_canvas', 'role': 'doctor',
            'signer_name': 'Dra. QA', 'image_data': 'data:image/png;base64,AAAA',
            'binding': {'version': 2, 'document_type': doc_type,
                        'document_uuid': draft_uuid, 'role': 'doctor',
                        'source': 'local_canvas', 'content_fingerprint': 'stale'}}}
        stale = context.post(route, data=body,
            headers={'Idempotency-Key': f'{prefix.lower()}:{uuid.uuid4()}'})
        check(f'{prefix}_STALE_SIGNATURE_REJECTED', stale.status == 400
              and stale.json()['message'] == f'{prefix}_PHYSICIAN_SIGNATURE_INVALID_CURRENT_VERSION')
        check(f'{prefix}_REJECTION_DID_NOT_EMIT',
              sql(f"SELECT status FROM clinical_documents WHERE document_uuid='{draft_uuid}'") == 'draft')

    for doc_type, marker, doc_uuid in [
        ('informe_medico', 'INFORME LEGADO DOC-CLOSE02A', '00000000-0000-4000-8000-000000000701'),
        ('interconsulta', 'INTERCONSULTA LEGADA DOC-CLOSE02A', '00000000-0000-4000-8000-000000000702'),
    ]:
        content = {'reason': marker, 'summary': marker, 'request': marker,
                   'clinical_summary': marker}
        payload = {'contract_version': 1, 'content': content,
                   'recipient': {'mode': 'service', 'source': 'manual', 'service': 'QA'}}
        statement = ("INSERT INTO clinical_documents "
            "(document_uuid,document_type,title,version,status,patient_id,care_setting,"
            "payload_json,event_datetime,created_at,created_by_user_id) VALUES "
            f"('{doc_uuid}','{doc_type}','{marker}',1,'generated','{PATIENT}',"
            f"'consulta','{json.dumps(payload, ensure_ascii=False)}','2026-10-10 10:00:00',"
            "UTC_TIMESTAMP(),'review-user')")
        sql(statement)
        doc = context.get(BASE + f'/api/clinical/index.php/doctors/1/documents/{doc_uuid}')
        check(f'{doc_type}_HISTORICAL_READ', doc.status == 200 and marker in doc.text())
        viewer_url = BASE + f'/modules/clinical/ui/viewer.php?uuid={doc_uuid}&doctor_id=1'
        viewer = context.get(viewer_url)
        check(f'{doc_type}_HISTORICAL_VIEW', viewer.status == 200
              and marker in viewer.text() and 'document-sheet' in viewer.text())
        printable = context.get(viewer_url + '&autoprint=1')
        check(f'{doc_type}_HISTORICAL_PRINT', printable.status == 200
              and marker in printable.text() and 'window.print' in printable.text())
    context.dispose()
