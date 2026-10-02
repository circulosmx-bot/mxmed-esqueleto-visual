"""CAT02 real VIS06 navigation, local Classification Simulator and universal catalog QA."""
import json
from urllib.parse import parse_qs,urlsplit
from playwright.sync_api import expect,sync_playwright

BASE='http://127.0.0.1:18148'
studies=[
 ('dental_cbct','Tomografía dental y maxilofacial de haz cónico','IMAGEN'),
 ('dental_panoramic_xray','Radiografía panorámica dental','IMAGEN'),
 ('dental_cephalometric_xray','Radiografía cefalométrica lateral','IMAGEN'),
 ('tmj_comparative_xray','Radiografía comparativa de articulaciones temporomandibulares','IMAGEN'),
 ('dental_intraoral_scan','Escaneo intraoral dental','DENTAL'),
 ('dental_clinical_photographs','Fotografías clínicas odontológicas','DENTAL'),
 ('dental_study_model','Modelo de estudio dental','DENTAL'),
 ('glucose','Glucosa','LABORATORIO'),
 ('ecg_12lead','Electrocardiograma','CARDIOVASCULAR'),
 ('eeg','Electroencefalograma','NEUROFISIOLOGIA'),
 ('spirometry','Espirometría','FUNCION_PULMONAR'),
 ('endoscopy','Endoscopía digestiva','ENDOSCOPIA')]
rows=[{'study_type_id':i+1,'study_type_key':key,'display_name_es':name,'category_key':cat,
       'category_label_es':cat,'aliases':[]} for i,(key,name,cat) in enumerate(studies)]
rows.extend({'study_type_id':i+13,'study_type_key':f'qa_imaging_{i}',
             'display_name_es':f'A Imagen general {i:02d}','category_key':'IMAGEN',
             'category_label_es':'IMAGEN','aliases':[]} for i in range(40))
HTML='''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/css/expediente-paciente-visual-normalization.css">
<link rel="stylesheet" href="/assets/css/clinical/dental-location-v1.css">
<style>body{margin:0;padding:12px;background:#f6fbfc}#p-expediente{max-width:1500px;margin:auto}#t-estudios{display:block}.material-symbols-rounded{font-size:0!important;overflow:hidden}</style></head><body>
<div id="mxmed_dev_role_switcher"></div><div id="p-expediente" data-patient-id="p_cat02"><div id="t-estudios" class="active"></div><div id="t-consent"></div><div id="t-tratamiento"></div></div>
<script>window.mxmedStore={doctor_id:'d_cat02'};window.mxmedGetQaPlan=()=>({});</script>
<script src="/assets/js/clinical/dental-location-v1.js"></script>
<script src="/assets/js/clinical/tax03c-study-composer.js"></script>
<script src="/assets/js/clinical/or05-specialty-navigation-v1.js"></script>
<script src="/assets/js/review/classification-simulator.js"></script>
<script src="/assets/js/clinical/vis06-modules.js"></script></body></html>'''

