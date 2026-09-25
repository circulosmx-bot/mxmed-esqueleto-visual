"""AGF02 safe Agenda actions against disposable clinical authority only."""
import os
import subprocess
from datetime import datetime, timedelta, timezone
from pathlib import Path
from zoneinfo import ZoneInfo

from playwright.sync_api import expect, sync_playwright

base = os.environ['AGF02_QA_BASE']
db = os.environ['AGF02_QA_DB']
root = Path(os.environ['AGF02_QA_ROOT'])
agenda_url = base + '/api/clinical/index.php/longitudinal/follow-ups/agenda'
due = (datetime.now(ZoneInfo('America/Mexico_City')) + timedelta(days=3)).astimezone(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')


def check(value, label):
    assert value, label
    print('PASS', label)


def sql(query):
    return subprocess.check_output(['mysql', '--batch', '--skip-column-names', db, '-e', query], text=True).strip()


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(channel='chrome', headless=True)
    context_a = browser.new_context()
    context_b = browser.new_context()
    context_a.add_cookies([{'name': 'PHPSESSID', 'value': 'agf02-a', 'url': base}])
    context_b.add_cookies([{'name': 'PHPSESSID', 'value': 'agf02-b', 'url': base}])
    a, b = context_a.request, context_b.request
    serial = 0

    def create(patient, title, **fields):
        global serial
        serial += 1
        response = a.post(base + f'/api/clinical/index.php/patients/{patient}/longitudinal/tasks',
                          data={'task_type': 'FOLLOW_UP', 'title': title, **fields},
                          headers={'Content-Type': 'application/json', 'Idempotency-Key': f'agf02-create-{serial}'})
        check(response.status == 200, f'synthetic task created: {title}')
        return response.json()['data']['item']

    navigate_task = create('p_a', 'Abrir expediente QA')
    resolve_task = create('p_b', 'Resolver con cita', due_at=due, appointment_id='agf-linked')
    cancel_task = create('p_b', 'Cancelar con cita', due_at=due, appointment_id='agf-linked')
    failure_task = create('p_a', 'Error de escritura QA')
    stale_task = create('p_a', 'Versión anterior QA')
    appointment_before = sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments')
    tasks_before_nav = sql('SELECT COUNT(*) FROM clinical_patient_task_audit_events')
    encounter_before = sql('SELECT encounter_id,status FROM clinical_encounters')

    html = (root / 'index.html').read_text()
    section = html[html.index('<section class="agf01-followups"'):html.index('<div class="modal fade" id="ag_new_appointment_modal"')]
    fixture = f'''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="{base}/assets/css/style.css"><style>.d-none{{display:none!important}}body{{margin:0}}#p-ag-admin{{padding:16px;box-sizing:border-box}}#ag_calendar{{min-height:100px;border:1px solid #ddd}}</style></head>
    <body><section id="p-ag-admin"><button id="ag_refresh_btn" type="button">Refrescar agenda</button><div id="ag_calendar">Calendario de citas sin cambios</div>{section}</section>
    <section id="p-expediente"><button type="button" data-vis01-open="#t-tareas-longitudinal">Tareas clínicas</button></section>
    <script>window.setActivePatientId=async (id,options)=>{{window.__selectedPatient=id;window.__selectedOptions=options;document.querySelector('#p-expediente').dataset.patientId=id;return true}};
    window.jumpTo=(id)=>{{window.__jumpTarget=id;return true}};
    document.addEventListener('click',event=>{{if(event.target.matches('[data-vis01-open]'))window.__clinicalTab=event.target.dataset.vis01Open}});</script>
    <script src="{base}/assets/js/agenda/agf01-followups.js"></script></body></html>'''
    page = context_a.new_page()
    page.goto(base + '/modules/clinical/README.md')
    page.set_content(fixture, wait_until='load')
    expect(page.locator('[data-agf01-status]')).to_be_hidden()
    card = lambda title: page.locator('.agf01-item').filter(has_text=title)
    check(card('Abrir expediente QA').get_by_role('button').count() == 3, 'open follow-up exposes three authorized actions')
    check(not any(label in page.locator('#agf01-followups').inner_text() for label in ['Crear seguimiento', 'Editar acción', 'Cambiar fecha límite']),
          'Agenda has no create or inline edit action')

    card('Abrir expediente QA').locator('.agf02-action--open').click()
    page.wait_for_function("window.__clinicalTab === '#t-tareas-longitudinal'")
    check(page.evaluate('window.__selectedPatient') == 'p_a' and page.evaluate('window.__jumpTarget') == 'p-expediente',
          'existing patient selection and clinical navigation receive correct patient')
    check(page.evaluate('window.__selectedOptions.source') == 'search_open' and page.evaluate('window.__selectedOptions.suppressEncounterAutoContext') is True,
          'navigation uses existing safe patient context authority')
    check(sql('SELECT COUNT(*) FROM clinical_patient_task_audit_events') == tasks_before_nav and sql('SELECT encounter_id,status FROM clinical_encounters') == encounter_before,
          'navigation does not mutate task or encounter')
    check(sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments') == appointment_before,
          'navigation does not mutate appointment')

    card('Resolver con cita').locator('.agf02-action--resolve').click()
    expect(page.locator('[data-agf02-title]')).to_have_text('Resolver seguimiento')
    expect(page.locator('[data-agf02-description]')).to_have_text('¿Confirmas que esta acción ya fue atendida?')
    check(page.evaluate('document.activeElement.id') == 'agf02-reason', 'resolve confirmation focuses reason input')
    page.locator('[data-agf02-reason]').fill('Acción atendida')
    page.locator('[data-agf02-confirm]').click()
    expect(card('Resolver con cita')).to_have_count(0)
    check(a.get(base + f"/api/clinical/index.php/patients/p_b/longitudinal/tasks/{resolve_task['task_id']}").json()['data']['item']['state'] == 'RESOLVED',
          'resolve uses canonical terminal state')
    check([row['operation'] for row in a.get(base + f"/api/clinical/index.php/patients/p_b/longitudinal/tasks/{resolve_task['task_id']}/history").json()['data']['events']] == ['CREATE', 'RESOLVE'],
          'resolved follow-up retained in longitudinal history')
    check(sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments') == appointment_before,
          'linked appointment unchanged by resolve')

    card('Cancelar con cita').locator('.agf02-action--cancel').click()
    expect(page.locator('[data-agf02-title]')).to_have_text('Cancelar seguimiento')
    expect(page.locator('[data-agf02-description]')).to_contain_text('Su registro se conservará en el expediente.')
    page.locator('[data-agf02-back]').click()
    expect(page.locator('#agf02-dialog')).not_to_be_visible()
    check(card('Cancelar con cita').count() == 1 and a.get(base + f"/api/clinical/index.php/patients/p_b/longitudinal/tasks/{cancel_task['task_id']}").json()['data']['item']['state'] == 'OPEN',
          'confirmation back action writes nothing')
    card('Cancelar con cita').locator('.agf02-action--cancel').click()
    page.locator('[data-agf02-reason]').fill('Ya no procede')
    page.locator('[data-agf02-confirm]').click()
    expect(card('Cancelar con cita')).to_have_count(0)
    check(a.get(base + f"/api/clinical/index.php/patients/p_b/longitudinal/tasks/{cancel_task['task_id']}").json()['data']['item']['state'] == 'CANCELED',
          'cancel uses canonical terminal state')
    check([row['operation'] for row in a.get(base + f"/api/clinical/index.php/patients/p_b/longitudinal/tasks/{cancel_task['task_id']}/history").json()['data']['events']] == ['CREATE', 'CANCEL'],
          'canceled follow-up retained in longitudinal history')
    check(sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments') == appointment_before,
          'linked appointment unchanged by cancel')

    def terminal(client, patient, task, key):
        return client.post(base + f"/api/clinical/index.php/patients/{patient}/longitudinal/tasks/{task['task_id']}/resolve",
                           data={'expected_version': 1, 'reason': 'Intento no autorizado'},
                           headers={'Content-Type': 'application/json', 'Idempotency-Key': key})

    check(terminal(a, 'p_b', navigate_task, 'agf02-wrong-patient').status == 404, 'cross-patient task mutation rejected')
    check(terminal(b, 'p_a', navigate_task, 'agf02-wrong-doctor').status == 404, 'cross-physician task mutation rejected')
    missing = dict(navigate_task, task_id=99999999)
    check(terminal(a, 'p_a', missing, 'agf02-missing').status == 404, 'nonexistent task mutation rejected')

    changed = a.patch(base + f"/api/clinical/index.php/patients/p_a/longitudinal/tasks/{stale_task['task_id']}",
                      data={'expected_version': 1, 'title': 'Versión actual QA', 'reason': 'Cambio concurrente'},
                      headers={'Content-Type': 'application/json', 'Idempotency-Key': 'agf02-external-update'})
    check(changed.status == 200, 'external concurrent update committed')
    card('Versión anterior QA').locator('.agf02-action--cancel').click()
    page.locator('[data-agf02-reason]').fill('Intento con versión antigua')
    page.locator('[data-agf02-confirm]').click()
    expect(page.locator('[data-agf02-feedback]')).to_contain_text('cambió en otra sesión')
    expect(card('Versión actual QA')).to_have_count(1)
    check(a.get(base + f"/api/clinical/index.php/patients/p_a/longitudinal/tasks/{stale_task['task_id']}").json()['data']['item']['state'] == 'OPEN',
          'stale mutation rejected without overwrite')

    page.route(f"**/api/clinical/index.php/patients/p_a/longitudinal/tasks/{failure_task['task_id']}/resolve",
               lambda route: route.fulfill(status=503, content_type='application/json', body='{"ok":false,"error":"QA_FAILURE"}'))
    card('Error de escritura QA').locator('.agf02-action--resolve').click()
    page.locator('[data-agf02-reason]').fill('Intento fallido')
    page.locator('[data-agf02-confirm]').click()
    expect(page.locator('[data-agf02-error]')).to_have_text('No se pudo actualizar el seguimiento. Intenta nuevamente.')
    check(card('Error de escritura QA').count() == 1 and page.locator('#ag_calendar').inner_text() == 'Calendario de citas sin cambios',
          'mutation failure preserves follow-up and appointment UI')
    check(a.get(base + f"/api/clinical/index.php/patients/p_a/longitudinal/tasks/{failure_task['task_id']}").json()['data']['item']['state'] == 'OPEN',
          'failed request does not change task')
    page.locator('[data-agf02-back]').click()
    page.unroute_all()

    for width, height in [(1440, 900), (1366, 768), (820, 1180), (390, 844)]:
        page.set_viewport_size({'width': width, 'height': height})
        check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), f'actions no overflow {width}x{height}')
        card('Abrir expediente QA').locator('.agf02-action--cancel').click()
        check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), f'confirmation no overflow {width}x{height}')
        check(page.locator('[data-agf02-confirm]').is_visible() and page.locator('[data-agf02-back]').is_visible(), f'confirmation controls usable {width}x{height}')
        page.keyboard.press('Escape')
        expect(page.locator('#agf02-dialog')).not_to_be_visible()

    check(sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments') == appointment_before,
          'all AGF02 actions leave appointment state and time unchanged')
    check(sql('SELECT encounter_id,status FROM clinical_encounters') == encounter_before,
          'AGF02 actions do not create or change encounters')
    check(int(sql('SELECT COUNT(*) FROM clinical_patient_tasks')) == 5, 'follow-ups never hard-deleted')
    page.close()
    context_a.close()
    context_b.close()
    browser.close()

print('AGF02_DISPOSABLE_GATE=PASS')
