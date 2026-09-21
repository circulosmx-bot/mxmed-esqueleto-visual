"""Disposable, authenticated browser gate for the real LON03B markup, JS and API."""
import json
import os
import subprocess
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

base = os.environ['LON03B_QA_BASE']
root = Path(os.environ['LON03B_QA_ROOT'])
window_path = os.environ['LON03B_QA_WINDOW_PATH']
qa_db = os.environ['LON03B_QA_DB']
source = (root / 'index.html').read_text()
start = source.index('<div class="tab-pane fade" id="t-resumen-longitudinal">')
end = source.index('<div class="tab-pane fade show active" id="t-datos">', start)
panes = source[start:end]
fixture = f'''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="{base}/assets/css/expediente-paciente-visual-normalization.css">
<style>body{{margin:0;background:#eef8fa;font:16px Arial,sans-serif}}#p-expediente{{max-width:1400px;margin:auto;padding:18px}}.d-none{{display:none!important}}.btn{{display:inline-block;border:1px solid #06aeb8;border-radius:9px;background:white;color:#06536e;padding:7px 12px;cursor:pointer}}.btn-primary{{background:#06aeb8;color:white}}.btn-link{{border:0;background:none}}</style>
</head><body><div id="p-expediente" data-patient-id="p_a">{panes}</div>
<script src="{base}/assets/js/clinical/lon02-summary.js"></script>
<script src="{base}/assets/js/clinical/lon03b-antecedents.js"></script></body></html>'''

def check(ok, name):
    if not ok:
        raise AssertionError(name)
    print('PASS', name)

def set_window(state):
    env = os.environ.copy()
    env['MXMED_CLINICAL_WRITE_WINDOW_CONTROL'] = 'FILE'
    env['MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH'] = window_path
    subprocess.run(['php', '-r', 'require $argv[1];clinical_m6_write_window_set_state($argv[2]);',
                    str(root / 'api/_lib/clinical_m6_write_window.php'), state], env=env, check=True)

def audit_count():
    return int(subprocess.check_output(['mysql', '--batch', '--skip-column-names', qa_db,
        '-e', 'SELECT COUNT(*) FROM clinical_longitudinal_audit_events']).decode().strip())

def fact_count():
    return int(subprocess.check_output(['mysql', '--batch', '--skip-column-names', qa_db,
        '-e', 'SELECT COUNT(*) FROM clinical_patient_antecedent_facts']).decode().strip())

def page_for(browser, user, width=1440, height=900):
    context = browser.new_context(viewport={'width': width, 'height': height})
    context.add_cookies([{'name':'PHPSESSID','value':f'lon03b-{user}','url':base}])
    page = context.new_page()
    page.route('**/longitudinal-summary', lambda route: route.fulfill(status=200,
        content_type='application/json', body=json.dumps({'ok':True,'data':{}})))
    page.goto(base + '/modules/clinical/README.md')
    page.set_content(fixture, wait_until='load')
    expect(page.locator('[data-lon03b-status]')).to_have_text('Datos longitudinales actualizados.')
    return context, page

def family(page):
    return page.locator('.lon03b-category').filter(has=page.locator('h4', has_text='Familiares'))

