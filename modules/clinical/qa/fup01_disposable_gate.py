"""FUP01 synthetic/disposable HTTP and browser gate; never uses a working database."""
import os
import subprocess
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

base = os.environ['FUP01_QA_BASE']
db = os.environ['FUP01_QA_DB']
root = Path(os.environ['FUP01_QA_ROOT'])
task_url = base + '/api/clinical/index.php/patients/p_a/longitudinal/tasks'


def check(value, label):
    assert value, label
    print('PASS', label)


def sql(query):
    return subprocess.check_output(['mysql', '--batch', '--skip-column-names', db, '-e', query], text=True).strip()


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(channel='chrome', headless=True)
    context = browser.new_context()
    context.add_cookies([{'name': 'PHPSESSID', 'value': 'fup01-qa', 'url': base}])
    request = context.request
    serial = 0

    def create(title, **fields):
        global serial
        serial += 1
        return request.post(task_url, data={'task_type': 'FOLLOW_UP', 'title': title, **fields},
                            headers={'Content-Type': 'application/json', 'Idempotency-Key': f'fup01-{serial}'})

    before_appointments = sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments ORDER BY appointment_id')
    no_due = create('Revisar resultados de laboratorio')
    check(no_due.status == 200 and no_due.json()['data']['item']['appointment_id'] is None and no_due.json()['data']['item']['due_at'] is None,
          'independent follow-up without appointment or due date')
    no_due_item = no_due.json()['data']['item']
    check(no_due_item['state'] == 'OPEN' and no_due_item['derived_due_state'] == 'PENDING', 'open no-due state pending')
    future = create('Revisión futura', due_at='2030-10-03 12:00:00')
    check(future.status == 200 and future.json()['data']['item']['derived_due_state'] == 'PENDING', 'open future due state pending')
    past = create('Revisión vencida', due_at='2020-01-01 10:00:00')
    check(past.status == 200 and past.json()['data']['item']['state'] == 'OPEN' and past.json()['data']['item']['derived_due_state'] == 'OVERDUE', 'overdue derived without lifecycle mutation')
    linked = create('Seguimiento con cita', appointment_id='a_valid', source_encounter_id=101)
    check(linked.status == 200 and linked.json()['data']['item']['appointment_id'] == 'a_valid', 'same-patient upcoming appointment linked')
    both = create('Seguimiento con fecha y cita', due_at='2030-10-04 12:00:00', appointment_id='a_valid')
    check(both.status == 200 and both.json()['data']['item']['appointment_id'] == 'a_valid' and both.json()['data']['item']['due_at'] is not None,
          'due date and appointment together')
    for name, appointment, code in [('foreign', 'a_foreign', 'FOREIGN_APPOINTMENT'), ('canceled', 'a_stale', 'INELIGIBLE_APPOINTMENT'), ('past', 'a_past', 'INELIGIBLE_APPOINTMENT'), ('missing', 'a_missing', 'FOREIGN_APPOINTMENT')]:
        rejected = create('Rechazo ' + name, appointment_id=appointment)
        check(rejected.status == 409 and rejected.json()['error'] == code, name + ' appointment rejected')
    appointment_query = base + '/api/agenda/index.php/appointments?from=2026-09-25%2000%3A00%3A00&to=2027-09-25%2000%3A00%3A00&patient_id=p_a&limit=500'
    appointments = request.get(appointment_query)
    check(appointments.status == 200 and appointments.json()['ok'] is True and all(row['patient_id'] == 'p_a' for row in appointments.json()['data']),
          'canonical Agenda reader scopes appointment list to current patient')
    check(sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments ORDER BY appointment_id') == before_appointments,
          'appointment identity, time and status unchanged')
    check(sql('SELECT status FROM clinical_encounters WHERE encounter_id=101') == 'open' and sql('SELECT COUNT(*) FROM clinical_encounters') == '1',
          'source encounter stays open and no encounter created')
    resolve = request.post(task_url + '/' + str(past.json()['data']['item']['task_id']) + '/resolve',
                           data={'expected_version': 1, 'reason': 'Revisado'},
                           headers={'Content-Type': 'application/json', 'Idempotency-Key': 'fup01-resolve'})
    check(resolve.status == 200 and resolve.json()['data']['item']['derived_due_state'] == 'RESOLVED', 'resolved past due never overdue')
    cancel = request.post(task_url + '/' + str(no_due_item['task_id']) + '/cancel',
                          data={'expected_version': 1, 'reason': 'Ya no aplica'},
                          headers={'Content-Type': 'application/json', 'Idempotency-Key': 'fup01-cancel'})
    check(cancel.status == 200 and cancel.json()['data']['item']['derived_due_state'] == 'CANCELED', 'canceled task never overdue')
    rows = request.get(task_url).json()['data']['items']
    check(len(rows) == 5 and all('derived_due_state' in row for row in rows), 'canonical read exposes derived due state')

    source = (root / 'index.html').read_text()
    start = source.index('<div class="tab-pane fade" id="t-resumen-longitudinal">')
    end = source.index('<div class="tab-pane fade show active" id="t-datos">', start)
    panes = source[start:end]
    fixture = f'''<!doctype html><html lang="es"><head><meta charset="utf-8"><style>.d-none{{display:none!important}}</style></head>
    <body><div id="p-expediente" data-patient-id="p_a">{panes}<section id="m7-workspace"><div data-m7-body data-encounter-id="101"></div><button type="button" data-lon06b-m7-follow-up>Crear seguimiento desde consulta</button></section></div>
    <script src="{base}/assets/js/clinical/lon06b-tasks.js"></script></body></html>'''
    page = context.new_page()
    page.goto(base + '/modules/clinical/README.md')
    page.set_content(fixture, wait_until='load')
    expect(page.locator('[data-lon06b-status]')).to_have_text('Tareas longitudinales actualizadas.')
    page.locator('[data-lon06b-add-follow-up]').click()
    expect(page.locator('[data-lon06b-dialog-title]')).to_have_text('Registra una acción para seguimiento')
    check(page.locator('[data-lon06b-context]').is_hidden(), 'follow-up modal subtitle removed')
    expect(page.locator('[data-lon06b-follow-up-appointment-select] option')).to_have_count(2)
    check('a_valid' not in page.locator('[data-lon06b-follow-up-appointment-select]').inner_text() and 'oct' in page.locator('[data-lon06b-follow-up-appointment-select]').inner_text().lower(),
          'selector shows human-readable future appointment without raw ID')
    page.locator('[data-lon06b-title]').fill('Seguimiento desde selector')
    page.locator('[data-lon06b-follow-up-appointment-select]').select_option('a_valid')
    page.locator('[data-lon06b-save]').click()
    expect(page.locator('[data-lon06b-open]')).to_contain_text('Seguimiento desde selector')
    check(sql('SELECT COUNT(*) FROM clinical_patient_tasks WHERE appointment_id="a_valid"') == '3', 'modal links through canonical task writer')
    check(sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments ORDER BY appointment_id') == before_appointments,
          'browser linkage does not mutate appointment')
    page.locator('[data-lon06b-m7-follow-up]').click()
    page.locator('[data-lon06b-title]').fill('Seguimiento independiente desde Plan')
    page.locator('[data-lon06b-save]').click()
    expect(page.locator('[data-lon06b-open]')).to_contain_text('Seguimiento independiente desde Plan')
    check(sql('SELECT status FROM clinical_encounters WHERE encounter_id=101') == 'open' and sql('SELECT COUNT(*) FROM clinical_encounters') == '1',
          'Plan follow-up leaves consultation open')
    page.route('**/api/agenda/index.php/appointments?*', lambda route: route.abort())
    page.locator('[data-lon06b-add-follow-up]').click()
    expect(page.locator('[data-lon06b-appointment-state]')).to_have_text('No se pudieron cargar las citas. Puedes crear el seguimiento sin vincular una cita.')
    page.locator('[data-lon06b-title]').fill('Seguimiento sin Agenda disponible')
    page.locator('[data-lon06b-save]').click()
    expect(page.locator('[data-lon06b-open]')).to_contain_text('Seguimiento sin Agenda disponible')
    check(any(row['title'] == 'Seguimiento sin Agenda disponible' and row['appointment_id'] is None for row in request.get(task_url).json()['data']['items']),
          'Agenda lookup failure does not block independent follow-up')
    sql("UPDATE agenda_appointments SET status='canceled' WHERE appointment_id='a_valid'")
    check(request.get(task_url + '/' + str(linked.json()['data']['item']['task_id'])).json()['data']['item']['state'] == 'OPEN',
          'later appointment cancellation does not cancel linked follow-up')
    edited = request.patch(task_url + '/' + str(linked.json()['data']['item']['task_id']),
                           data={'title': 'Seguimiento aún vigente', 'appointment_id': 'a_valid', 'expected_version': 1, 'reason': 'Ajuste de acción'},
                           headers={'Content-Type': 'application/json', 'Idempotency-Key': 'fup01-edit-after-agenda-change'})
    check(edited.status == 200 and edited.json()['data']['item']['state'] == 'OPEN' and edited.json()['data']['item']['appointment_id'] == 'a_valid',
          'existing link remains editable after appointment cancellation')
    context.close()
    browser.close()

print('FUP01_DISPOSABLE_GATE=PASS')
