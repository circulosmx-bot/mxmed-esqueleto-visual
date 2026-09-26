"""AGF01 read-only Agenda integration against a disposable clinical database."""
import os
import subprocess
from datetime import datetime, timedelta, timezone
from pathlib import Path
from zoneinfo import ZoneInfo

from playwright.sync_api import expect, sync_playwright

base = os.environ['AGF01_QA_BASE']
db = os.environ['AGF01_QA_DB']
root = Path(os.environ['AGF01_QA_ROOT'])
agenda_url = base + '/api/clinical/index.php/longitudinal/follow-ups/agenda'
local_zone = ZoneInfo('America/Mexico_City')
now = datetime.now(local_zone)


def check(value, label):
    assert value, label
    print('PASS', label)


def sql(query):
    return subprocess.check_output(['mysql', '--batch', '--skip-column-names', db, '-e', query], text=True).strip()


def utc_due(local):
    return local.astimezone(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(channel='chrome', headless=True)
    context_a = browser.new_context()
    context_b = browser.new_context()
    context_a.add_cookies([{'name': 'PHPSESSID', 'value': 'agf01-a', 'url': base}])
    context_b.add_cookies([{'name': 'PHPSESSID', 'value': 'agf01-b', 'url': base}])
    a, b = context_a.request, context_b.request
    serial = 0

    def create(client, patient, title, **fields):
        global serial
        serial += 1
        response = client.post(base + f'/api/clinical/index.php/patients/{patient}/longitudinal/tasks',
                               data={'task_type': 'FOLLOW_UP', 'title': title, **fields},
                               headers={'Content-Type': 'application/json', 'Idempotency-Key': f'agf01-{serial}'})
        check(response.status == 200, f'synthetic follow-up created: {title}')
        return response.json()['data']['item']

    empty = a.get(agenda_url)
    check(empty.status == 200 and all(not rows for rows in empty.json()['data']['groups'].values()), 'empty physician read')
    check(b.get(agenda_url).status == 200 and all(not rows for rows in b.get(agenda_url).json()['data']['groups'].values()), 'other physician empty read')

    past = create(a, 'p_a', 'Revisar análisis vencidos', due_at=utc_due(now - timedelta(days=2)))
    create(a, 'p_a', 'Vencido más antiguo', due_at=utc_due(now - timedelta(days=5)))
    today_local = now.replace(hour=23, minute=59, second=0, microsecond=0)
    check(today_local > now, 'synthetic future deadline exists today')
    create(a, 'p_a', 'Llamar hoy', due_at=utc_due(today_local))
    create(a, 'p_b', 'Valorar evolución', due_at=utc_due(now + timedelta(days=3)), appointment_id='agf-linked')
    create(a, 'p_b', 'Próximo más cercano', due_at=utc_due(now + timedelta(days=1)))
    create(a, 'p_b', 'Revisar resultados sin fecha')
    create(a, 'p_a', 'Otra acción sin fecha')
    resolved = create(a, 'p_a', 'Seguimiento resuelto', due_at=utc_due(now - timedelta(days=3)))
    canceled = create(a, 'p_b', 'Seguimiento cancelado', due_at=utc_due(now - timedelta(days=4)))
    for item, action in [(resolved, 'resolve'), (canceled, 'cancel')]:
        response = a.post(base + f"/api/clinical/index.php/patients/{item['patient_id']}/longitudinal/tasks/{item['task_id']}/{action}",
                          data={'expected_version': 1, 'reason': 'QA sintética'},
                          headers={'Content-Type': 'application/json', 'Idempotency-Key': f'agf01-{action}'})
        check(response.status == 200, f'{action} synthetic terminal state')
    create(b, 'p_x', 'Seguimiento de otro médico', due_at=utc_due(now - timedelta(days=1)))

    appointment_before = sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments')
    result = a.get(agenda_url)
    check(result.status == 200 and result.json()['ok'] is True, 'Agenda follow-up reader available')
    groups = result.json()['data']['groups']
    check([len(groups[key]) for key in ['overdue', 'today', 'upcoming', 'no_due']] == [2, 1, 2, 2], 'past, today, future and no-date grouped')
    check([row['title'] for row in groups['overdue']] == ['Vencido más antiguo', past['title']] and all(row['derived_due_state'] == 'OVERDUE' for row in groups['overdue']), 'oldest overdue first')
    check(groups['today'][0]['title'] == 'Llamar hoy' and [row['title'] for row in groups['upcoming']] == ['Próximo más cercano', 'Valorar evolución'], 'local today and upcoming sorted by due date')
    check(all(row['due_at'] is None for row in groups['no_due']) and [row['title'] for row in groups['no_due']] == ['Revisar resultados sin fecha', 'Otra acción sin fecha'], 'undated follow-ups stably ordered')
    check(all(row['title'] not in {'Seguimiento resuelto', 'Seguimiento cancelado', 'Seguimiento de otro médico'} for values in groups.values() for row in values),
          'terminal and foreign-physician tasks excluded')
    linked = next(row for row in groups['upcoming'] if row['title'] == 'Valorar evolución')
    check(linked['patient_name'] == 'Bruno López' and 'Cita vinculada' in linked['linked_appointment_display'],
          'patient name and linked appointment context visible')
    check('agf-linked' not in str(groups), 'raw appointment ID absent from read projection')
    check(sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments') == appointment_before,
          'Agenda appointment unchanged by read')
    check(all(row['patient_name'] == 'Paciente Ajeno' for values in b.get(agenda_url).json()['data']['groups'].values() for row in values),
          'physician read scope enforced server-side')

    html = (root / 'index.html').read_text()
    section = html[html.index('<section class="agf01-followups"'):html.index('<div class="modal fade" id="ag_new_appointment_modal"')]
    fixture = f'''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="{base}/assets/css/style.css"><style>.d-none{{display:none!important}}body{{margin:0}}#p-ag-admin{{padding:16px;box-sizing:border-box}}#ag_calendar{{min-height:100px;border:1px solid #ddd}}</style></head>
    <body><section id="p-ag-admin"><button id="ag_refresh_btn" type="button">Refrescar agenda</button><div id="ag_calendar">Calendario de citas sin cambios</div>{section}</section>
    <script src="{base}/assets/js/agenda/agf01-followups.js"></script></body></html>'''
    page = context_a.new_page()
    page.goto(base + '/modules/clinical/README.md')
    page.set_content(fixture, wait_until='load')
    expect(page.locator('[data-agf01-status]')).to_be_hidden()
    for key, title in [('overdue', 'Vencidos'), ('today', 'Para hoy'), ('upcoming', 'Próximos'), ('no_due', 'Sin fecha límite')]:
        expect(page.locator(f'[data-agf01-group="{key}"]')).to_be_visible()
        expect(page.locator(f'[data-agf01-group="{key}"] h6')).to_have_text(title)
    check('agf-linked' not in page.locator('#agf01-followups').inner_text(), 'Agenda UI hides internal appointment ID')
    check(page.locator('#ag_calendar').inner_text() == 'Calendario de citas sin cambios', 'follow-ups stay outside appointment calendar')
    for width, height in [(1440, 900), (1366, 768), (820, 1180), (390, 844)]:
        page.set_viewport_size({'width': width, 'height': height})
        check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), f'responsive no overflow {width}x{height}')
        check(page.locator('#ag_calendar').is_visible() and page.locator('#ag_refresh_btn').is_visible(), f'appointment UI usable {width}x{height}')

    page.evaluate('(()=>{window.__agf01Fetch=window.fetch;window.fetch=()=>new Promise(()=>{});return true})()')
    page.locator('#ag_refresh_btn').click()
    expect(page.locator('[data-agf01-status]')).to_have_text('Cargando seguimientos…')
    check(page.locator('#ag_calendar').is_visible(), 'loading leaves appointment calendar visible')
    page.evaluate('(()=>{window.fetch=window.__agf01Fetch;return true})()')
    page.locator('#ag_refresh_btn').click()
    expect(page.locator('[data-agf01-group="overdue"]')).to_be_visible()

    page.route('**/api/clinical/index.php/longitudinal/follow-ups/agenda', lambda route: route.fulfill(status=200, content_type='application/json', body='{"ok":true,"data":{"groups":{"overdue":[],"today":[],"upcoming":[],"no_due":[]}}}'))
    page.locator('#ag_refresh_btn').click()
    expect(page.locator('[data-agf01-status]')).to_have_text('No hay seguimientos pendientes.')
    check(page.locator('[data-agf01-groups]').is_hidden(), 'compact empty state hides empty group grid')
    page.unroute_all()

    page.route('**/api/clinical/index.php/longitudinal/follow-ups/agenda', lambda route: route.fulfill(status=503, content_type='application/json', body='{"ok":false}'))
    page.locator('#ag_refresh_btn').click()
    expect(page.locator('[data-agf01-status]')).to_have_text('No se pudieron cargar los seguimientos.')
    check(page.locator('#ag_calendar').inner_text() == 'Calendario de citas sin cambios', 'follow-up reader failure isolated from appointments')
    page.unroute_all()
    context_b_page = context_b.new_page()
    context_b_page.goto(base + '/modules/clinical/README.md')
    context_b_page.set_content(fixture, wait_until='load')
    expect(context_b_page.locator('[data-agf01-group="overdue"]')).to_be_visible()
    check('Paciente Ajeno' in context_b_page.locator('#agf01-followups').inner_text() and 'Ana Rivera' not in context_b_page.locator('#agf01-followups').inner_text(),
          'browser physician isolation')
    sql("UPDATE agenda_appointments SET status='canceled' WHERE appointment_id='agf-linked'")
    after_cancellation = a.get(agenda_url).json()['data']['groups']['upcoming']
    check(any(row['title'] == 'Valorar evolución' and row['linked_appointment_display'] is None for row in after_cancellation),
          'unavailable appointment detail does not hide follow-up or claim a scheduled link')
    context_b_page.close()
    page.close()
    context_a.close()
    context_b.close()
    browser.close()

print('AGF01_DISPOSABLE_GATE=PASS')
