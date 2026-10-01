import json
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright, expect
root=Path(__file__).resolve().parents[3]
pdf=Path('/tmp/res02a-qa.pdf')
pdf.write_bytes(b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF')
order_uuid='00000000-0000-4000-8000-000000000100'
item_uuid='00000000-0000-4000-8000-000000000001'
state={'saved':False,'writes':[]}
order={'id':100,'document_uuid':order_uuid,'document_type':'lab_order','title':'Glucosa','status':'generated',
 'chronology_at':'2026-09-30 12:00:00','source_scope':'PATIENT','order_payload_version':2,
 'order_items':[{'order_item_id':item_uuid,'study_display_name':'Glucosa','study_category':'LABORATORIO',
   'coverage_state':'NO_RESULT'}], 'requested_studies':['Glucosa'],'versions':[],'coverage_state':'NO_RESULTS'}
def handler(route):
 req=route.request;path=urlsplit(req.url).path
 if req.method=='POST' and path.endswith('/documents'):
  state['writes'].append((path,req.post_data));state['saved']=True;data={'document_id':101};status=201
 elif 'orders_results_mode=1' in req.url:
  current=dict(order);current['coverage_state']='ALL_ITEMS_HAVE_RESULTS' if state['saved'] else 'NO_RESULTS'
  current['order_items']=[{**order['order_items'][0],'coverage_state':'RESULT_AVAILABLE' if state['saved'] else 'NO_RESULT'}]
  result={'id':101,'document_uuid':'00000000-0000-4000-8000-000000000101','document_type':'lab_result',
   'title':'Glucosa','related_order_document_id':100,'related_order_item_ids':[item_uuid],'created_at':'2026-09-30 13:00:00'}
  data={'items':[{'kind':'ORDER','order':current,'result_count':int(state['saved']),
    'results':[result] if state['saved'] else []}],'has_more':False,'cursor_next':None};status=200
 elif path.endswith('/encounters/active'):data={'doctor_id':'d_qa'};status=200
 elif path.endswith('/documents/'+order_uuid):
  data={'document':{'document_uuid':order_uuid,'document_type':'lab_order','title':'Glucosa','status':'generated',
    'context':{'patient_id':'p_qa','encounter_id':None,'appointment_id':None},
    'content':{'payload':{'order_payload_version':2,'order_items':order['order_items']}}}};status=200
 else:data={'items':[]};status=200
 route.fulfill(status=status,content_type='application/json',body=json.dumps({'ok':True,'data':data}))
with sync_playwright() as p:
 browser=p.chromium.launch();page=browser.new_page(viewport={'width':1366,'height':768})
 errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
 page.route('http://127.0.0.1:38999/',lambda route:route.fulfill(status=200,content_type='text/html',body='<html></html>'))
 page.route('**/api/clinical/index.php/**',handler)
 page.goto('http://127.0.0.1:38999/')
 page.set_content('<div id="p-expediente" data-patient-id="p_qa"><div id="t-estudios" class="active"></div><div id="t-consent"></div><div id="t-tratamiento"></div></div>')
 page.add_style_tag(path=str(root/'assets/css/expediente-paciente-visual-normalization.css'))
 page.add_script_tag(path=str(root/'assets/js/clinical/res02a-linked-result-composer.js'))
 page.add_script_tag(path=str(root/'assets/js/clinical/vis06-modules.js'))
 expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(1)
 page.locator('#t-estudios .vis06-index-card').click()
 expect(page.locator('.vis06-register-result')).to_be_visible()
 page.locator('.vis06-register-result').click()
 expect(page.locator('.res02a-dialog')).to_be_visible()
 assert page.locator('.res02a-dialog [data-order-picker] select').count()==0
 expect(page.locator('.res02a-dialog input[data-item]')).to_be_checked()
 page.locator('.res02a-dialog [data-provenance]').fill('Laboratorio QA')
 page.locator('.res02a-dialog [data-file]').set_input_files(str(pdf))
 page.locator('.res02a-dialog [data-save]').click()
 expect(page.locator('.res02a-dialog')).not_to_be_visible()
 expect(page.locator('#t-estudios .vis06-detail .vis06-count')).to_contain_text('1')
 expect(page.locator('#t-estudios .vis06-coverage-summary')).to_have_text('Todos los estudios tienen resultado')
 expect(page.locator('#t-estudios .vis06-item-coverage')).to_have_text('Resultado disponible')
 page.locator('#t-estudios .vis06-result-entry').click()
 expect(page.locator('#t-estudios .vis06-detail')).to_contain_text('CORRESPONDE A')
 expect(page.locator('#t-estudios .vis06-detail')).to_contain_text('Glucosa')
 assert state['writes'] and '/patients/p_qa/documents' in state['writes'][0][0]
 assert not errors,errors
 print('QA_GENERAL_SELECTED_ORDER_FIXED_AND_REFRESH=PASS')
 browser.close()