def no_overflow(page):
    return page.evaluate('document.documentElement.scrollWidth <= window.innerWidth')

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(channel='chrome', headless=True)
    context_a, a = page_for(browser, 'a')
    failures = []
    starts = []
    a.on('pageerror', lambda error: failures.append(str(error)))
    a.on('console', lambda msg: failures.append(msg.text) if msg.type == 'error' and not
        (msg.text.startswith('Failed to load resource:') and ('503' in msg.text or '404' in msg.text)) else None)
    a.on('request', lambda req: starts.append(req.url) if req.method == 'POST' and req.url.endswith('/encounters') else None)
    a.on('dialog', lambda dialog: dialog.accept())

    check(a.locator('.lon03b-category').count() == 8, 'canonical eight antecedent categories')
    check(family(a).locator('.lon03b-review-state').inner_text() == 'Sin revisar', 'initial antecedent UNKNOWN')
    check(a.locator('.lon03b-allergy-state').inner_text() == 'Sin revisar', 'initial allergy UNKNOWN')
    check('Sin alergias conocidas' not in a.locator('[data-lon03b-allergies]').inner_text(), 'absence never implies no allergies')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-allergies]')).to_contain_text('Sin revisar')
    check('Sin revisar' in a.locator('[data-lon02-antecedents]').inner_text(), 'LON02 unknown projection')

    family(a).get_by_role('button', name='Confirmar sin datos conocidos').click()
    expect(family(a).locator('.lon03b-review-state')).to_have_text('Sin datos conocidos, confirmado')
    check('CONFIRMED_NONE' == a.request.get(base + '/api/clinical/index.php/patients/p_a/longitudinal/antecedents').json()['data']['knowledge_state_by_category']['FAMILY'], 'explicit none persists')

    family(a).get_by_role('button', name='Agregar dato').click()
    expect(a.locator('[data-lon03b-dialog]')).to_be_visible()
    a.keyboard.press('Escape')
    expect(a.locator('[data-lon03b-dialog]')).not_to_be_visible()
    check('Agregar dato' in a.evaluate('document.activeElement.textContent'), 'dialog returns keyboard focus')
    family(a).get_by_role('button', name='Agregar dato').click()
    a.locator('#lon03b-fact-content').fill('Antecedente sintético A')
    a.locator('#lon03b-provenance').select_option('PATIENT_REPORTED')
    a.locator('[data-lon03b-save]').click()
    expect(family(a).locator('.lon03b-item-value')).to_have_text('Antecedente sintético A')
    family(a).get_by_role('button', name='Ver cambios').click()
    expect(family(a).locator('.lon03b-history')).to_contain_text('Registro inicial')
    check('Registro inicial' in family(a).locator('.lon03b-history').inner_text(), 'antecedent audit history visible')
    expect(family(a).locator('.lon03b-review-state')).to_have_text('Revisión pendiente')
    check('Reportado por el paciente' in family(a).inner_text(), 'fact provenance visible')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-antecedents]')).to_contain_text('Revisión pendiente')
    family(a).get_by_role('button', name='Confirmar revisión de datos').click()
    expect(family(a).locator('.lon03b-review-state')).to_have_text('Datos revisados')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-antecedents]')).to_contain_text('Antecedente sintético A')

    context_b, b = page_for(browser, 'b', 1366, 768)
    b.on('dialog', lambda dialog: dialog.accept())
    family(b).get_by_role('button', name='Editar').click()
    b.locator('#lon03b-fact-content').fill('Borrador no guardado B')
    b.locator('#lon03b-reason').fill('Corrección B')
    family(a).get_by_role('button', name='Editar').click()
    a.locator('#lon03b-fact-content').fill('Valor nuevo A')
    a.locator('#lon03b-reason').fill('Corrección A')
    a.locator('[data-lon03b-save]').click()
    expect(family(a).locator('.lon03b-item-value')).to_have_text('Valor nuevo A')
    b.locator('[data-lon03b-save]').click()
    expect(b.locator('[data-lon03b-conflict]')).to_be_visible()
    check(b.locator('#lon03b-fact-content').input_value() == 'Borrador no guardado B', 'stale antecedent draft preserved')
    check(a.request.get(base + '/api/clinical/index.php/patients/p_a/longitudinal/antecedents').json()['data']['items'][0]['content'] == 'Valor nuevo A', 'newer antecedent survives conflict')
    b.locator('[data-lon03b-cancel]').click()

    replay_url = base + '/api/clinical/index.php/patients/p_a/longitudinal/antecedents'
    replay_body = {'category':'OTHER','content':'Idempotent QA fact','state':'CURRENT','provenance':'PATIENT_REPORTED'}
    replay_headers = {'Content-Type':'application/json','Idempotency-Key':'lon03b-replay-1'}
    before_facts, before_events = fact_count(), audit_count()
    first = a.request.post(replay_url, data=replay_body, headers=replay_headers)
    second = a.request.post(replay_url, data=replay_body, headers=replay_headers)
    check(first.status == 200 and second.status == 200 and
          first.json()['data']['item']['fact_id'] == second.json()['data']['item']['fact_id'], 'exact idempotency replay')
    check(fact_count() == before_facts + 1 and audit_count() == before_events + 1, 'replay creates one fact and audit event')
    altered = dict(replay_body, content='Changed protected payload')
    changed = a.request.post(replay_url, data=altered, headers=replay_headers)
    check(changed.status == 409 and changed.json()['error'] == 'IDEMPOTENCY_PAYLOAD_CONFLICT', 'changed payload reuse conflicts')
    check(fact_count() == before_facts + 1 and audit_count() == before_events + 1, 'changed payload creates no duplicate')

    a.locator('[data-lon03b-allergies]').get_by_role('button', name='Confirmar sin alergias conocidas').click()
    expect(a.locator('.lon03b-allergy-state')).to_contain_text('Sin alergias conocidas')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-allergies]')).to_contain_text('Sin alergias conocidas')
    a.locator('[data-lon03b-add-allergy]').click()
    a.locator('#lon03b-substance').fill('Alérgeno sintético')
    a.locator('#lon03b-provenance').select_option('PATIENT_REPORTED')
    a.locator('[data-lon03b-save]').click()
    expect(a.locator('.lon03b-allergy-state')).to_have_text('Revisión pendiente')
    a.locator('[data-lon03b-allergies]').get_by_role('button', name='Ver cambios').click()
    expect(a.locator('[data-lon03b-allergies] .lon03b-history')).to_contain_text('Registro inicial')
    check('Registro inicial' in a.locator('[data-lon03b-allergies] .lon03b-history').inner_text(), 'allergy audit history visible')
    check('No conocida' in a.locator('[data-lon03b-allergies]').inner_text(), 'unknown reaction remains unknown')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-allergies]')).to_contain_text('revisión pendiente')
    a.locator('[data-lon03b-allergies]').get_by_role('button', name='Confirmar revisión de alergias').click()
    expect(a.locator('.lon03b-allergy-state')).to_have_text('Alergias revisadas')
    b.locator('[data-lon03b-refresh]').click()
    b.locator('[data-lon03b-allergies]').get_by_role('button', name='Editar').click()
    b.locator('#lon03b-reaction').fill('Reacción borrador B')
    b.locator('#lon03b-reason').fill('Corrección B')
    a.locator('[data-lon03b-allergies]').get_by_role('button', name='Editar').click()
    a.locator('#lon03b-reaction').fill('Reacción confirmada A')
    a.locator('#lon03b-reason').fill('Corrección A')
    a.locator('[data-lon03b-save]').click()
    b.locator('[data-lon03b-save]').click()
    expect(b.locator('[data-lon03b-conflict]')).to_be_visible()
    check(b.locator('#lon03b-reaction').input_value() == 'Reacción borrador B', 'stale allergy draft preserved')
    b.locator('[data-lon03b-cancel]').click()

    set_window('BLOCK_WRITES')
    before_audit = audit_count()
    api_base = base + '/api/clinical/index.php/patients/p_a/longitudinal/'
    cases = [
        ('POST','antecedents',{'category':'OTHER','content':'Blocked','state':'CURRENT','provenance':'PATIENT_REPORTED'}),
        ('PATCH','antecedents/1',{'category':'FAMILY','content':'Blocked','state':'CURRENT','provenance':'PATIENT_REPORTED','expected_version':1,'reason':'Blocked'}),
        ('POST','antecedent-reviews',{'category':'OTHER','review_state':'CONFIRMED_NONE'}),
        ('PATCH','antecedent-reviews/1',{'category':'FAMILY','review_state':'REVIEWED_WITH_FACTS','expected_version':1,'reason':'Blocked'}),
        ('POST','allergies',{'substance':'Blocked','state':'CURRENT','validation_state':'REPORTED','provenance':'PATIENT_REPORTED'}),
        ('PATCH','allergies/1',{'substance':'Blocked','state':'CURRENT','validation_state':'REPORTED','provenance':'PATIENT_REPORTED','expected_version':1,'reason':'Blocked'}),
        ('POST','allergy-reviews',{'review_state':'CONFIRMED_NONE'}),
        ('PATCH','allergy-reviews/1',{'review_state':'REVIEWED_WITH_ALLERGIES','expected_version':1,'reason':'Blocked'})
    ]
    for index, (method, path, body) in enumerate(cases):
        response = a.request.fetch(api_base + path, method=method, data=body,
            headers={'Content-Type':'application/json','Idempotency-Key':f'blocked-{index}'})
        check(response.status == 503, f'write window blocks {method} {path}')
    check(audit_count() == before_audit, 'write window creates no audit orphan')
    a.locator('[data-lon03b-add-allergy]').click()
    a.locator('#lon03b-substance').fill('Alérgeno bloqueado')
    a.locator('[data-lon03b-save]').click()
    expect(a.locator('[data-lon03b-form-error]')).to_contain_text('pausadas')
    check(a.locator('#lon03b-substance').input_value() == 'Alérgeno bloqueado', 'blocked write preserves draft')
    a.locator('[data-lon03b-cancel]').click()
    set_window('OPEN')

    a.locator('#p-expediente').evaluate('(node) => node.dataset.patientId = "p_b"')
    expect(a.locator('[data-lon03b-status]')).to_have_text('No se pudieron cargar los datos longitudinales.')
    check(a.locator('[data-lon03b-content]').is_hidden(), 'foreign patient content not disclosed')
    check(len(starts) == 0, 'navigation never starts encounter')
    check(not failures, 'no browser console/page errors: ' + '; '.join(failures))

    for width, height in [(1440,900),(1366,768),(820,1180),(390,844)]:
        context, page = page_for(browser, 'a', width, height)
        check(no_overflow(page), f'no horizontal overflow {width}x{height}')
        check(page.locator('.lon03b-category').count() == 8, f'categories visible {width}x{height}')
        if width in (1440, 390):
            page.screenshot(path=f'/tmp/lon03b-qa-{width}x{height}.png', full_page=True)
        page.locator('[data-lon03b-add-allergy]').click()
        check(page.locator('[data-lon03b-dialog]').is_visible(), f'dialog usable {width}x{height}')
        check(no_overflow(page), f'dialog no horizontal overflow {width}x{height}')
        context.close()
    check(a.request.get(base + '/api/clinical/index.php/patients/p_a/longitudinal/antecedents').status == 200, 'authorized patient remains readable')
    check(a.request.get(base + '/api/clinical/index.php/patients/p_b/longitudinal/antecedents').status == 404, 'foreign patient API rejected')
    context_a.close(); context_b.close(); browser.close()
    print('LON03B_DISPOSABLE_BROWSER_GATE=PASS')
