"""Disposable browser/API gate for the real LON04B markup and canonical routes."""
import json
import os
import subprocess
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

base = os.environ['LON04B_QA_BASE']
root = Path(os.environ['LON04B_QA_ROOT'])
db = os.environ['LON04B_QA_DB']
window_path = os.environ['LON04B_QA_WINDOW_PATH']
source = (root / 'index.html').read_text()
start = source.index('<div class="tab-pane fade" id="t-resumen-longitudinal">')
end = source.index('<div class="tab-pane fade show active" id="t-datos">', start)
panes = source[start:end]
fixture = f'''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="{base}/assets/css/expediente-paciente-visual-normalization.css">
<style>body{{margin:0;background:#eef8fa;font:16px Arial,sans-serif}}#p-expediente{{max-width:1400px;margin:auto;padding:18px}}.d-none{{display:none!important}}.btn{{display:inline-block;border:1px solid #06aeb8;border-radius:9px;background:white;color:#06536e;padding:7px 12px;cursor:pointer}}.btn-primary{{background:#06aeb8;color:white}}.btn-link{{border:0;background:none}}</style></head>
<body><div id="p-expediente" data-patient-id="p_a">{panes}
<section id="m7-workspace"><p data-m7-context>Consulta histórica · Finalizada · 21 sep 2026</p><textarea data-m7-editor-text>Synthetic saved assessment</textarea><button type="button" data-lon04b-m7-promote data-encounter-id="101" data-section-id="201">Agregar a problemas</button></section>
</div><script src="{base}/assets/js/clinical/lon02-summary.js"></script><script src="{base}/assets/js/clinical/lon04b-problems.js"></script></body></html>'''

def check(value, name):
    if not value:
        raise AssertionError(name)
    print('PASS', name)

def count(table):
    allowed = {'clinical_patient_problems', 'clinical_patient_problem_audit_events', 'clinical_record_entries'}
    if table not in allowed:
        raise ValueError(table)
    return int(subprocess.check_output(['mysql','--batch','--skip-column-names',db,'-e',f'SELECT COUNT(*) FROM {table}']).decode().strip())

def set_window(state):
    env = os.environ.copy()
    env['MXMED_CLINICAL_WRITE_WINDOW_CONTROL'] = 'FILE'
    env['MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH'] = window_path
    subprocess.run(['php','-r','require $argv[1];clinical_m6_write_window_set_state($argv[2]);',
                    str(root / 'api/_lib/clinical_m6_write_window.php'),state],env=env,check=True)

def page_for(browser, user='a', width=1440, height=900):
    context = browser.new_context(viewport={'width':width,'height':height})
    context.add_cookies([{'name':'PHPSESSID','value':f'lon04b-{user}','url':base}])
    page = context.new_page()
    page.route('**/longitudinal-summary',lambda route: route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{}})))
    page.goto(base + '/modules/clinical/README.md')
    page.set_content(fixture,wait_until='load')
    expect(page.locator('[data-lon04b-status]')).to_have_text('Problemas longitudinales actualizados.')
    return context,page

def group(page,state):
    return page.locator(f'[data-lon04b-{state.lower()}]')

def no_overflow(page):
    return page.evaluate('document.documentElement.scrollWidth <= window.innerWidth')

