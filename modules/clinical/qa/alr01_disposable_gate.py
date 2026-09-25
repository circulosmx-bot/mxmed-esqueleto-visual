"""ALR01 in-app attention QA against disposable clinical state and synthetic clock data."""
import json
import os
import subprocess
from datetime import datetime, timedelta, timezone
from pathlib import Path
from zoneinfo import ZoneInfo

from playwright.sync_api import expect, sync_playwright

base = os.environ['ALR01_QA_BASE']
db = os.environ['ALR01_QA_DB']
root = Path(os.environ['ALR01_QA_ROOT'])
reader = base + '/api/clinical/index.php/longitudinal/follow-ups/agenda'
now = datetime.now(ZoneInfo('America/Mexico_City'))


def check(value, label):
    assert value, label
    print('PASS', label)


def sql(query):
    return subprocess.check_output(['mysql', '--batch', '--skip-column-names', db, '-e', query], text=True).strip()


def utc_due(local):
    return local.astimezone(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')


html = (root / 'index.html').read_text()
section = html[html.index('<section class="agf01-followups"'):html.index('<div class="modal fade" id="ag_new_appointment_modal"')]
fixture = f'''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="{base}/assets/css/style.css"><style>
.d-none{{display:none!important}}body{{margin:0}}.qa-shell{{display:grid;grid-template-columns:220px minmax(0,1fr)}}
#mmSidebar{{width:220px;box-sizing:border-box}}#p-ag-admin{{min-width:0;padding:16px;box-sizing:border-box}}
#ag_calendar{{min-height:100px;border:1px solid #ddd}}
@media(max-width:900px){{.qa-shell{{grid-template-columns:64px minmax(0,1fr)}}#mmSidebar{{width:64px}}}}
</style></head><body class="mx-sidebar-expanded"><div class="qa-shell">
<aside id="mmSidebar" class="mm-sidebar"><button type="button" class="menu-main" data-group="agenda">
<span class="txt"><span class="ttl">Agenda</span><span class="sub">configuración</span></span>
<span class="alr01-nav-badge" data-alr01-badge aria-hidden="true" hidden></span>
<span class="ico" aria-hidden="true"><span class="material-symbols-rounded">calendar_month</span></span>
</button></aside>
<main><section id="p-ag-admin"><button id="ag_refresh_btn" type="button">Refrescar agenda</button>
<div id="ag_calendar">Calendario de citas sin cambios</div>{section}</section></main></div>
<script>document.querySelector('[data-group="agenda"]').addEventListener('click',()=>window.__agendaClicks=(window.__agendaClicks||0)+1);</script>
<script src="{base}/assets/js/agenda/agf01-followups.js"></script></body></html>'''

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(channel='chrome', headless=True)
    context_a = browser.new_context()
    context_b = browser.new_context()
    context_a.add_cookies([{'name': 'PHPSESSID', 'value': 'alr01-a', 'url': base}])
    context_b.add_cookies([{'name': 'PHPSESSID', 'value': 'alr01-b', 'url': base}])
    a, b = context_a.request, context_b.request
    serial = 0

    def create(client, patient, title, **fields):
        global serial
        serial += 1
        response = client.post(base + f'/api/clinical/index.php/patients/{patient}/longitudinal/tasks',
                               data={'task_type': 'FOLLOW_UP', 'title': title, **fields},
                               headers={'Content-Type': 'application/json', 'Idempotency-Key': f'alr01-create-{serial}'})
        check(response.status == 200, f'disposable follow-up created: {title}')
        return response.json()['data']['item']

    def terminal(task, kind):
        response = a.post(base + f"/api/clinical/index.php/patients/{task['patient_id']}/longitudinal/tasks/{task['task_id']}/{kind}",
                          data={'expected_version': 1, 'reason': 'QA desechable'},
                          headers={'Content-Type': 'application/json', 'Idempotency-Key': f"alr01-{kind}-{task['task_id']}"})
        check(response.status == 200, f'disposable {kind} committed')

    future = now + timedelta(days=2)
    today_future = now.replace(hour=23, minute=59, second=59, microsecond=0)
    check(today_future > now, 'today has a future local due boundary')
    create(a, 'p_a', 'Próximo A', due_at=utc_due(future))
    create(a, 'p_b', 'Próximo B', due_at=utc_due(future + timedelta(days=1)))
    create(a, 'p_a', 'Sin fecha')
    terminal(create(a, 'p_a', 'Resuelto antiguo', due_at=utc_due(now - timedelta(days=2))), 'resolve')
    terminal(create(a, 'p_a', 'Cancelado antiguo', due_at=utc_due(now - timedelta(days=3))), 'cancel')
    create(b, 'p_x', 'Otro médico y ámbito', due_at=utc_due(now - timedelta(days=1)))
    appointment_before = sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments')

    page = context_a.new_page()
    page.goto(base + '/modules/clinical/README.md')
    page.set_content(fixture, wait_until='load')
    badge = page.locator('[data-alr01-badge]')
    summary = page.locator('[data-alr01-summary]')
    expect(badge).to_be_hidden()
    expect(summary).to_be_hidden()
    check('Otro médico y ámbito' not in page.locator('#agf01-followups').inner_text(), 'foreign physician/tenant scope absent')
    check(all(row['patient_id'] == 'p_x' for rows in b.get(reader).json()['data']['groups'].values() for row in rows),
          'canonical physician/tenant scope enforced')

    overdue_one = create(a, 'p_a', 'Vencido uno', due_at=utc_due(now - timedelta(hours=2)), appointment_id='alr-linked')
    page.locator('#ag_refresh_btn').click()
    expect(badge).to_have_text('1')
    expect(summary).to_have_text('1 vencido')
    check(page.locator('[data-group="agenda"]').get_attribute('aria-label') == 'Agenda · 1 seguimiento requiere atención',
          'badge has meaningful accessible label')
    page.locator('.agf01-item').filter(has_text='Vencido uno').locator('.agf02-action--resolve').click()
    page.locator('[data-agf02-reason]').fill('Atendido')
    page.locator('[data-agf02-confirm]').click()
    expect(badge).to_be_hidden()
    check(a.get(base + f"/api/clinical/index.php/patients/p_a/longitudinal/tasks/{overdue_one['task_id']}").json()['data']['item']['state'] == 'RESOLVED',
          'resolve changes only canonical task state and immediately decreases count')

    today_tasks = [create(a, 'p_a', f'Para hoy {i}', due_at=utc_due(today_future)) for i in (1, 2)]
    page.locator('#ag_refresh_btn').click()
    expect(badge).to_have_text('2')
    expect(summary).to_have_text('2 para hoy')
    page.locator('.agf01-item').filter(has_text='Para hoy 1').locator('.agf02-action--cancel').click()
    page.locator('[data-agf02-reason]').fill('Ya no procede')
    page.locator('[data-agf02-confirm]').click()
    expect(badge).to_have_text('1')
    check(a.get(base + f"/api/clinical/index.php/patients/p_a/longitudinal/tasks/{today_tasks[0]['task_id']}").json()['data']['item']['state'] == 'CANCELED',
          'cancel changes only canonical task state and immediately decreases count')

    for i in (2, 3):
        create(a, 'p_a', f'Vencido {i}', due_at=utc_due(now - timedelta(days=i)))
    for i in (3, 4):
        create(a, 'p_b', f'Para hoy {i}', due_at=utc_due(today_future))
    page.locator('#ag_refresh_btn').click()
    expect(badge).to_have_text('5')
    expect(summary).to_have_text('2 vencidos · 3 para hoy')
    check([len(a.get(reader).json()['data']['groups'][key]) for key in ('overdue', 'today', 'upcoming', 'no_due')] == [2, 3, 2, 1],
          'canonical groups yield 5 attention items while excluding upcoming, no-date and terminal tasks')
    check(sql('SELECT appointment_id,doctor_id,patient_id,start_at,status FROM agenda_appointments') == appointment_before,
          'linked appointment state and time unchanged')

    for width, height in [(1440, 900), (1366, 768), (820, 1180), (390, 844)]:
        page.set_viewport_size({'width': width, 'height': height})
        page.evaluate("document.body.classList.toggle('mx-sidebar-collapsed', window.innerWidth <= 900)")
        check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), f'no horizontal overflow {width}x{height}')
        check(badge.is_visible() and page.locator('#ag_calendar').is_visible(), f'badge and appointments usable {width}x{height}')

    page.route('**/api/clinical/index.php/longitudinal/follow-ups/agenda',
               lambda route: route.fulfill(status=503, content_type='application/json', body='{"ok":false}'))
    page.locator('#ag_refresh_btn').click()
    expect(badge).to_be_hidden()
    expect(page.locator('[data-agf01-status]')).to_have_text('No se pudieron cargar los seguimientos.')
    page.locator('[data-group="agenda"]').click()
    check(page.evaluate('window.__agendaClicks') == 1 and page.locator('#ag_calendar').is_visible(),
          'read failure omits badge without blocking navigation or appointments')
    page.unroute_all()
    page.locator('#ag_refresh_btn').click()
    expect(badge).to_have_text('5')
    check('2 vencidos · 3 para hoy' == summary.inner_text(), 'canonical server refresh reconciles badge and summary')
    task_states_before_clock = sql('SELECT task_id,state,due_at FROM clinical_patient_tasks ORDER BY task_id')

    clock_page = context_a.new_page()
    clock_start = datetime(2026, 9, 25, 18, 0, tzinfo=timezone.utc)
    clock_page.clock.install(time=clock_start)
    clock_page.goto(base + '/modules/clinical/README.md')
    due = (clock_start + timedelta(seconds=30)).strftime('%Y-%m-%d %H:%M:%S')
    crossing = {'task_id': 99101, 'row_version': 1, 'patient_id': 'p_a', 'patient_name': 'Ana Rivera',
                'title': 'Cruce de límite', 'due_at': due, 'due_display': '25 sep 2026 · 12:00 p. m.',
                'derived_due_state': 'PENDING'}
    synthetic = json.dumps({'ok': True, 'data': {'groups': {'overdue': [], 'today': [crossing], 'upcoming': [], 'no_due': []}}})
    calls = []
    clock_page.route('**/api/clinical/index.php/longitudinal/follow-ups/agenda',
                     lambda route: (calls.append(1), route.fulfill(status=200, content_type='application/json', body=synthetic)))
    clock_page.set_content(fixture, wait_until='load')
    expect(clock_page.locator('[data-agf01-group="today"]')).to_be_visible()
    check(clock_page.locator('[data-agf01-group="overdue"]').is_hidden(), 'before due boundary is not overdue')
    clock_page.clock.fast_forward(61_000)
    expect(clock_page.locator('[data-agf01-group="overdue"]')).to_be_visible()
    expect(clock_page.locator('[data-alr01-summary]')).to_have_text('1 vencido')
    check(len(calls) == 1, 'time crossing uses loaded canonical data without server polling')
    check(sql('SELECT task_id,state,due_at FROM clinical_patient_tasks ORDER BY task_id') == task_states_before_clock,
          'attention calculation never writes lifecycle state')
    clock_page.close()

    page.close()
    context_a.close()
    context_b.close()
    browser.close()

print('ALR01_DISPOSABLE_GATE=PASS')
