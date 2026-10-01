import json
import os
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[3]
BASE=os.environ.get('OR02A_REVIEW_BASE','http://127.0.0.1:18148/index.html?review_patient=plan02ux&qa_tools=hide')

def run_case(browser,with_open):
 page=browser.new_page(viewport={'width':1440,'height':900})
 writes=[]
 def guard(route):
  request=route.request
  if request.method in ('GET','HEAD'):
   route.continue_();return
  writes.append(request)
  route.fulfill(status=201,content_type='application/json',body=json.dumps({'ok':True,'data':{'document':{'document_uuid':'or02a-mock'}}}))
 page.route('**/api/**',guard)
 page.goto(BASE,wait_until='domcontentloaded')
 page.locator('[data-bs-target="#t-estudios"]').click()
 page.wait_for_selector('#t-estudios .vis06-module')
 assert page.get_by_role('button',name='Nueva orden').count()==0
 assert page.get_by_role('button',name='Solicitar estudios').count()==1
 assert page.locator('#t-estudios [data-est-section="solicitar"]').is_hidden()
 page.locator('#t-estudios .vis06-create').click()
 assert page.locator('#t-estudios .vis06-create').is_hidden()
 assert page.locator('#t-estudios [data-est-section="solicitar"]').is_visible()
 assert page.locator('#t-estudios [data-est-section="solicitar"]').get_attribute('class').find('active')>=0
 if with_open:
  page.evaluate("() => {window.mxmedStore.activeEncounterKey='enc:1021';window.mxmedStore.currentEncounterKey='enc:1021';document.querySelector('#p-expediente').dataset.activeEncounterKey='enc:1021';document.querySelector('#mm-p10-bar').dataset.appointmentId='ambient-appointment'}")
 else:
  page.evaluate("() => {window.mxmedStore.activeEncounterKey='';window.mxmedStore.currentEncounterKey='';delete document.querySelector('#p-expediente').dataset.activeEncounterKey;document.querySelector('#mm-p10-bar').dataset.appointmentId=''}")
 assert bool(page.evaluate('getActiveEncounterKey()'))==with_open
 page.locator('#t-estudios [data-est-open-modal]').first.click()
 page.locator('#modalEstudiosLab [data-est-lab-pick="HbA1c"]').click()
 page.locator('#modalEstudiosLab .modal-footer .btn-primary').evaluate('(button)=>{button.click();button.click()}')
 page.wait_for_function("document.querySelector('#t-estudios [data-role=ac-order-feedback]')?.textContent.includes('guardada')")
 assert len(writes)==1,[x.url for x in writes]
 request=writes[0]
 assert request.url.endswith('/doctors/1/patients/p_plan02ux_review/documents'),request.url
 body=request.post_data
 assert 'name="document_type"' in body and 'lab_order' in body
 assert 'name="encounter_key"' not in body and 'name="encounter_id"' not in body and 'name="appointment_id"' not in body
 assert 'ambient-appointment' not in body
 assert not page.locator('#t-estudios .vis06-create').is_visible()
 page.wait_for_function("!document.querySelector('#modalEstudiosLab').classList.contains('show')")
 page.locator('#t-estudios [data-est-open-modal]').first.click()
 page.locator('#modalEstudiosLab [data-est-lab-pick=\"HbA1c\"]').click()
 page.locator('#modalEstudiosLab .modal-footer .btn-primary').click()
 assert len(writes)==1,'identical retry escaped client lock'
 assert 'duplicada' in page.locator('#t-estudios [data-role=ac-order-feedback]').inner_text()
 page.locator('#modalEstudiosLab .btn-close').click()
 page.locator('#t-estudios .vis06-module button').filter(has_text='Volver al listado').click()
 assert page.locator('#t-estudios .vis06-create').is_visible()
 assert page.locator('#t-estudios [data-est-section="solicitar"]').is_hidden()
 page.close()
 print('BROWSER_GENERAL_'+('OPEN' if with_open else 'NO_OPEN')+'=PASS')

with sync_playwright() as playwright:
 browser=playwright.chromium.launch(headless=True)
 run_case(browser,True)
 run_case(browser,False)
 browser.close()
consultation=(ROOT/'assets/js/clinical/plan02b-next-steps.js').read_text()
assert "`/api/clinical/index.php/encounters/${encodeURIComponent(c.key)}/documents`" in consultation
assert "document_type:'order'" in consultation
print('CONSULTATION_PLAN_ROUTE_UNCHANGED=PASS')
