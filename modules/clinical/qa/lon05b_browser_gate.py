"""Disposable, authenticated LON05B browser and API gate."""
import json
import os
import subprocess
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

base = os.environ['LON05B_QA_BASE']
root = Path(os.environ['LON05B_QA_ROOT'])
db = os.environ['LON05B_QA_DB']
window_path = os.environ['LON05B_QA_WINDOW_PATH']
source = (root / 'index.html').read_text()
start = source.index('<div class="tab-pane fade" id="t-resumen-longitudinal">')
end = source.index('<div class="tab-pane fade show active" id="t-datos">', start)
fixture = f'''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="{base}/assets/css/expediente-paciente-visual-normalization.css">
<style>body{{margin:0;background:#eef8fa;font:16px Arial,sans-serif}}#p-expediente{{max-width:1400px;margin:auto;padding:18px}}.d-none{{display:none!important}}.btn{{display:inline-block;border:1px solid #06aeb8;border-radius:9px;background:white;color:#06536e;padding:7px 12px;cursor:pointer}}.btn-primary{{background:#06aeb8;color:white}}.btn-link{{border:0;background:none}}</style></head>
<body><div id="p-expediente" data-patient-id="p_a">{source[start:end]}<button id="m7-prescription" type="button">Receta M7</button></div>
<script src="{base}/assets/js/clinical/lon02-summary.js"></script><script src="{base}/assets/js/clinical/lon05b-medications.js"></script></body></html>'''

def check(value, name):
    if not value:
        raise AssertionError(name)
    print('PASS', name, flush=True)

def count(table):
    if table not in {'clinical_patient_medications','clinical_patient_medication_audit_events','clinical_medication_reconciliations'}:
        raise ValueError(table)
    return int(subprocess.check_output(['mysql','--batch','--skip-column-names',db,'-e',f'SELECT COUNT(*) FROM {table}']).decode().strip())

def set_window(state):
    env = os.environ.copy()
    env['MXMED_CLINICAL_WRITE_WINDOW_CONTROL'] = 'FILE'
    env['MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH'] = window_path
    subprocess.run(['php','-r','require $argv[1];clinical_m6_write_window_set_state($argv[2]);',str(root / 'api/_lib/clinical_m6_write_window.php'),state],env=env,check=True)

def page_for(browser, user='a', width=1440, height=900):
    context = browser.new_context(viewport={'width':width,'height':height})
    context.add_cookies([{'name':'PHPSESSID','value':f'lon05b-{user}','url':base}])
    page = context.new_page()
    page.route('**/longitudinal/tasks',lambda route: route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'items':[]}})))
    page.route('**/longitudinal-summary',lambda route: route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{}})))
    page.goto(base + '/modules/clinical/README.md')
    page.set_content(fixture,wait_until='load')
    expect(page.locator('[data-lon05b-status]')).to_have_text('Medicación longitudinal actualizada.')
    page.on('dialog',lambda popup: popup.accept())
    return context,page

def listing(page):
    response=page.request.get(base+'/api/clinical/index.php/patients/p_a/longitudinal/medications')
    check(response.status==200,'medication listing authorized')
    return response.json()['data']

def row_for(page,name):
    return next(row for row in listing(page)['items'] if row['medication_name']==name)

def card(page,name):
    return page.locator('.lon05b-item').filter(has=page.locator('h5',has_text=name)).first

def no_overflow(page):
    return page.evaluate('document.documentElement.scrollWidth <= window.innerWidth')