with sync_playwright() as playwright:
 browser=playwright.chromium.launch(headless=True)
 for width,height in [(1440,900),(1366,768),(390,844)]:
  page=browser.new_page(viewport={'width':width,'height':height})
  errors=[];writes=[]
  profile_state={'primary':'Dentista','secondary':[]}
  page.on('pageerror',lambda error:errors.append(str(error)))
  page.route(BASE+'/__dental_cat02_navigation_qa__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
  page.route('**/api/profiles/index.php/private/doctor/**',lambda route:route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{
   'identity_public':{'specialty_primary':profile_state['primary'],'specialty_secondary':profile_state['secondary']},
   'verified_credentials':{'professional':None,'specialties':[]},'primary_specialty_credential_id':None}})))
  def clinical(route):
   request=route.request;url=urlsplit(request.url);query=parse_qs(url.query)
   if request.method!='GET':writes.append(request.post_data_json)
   if url.path.endswith('/study-types'):
    cat=query.get('category',[''])[0];search=query.get('search',[''])[0].casefold();offset=int(query.get('offset',['0'])[0]);limit=int(query.get('limit',['30'])[0])
    filtered=sorted((r for r in rows if (not cat or r['category_key']==cat) and
              (not search or search in r['study_type_key'] or search in r['display_name_es'].casefold())),
              key=lambda r:r['display_name_es'])
    categories=[{'category_key':key,'label_es':key,'active_count':sum(r['category_key']==key for r in rows)} for key in ['IMAGEN','DENTAL','LABORATORIO','CARDIOVASCULAR','NEUROFISIOLOGIA','FUNCION_PULMONAR','ENDOSCOPIA']]
    data={'items':filtered[offset:offset+limit],'has_more':offset+limit<len(filtered),'categories':categories}
   elif url.path.endswith('/encounters/active'):data={'doctor_id':'d_cat02'}
   elif 'orders_results_mode' in query:data={'items':[],'cursor_next':None,'has_more':False}
   else:data={'items':[]}
   route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data},ensure_ascii=False))
  page.route('**/api/clinical/index.php/**',clinical)
  page.goto(BASE+'/__dental_cat02_navigation_qa__',wait_until='networkidle')
  expect(page.locator('.vis06-intent-card')).to_have_count(3)
  page.locator('.vis06-intent-card').first.click()
  quick=page.locator('.vis06-primary-categories .vis06-category-label')
  expect(quick).to_have_text(['Radiología dental 2D','Cone Beam / CBCT','Escaneo y modelos'])
  assert page.locator('.vis06-orders').get_attribute('data-or-family')=='dental'
  expect(page.locator('.vis06-secondary-categories button')).to_have_count(0)
  expect(page.locator('.vis06-lower-link').filter(has_text='Buscar en todo el catálogo')).to_be_visible()
  page.locator('.vis06-primary-categories button').first.click()
  expect(page.locator('.tax03c-dialog')).to_be_visible()
  expect(page.locator('[data-tax03c-id]')).to_have_count(3)
  page.locator('[data-tax03c-id="2"]').click()
  assert page.evaluate('document.querySelector("[data-tax03c-host]") && [...document.querySelectorAll("[data-tax03c-selected] .tax03c-selected-row")].length')==1
  page.locator('[data-tax03c-search]').fill('glucose')
  expect(page.locator('[data-tax03c-id]')).to_have_count(0)
  assert 'Estudios dentales' in page.locator('[data-tax03c-navigation-scope]').inner_text()
  page.locator('[data-tax03c-global]').click()
  assert page.locator('[data-tax03c-navigation-scope]').inner_text()=='Catálogo general'
  page.locator('[data-tax03c-search]').fill('glucose')
  expect(page.locator('[data-tax03c-id]')).to_have_count(1)
  assert page.locator('[data-tax03c-id]').inner_text().find('Glucosa')>=0
  page.locator('[data-tax03c-id="8"]').click()
  assert page.locator('[data-tax03c-selected] .tax03c-selected-row strong').all_text_contents()==['Radiografía panorámica dental','Glucosa']
  expect(page.locator('[data-tax03c-selected] .tax03c-selected-row')).to_have_count(2)
  expect(page.locator('[data-tax03c-custom-open]')).to_be_visible()
  page.locator('[data-tax03c-dental-back]').click()
  page.locator('[data-tax03c-search]').fill('glucose')
  expect(page.locator('[data-tax03c-id]')).to_have_count(0)
  expect(page.locator('[data-tax03c-selected] .tax03c-selected-row')).to_have_count(2)
  page.locator('[data-tax03c-custom-open]').click()
  page.locator('[data-tax03c-custom-category]').select_option('DENTAL')
  page.locator('[data-tax03c-custom-name]').fill('Estudio dental externo')
  page.locator('[data-tax03c-custom-add]').click()
  expect(page.locator('[data-tax03c-selected] .tax03c-selected-row')).to_have_count(3)
  page.on('dialog',lambda dialog:dialog.accept())
  page.locator('.tax03c-dialog [data-tax03c-close]').first.click()
  page.locator('.vis06-lower-link').filter(has_text='Buscar en todo el catálogo').click()
  expect(page.locator('.tax03c-dialog')).to_be_visible()
  assert page.locator('[data-tax03c-navigation-scope]').inner_text()=='Catálogo general'
  page.locator('[data-tax03c-search]').fill('glucose')
  expect(page.locator('[data-tax03c-id]')).to_have_count(1)
  page.locator('[data-tax03c-dental-back]').click()
  page.locator('[data-tax03c-search]').fill('glucose')
  expect(page.locator('[data-tax03c-id]')).to_have_count(0)
  page.locator('.tax03c-dialog [data-tax03c-close]').first.click()
  assert not writes
  page.screenshot(path=f'/tmp/dental-nav01-{width}x{height}.png')
  print(f'QA_VIS06_DENTAL_NAVIGATION_{width}x{height}=PASS')
  if width==1440:
   profiles={
    'Dentista':['Radiología dental 2D','Cone Beam / CBCT','Escaneo y modelos'],
    'Odontología':['Radiología dental 2D','Cone Beam / CBCT','Escaneo y modelos'],
    'Cirujano Dentista':['Radiología dental 2D','Cone Beam / CBCT','Escaneo y modelos'],
    'Ortodoncia':['Registros ortodóncicos','Radiología dental 2D','Escaneo y modelos','Cone Beam / CBCT'],
    'Ortopedia Dental':['Registros ortodóncicos','Radiología dental 2D','Escaneo y modelos','Cone Beam / CBCT'],
    'Implantología':['Cone Beam / CBCT','Radiología dental 2D','Escaneo y modelos'],
    'Implantología Dental':['Cone Beam / CBCT','Radiología dental 2D','Escaneo y modelos'],
    'Endodoncia':['Radiología dental 2D','Cone Beam / CBCT'],
    'Periodoncia':['Radiología dental 2D','Cone Beam / CBCT'],
    'Cirugía Oral y Maxilofacial':['Cone Beam / CBCT','Radiología dental 2D'],
    'Cirugía Maxilofacial':['Cone Beam / CBCT','Radiología dental 2D'],
    'Odontopediatría':['Radiología dental 2D','Registros ortodóncicos','Escaneo y modelos'],
    'Prótesis Bucal':['Escaneo y modelos','Radiología dental 2D'],
    'Rehabilitación Oral':['Escaneo y modelos','Radiología dental 2D'],
    'Odontología Estética':['Escaneo y modelos','Registros ortodóncicos','Radiología dental 2D'],
    'Patología Bucal':['Radiología dental 2D','Cone Beam / CBCT'],
   }
   for label,expected in profiles.items():
    kind='title' if label=='Cirujano Dentista' else 'specialty'
    page.evaluate("([kind,label])=>mxmedReviewClassification.set(kind+':'+mxmedSpecialtyNavigationV1.normalize(label))",[kind,label])
    expect(quick).to_have_text(expected)
    assert page.locator('.vis06-orders').get_attribute('data-or-family')=='dental'
    assert len(page.locator('.vis06-primary-categories').evaluate('(node)=>getComputedStyle(node).gridTemplateColumns.split(" ")'))==len(expected)
    expect(page.locator('.vis06-secondary-categories button')).to_have_count(0)
    assert page.locator('.vis06-lower-link').all_text_contents()==['Buscar en todo el catálogo']
    print(f'QA_SIMULATED_{label}=PASS')
   for label in ['Médico General','Cardiología','Neurología','Gastroenterología','Neumología']:
    kind='title' if label=='Médico General' else 'specialty'
    page.evaluate("([kind,label])=>mxmedReviewClassification.set(kind+':'+mxmedSpecialtyNavigationV1.normalize(label))",[kind,label])
    page.wait_for_function("() => [...document.querySelectorAll('.vis06-primary-categories .vis06-category-label')].some(node=>node.textContent==='LABORATORIO')")
    expect(quick).to_have_text(['LABORATORIO','IMAGENOLOGÍA','ESTUDIOS FUNCIONALES','PROCEDIMIENTOS DIAGNÓSTICOS'])
    assert page.locator('.vis06-orders').get_attribute('data-or-family')=='medical'
    assert 'Cone Beam / CBCT' not in quick.all_text_contents()
    assert not any('dental' in x.lower() or 'ortodón' in x.lower() for x in quick.all_text_contents())
    print(f'QA_NON_DENTAL_{label}=PASS')
   expect(page.locator('#mxmed_qa_classification_trigger')).to_be_visible()
   for label in ['Licenciado en Nutrición','Licenciado en Psicología','Licenciado en Fisioterapia','Químico Farmacobiólogo','Enfermería']:
    family=page.evaluate("label=>mxmedSpecialtyNavigationV1.resolve({identity_public:{professional_designation:label}}).family",label)
    assert family=='OTHER',(label,family)
   profile_state['secondary']=['Cardiología']
   page.evaluate('mxmedReviewClassification.set(null)')
   expect(quick).to_have_text(profiles['Dentista'])
   expect(page.locator('.vis06-secondary-categories button')).to_have_count(0)
   print('QA_DENTAL_PRIMARY_WITH_MEDICAL_SECONDARY=PASS')
   page.evaluate("mxmedReviewClassification.set('specialty:dentista')")
   expect(quick).to_have_text(profiles['Dentista'])
   cbct=next(row for row in rows if row['study_type_key']=='dental_cbct')
   rows.remove(cbct)
   page.evaluate("mxmedReviewClassification.set('specialty:endodoncia')")
   expect(quick).to_have_text(['Radiología dental 2D'])
   rows.append(cbct)
   page.evaluate("mxmedReviewClassification.set('specialty:dentista')")
   expect(quick).to_have_text(profiles['Dentista'])
   print('QA_NO_EMPTY_DENTAL_GROUPERS=PASS')
   page.locator('.vis06-primary-categories button').nth(1).click()
   expect(page.locator('[data-tax03c-id="1"]')).to_be_visible()
   page.locator('[data-tax03c-id="1"]').click()
   page.locator('[data-dental-field="coverage"]').select_option('LOCALIZED')
   page.locator('[data-tooth="16"]').click()
   assert '16' in page.locator('[data-dental-summary]').inner_text()
   page.locator('.tax03c-dialog [data-tax03c-close]').first.click()
   print('QA_FDI_SELECTOR_PRESERVED_FROM_DENTAL_NAVIGATION=PASS')
   page.locator('.vis06-primary-categories button').first.click()
   page.locator('[data-tax03c-id="2"]').click()
   page.locator('[data-tax03c-global]').click()
   page.locator('[data-tax03c-search]').fill('glucose')
   expect(page.locator('[data-tax03c-id="8"]')).to_be_visible()
   page.locator('[data-tax03c-id="8"]').click()
   page.locator('[data-tax03c-submit]').click()
   expect(page.locator('.tax03c-dialog')).to_have_count(0)
   assert len(writes)==1 and {item['study_type_key'] for item in writes[0]['payload']['order_items']}=={'dental_panoramic_xray','glucose'}
   print('QA_MIXED_ORDER_AFTER_GLOBAL_ESCAPE=PASS')
   print('QA_CLASSIFICATION_SIMULATOR_RUNTIME=PASS')
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(width,height)
  assert not errors,errors
  page.close()
 browser.close()
