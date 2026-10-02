"""CAT02 graphical dental ordering and profile QA with bundled Playwright Chromium."""
import json
from pathlib import Path
from urllib.parse import parse_qs, urlsplit
from playwright.sync_api import expect, sync_playwright

ROOT=Path(__file__).resolve().parents[3]
BASE='http://127.0.0.1:18148'
catalog=[
 ('dental_cbct','Tomografía dental y maxilofacial de haz cónico','IMAGEN'),
 ('dental_panoramic_xray','Radiografía panorámica dental','IMAGEN'),
 ('dental_cephalometric_xray','Radiografía cefalométrica lateral','IMAGEN'),
 ('tmj_comparative_xray','Radiografía comparativa de articulaciones temporomandibulares','IMAGEN'),
 ('dental_intraoral_scan','Escaneo intraoral dental','DENTAL'),
 ('dental_clinical_photographs','Fotografías clínicas odontológicas','DENTAL'),
 ('dental_study_model','Modelo de estudio dental','DENTAL'),
 ('glucose','Glucosa','LABORATORIO'),
]
rows=[{'study_type_id':i+1,'study_type_key':key,'display_name_es':name,'category_key':category,
       'category_label_es':category,'aliases':[]} for i,(key,name,category) in enumerate(catalog)]
HARNESS='''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/css/expediente-paciente-visual-normalization.css">
<link rel="stylesheet" href="/assets/css/clinical/dental-location-v1.css">
<style>body{margin:0;padding:10px;background:#eff9fa}dialog{margin:auto}</style></head><body>
<div id="mxmed_dev_role_switcher"></div><dialog class="tax03c-dialog"><form><header><h4>Solicitar estudios</h4></header>
<div data-tax03c-host></div><footer><button type="button">Cerrar</button></footer></form></dialog>
<script>window.mxmedGetQaPlan=()=>({});</script>
<script src="/assets/js/clinical/dental-location-v1.js"></script>
<script src="/assets/js/clinical/tax03c-study-composer.js"></script>
<script src="/assets/js/clinical/or05-specialty-navigation-v1.js"></script>
<script src="/assets/js/review/classification-simulator.js"></script></body></html>'''

def catalog_route(route):
 query=parse_qs(urlsplit(route.request.url).query)
 search=query.get('search',[''])[0].casefold()
 category=query.get('category',[''])[0]
 found=[r for r in rows if (not category or r['category_key']==category) and
        (not search or search in r['display_name_es'].casefold() or search in r['study_type_key'])]
 offset=int(query.get('offset',['0'])[0]);limit=int(query.get('limit',['30'])[0])
 categories=[{'category_key':key,'label_es':key,'active_count':sum(r['category_key']==key for r in rows)}
             for key in ['LABORATORIO','IMAGEN','DENTAL']]
 route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{
   'items':found[offset:offset+limit],'has_more':offset+limit<len(found),'categories':categories}},ensure_ascii=False))