with sync_playwright() as playwright:
    browser=playwright.chromium.launch(channel='chrome',headless=True)
    ctx_a,a=page_for(browser)
    failures=[];starts=[]
    a.on('pageerror',lambda failure:failures.append(str(failure)))
    a.on('console',lambda message:failures.append(message.text) if message.type=='error' and not (message.text.startswith('Failed to load resource:') and any(x in message.text for x in ('503','404','409'))) else None)
    a.on('request',lambda request:starts.append(request.url) if request.method=='POST' and '/encounters' in request.url and request.url.endswith('/start') else None)
    check('no confirma ausencia' in a.locator('[data-lon05b-active-confirmed]').inner_text(),'empty list does not claim no medication')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-medications]')).to_contain_text('no confirma que el paciente no tome')
    check(a.locator('[data-lon02-medications] button').count()==0,'LON02 medication card read-only')

    a.locator('[data-lon05b-add-reported]').click()
    expect(a.locator('[data-lon05b-dialog]')).to_be_visible()
    a.keyboard.press('Escape')
    expect(a.locator('[data-lon05b-dialog]')).not_to_be_visible()
    check(a.evaluate('document.activeElement.hasAttribute("data-lon05b-add-reported")'),'dialog returns focus')
    a.locator('[data-lon05b-add-reported]').click()
    a.locator('[data-lon05b-name]').fill('Metformina referida')
    a.locator('[data-lon05b-dose]').fill('500')
    a.locator('[data-lon05b-save]').click()
    expect(a.locator('[data-lon05b-reported-by-patient]')).to_contain_text('Metformina referida')
    reported=row_for(a,'Metformina referida')
    check(reported['state']=='REPORTED_BY_PATIENT','patient reported create state')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-medications]')).to_contain_text('no confirmado como uso actual')
    card(a,'Metformina referida').get_by_role('button',name='Confirmar como actual').click()
    a.locator('[data-lon05b-reason]').fill('Uso confirmado con paciente')
    a.locator('[data-lon05b-save]').click()
    expect(a.locator('[data-lon05b-active-confirmed]')).to_contain_text('Metformina referida')
    check(row_for(a,'Metformina referida')['state']=='ACTIVE_CONFIRMED','explicit confirmation')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-medications]')).to_contain_text('Uso actual confirmado')

    a.locator('#m7-prescription').evaluate('node => node.dispatchEvent(new CustomEvent("lon05b:prescription-selected",{bubbles:true,detail:{documentId:301,patientId:"p_a",title:"Receta sintética",trigger:node}}))')
    expect(a.locator('[data-lon05b-dialog]')).to_be_visible()
    expect(a.locator('[data-lon05b-context]')).to_contain_text('uso actual aún no está confirmado')
    a.locator('[data-lon05b-name]').fill('Amoxicilina prescrita')
    a.locator('[data-lon05b-save]').click()
    expect(a.locator('[data-lon05b-prescribed-not-confirmed-active]')).to_contain_text('Amoxicilina prescrita')
    prescribed=row_for(a,'Amoxicilina prescrita')
    check(prescribed['state']=='PRESCRIBED_NOT_CONFIRMED_ACTIVE' and int(prescribed['source_document_id'])==301,'prescription explicitly added, unconfirmed')
    a.locator('[data-lon02-refresh]').click()
    expect(a.locator('[data-lon02-medications]')).to_contain_text('Prescrito; uso actual no confirmado')
    card(a,'Amoxicilina prescrita').get_by_role('button',name='Completar tratamiento').click()
    a.locator('[data-lon05b-reason]').fill('Curso de tratamiento concluido')
    a.locator('[data-lon05b-save]').click()
    expect(a.locator('[data-lon05b-completed]')).to_contain_text('Amoxicilina prescrita')
    check(card(a,'Amoxicilina prescrita').get_by_role('button',name='Reactivar').count()==0,'terminal reactivation absent')
    terminal_id=int(prescribed['medication_id'])
    card(a,'Amoxicilina prescrita').get_by_role('button',name='Registrar nuevo episodio').click()
    a.locator('[data-lon05b-save]').click()
    expect(a.locator('[data-lon05b-active-confirmed]')).to_contain_text('Amoxicilina prescrita')
    check(count('clinical_patient_medications')==3,'new episode retains old terminal record')
    check(row_for(a,'Amoxicilina prescrita')['state']=='COMPLETED','old terminal episode immutable')
    card(a,'Metformina referida').get_by_role('button',name='Suspender').click()
    a.locator('[data-lon05b-reason]').fill('Decisión clínica explícita')
    a.locator('[data-lon05b-save]').click()
    expect(a.locator('[data-lon05b-discontinued]')).to_contain_text('Metformina referida')
    check(row_for(a,'Metformina referida')['state']=='DISCONTINUED','explicit discontinue')
    card(a,'Metformina referida').get_by_role('button',name='Ver historial').click()
    expect(card(a,'Metformina referida').locator('.lon05b-history')).to_contain_text('Suspensión')
    check(count('clinical_patient_medication_audit_events')>=5,'immutable lifecycle audit')

    ctx_b,b=page_for(browser,'b')
    b.locator('[data-lon05b-active-confirmed] .lon05b-item').first.get_by_role('button',name='Editar régimen').click()
    b.locator('[data-lon05b-dose]').fill('750')
    active_id=int(next(row['medication_id'] for row in listing(a)['items'] if row['state']=='ACTIVE_CONFIRMED'))
    a_card=a.locator('[data-lon05b-active-confirmed] .lon05b-item').first
    a_card.get_by_role('button',name='Editar régimen').click()
    a.locator('[data-lon05b-dose]').fill('600')
    a.locator('[data-lon05b-reason]').fill('Ajuste de dosis')
    a.locator('[data-lon05b-save]').click()
    b.locator('[data-lon05b-reason]').fill('Ajuste concurrente')
    b.locator('[data-lon05b-save]').click()
    expect(b.locator('[data-lon05b-conflict]')).to_be_visible()
    check(b.locator('[data-lon05b-dose]').input_value()=='750','stale episode draft preserved')
    check(next(row for row in listing(a)['items'] if int(row['medication_id'])==active_id)['dose']=='600','newer episode state preserved')
    b.locator('[data-lon05b-cancel]').click()

    a.locator('[data-lon05b-reconcile]').click()
    expect(a.locator('[data-lon05b-reconcile-dialog]')).to_be_visible()
    a.locator('[data-lon05b-reconcile-add]').fill('Nueva medicación conciliada')
    a.locator('[data-lon05b-reconcile-save]').click()
    expect(a.locator('[data-lon05b-active-confirmed]')).to_contain_text('Nueva medicación conciliada')
    check(count('clinical_medication_reconciliations')==1,'atomic reconciliation stored')
    a.locator('[data-lon05b-history]').click()
    expect(a.locator('[data-lon05b-reconciliation-history]')).to_contain_text('ADD')

    b.locator('[data-lon05b-refresh]').click()
    b.locator('[data-lon05b-reconcile]').click()
    expect(b.locator('[data-lon05b-reconcile-dialog]')).to_be_visible()
    b.locator('[data-lon05b-reconcile-add]').fill('Borrador de conciliación B')
    a.locator('[data-lon05b-reconcile]').click()
    a.locator('[data-lon05b-reconcile-add]').fill('Cambio concurrente A')
    a.locator('[data-lon05b-reconcile-save]').click()
    b.locator('[data-lon05b-reconcile-save]').click()
    expect(b.locator('[data-lon05b-reconcile-conflict]')).to_be_visible()
    check(b.locator('[data-lon05b-reconcile-add]').input_value()=='Borrador de conciliación B','stale reconciliation draft preserved')
    check(not any(row['medication_name']=='Borrador de conciliación B' for row in listing(a)['items']),'stale reconciliation did not overwrite')
    b.locator('[data-lon05b-reconcile-cancel]').click()

    b.locator('[data-lon05b-refresh]').click()
    b.locator('[data-lon05b-reconcile]').click()
    expect(b.locator('[data-lon05b-reconcile-dialog]')).to_be_visible()
    b.locator('[data-lon05b-reconcile-add]').fill('Borrador frente a edición')
    a.locator('[data-lon05b-active-confirmed] .lon05b-item').first.get_by_role('button',name='Editar régimen').click()
    a.locator('[data-lon05b-dose]').fill('650')
    a.locator('[data-lon05b-reason]').fill('Edición directa concurrente')
    a.locator('[data-lon05b-save]').click()
    b.locator('[data-lon05b-reconcile-save]').click()
    expect(b.locator('[data-lon05b-reconcile-conflict]')).to_be_visible()
    check(b.locator('[data-lon05b-reconcile-add]').input_value()=='Borrador frente a edición','direct edit versus reconciliation conflict preserves draft')
    b.locator('[data-lon05b-reconcile-cancel]').click()

    url=base+'/api/clinical/index.php/patients/p_a/longitudinal/medications'
    headers={'Content-Type':'application/json','Idempotency-Key':'lon05b-replay'}
    before,before_audit=count('clinical_patient_medications'),count('clinical_patient_medication_audit_events')
    body={'medication_name':'Idempotente'}
    first=a.request.post(url,data=body,headers=headers);second=a.request.post(url,data=body,headers=headers)
    check(first.status==200 and second.status==200 and first.json()['data']['item']['medication_id']==second.json()['data']['item']['medication_id'],'exact idempotency replay')
    check(count('clinical_patient_medications')==before+1 and count('clinical_patient_medication_audit_events')==before_audit+1,'replay creates no duplicate')
    changed=a.request.post(url,data={'medication_name':'Cambiado'},headers=headers)
    check(changed.status==409 and changed.json()['error']=='IDEMPOTENCY_PAYLOAD_CONFLICT','changed payload rejected')
    foreign=a.request.post(url+'/from-prescription',data={'medication_name':'Extranjero','source_document_id':302,'initial_state':'PRESCRIBED_NOT_CONFIRMED_ACTIVE'},headers={'Content-Type':'application/json','Idempotency-Key':'lon05b-foreign'})
    check(foreign.status==409 and foreign.json()['error']=='FOREIGN_SOURCE','foreign prescription rejected')
    forced=a.request.post(url+f'/{terminal_id}/confirm-active',data={'expected_version':2,'reason':'Intento inválido'},headers={'Content-Type':'application/json','Idempotency-Key':'lon05b-terminal'})
    check(forced.status==409,'terminal episode cannot reactivate')
    check(a.request.get(base+'/api/clinical/index.php/patients/p_b/longitudinal/medications').status==404,'foreign patient hidden')

    set_window('BLOCK_WRITES')
    before_audit=count('clinical_patient_medication_audit_events')
    a.locator('[data-lon05b-add-reported]').click()
    a.locator('[data-lon05b-name]').fill('Borrador bloqueado')
    a.locator('[data-lon05b-save]').click()
    expect(a.locator('[data-lon05b-form-error]')).to_contain_text('pausadas')
    check(a.locator('[data-lon05b-name]').input_value()=='Borrador bloqueado','blocked write preserves draft')
    a.locator('[data-lon05b-cancel]').click()
    a.locator('[data-lon05b-reconcile]').click()
    a.locator('[data-lon05b-reconcile-add]').fill('Conciliación bloqueada')
    a.locator('[data-lon05b-reconcile-save]').click()
    expect(a.locator('[data-lon05b-reconcile-error]')).to_contain_text('pausadas')
    check(a.locator('[data-lon05b-reconcile-add]').input_value()=='Conciliación bloqueada','blocked reconciliation preserves draft')
    check(count('clinical_patient_medication_audit_events')==before_audit,'blocked writes no audit')
    a.locator('[data-lon05b-reconcile-cancel]').click()
    set_window('OPEN')

    a.locator('#p-expediente').evaluate('node => node.dataset.patientId = "p_b"')
    expect(a.locator('[data-lon05b-status]')).to_have_text('No se pudo cargar la medicación.')
    check(a.locator('[data-lon05b-groups]').is_hidden(),'foreign patient content hidden')
    check(not starts,'medication navigation and actions never start encounter')
    check(not failures,'no browser exceptions: '+'; '.join(failures))
    for width,height in [(1440,900),(1366,768),(820,1180),(390,844)]:
        context,page=page_for(browser,'a',width,height)
        check(no_overflow(page),f'no horizontal overflow {width}x{height}')
        page.locator('[data-lon05b-reconcile]').click()
        expect(page.locator('[data-lon05b-reconcile-dialog]')).to_be_visible()
        check(no_overflow(page),f'reconciliation dialog no horizontal overflow {width}x{height}')
        context.close()
    ctx_a.close();ctx_b.close();browser.close()
    print('LON05B_DISPOSABLE_BROWSER_GATE=PASS')
