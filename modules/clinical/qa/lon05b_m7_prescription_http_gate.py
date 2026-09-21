"""Authenticated real M7 prescription write, negative medication proof, explicit add."""
import os
import subprocess

from playwright.sync_api import sync_playwright

base=os.environ['LON05B_M7_BASE']
db=os.environ['LON05B_M7_DB']

def check(value,name):
    if not value:
        raise AssertionError(name)
    print('PASS',name,flush=True)

def count():
    return int(subprocess.check_output(['mysql','--batch','--skip-column-names',db,'-e','SELECT COUNT(*) FROM clinical_patient_medications']).decode().strip())

with sync_playwright() as playwright:
    browser=playwright.chromium.launch(channel='chrome',headless=True)
    context=browser.new_context()
    context.add_cookies([{'name':'PHPSESSID','value':'lon05b-m7','url':base}])
    page=context.new_page()
    page.goto(base+'/modules/clinical/README.md')
    request=context.request
    encounter_key='enc:1'
    payload={'document_type':'prescription','title':'Receta sintética M7','summary':'Prescripción de prueba sin inferencia de uso actual','payload':{'medications':[{'name':'Fármaco sintético'}]}}
    result=request.post(base+f'/api/clinical/index.php?route=encounters/{encounter_key}/documents',data=payload,headers={'Content-Type':'application/json','Idempotency-Key':'lon05b-m7-prescription'})
    print('M7_HTTP_STATUS',result.status,'M7_HTTP_BODY',result.text()[:1200],flush=True)
    check(result.status in (200,201) and result.json().get('ok') is True,'real authenticated canonical M7 prescription create')
    doc_id=int(result.json()['data']['document_id'])
    check(count()==0,'M7 prescription did not auto-create medication')
    meds=request.get(base+'/api/clinical/index.php/patients/p_a/longitudinal/medications')
    check(meds.status==200 and meds.json()['data']['items']==[],'canonical medication authority empty after prescription')
    added=request.post(base+'/api/clinical/index.php/patients/p_a/longitudinal/medications/from-prescription',data={'medication_name':'Fármaco sintético','source_document_id':doc_id,'initial_state':'PRESCRIBED_NOT_CONFIRMED_ACTIVE'},headers={'Content-Type':'application/json','Idempotency-Key':'lon05b-m7-explicit-add'})
    print('EXPLICIT_ADD_STATUS',added.status,'EXPLICIT_ADD_BODY',added.text()[:1200],flush=True)
    check(added.status==200 and added.json().get('ok') is True,'explicit prescription-linked medication create')
    check(count()==1 and added.json()['data']['item']['state']=='PRESCRIBED_NOT_CONFIRMED_ACTIVE','medication appears only after explicit add, unconfirmed')
    browser.close()
    print('LON05B_M7_FULL_HTTP_PRESCRIPTION_GATE=PASS')