with sync_playwright() as playwright:
    browser=playwright.chromium.launch(channel='chrome',headless=True)
    context_a,a=page_for(browser)
    failures=[];starts=[]
    a.on('pageerror',lambda failure: failures.append(str(failure)))
    a.on('console',lambda message: failures.append(message.text) if message.type=='error' and not (message.text.startswith('Failed to load resource:') and ('503' in message.text or '404' in message.text)) else None)
    a.on('request',lambda request: starts.append(request.url) if request.method=='POST' and request.url.endswith('/encounters') else None)
    a.on('dialog',lambda popup: popup.accept())
    check('no confirma ausencia' in group(a,'active').inner_text(),'empty problems do not imply clinical none')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-problems]')).to_contain_text('no confirma ausencia clínica')
    check(a.locator('[data-lon02-problems] button').count()==0,'LON02 remains read-only')

    a.locator('[data-lon04b-add]').click()
    expect(a.locator('[data-lon04b-dialog]')).to_be_visible()
    a.keyboard.press('Escape')
    expect(a.locator('[data-lon04b-dialog]')).not_to_be_visible()
    check(a.evaluate('document.activeElement.hasAttribute("data-lon04b-add")'),'dialog returns keyboard focus')
    a.locator('[data-lon04b-add]').click()
    a.locator('#lon04b-label').fill('Synthetic longitudinal problem')
    a.locator('[data-lon04b-save]').click()
    expect(group(a,'active')).to_contain_text('Synthetic longitudinal problem')
    rows=a.request.get(base+'/api/clinical/index.php/patients/p_a/longitudinal/problems').json()['data']['items']
    item=next(row for row in rows if row['label']=='Synthetic longitudinal problem')
    problem_id=int(item['problem_id'])
    check(item['status']=='ACTIVE' and int(item['row_version'])==1,'explicit create persisted')
    check(count('clinical_patient_problem_audit_events')==1,'create audit persisted')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-problems]')).to_contain_text('Synthetic longitudinal problem')

    a.locator('[data-lon04b-m7-promote]').click()
    expect(a.locator('[data-lon04b-dialog]')).to_be_visible()
    expect(a.locator('[data-lon04b-context]')).to_contain_text('Valoración guardada: Synthetic saved assessment')
    a.locator('#lon04b-label').fill('Synthetic promoted problem')
    a.locator('[data-lon04b-save]').click()
    expect(group(a,'active')).to_contain_text('Synthetic promoted problem')
    rows=a.request.get(base+'/api/clinical/index.php/patients/p_a/longitudinal/problems').json()['data']['items']
    promoted=next(row for row in rows if row['label']=='Synthetic promoted problem')
    check(promoted['provenance']=='ENCOUNTER_DERIVED_EXPLICIT_PROMOTION' and int(promoted['source_section_id'])==201,'explicit M7 assessment promotion')
    check(count('clinical_record_entries')==1,'legacy and source encounter unchanged')

    item_card=group(a,'active').locator('.lon04b-item').filter(has_text='Synthetic longitudinal problem')
    item_card.get_by_role('button',name='Marcar inactivo').click()
    a.locator('#lon04b-reason').fill('Currently quiescent')
    a.locator('[data-lon04b-save]').click()
    expect(group(a,'inactive')).to_contain_text('Synthetic longitudinal problem')
    check('Synthetic longitudinal problem' not in group(a,'active').inner_text(),'ACTIVE to INACTIVE explicit')
    group(a,'inactive').locator('.lon04b-item').filter(has_text='Synthetic longitudinal problem').get_by_role('button',name='Activar').click()
    a.locator('#lon04b-reason').fill('Clinically active again')
    a.locator('[data-lon04b-save]').click()
    expect(group(a,'active')).to_contain_text('Synthetic longitudinal problem')
    item_card=group(a,'active').locator('.lon04b-item').filter(has_text='Synthetic longitudinal problem')
    item_card.get_by_role('button',name='Resolver').click()
    a.locator('#lon04b-reason').fill('Clinician determined concluded')
    a.locator('[data-lon04b-save]').click()
    expect(group(a,'resolved')).to_contain_text('Synthetic longitudinal problem')
    resolved_card=group(a,'resolved').locator('.lon04b-item').filter(has_text='Synthetic longitudinal problem')
    check(resolved_card.get_by_role('button',name='Marcar inactivo').count()==0,'RESOLVED to INACTIVE unavailable')
    resolved_card.get_by_role('button',name='Reactivar').click()
    a.locator('#lon04b-reason').fill('Explicit recurrence')
    a.locator('[data-lon04b-save]').click()
    expect(group(a,'active')).to_contain_text('Synthetic longitudinal problem')
    current=a.request.get(base+f'/api/clinical/index.php/patients/p_a/longitudinal/problems/{problem_id}').json()['data']['item']
    check(int(current['problem_id'])==problem_id and current['status']=='ACTIVE','resolved reactivation retains identity')
    item_card=group(a,'active').locator('.lon04b-item').filter(has_text='Synthetic longitudinal problem')
    item_card.get_by_role('button',name='Ver historial').click()
    expect(item_card.locator('.lon04b-history')).to_contain_text('Reactivado')
    history=item_card.locator('.lon04b-history').inner_text()
    check(all(word in history for word in ['Creado','Marcado inactivo','Activado','Resuelto','Reactivado']),'immutable audit history shown')

    context_b,b=page_for(browser,'b',1366,768)
    b.on('dialog',lambda popup: popup.accept())
    b_card=group(b,'active').locator('.lon04b-item').filter(has_text='Synthetic longitudinal problem')
    b_card.get_by_role('button',name='Editar').click()
    b.locator('#lon04b-label').fill('Stale local draft B')
    b.locator('#lon04b-reason').fill('Stale correction')
    item_card.get_by_role('button',name='Resolver').click()
    a.locator('#lon04b-reason').fill('New resolution A')
    a.locator('[data-lon04b-save]').click()
    b.locator('[data-lon04b-save]').click()
    expect(b.locator('[data-lon04b-conflict]')).to_be_visible()
    check(b.locator('#lon04b-label').input_value()=='Stale local draft B','stale edit draft preserved')
    expect(b.locator('[data-lon04b-server-state]')).to_contain_text('Resuelto')
    check(a.request.get(base+f'/api/clinical/index.php/patients/p_a/longitudinal/problems/{problem_id}').json()['data']['item']['status']=='RESOLVED','newer server state preserved')
    b.locator('[data-lon04b-cancel]').click()

    url=base+'/api/clinical/index.php/patients/p_a/longitudinal/problems'
    body={'label':'Idempotent synthetic problem','provenance':'EXPLICIT_LONGITUDINAL_ENTRY'}
    headers={'Content-Type':'application/json','Idempotency-Key':'lon04b-replay'}
    before_count,before_audit=count('clinical_patient_problems'),count('clinical_patient_problem_audit_events')
    first=a.request.post(url,data=body,headers=headers);second=a.request.post(url,data=body,headers=headers)
    check(first.status==200 and second.status==200 and first.json()['data']['item']['problem_id']==second.json()['data']['item']['problem_id'],'exact idempotency replay')
    check(count('clinical_patient_problems')==before_count+1 and count('clinical_patient_problem_audit_events')==before_audit+1,'replay creates no duplicate state/audit')
    changed=a.request.post(url,data=dict(body,label='Changed payload'),headers=headers)
    check(changed.status==409 and changed.json()['error']=='IDEMPOTENCY_PAYLOAD_CONFLICT','changed payload conflict')
    foreign=a.request.post(url+'/promotions',data={'label':'Foreign','source_encounter_id':102,'source_section_id':202},headers={'Content-Type':'application/json','Idempotency-Key':'foreign-source'})
    check(foreign.status==409 and foreign.json()['error']=='FOREIGN_SOURCE','foreign encounter promotion rejected')

    set_window('BLOCK_WRITES')
    before_audit=count('clinical_patient_problem_audit_events')
    for path,payload in [(url,body),(url+'/promotions',{'label':'Blocked','source_encounter_id':101,'source_section_id':201}),
                         (url+f'/{problem_id}/reactivate',{'expected_version':6,'reason':'Blocked'})]:
        response=a.request.post(path,data=payload,headers={'Content-Type':'application/json','Idempotency-Key':'blocked-'+path[-12:]})
        check(response.status==503,'write window blocks '+path.rsplit('/',1)[-1])
    a.locator('[data-lon04b-add]').click()
    a.locator('#lon04b-label').fill('Blocked local draft')
    a.locator('[data-lon04b-save]').click()
    expect(a.locator('[data-lon04b-form-error]')).to_contain_text('pausadas')
    check(a.locator('#lon04b-label').input_value()=='Blocked local draft','blocked write preserves local draft')
    check(count('clinical_patient_problem_audit_events')==before_audit,'blocked writes create no orphan audit')
    a.locator('[data-lon04b-cancel]').click()
    set_window('OPEN')

    a.locator('#p-expediente').evaluate('(node) => node.dataset.patientId = "p_b"')
    expect(a.locator('[data-lon04b-status]')).to_have_text('No se pudieron cargar los problemas.')
    check(a.locator('[data-lon04b-groups]').is_hidden(),'foreign patient content hidden')
    check(a.request.get(base+'/api/clinical/index.php/patients/p_b/longitudinal/problems').status==404,'foreign patient API hidden')
    check(not starts,'navigation never starts encounter')
    check(not failures,'no unclassified browser errors: '+'; '.join(failures))

    for width,height in [(1440,900),(1366,768),(820,1180),(390,844)]:
        context,page=page_for(browser,'a',width,height)
        check(no_overflow(page),f'no horizontal overflow {width}x{height}')
        check(page.locator('.lon04b-group').count()==3,f'three state groups {width}x{height}')
        if width in (1440,390): page.screenshot(path=f'/tmp/lon04b-qa-{width}x{height}.png',full_page=True)
        page.locator('[data-lon04b-add]').click()
        check(page.locator('[data-lon04b-dialog]').is_visible(),f'dialog usable {width}x{height}')
        check(no_overflow(page),f'dialog no horizontal overflow {width}x{height}')
        context.close()
    context_a.close();context_b.close();browser.close()
    print('LON04B_DISPOSABLE_BROWSER_GATE=PASS')