def start(browser,viewport):
 page=browser.new_page(viewport={'width':viewport[0],'height':viewport[1]})
 errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
 page.route(BASE+'/__dental_cat02_qa__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HARNESS))
 page.route('**/api/clinical/index.php/doctors/d_cat02/study-types?**',catalog_route)
 page.goto(BASE+'/__dental_cat02_qa__',wait_until='networkidle')
 page.evaluate("window.qaComposer=window.mxmedStudyComposer.mount(document.querySelector('[data-tax03c-host]'),{doctorId:'d_cat02'})")
 page.locator('dialog.tax03c-dialog').evaluate('(dialog)=>dialog.showModal()')
 return page,errors

def add(page,key):
 page.locator('[data-tax03c-search]').fill(key)
 study=next(r for r in rows if r['study_type_key']==key)
 control=page.locator(f'[data-tax03c-id="{study["study_type_id"]}"]')
 expect(control).to_be_visible()
 control.click()
 return page.locator('[data-tax03c-selected] .tax03c-selected-row').last

def reset(page):
 page.evaluate("qaComposer.destroy();document.querySelector('[data-tax03c-host]').replaceChildren();window.qaComposer=window.mxmedStudyComposer.mount(document.querySelector('[data-tax03c-host]'),{doctorId:'d_cat02'})")

with sync_playwright() as playwright:
 browser=playwright.chromium.launch(headless=True)
 for viewport in [(1440,900),(1366,768),(390,844)]:
  page,errors=start(browser,viewport)
  add(page,'dental_cbct')
  expect(page.locator('[data-dental-field="coverage"]')).to_be_visible()
  assert page.locator('[data-dental-arches] [data-tooth]').count()==0
  assert not page.evaluate('qaComposer.valid()')
  page.locator('[data-dental-field="coverage"]').select_option('LOCALIZED')
  expect(page.locator('[data-dental-arches] [data-tooth]')).to_have_count(32)
  tooth=page.locator('[data-tooth="16"]')
  expect(tooth).to_have_attribute('aria-label','Pieza 16, primer molar superior derecho, no seleccionada')
  tooth.click();expect(tooth).to_have_attribute('aria-pressed','true')
  assert 'Piezas seleccionadas: 16' in page.locator('[data-dental-summary]').inner_text()
  assert page.evaluate('qaComposer.valid()')
  tooth.click();expect(tooth).to_have_attribute('aria-pressed','false')
  tooth.focus();tooth.press('Enter');expect(tooth).to_have_attribute('aria-pressed','true')
  tooth.press('Space');expect(tooth).to_have_attribute('aria-pressed','false')
  tooth.click();page.locator('[data-dental-mode="MIXED"]').click()
  assert page.evaluate('qaComposer.orderItems()[0].dental_location.dentition_mode')=='MIXED'
  page.locator('[data-dental-mode="DECIDUOUS"]').click()
  assert '16' in page.locator('[data-dental-summary]').inner_text()
  expect(page.locator('[data-tooth="16"]')).to_have_count(0)
  page.locator('[data-tooth="55"]').click()
  page.locator('[data-dental-mode="MIXED"]').click()
  expect(page.locator('[data-dental-arches] [data-tooth]')).to_have_count(52)
  location=page.evaluate('qaComposer.orderItems()[0].dental_location')
  assert location['selected_teeth']==['16','55'] and location['dentition_mode']=='MIXED',location
  page.locator('[data-dental-mode="PERMANENT"]').click()
  assert '55' in page.locator('[data-dental-summary]').inner_text()
  assert page.evaluate('qaComposer.valid()')
  page.locator('[data-dental-field="coverage"]').select_option('MAXILLOFACIAL')
  assert not page.evaluate('qaComposer.valid()')
  page.locator('[data-dental-clear]').click()
  assert page.evaluate('qaComposer.valid()')
  for value in ['MAXILLARY_ARCH','MANDIBULAR_ARCH','BOTH_ARCHES','MAXILLOFACIAL']:
   page.locator('[data-dental-field="coverage"]').select_option(value)
   assert page.evaluate('qaComposer.valid()')
   assert page.evaluate('qaComposer.orderItems()[0].dental_location.coverage')==value
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),viewport
  assert page.locator('[data-tooth="16"]').count()==0
  page.locator('[data-dental-field="coverage"]').select_option('LOCALIZED')
  target=page.locator('[data-tooth="16"]');target.focus();target.press('Enter')
  assert page.evaluate("getComputedStyle(document.querySelector('[data-tooth=\"16\"]')).outlineStyle")!='none'
  if viewport[0]==390:
   width=page.locator('[data-tooth="16"]').bounding_box()['width']
   assert width>=36,width
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
  page.screenshot(path=f'/tmp/dental-cat02-{viewport[0]}x{viewport[1]}.png')
  assert not errors,errors
  print(f'QA_GRAPHICAL_FDI_{viewport[0]}x{viewport[1]}=PASS')
  page.close()

 page,errors=start(browser,(1440,900))
 for key,field,value in [
  ('dental_panoramic_xray',None,None),('dental_cephalometric_xray',None,None),
  ('tmj_comparative_xray','projection','PA'),('dental_intraoral_scan','arch','BOTH'),
  ('dental_clinical_photographs','photograph_scope','BOTH'),('dental_study_model','arch','MAXILLARY')]:
  reset(page);add(page,key)
  if field:page.locator(f'[data-dental-field="{field}"]').select_option(value)
  assert page.evaluate('qaComposer.valid()'),(key,page.evaluate('qaComposer.selected()'),page.evaluate('qaComposer.orderItems()'))
  item=page.evaluate('qaComposer.orderItems()[0]')
  assert (item.get('dental_location') or {}).get(field)==value if field else 'dental_location' not in item
  print(f'QA_STUDY_{key}=PASS')
 reset(page);add(page,'dental_cbct')
 page.locator('[data-dental-field="coverage"]').select_option('LOCALIZED')
 page.locator('[data-tooth="16"]').click()
 add(page,'glucose')
 assert page.evaluate('qaComposer.valid()')
 mixed=page.evaluate('qaComposer.orderItems()')
 assert len(mixed)==2 and mixed[0]['dental_location']['selected_teeth']==['16'] and 'dental_location' not in mixed[1]
 assert page.evaluate('mxmedStudyComposer.documentType(qaComposer.selected())')=='orders'
 print('QA_MIXED_CATEGORY_AND_UNIVERSAL_SEARCH=PASS')
 profiles={
  'Dentista':['Radiología dental 2D','Cone Beam / CBCT','Escaneo y modelos'],
  'Ortodoncia':['Registros ortodóncicos','Radiología dental 2D','Escaneo y modelos','Cone Beam / CBCT'],
  'Implantología':['Cone Beam / CBCT','Radiología dental 2D','Escaneo y modelos'],
  'Endodoncia':['Radiología dental 2D','Cone Beam / CBCT'],
  'Periodoncia':['Radiología dental 2D','Cone Beam / CBCT'],
  'Cirugía Oral y Maxilofacial':['Cone Beam / CBCT','Radiología dental 2D'],
  'Odontopediatría':['Radiología dental 2D','Registros ortodóncicos','Escaneo y modelos'],
 }
 for specialty,expected in profiles.items():
  actual=page.evaluate("label=>{const n=mxmedSpecialtyNavigationV1;return n.resolve({identity_public:{specialty_primary:label}}).quick.map(key=>n.config.groups.quick[key].label)}",specialty)
  assert actual==expected,(specialty,actual)
  print(f'QA_PROFILE_{specialty}=PASS')
 for specialty in ['Médico General','Cardiología','Neurología','Gastroenterología']:
  actual=page.evaluate("label=>{const n=mxmedSpecialtyNavigationV1;return n.resolve({identity_public:{specialty_primary:label}}).quick.map(key=>n.config.groups.quick[key].label)}",specialty)
  assert not any('dental' in label.lower() or 'cbct' in label.lower() or 'ortodón' in label.lower() for label in actual),(specialty,actual)
  print(f'QA_REGRESSION_{specialty}=PASS')
 expect(page.locator('#mxmed_qa_classification_trigger')).to_be_visible()
 page.evaluate("mxmedReviewClassification.set('specialty:ortodoncia')")
 assert page.evaluate('mxmedReviewClassification.resolveNavigation({}).profile')=='dental_ortho'
 assert not errors,errors
 print('QA_CLASSIFICATION_SIMULATOR=PASS')
 page.close();browser.close()
