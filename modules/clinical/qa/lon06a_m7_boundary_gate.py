"""Real authenticated M7 order/finalize and Agenda boundary for LON06A."""
import os
import subprocess
from playwright.sync_api import sync_playwright

base=os.environ['LON06A_M7_BASE']
db=os.environ['LON06A_M7_DB']

def check(value,name):
    if not value:raise AssertionError(name)
    print('PASS',name,flush=True)

def sql(statement):
    return subprocess.check_output(['mysql','--batch','--skip-column-names',db,'-e',statement]).decode().strip()

with sync_playwright() as playwright:
    browser=playwright.chromium.launch(channel='chrome',headless=True)
    context=browser.new_context()
    context.add_cookies([{'name':'PHPSESSID','value':'lon06a-m7','url':base}])
    page=context.new_page();page.goto(base+'/modules/clinical/README.md')
    request=context.request
    task_url=base+'/api/clinical/index.php/patients/p_a/longitudinal/tasks'
    endpoint=base+'/api/clinical/index.php?route=encounters/enc:1/'
    check(request.get(task_url).json()['data']['items']==[],'Plan content does not create task')
    order=request.post(endpoint+'documents',data={'document_type':'order','title':'Orden sintética sin resultado','summary':'Estudio pendiente','payload':{'source':'lon06a_qa'}},headers={'Content-Type':'application/json','Idempotency-Key':'lon06a-m7-order'})
    print('ORDER_STATUS',order.status,'ORDER_BODY',order.text()[:700],flush=True)
    check(order.status in (200,201) and order.json().get('ok') is True,'real authenticated M7 order create')
    check(request.get(task_url).json()['data']['items']==[],'pending order did not create task')
    finalized=request.post(endpoint+'finalize',data={},headers={'Content-Type':'application/json'})
    print('FINALIZE_STATUS',finalized.status,'FINALIZE_BODY',finalized.text()[:700],flush=True)
    check(finalized.status==200 and finalized.json().get('ok') is True,'real authenticated M7 finalize')
    check(request.get(task_url).json()['data']['items']==[],'finalize with Plan content did not create follow-up')
    before=sql('SELECT COUNT(*) FROM clinical_patient_tasks')
    sql("UPDATE agenda_appointments SET start_at=DATE_ADD(start_at,INTERVAL 1 DAY),status='rescheduled' WHERE appointment_id='a_a'")
    sql("UPDATE agenda_appointments SET status='canceled' WHERE appointment_id='a_a'")
    check(sql('SELECT COUNT(*) FROM clinical_patient_tasks')==before,'Agenda reschedule/cancel did not mutate tasks')
    context.close();browser.close()
    print('LON06A_M7_BOUNDARY_GATE=PASS')
