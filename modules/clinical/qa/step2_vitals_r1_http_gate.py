"""Disposable HTTP/SQL proof of authoritative Step 2 server capture time."""
import json
import os
import subprocess

from playwright.sync_api import sync_playwright

base = os.environ['STEP2_QA_BASE']
database = os.environ['STEP2_QA_DB']


def sql(query):
    return subprocess.check_output(['mysql', '--batch', '--skip-column-names', database, '-e', query], text=True).strip()


def check(condition, name):
    assert condition, name
    print('PASS', name, flush=True)


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    context.add_cookies([{'name': 'PHPSESSID', 'value': 'step2-qa', 'url': base}])
    request = context.request
    endpoint = base + '/api/clinical/index.php?route=encounters/enc:1/observations'

    def create(key, payload):
        return request.post(endpoint, data=payload, headers={'Content-Type': 'application/json', 'Idempotency-Key': key})

    server = create('step2-server', {
        'code': 'temperature', 'unit': '°C', 'value_numeric': 36.5,
        'source': 'direct_measurement', 'capture_time_mode': 'SERVER_AT_SAVE',
    })
    check(server.status == 201 and server.json()['ok'], 'server-at-save create succeeds')
    server_row = server.json()['data']
    server_id = int(server_row['observation_id'])
    stored = sql(f'SELECT effective_at,effective_at_authority,recorded_at,provenance_json FROM clinical_observations WHERE observation_id={server_id}').split('\t')
    check(stored[0] == stored[2] == server_row['effective_at'] == server_row['recorded_at'], 'server-assigned capture time is persisted and returned')
    check(stored[1] == 'EXPLICIT_EFFECTIVE_TIME' and json.loads(stored[3])['capture_time_mode'] == 'SERVER_AT_SAVE', 'server capture has distinct authoritative provenance')
    replay = create('step2-server', {
        'code': 'temperature', 'unit': '°C', 'value_numeric': 36.5,
        'source': 'direct_measurement', 'capture_time_mode': 'SERVER_AT_SAVE',
    })
    check(replay.status == 200 and int(replay.json()['data']['observation_id']) == server_id, 'idempotent replay preserves server timestamp')
    edited = request.patch(endpoint + f'/{server_id}', data={
        'code': 'temperature', 'unit': '°C', 'value_numeric': 36.6,
        'source': 'direct_measurement', 'row_version': int(server_row['row_version']),
    }, headers={'Content-Type': 'application/json'})
    check(edited.status == 200 and edited.json()['ok'], 'server-captured value remains editable')
    preserved = sql(f'SELECT effective_at,provenance_json FROM clinical_observations WHERE observation_id={server_id}').split('\t')
    check(preserved[0] == stored[0] and json.loads(preserved[1])['capture_time_mode'] == 'SERVER_AT_SAVE', 'edit preserves capture time and provenance')

    fallback = create('step2-fallback', {
        'code': 'heart_rate', 'unit': 'bpm', 'value_numeric': 80, 'source': 'direct_measurement',
    })
    check(fallback.status == 201, 'legacy omitted-time contract still works')
    fallback_id = int(fallback.json()['data']['observation_id'])
    check(sql(f'SELECT effective_at_authority FROM clinical_observations WHERE observation_id={fallback_id}') == 'CAPTURE_TIME_FALLBACK', 'genuine fallback remains fallback')

    explicit = create('step2-explicit', {
        'code': 'height', 'unit': 'cm', 'value_numeric': 170,
        'source': 'import', 'effective_at': '2025-03-04 05:06:07',
    })
    check(explicit.status == 201, 'explicit historical write succeeds')
    explicit_id = int(explicit.json()['data']['observation_id'])
    check(sql(f'SELECT effective_at,effective_at_authority FROM clinical_observations WHERE observation_id={explicit_id}').split('\t') == ['2025-03-04 05:06:07', 'EXPLICIT_EFFECTIVE_TIME'], 'explicit historical time unchanged')

    invalid = create('step2-invalid', {
        'code': 'weight', 'unit': 'kg', 'value_numeric': 70, 'source': 'direct_measurement',
        'capture_time_mode': 'SERVER_AT_SAVE', 'effective_at': '2025-01-01 00:00:00',
    })
    check(invalid.status == 400, 'client cannot combine server mode with supplied time')
    forged = create('step2-forged', {
        'code': 'weight', 'unit': 'kg', 'value_numeric': 70, 'source': 'direct_measurement',
        'provenance': {'capture_time_mode': 'SERVER_AT_SAVE'},
    })
    check(forged.status == 400, 'client cannot forge server provenance')
    finalized = request.post(base + '/api/clinical/index.php?route=encounters/enc:1/finalize', data={}, headers={'Content-Type': 'application/json'})
    check(finalized.status == 200 and finalized.json()['ok'], 'consultation A finalized canonically')
    sql("INSERT INTO clinical_encounters (doctor_id,patient_id,encounter_dt,status,opened_by_user_id) VALUES ('d_a','p_a',UTC_TIMESTAMP(),'open','u_a')")
    later_id = int(sql("SELECT MAX(encounter_id) FROM clinical_encounters WHERE doctor_id='d_a' AND patient_id='p_a'"))
    check(later_id != 1, 'later consultation context exists')
    prior = request.get(base + f'/api/clinical/index.php?route=patients/p_a/longitudinal/measurements&view=prior&exclude_encounter_id={later_id}')
    check(prior.status == 200 and prior.json()['ok'], 'prior-value reader responds in later consultation')
    ids = {int(item['observation_id']) for item in prior.json()['data']['items']}
    check(server_id in ids, 'server-at-save value is eligible in later consultation')
    check(fallback_id not in ids, 'genuine fallback remains excluded from prior values')
    check(explicit_id in ids, 'explicit historical value remains eligible')
    context.close()
    browser.close()

print('STEP2_VITALS_R1_HTTP_GATE=PASS')
