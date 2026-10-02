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
 ('glucose','Glucosa','LABORATORIO')]
rows=[{'study_type_id':i+1,'study_type_key':key,'display_name_es':name,'category_key':cat,
       'category_label_es':cat,'aliases':[]} for i,(key,name,cat) in enumerate(studies)]
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
  page.on('pageerror',lambda error:errors.append(str(error)))
  page.route(BASE+'/__dental_cat02_navigation_qa__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
  page.route('**/api/profiles/index.php/private/doctor/**',lambda route:route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{
   'identity_public':{'specialty_primary':'Dentista'},'verified_credentials':{'professional':None,'specialties':[]},'primary_specialty_credential_id':None}})))
  def clinical(route):
   request=route.request;url=urlsplit(request.url);query=parse_qs(url.query)
   if request.method!='GET':writes.append(request.url)
   if url.path.endswith('/study-types'):
    cat=query.get('category',[''])[0];search=query.get('search',[''])[0].casefold();offset=int(query.get('offset',['0'])[0]);limit=int(query.get('limit',['30'])[0])
    filtered=[r for r in rows if (not cat or r['category_key']==cat) and
              (not search or search in r['study_type_key'] or search in r['display_name_es'].casefold())]
    categories=[{'category_key':key,'label_es':key,'active_count':sum(r['category_key']==key for r in rows)} for key in ['IMAGEN','DENTAL','LABORATORIO']]
    data={'items':filtered[offset:offset+limit],'has_more':offset+limit<len(filtered),'categories':categories}
   elif url.path.endswith('/encounters/active'):data={'doctor_id':'d_cat02'}
   elif 'orders_results_mode' in query:data={'items':[],'cursor_next':None,'has_more':False}
   else:data={'items':[]}
   route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data},ensure_ascii=False))
  page.route('**/api/clinical/index.php/**',clinical)
  page.goto(BASE+'/__dental_cat02_navigation_qa__',wait_until='networkidle')
  expect(page.locator('.vis06-intent-card')).to_have_count(3)
  page.locator('.vis06-intent-card').first.click()
  quick=page.locator('.vis06-secondary-categories .vis06-category-label')
  expect(quick).to_have_text(['Radiología dental 2D','Escaneo y modelos','Cone Beam / CBCT','Registros ortodóncicos'])
  expect(page.locator('.vis06-lower-link').filter(has_text='Todos los estudios')).to_be_visible()
  page.locator('.vis06-secondary-categories button').first.click()
  expect(page.locator('.tax03c-dialog')).to_be_visible()
  expect(page.locator('[data-tax03c-id]')).to_have_count(3)
  page.locator('[data-tax03c-search]').fill('glucose')
  expect(page.locator('[data-tax03c-id]')).to_have_count(1)
  assert page.locator('[data-tax03c-id]').inner_text().find('Glucosa')>=0
  page.locator('.tax03c-dialog [data-tax03c-close]').first.click()
  assert not writes
  print(f'QA_VIS06_DENTAL_NAVIGATION_{width}x{height}=PASS')
  if width==1440:
   profiles={
    'Ortodoncia':['Registros ortodóncicos','Radiología dental 2D','Escaneo y modelos','Cone Beam / CBCT'],
    'Implantología':['Cone Beam / CBCT','Radiología dental 2D','Escaneo y modelos'],
    'Endodoncia':['Radiología dental 2D','Cone Beam / CBCT'],
    'Periodoncia':['Radiología dental 2D','Escaneo y modelos'],
    'Cirugía Oral y Maxilofacial':['Cone Beam / CBCT','Radiología dental 2D','Escaneo y modelos'],
    'Odontopediatría':['Radiología dental 2D','Registros ortodóncicos','Escaneo y modelos'],
   }
   for label,expected in profiles.items():
    page.evaluate("label=>mxmedReviewClassification.set('specialty:'+mxmedSpecialtyNavigationV1.normalize(label))",label)
    expect(quick).to_have_text(expected)
    print(f'QA_SIMULATED_{label}=PASS')
   for label in ['Médico General','Cardiología','Neurología','Gastroenterología']:
    kind='title' if label=='Médico General' else 'specialty'
    page.evaluate("([kind,label])=>mxmedReviewClassification.set(kind+':'+mxmedSpecialtyNavigationV1.normalize(label))",[kind,label])
    page.wait_for_function("() => !document.querySelector('.vis06-category-status')?.textContent?.includes('Cargando') && ![...document.querySelectorAll('.vis06-secondary-categories .vis06-category-label')].some(node=>node.textContent.includes('Cone Beam / CBCT'))")
    assert 'Cone Beam / CBCT' not in quick.all_text_contents()
    assert not any('dental' in x.lower() or 'ortodón' in x.lower() for x in quick.all_text_contents())
    print(f'QA_NON_DENTAL_{label}=PASS')
   expect(page.locator('#mxmed_qa_classification_trigger')).to_be_visible()
   print('QA_CLASSIFICATION_SIMULATOR_RUNTIME=PASS')
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(width,height)
  assert not errors,errors
  page.close()
 browser.close()
