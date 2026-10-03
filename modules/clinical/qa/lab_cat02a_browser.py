"""LAB-CAT02A physician navigation and shared composer gate on the Director runtime."""
import json
import subprocess
from urllib.parse import parse_qs, urlsplit
from playwright.sync_api import expect, sync_playwright

BASE = 'http://127.0.0.1:18148'
raw = subprocess.check_output(['mysql','-N','-B','mxmed_director_review_lon07c','-e',
    'SELECT study_type_id,study_type_key,display_name_es,category_key,aliases_json FROM clinical_study_types WHERE is_active=1 ORDER BY display_name_es,study_type_id'], text=True)
rows = [dict(study_type_id=int(i),study_type_key=k,display_name_es=n,category_key=c,category_label_es=c,aliases=json.loads(a))
        for i,k,n,c,a in (line.split('\t') for line in raw.splitlines())]
counts = {category:sum(row['category_key']==category for row in rows) for category in
          ['LABORATORIO','GENETICA','PATOLOGIA','IMAGEN','DENTAL','CARDIOVASCULAR','NEUROFISIOLOGIA','FUNCION_PULMONAR','ENDOSCOPIA','SUENO','AUDIOLOGIA','OTROS','OFTALMOLOGIA']}
assert len(rows)==202 and counts['LABORATORIO']==93
HTML = '''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/css/expediente-paciente-visual-normalization.css?v=lab-cat02a">
<link rel="stylesheet" href="/assets/css/clinical/dental-location-v1.css?v=dental-cat02">
<link rel="stylesheet" href="/assets/css/clinical/lab-cat02a-navigation-v1.css?v=lab-cat02a">
<style>body{margin:0;padding:12px;background:#f6fbfc}#p-expediente{max-width:1500px;margin:auto}#t-estudios{display:block}.material-symbols-rounded{font-size:0!important;overflow:hidden}</style>
</head><body><div id="mxmed_dev_role_switcher"></div><div id="p-expediente" data-patient-id="p_labcat02a"><div id="t-estudios" class="active"></div><div id="t-consent"></div><div id="t-tratamiento"></div></div>
<script>window.mxmedStore={doctor_id:'d_labcat02a'};window.mxmedGetQaPlan=()=>({});</script>
<script src="/assets/js/clinical/dental-location-v1.js?v=dental-cat02"></script>
<script src="/assets/js/clinical/tax03c-study-composer.js?v=lab-cat02a"></script>
<script src="/assets/js/clinical/or05-specialty-navigation-v1.js?v=dental-nav01"></script>
<script src="/assets/js/clinical/lab-cat02a-navigation-v1.js?v=lab-cat02a"></script>
<script src="/assets/js/review/classification-simulator.js?v=classification-sim01"></script>
<script src="/assets/js/clinical/vis06-modules.js?v=lab-cat02a"></script></body></html>'''
profiles = {
 'Médico General':['chemistry','hematology','urine','panels'],
 'Medicina Interna':['chemistry','hematology','immunology','microbiology'],
 'Endocrinología':['endocrine','chemistry','panels','urine'],
 'Hematología':['hematology','coagulation','immunology'],
 'Infectología':['microbiology','serology','immunology'],
 'Nefrología':['chemistry','urine','hematology','panels'],
 'Oncología':['hematology','chemistry','tumor'],
 'Cardiología':['chemistry','hematology','coagulation'],
 'Gastroenterología':['chemistry','stool','serology','microbiology'],
 'Reumatología':['autoimmunity','immunology','hematology','chemistry'],
 'Ginecología y Obstetricia':['endocrine','microbiology','serology','chemistry'],
 'Pediatría':['hematology','chemistry','microbiology','urine'],
 'Neumología':['microbiology','hematology','chemistry'],
}

def main():
 with sync_playwright() as playwright:
  browser=playwright.chromium.launch(headless=True)
  for width,height in [(1440,900),(1366,768),(390,844)]:
   page=browser.new_page(viewport={'width':width,'height':height})
   errors=[];writes=[];profile={'label':'Médico General'}
   page.on('pageerror',lambda error:errors.append(str(error)))
   page.route(BASE+'/__lab_cat02a_qa__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
   page.route('**/api/profiles/index.php/private/doctor/**',lambda route:route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'identity_public':{'specialty_primary':profile['label']},'verified_credentials':{'professional':None,'specialties':[]},'primary_specialty_credential_id':None}},ensure_ascii=False)))
   def clinical(route):
    url=urlsplit(route.request.url);query=parse_qs(url.query)
    if url.path.endswith('/study-types'):
     cat=query.get('category',[''])[0];term=query.get('search',[''])[0].casefold();offset=int(query.get('offset',['0'])[0]);limit=int(query.get('limit',['30'])[0])
     filtered=[row for row in rows if (not cat or row['category_key']==cat) and (not term or term in (row['display_name_es']+' '+row['study_type_key']+' '+' '.join(row['aliases'])).casefold())]
     data={'items':filtered[offset:offset+limit],'has_more':offset+limit<len(filtered),'categories':[{'category_key':key,'label_es':key,'active_count':n} for key,n in counts.items()]}
    elif route.request.method=='POST' and url.path.endswith('/documents'):
     writes.append(route.request.post_data_json);data={'document_id':42,'document_uuid':'00000000-0000-4000-8000-000000000042'}
    elif url.path.endswith('/encounters/active'):data={'doctor_id':'d_labcat02a'}
    elif 'orders_results_mode' in query:data={'items':[],'cursor_next':None,'has_more':False}
    else:data={'items':[]}
    route.fulfill(status=201 if route.request.method=='POST' else 200,content_type='application/json',body=json.dumps({'ok':True,'data':data},ensure_ascii=False))
   page.route('**/api/clinical/index.php/**',clinical)
   page.goto(BASE+'/__lab_cat02a_qa__',wait_until='networkidle')
   expect(page.locator('.vis06-intent-card')).to_have_count(3)
   page.locator('.vis06-intent-card').first.click()
   page.locator('.vis06-primary-categories button').filter(has_text='LABORATORIO').click()
   expect(page.locator('.lab-cat02a-screen')).to_be_visible()
   expect(page.locator('.lab-cat02a-priority button')).to_have_count(4)
   assert page.locator('.lab-cat02a-priority button').evaluate_all('(items)=>items.map(x=>x.dataset.labGroup)')==profiles['Médico General']
   page.wait_for_function('()=>document.querySelectorAll(".lab-cat02a-screen button[data-lab-group]").length===24')
   assert page.locator('.lab-cat02a-screen button[data-lab-group]').count()==24 # Trasplante and molecular/PCR have no supported active keys.
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
   if width in (1366,390):page.screenshot(path=f'/tmp/lab-cat02a-{width}x{height}.png',full_page=True)
   for label,expected in profiles.items():
    value=page.evaluate('(label)=>window.mxmedReviewClassification.options().find(o=>o.label===label)?.value',label)
    assert value,label
    page.evaluate('(value)=>window.mxmedReviewClassification.set(value)',value)
    expect(page.locator('.lab-cat02a-screen')).to_have_attribute('data-lab-profile',
      { 'Médico General':'general','Medicina Interna':'internal','Endocrinología':'endocrine','Hematología':'heme','Infectología':'infect','Nefrología':'nephro','Oncología':'oncology','Cardiología':'cardio','Gastroenterología':'gi','Reumatología':'rheum','Ginecología y Obstetricia':'obgyn','Pediatría':'peds','Neumología':'pulm'}[label])
   page.wait_for_function('(expected)=>JSON.stringify([...document.querySelectorAll(".lab-cat02a-priority button")].map(x=>x.dataset.labGroup))===JSON.stringify(expected)', arg=expected)
   # Return to general, then exercise grouped search, cross-group, mixed-category and custom selection.
   value=page.evaluate('window.mxmedReviewClassification.options().find(o=>o.label==="Médico General").value')
   page.evaluate('(value)=>window.mxmedReviewClassification.set(value)',value)
   expect(page.locator('.lab-cat02a-screen')).to_have_attribute('data-lab-profile','general')
   page.wait_for_function('()=>document.querySelectorAll(".lab-cat02a-priority button").length===4')
   # Both the full-laboratory escape and the panel route must open only real canonical studies.
   page.locator('.lab-cat02a-screen button').filter(has_text='Todos los estudios de laboratorio').first.click()
   expect(page.locator('.tax03c-dialog')).to_be_visible()
   expect(page.locator('[data-tax03c-navigation-scope]')).to_have_text('Todos los estudios de laboratorio')
   page.locator('[data-tax03c-search]').fill('Coombs directo')
   expect(page.locator('[data-tax03c-id]')).to_have_count(1)
   page.locator('.tax03c-dialog [data-tax03c-close]').first.click()
   page.locator('.lab-cat02a-screen button[data-lab-group="panels"]').click()
   expect(page.locator('.tax03c-dialog')).to_be_visible()
   page.wait_for_function('()=>document.querySelectorAll("[data-tax03c-id]").length===7')
   assert page.locator('[data-tax03c-id]').count()==7
   page.locator('.tax03c-dialog [data-tax03c-close]').first.click()
   page.locator('.lab-cat02a-screen button[data-lab-group="hematology"]').click()
   expect(page.locator('.tax03c-dialog')).to_be_visible()
   page.locator('[data-tax03c-search]').fill('Glucosa')
   expect(page.locator('[data-tax03c-id]')).to_have_count(0)
   page.locator('[data-tax03c-search]').fill('Coombs directo')
   expect(page.locator('[data-tax03c-id]')).to_have_count(1)
   page.locator('[data-tax03c-id]').click()
   page.locator('[data-tax03c-lab-all]').click()
   page.locator('[data-tax03c-search]').fill('Glucosa')
   glucose_id=next(row['study_type_id'] for row in rows if row['study_type_key']=='glucose')
   expect(page.locator(f'[data-tax03c-id="{glucose_id}"]')).to_have_count(1)
   page.locator(f'[data-tax03c-id="{glucose_id}"]').click()
   page.locator('[data-tax03c-global]').click()
   page.locator('[data-tax03c-search]').fill('Radiografía de tórax')
   # Current canonical name is RX Tórax; use a key search if synonym is absent.
   page.locator('[data-tax03c-search]').fill('rx_chest')
   expect(page.locator('[data-tax03c-id]')).to_have_count(1)
   page.locator('[data-tax03c-id]').click()
   page.locator('[data-tax03c-custom-open]').click()
   page.locator('[data-tax03c-custom-category]').select_option('LABORATORIO')
   page.locator('[data-tax03c-custom-name]').fill('Estudio solicitado fuera de catálogo QA')
   page.locator('[data-tax03c-custom-add]').click()
   assert page.locator('[data-tax03c-selected] .tax03c-selected-row').count()==4
   page.locator('[data-tax03c-submit]').click()
   expect(page.locator('.tax03c-dialog')).to_have_count(0)
   assert len(writes)==1 and len(writes[0]['payload']['order_items'])==4
   assert len({item.get('study_type_id') for item in writes[0]['payload']['order_items'] if 'study_type_id' in item})==3
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
   assert not errors,errors
   print(f'QA_VIEWPORT_{width}x{height}=PASS profiles=13 groupers=24 items=4')
   page.close()
  # Dental route stays the original OR05 route, including the Classification Simulator.
  for label in ['Ortodoncia','Implantología','Endodoncia']:
   page=browser.new_page()
   page.route(BASE+'/__lab_cat02a_qa__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
   page.route('**/api/profiles/index.php/private/doctor/**',lambda route:route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'identity_public':{'specialty_primary':label},'verified_credentials':{'professional':None,'specialties':[]},'primary_specialty_credential_id':None}},ensure_ascii=False)))
   def clinical(route):
    url=urlsplit(route.request.url);query=parse_qs(url.query)
    if url.path.endswith('/study-types'):
     cat=query.get('category',[''])[0];offset=int(query.get('offset',['0'])[0]);limit=int(query.get('limit',['30'])[0]);filtered=[r for r in rows if not cat or r['category_key']==cat]
     data={'items':filtered[offset:offset+limit],'has_more':offset+limit<len(filtered),'categories':[{'category_key':k,'label_es':k,'active_count':n} for k,n in counts.items()]}
    elif url.path.endswith('/encounters/active'):data={'doctor_id':'d_labcat02a'}
    else:data={'items':[]}
    route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data},ensure_ascii=False))
   page.route('**/api/clinical/index.php/**',clinical)
   page.goto(BASE+'/__lab_cat02a_qa__',wait_until='networkidle')
   page.locator('.vis06-intent-card').first.click()
   expect(page.locator('.vis06-orders')).to_have_attribute('data-or-family','dental')
   expect(page.locator('.vis06-primary-categories button')).to_have_count(4 if label=='Ortodoncia' else 3 if label=='Implantología' else 2)
   page.close();print('QA_DENTAL_'+label+'=PASS')
  browser.close()

if __name__=='__main__':main()
