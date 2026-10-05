"""Inline selector on served assets with the isolated PHP-search fixture; no clinical writes."""
import ast,json,pathlib,re,subprocess,uuid
from urllib.parse import parse_qs,urlsplit
from playwright.sync_api import expect,sync_playwright
ROOT=pathlib.Path(__file__).resolve().parents[3];BASE='http://127.0.0.1:18148'
# Reuse the established fixture shell, without importing obsolete baseline count assertions.
tree=ast.parse((ROOT/'modules/clinical/qa/lab_cat02a_browser.py').read_text())
HTML=next(ast.literal_eval(n.value) for n in tree.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in n.targets))
HTML=HTML.replace('</head>','<link rel="stylesheet" href="/assets/css/clinical/study-navigation-hierarchy-v2.css"><link rel="stylesheet" href="/assets/css/clinical/order-composition-v1.css"></head>')
HTML=HTML.replace('<script src="/assets/js/clinical/vis06-modules.js','<script src="/assets/js/clinical/study-navigation-hierarchy-v2.js"></script><script src="/assets/js/clinical/patient-workspace-navigation-guard.js"></script><script src="/assets/js/clinical/order-composition-v1.js"></script><script src="/assets/js/clinical/vis06-modules.js')
raw=subprocess.check_output(['mysql','-N','-B','mxmed_director_review_lon07c','-e','SELECT study_type_id,study_type_key,display_name_es,category_key,aliases_json FROM clinical_study_types WHERE is_active=1'],text=True)
rows=[dict(study_type_id=int(i),study_type_key=k,display_name_es=n,category_key=c,aliases=json.loads(a)) for i,k,n,c,a in (line.split('\t') for line in raw.splitlines())]
search_authority=json.loads((ROOT/'modules/clinical/catalog/study_search_authority_v1.json').read_text())
common_by_key={entry['study_key']:entry['common_display_name'] for entry in search_authority['studies']}
seed=(ROOT/'modules/clinical/db/migrations/2026_10_03_19_urine_fluids_catalog.sql').read_text()
for key,name in re.findall(r"\('([^']+)','([^']+)','LABORATORIO'",seed):
 if not any(r['study_type_key']==key for r in rows):rows.append(dict(study_type_id=10000+len(rows),study_type_key=key,display_name_es=name,category_key='LABORATORIO',aliases=[]))
seed=(ROOT/'modules/clinical/db/migrations/2026_10_03_20_urine_fluids_cat03a.sql').read_text()
for key,name in re.findall(r"\('([^']+)','([^']+)','LABORATORIO'",seed):
 if not any(r['study_type_key']==key for r in rows):rows.append(dict(study_type_id=10000+len(rows),study_type_key=key,display_name_es=name,category_key='LABORATORIO',aliases=[]))
counts={k:sum(r['category_key']==k for r in rows) for k in {r['category_key'] for r in rows}}
ids={r['study_type_key']:r['study_type_id'] for r in rows}
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True)
 for width,height in [(1440,900),(1366,768),(390,844)]:
  page=browser.new_page(viewport={'width':width,'height':height});errors=[];writes=[];state={'profile':'Médico General','failure':False}
  page.on('pageerror',lambda e:errors.append(str(e)))
  page.route(BASE+'/__ordcomp01__',lambda r:r.fulfill(content_type='text/html',body=HTML))
  page.route('**/api/profiles/index.php/private/doctor/**',lambda r:r.fulfill(content_type='application/json',body=json.dumps({'ok':True,'data':{'identity_public':{'specialty_primary':state['profile']},'verified_credentials':{'professional':None,'specialties':[]}}})))
  def api(route):
   url=urlsplit(route.request.url);q=parse_qs(url.query);status=200
   if url.path.endswith('/study-types'):
    filtered=[r for r in rows if not q.get('category') or r['category_key']==q['category'][0]]
    if q.get('search'):
     fixture=json.loads(subprocess.check_output(['php',str(ROOT/'modules/clinical/qa/study_search02_fixture.php'),'--response',json.dumps({'search':q['search'][0],'limit':'100','offset':'0'})],text=True))
     by_key={r['study_type_key']:r for r in filtered}
     filtered=[by_key[item['study_type_key']] for item in fixture['data']['items'] if item['study_type_key'] in by_key]
    offset=int(q.get('offset',['0'])[0]);limit=int(q.get('limit',['30'])[0]);items=[dict(r,common_display_name=common_by_key.get(r['study_type_key'])) for r in filtered[offset:offset+limit]]
    data={'items':items,'has_more':offset+limit<len(filtered),'categories':[{'category_key':k,'label_es':k,'active_count':v} for k,v in counts.items()],'search_authority_version':1}
   elif url.path.endswith('/orders/batch'):
    body=route.request.post_data_json;writes.append(body)
    if state.get('uncertain'):
     state['uncertain']=False;route.fulfill(status=500,content_type='application/json',body=json.dumps({'ok':False,'message':'unknown'}));return
    if state['failure']:
     state['failure']=False;route.fulfill(status=422,content_type='application/json',body=json.dumps({'ok':False,'message':'STUDY_TYPE_INVALID','order_routing_group_key':body['orders'][0]['order_routing_group_key']}));return
    status=201;data={'order_composition_batch_uuid':body['order_composition_batch_uuid'],'orders':[{'document_id':i+1,'document_uuid':str(uuid.uuid4()),'order_routing_group_key':o['order_routing_group_key']} for i,o in enumerate(body['orders'])]}
   elif url.path.endswith('/encounters/active'):data={'doctor_id':'d_labcat02a'}
   else:data={'items':[]}
   route.fulfill(status=status,content_type='application/json',body=json.dumps({'ok':True,'data':data},ensure_ascii=False))
  page.route('**/api/clinical/index.php/**',api)
  page.goto(BASE+'/__ordcomp01__',wait_until='networkidle');page.locator('.vis06-intent-card').first.click()
  def nav(key):
   page.locator('[data-hier-node="'+key+'"]').click()
  def back():page.locator('.vis06-head .vis06-flow-back').click()
  def choose(key):
   search=page.locator('.ordcomp [data-tax03c-search]');search.fill(next(r['display_name_es'] for r in rows if r['study_type_key']==key));page.locator(f'.ordcomp [data-tax03c-id="{ids[key]}"]').first.click()
  def catalog_mode():
   if width<768 and page.locator('.ordcomp').evaluate('(x)=>x.classList.contains("ordcomp-show-summary")'):page.get_by_role('button',name='Volver al catálogo',exact=True).click()
  def assert_family_add(label):
   if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
   page.get_by_role('button',name='+ Agregar estudios',exact=True).click()
   expect(page.get_by_role('button',name=label,exact=True)).to_be_visible()
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
   page.get_by_role('button',name='Cerrar',exact=True).click()
   expect(page.locator('dialog[open]')).to_have_count(0)
   catalog_mode()
  nav('laboratory');nav('urine')
  expect(page.locator('.ordcomp')).to_be_visible();expect(page.locator('dialog[open]')).to_have_count(0)
  expect(page.locator('[data-ordcomp-featured] button')).to_have_count(6)
  expect(page.locator('.ordcomp-prepared-order')).to_have_count(0)
  assert page.locator('[data-ordcomp-featured] button[aria-pressed="true"]').count()==0
  expect(page.locator('[data-tax03c-custom-open]')).to_be_hidden()
  page.locator('.ordcomp-full-catalog > summary').click()
  expect(page.locator('[data-tax03c-custom-open]')).to_be_visible()
  assert page.locator('[data-tax03c-custom-open]').inner_text()=='¿No encuentras el estudio? Agregar estudio no catalogado'
  assert page.locator('[data-tax03c-custom-open]').evaluate('(e)=>e.tagName')=='A'
  assert page.locator('.ordcomp-full-catalog [data-catalog-group]').count()>0
  page.locator('.ordcomp-full-catalog > summary').click()
  expect(page.locator('[data-tax03c-custom-open]')).to_be_hidden()
  for control in page.locator('[data-ordcomp-featured] button').all():control.click()
  expect(page.locator('[data-order-group="CLINICAL_LAB"] .tax03c-selected-row')).to_have_count(6)
  assert page.locator('.ordcomp-count').inner_text()=='1 orden · 6 estudios seleccionados'
  assert page.locator('.ordcomp-summary').locator('text=Orden independiente').count()==0
  assert page.locator('.ordcomp-summary .tax03c-selected-row small').count()==0
  assert page.locator('.ordcomp-summary .tax03c-selected-row').first.evaluate('(e)=>e.getBoundingClientRect().height')<=50
  assert page.locator('.ordcomp-summary .tax03c-selected-row strong').first.evaluate('(e)=>Number(getComputedStyle(e).fontWeight)>=700')
  assert page.locator('.ordcomp-summary .tax03c-selected-row').first.evaluate('(e)=>getComputedStyle(e).backgroundColor')!='rgba(0, 0, 0, 0)'
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  long_row=page.locator('.ordcomp-summary .tax03c-selected-row').first
  assert long_row.evaluate("(e)=>{const n=e.querySelector('strong'),original=n.textContent;n.textContent='Estudio de laboratorio con un nombre clínico excepcionalmente largo para comprobar el ajuste natural de varias líneas';const row=e.getBoundingClientRect(),title=n.getBoundingClientRect();n.textContent=original;return title.right<=row.right&&document.documentElement.scrollWidth<=innerWidth}")
  remove=page.locator('.ordcomp-summary .ordcomp-remove').first
  assert remove.inner_text()=='×' and remove.get_attribute('aria-label').startswith('Retirar ')
  assert remove.evaluate('(e)=>e.getBoundingClientRect().width')>=44
  remove.click()
  expect(page.locator('[data-order-group="CLINICAL_LAB"] .tax03c-selected-row')).to_have_count(5)
  if width<768:page.get_by_role('button',name='Volver al catálogo',exact=True).click()
  page.locator('.ordcomp-full-catalog > summary').click()
  page.locator('[data-tax03c-custom-open]').click()
  page.locator('[data-tax03c-custom-name]').fill('Estudio en borrador')
  page.locator('[data-tax03c-custom-note]').fill('Parámetro pendiente')
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  page.get_by_role('button',name='+ Agregar estudios',exact=True).click()
  expect(page.locator('.ordcomp-add-chooser')).to_be_visible()
  expect(page.locator('.ordcomp-add-other')).to_be_hidden()
  expect(page.get_by_role('button',name='+ Más estudios de Laboratorio',exact=True)).to_be_focused()
  page.get_by_role('button',name='Cerrar',exact=True).click()
  expect(page.locator('.ordcomp-add-chooser')).to_be_hidden()
  expect(page.locator('.ordcomp-add-other')).to_be_visible()
  expect(page.get_by_role('button',name='+ Agregar estudios',exact=True)).to_be_focused()
  assert page.locator('.ordcomp-summary .tax03c-selected-row').count()==5
  page.get_by_role('button',name='+ Agregar estudios',exact=True).click()
  page.get_by_role('button',name='+ Más estudios de Laboratorio',exact=True).click()
  expect(page.locator('.vis06-category-screen[data-hier-parent="laboratory"]')).to_be_visible()
  expect(page.locator('.vis06-hier-draft')).to_contain_text('5 estudios')
  expect(page.locator('dialog[open]')).to_have_count(0)
  nav('urine')
  expect(page.locator('[data-order-group="CLINICAL_LAB"] .tax03c-selected-row')).to_have_count(5)
  expect(page.locator('[data-ordcomp-featured] button')).to_have_count(6)
  assert page.locator('[data-ordcomp-featured] button[aria-pressed="true"]').count()==5
  assert page.locator('[data-tax03c-custom-name]').input_value()=='Estudio en borrador'
  assert page.locator('[data-tax03c-custom-note]').input_value()=='Parámetro pendiente'
  page.locator('[data-tax03c-custom-cancel]').click()
  page.locator('.ordcomp-full-catalog > summary').click()
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  while page.locator('.ordcomp-summary .ordcomp-remove').count():page.locator('.ordcomp-summary .ordcomp-remove').first.click()
  expect(page.locator('.ordcomp-prepared-order')).to_have_count(0)
  if width<768:page.get_by_role('button',name='Volver al catálogo',exact=True).click()
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
  print(f'QA_ORDCOMP01_R1_FEATURED_ROOT_DRAFT_REMOVE_{width}x{height}=PASS',flush=True)
  assert page.locator('[data-catalog-group]').count()==7
  labels=[node.get_attribute('data-catalog-group') for node in page.locator('[data-catalog-group]').all()]
  assert next(i for i,s in enumerate(labels) if 'Semen' in s)==next(i for i,s in enumerate(labels) if 'Microbiología urinaria' in s)+1
  assert page.locator('.specimen-editor').count()==0
  choose('synovial_crystals')
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  expect(page.locator('[data-tax03c-specimen]')).to_have_count(1)
  page.locator('[data-tax03c-specimen]').click()
  expect(page.locator('.specimen-editor label')).to_have_count(1)
  page.locator('.specimen-editor select').select_option('JOINT')
  page.locator('.specimen-editor input').fill('Rodilla derecha')
  assert page.locator('.specimen-item-summary').inner_text()=='Datos de muestra completos'
  page.locator('.ordcomp-summary .ordcomp-remove').click()
  if width<768:page.get_by_role('button',name='Volver al catálogo',exact=True).click()
  page.locator('.ordcomp [data-tax03c-search]').fill('')
  new_groups={'urine_creatinine_spot':'Estudios generales y renales','urine_sodium_spot':'Electrolitos y minerales urinarios','urine_potassium_spot':'Electrolitos y minerales urinarios','urine_pregnancy_qualitative':'Estudios generales y renales','csf_glucose':'Líquido cefalorraquídeo (LCR)','csf_total_protein':'Líquido cefalorraquídeo (LCR)','post_vasectomy_semen_check':'Semen'}
  for key in new_groups:
   page.locator('.ordcomp [data-tax03c-search]').fill(next(r['display_name_es'] for r in rows if r['study_type_key']==key))
   expect(page.locator('[data-tax03c-results] [data-tax03c-id]')).to_have_count(1)
  page.locator('.ordcomp [data-tax03c-search]').fill('')
  page.locator('.ordcomp-full-catalog > summary').click()
  for key,group_name in new_groups.items():
   group=page.locator('[data-catalog-group="'+group_name+'"]')
   assert group.locator('[data-tax03c-id="'+str(ids[key])+'"]').count()==1
   if group.get_attribute('open') is None:group.locator('summary').first.click()
   group.locator('[data-tax03c-id="'+str(ids[key])+'"]').click()
   expect(page.locator('[data-order-group="CLINICAL_LAB"] .tax03c-selected-row')).to_have_count(1)
   if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
   page.locator('.ordcomp-summary .ordcomp-remove').first.click()
   expect(page.locator('.ordcomp-prepared-order')).to_have_count(0)
   if width<768:page.get_by_role('button',name='Volver al catálogo',exact=True).click()
  expect(page.locator('.ordcomp [data-tax03c-custom-open]')).to_be_visible()
  page.locator('.ordcomp-full-catalog > summary').click()
  print(f'QA_CAT03A_SEVEN_ACCORDION_ADD_REMOVE_{width}x{height}=PASS',flush=True)
  assert page.locator('.ordcomp-full-catalog').get_attribute('open') is None
  expect(page.locator('[data-ordcomp-featured] button').first).to_be_visible()
  page.locator('[data-ordcomp-featured] button').first.focus();page.keyboard.press('Enter')
  expect(page.locator('[data-ordcomp-featured] button').first).to_have_attribute('aria-pressed','true')
  if page.locator('.ordcomp-full-catalog').get_attribute('open') is None:page.locator('.ordcomp-full-catalog > summary').click()
  if page.locator('[data-catalog-group]').first.get_attribute('open') is None:page.locator('[data-catalog-group] > summary').first.click()
  expect(page.locator('[data-catalog-group][open]')).to_have_count(1)
  page.locator('[data-catalog-group] > summary').nth(1).click();page.wait_for_timeout(100)
  expect(page.locator('[data-catalog-group][open]')).to_have_count(1)
  choose('microalbumin')
  page.locator('[data-tax03c-search]').fill('RX Tórax');expect(page.locator('.ordcomp [data-tax03c-id]')).to_have_count(0)
  page.locator('[data-tax03c-search]').fill('');expect(page.locator('[data-ordcomp-featured] button')).to_have_count(6)
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  page.get_by_role('button',name='+ Agregar estudios',exact=True).click()
  assert page.get_by_role('button',name='+ Más estudios de Laboratorio',exact=True).count()==1
  assert page.get_by_role('button',name='Imagenología',exact=True).count()==1
  page.get_by_role('button',name='Imagenología',exact=True).click()
  expect(page.locator('.vis06-category-screen[data-hier-parent="imaging"]')).to_be_visible()
  expect(page.locator('dialog[open]')).to_have_count(0)
  nav('radiography');choose('rx_chest');assert_family_add('+ Más estudios de Imagenología')
  back();back();nav('pathology');nav('cervical_cytology');choose('cyto_pap');assert_family_add('+ Más estudios de Patología y biopsias')
  back();back();nav('functional');nav('cardiovascular');choose('ecg_12lead');assert_family_add('+ Más estudios funcionales')
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  expect(page.locator('.ordcomp-prepared-order')).to_have_count(4)
  assert '2' in page.locator('[data-order-group="CLINICAL_LAB"] summary').inner_text()
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),page.evaluate('document.documentElement.scrollWidth')
  expect(page.locator('.ordcomp-review-one')).to_have_count(4)
  expect(page.locator('.ordcomp-review-one').first).to_have_attribute('aria-label','Revisar orden de Laboratorio clínico')
  page.evaluate('window.ordcompQANode=document.querySelector(".ordcomp-catalog")')
  page.locator('.ordcomp-review-one').first.click()
  expect(page.locator('dialog.ordcomp-review[open]')).to_have_attribute('aria-modal','true')
  expect(page.locator('dialog.ordcomp-review')).to_have_attribute('aria-labelledby','ordcomp-review-title')
  expect(page.locator('#ordcomp-review-title')).to_contain_text('Laboratorio clínico')
  page.keyboard.press('Shift+Tab')
  assert page.evaluate('document.activeElement.closest("dialog.ordcomp-review")!==null')
  page.keyboard.press('Tab')
  assert page.evaluate('document.activeElement.closest("dialog.ordcomp-review")!==null')
  expect(page.locator('.ordcomp-review-order')).to_have_count(1)
  expect(page.get_by_role('button',name='Generar todas las órdenes',exact=True)).to_have_count(0)
  page.locator('.ordcomp-review-order textarea').fill('Indicación laboratorio')
  page.locator('.ordcomp-review-order .ordcomp-priority input[value="Urgente"]').check()
  assert page.evaluate('document.querySelector(".ordcomp-catalog")===window.ordcompQANode')
  page.get_by_role('button',name='Cerrar revisión').click()
  expect(page.locator('dialog.ordcomp-review[open]')).to_have_count(0)
  expect(page.locator('.ordcomp-review-one').first).to_be_focused()
  page.locator('.ordcomp-review-one').first.click()
  expect(page.locator('.ordcomp-review-order textarea')).to_have_value('Indicación laboratorio')
  expect(page.locator('.ordcomp-review-order .ordcomp-priority input[value="Urgente"]')).to_be_checked()
  page.keyboard.press('Escape')
  expect(page.locator('dialog.ordcomp-review[open]')).to_have_count(0)
  expect(page.locator('.ordcomp-review-one').first).to_be_focused()
  page.locator('.ordcomp-review-one').first.click()
  page.mouse.click(2,2)
  expect(page.locator('dialog.ordcomp-review[open]')).to_have_count(0)
  expect(page.locator('.ordcomp-review-one').first).to_be_focused()
  expect(page.locator('.ordcomp-prepared-order')).to_have_count(4)
  assert page.evaluate('document.querySelector(".ordcomp-catalog")===window.ordcompQANode')
  assert len(writes)==0
  page.screenshot(path=f'/tmp/ordcomp01_fixture_{width}x{height}.png',full_page=True)
  page.get_by_role('button',name='Revisar todas las órdenes',exact=True).click()
  expect(page.locator('dialog.ordcomp-review[open]')).to_have_count(1)
  expect(page.locator('#ordcomp-review-title')).to_contain_text('Revisar 4 órdenes')
  expect(page.locator('.ordcomp-review-order')).to_have_count(4)
  expect(page.locator('.ordcomp-review-order textarea').first).to_have_value('Indicación laboratorio')
  expect(page.locator('.ordcomp-review-order .ordcomp-priority input[value="Urgente"]').first).to_be_checked()
  page.get_by_role('button',name='Cerrar revisión').click()
  expect(page.locator('.ordcomp-prepared-order')).to_have_count(4)
  page.get_by_role('button',name='Revisar todas las órdenes',exact=True).click()
  page.keyboard.press('Escape')
  expect(page.locator('dialog.ordcomp-review[open]')).to_have_count(0)
  page.get_by_role('button',name='Revisar todas las órdenes',exact=True).click()
  page.mouse.click(2,2)
  expect(page.locator('dialog.ordcomp-review[open]')).to_have_count(0)
  expect(page.locator('.ordcomp-prepared-order')).to_have_count(4)
  page.get_by_role('button',name='Revisar todas las órdenes',exact=True).click()
  page.locator('.ordcomp-review-order textarea').first.fill('Indicación laboratorio')
  page.locator('.ordcomp-review-order textarea').nth(1).fill('Indicación imagen')
  expect(page.locator('.ordcomp-custom-link')).to_have_count(1)
  state['failure']=True;page.get_by_role('button',name='Generar todas las órdenes',exact=True).click()
  expect(page.locator('.ordcomp-review-order .ordcomp-error').first).to_contain_text('Revisa esta orden')
  expect(page.locator('.ordcomp-review')).to_be_visible()
  page.get_by_role('button',name='Generar todas las órdenes',exact=True).click();expect(page.locator('.ordcomp-issued-order')).to_have_count(4)
  assert len(writes)==2 and len(writes[1]['orders'])==4
  assert writes[1]['orders'][0]['indication']!=writes[1]['orders'][1]['indication']
  assert writes[1]['orders'][0]['priority']=='Urgente'
  assert writes[1]['orders'][1]['priority']=='Rutinaria'
  expect(page.locator('.ordcomp-issued-order a')).to_have_count(8)
  page.get_by_role('button',name='Volver a Estudios de diagnóstico',exact=True).click()
  expect(page.locator('.vis06-orders .vis06-head')).to_contain_text('Estudios de diagnóstico')
  page.locator('.vis06-intent-card').nth(1).click();expect(page.locator('.vis06-orders .vis06-head')).to_contain_text('Revisar órdenes pendientes')
  page.locator('.vis06-orders > .vis06-flow-back').click()
  page.locator('.vis06-intent-card').nth(2).click();expect(page.locator('.vis06-orders .vis06-head')).to_contain_text('Ver resultados e historial')
  page.locator('.vis06-orders > .vis06-flow-back').click()
  page.locator('.vis06-intent-card').first.click();nav('procedures');nav('bronchoscopy');choose('bronchoscopy_base');assert_family_add('+ Más procedimientos diagnósticos')
  back();back();nav('laboratory');nav('urine')
  page.locator('.ordcomp-full-catalog > summary').click()
  page.locator('[data-tax03c-custom-open]').click();page.locator('[data-tax03c-custom-name]').fill('Estudio propio');page.locator('[data-tax03c-custom-category]').select_option('LABORATORIO')
  assert page.locator('[data-tax03c-custom-route]').input_value()=='CLINICAL_LAB'
  page.locator('[data-tax03c-custom-add]').click()
  assert page.locator('[data-order-group="CLINICAL_LAB"]').count()==1
  back();page.locator('.vis06-lower-links').get_by_role('button',name='Buscar en todo el catálogo').click()
  page.locator('.ordcomp-full-catalog > summary').click();page.locator('[data-tax03c-custom-open]').click()
  # Global custom must not inherit the previous contextual group.
  page.locator('[data-tax03c-custom-name]').fill('Global custom');page.locator('[data-tax03c-custom-category]').select_option('OTROS')
  assert page.locator('[data-tax03c-custom-route]').input_value()==''
  page.locator('[data-tax03c-custom-add]').click();expect(page.locator('[data-tax03c-custom-error]')).to_contain_text('Selecciona el servicio')
  # Discard through the actual VIS24 registered source.
  page.evaluate("void window.mxmedPatientWorkspaceNavigationGuard.request('qa-exit',()=>{},null,['ordcomp01-composition'])")
  expect(page.locator('dialog[open]')).to_have_count(1)
  page.locator('dialog[open] button').filter(has_text='Salir sin guardar').click()
  expect(page.locator('.ordcomp')).not_to_be_visible()
  state['profile']='Dentista'
  page.reload(wait_until='networkidle');page.locator('.vis06-intent-card').first.click()
  page.locator('.vis06-primary-categories button').filter(has_text='Cone Beam').click()
  choose('dental_cbct')
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  page.get_by_role('button',name='+ Agregar estudios',exact=True).click()
  page.get_by_role('button',name='+ Más estudios dentales',exact=True).click()
  expect(page.locator('.vis06-category-screen[data-hier-level="dental"]')).to_be_visible()
  expect(page.locator('.vis06-hier-draft')).to_contain_text('1 estudios')
  expect(page.locator('dialog[open]')).to_have_count(0)
  page.locator('.vis06-primary-categories button').filter(has_text='Cone Beam').click()
  expect(page.locator('.ordcomp-prepared-order')).to_have_count(1)
  expect(page.locator('dialog[open]')).to_have_count(0)
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  page.locator('[data-tax03c-dental]').first.click()
  page.locator('[data-dental-field="coverage"]').select_option('LOCALIZED')
  page.locator('[data-tooth="16"]').click()
  expect(page.locator('[data-dental-summary]')).to_contain_text('16')
  back()
  page.locator('.vis06-primary-categories button').filter(has_text='Radiología dental 2D').click()
  choose('dental_panoramic_xray')
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  expect(page.locator('.ordcomp-prepared-order')).to_have_count(1)
  page.locator('.ordcomp-review-one').click()
  expect(page.locator('dialog.ordcomp-review[open]')).to_have_count(1)
  page.get_by_role('button',name='Generar orden',exact=True).click()
  expect(page.locator('.ordcomp-issued-order')).to_have_count(1)
  assert writes[-1]['orders'][0]['order_routing_group_key']=='DENTAL_DIAGNOSTICS'
  assert writes[-1]['orders'][0]['order_items'][0]['dental_location']['selected_teeth']==['16']
  print(f'QA_INLINE_DENTAL_FDI_{width}x{height}=PASS',flush=True)
  page.get_by_role('button',name='Volver a Estudios de diagnóstico',exact=True).click()
  page.locator('.vis06-intent-card').first.click()
  page.locator('.vis06-primary-categories button').filter(has_text='Radiología dental 2D').click()
  choose('dental_panoramic_xray')
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  assert page.evaluate("(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;})()")
  page.locator('.ordcomp-review-one').click()
  state['uncertain']=True
  page.get_by_role('button',name='Generar orden',exact=True).click()
  expect(page.get_by_role('button',name='Volver a seleccionar estudios',exact=True)).to_be_disabled()
  page.get_by_role('button',name='Reintentar emisión',exact=True).click()
  expect(page.locator('.ordcomp-issued-order')).to_have_count(1)
  assert writes[-1]==writes[-2]
  print(f'QA_UNCERTAIN_RETRY_SAME_BATCH_{width}x{height}=PASS',flush=True)
  # QA-only rule on an existing catalog identity; production config remains fixed and unchanged.
  qa_specimen=json.loads((ROOT/'modules/clinical/catalog/study_specimen_requirements_v1.json').read_text())
  qa_specimen['studies']['urine_osmolality']={'specimen_mode':'FIXED','fixed_specimen_type_key':'URINE','collection_mode':'REQUIRED_SELECTION','allowed_collection_modes':['SPOT','TIMED'],'allowed_duration_minutes':[1440]}
  page.route('**/modules/clinical/catalog/study_specimen_requirements_v1.json',lambda route:route.fulfill(content_type='application/json',body=json.dumps(qa_specimen)))
  state['profile']='Médico General'
  page.reload(wait_until='networkidle');page.locator('.vis06-intent-card').first.click();nav('laboratory');nav('urine');choose('urine_osmolality')
  if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  expect(page.locator('[data-tax03c-specimen]')).to_have_count(1)
  expect(page.locator('.specimen-item-summary')).to_contain_text('Completar datos')
  before=len(writes)
  page.locator('.ordcomp-review-one').click()
  expect(page.locator('dialog.ordcomp-review[open] .ordcomp-error').first).to_contain_text('Completa los datos de muestra')
  page.get_by_role('button',name='Generar orden',exact=True).click()
  assert len(writes)==before
  page.get_by_role('button',name='Cerrar revisión').click()
  page.locator('.specimen-editor select').first.select_option('TIMED')
  expect(page.locator('.specimen-editor select')).to_have_count(2)
  page.locator('.specimen-editor select').nth(1).select_option('1440')
  expect(page.locator('.specimen-item-summary')).to_contain_text('completos')
  assert page.locator('.specimen-editor').evaluate('(e)=>e.getBoundingClientRect().right<=innerWidth')
  page.locator('.ordcomp-review-one').click()
  page.get_by_role('button',name='Generar orden',exact=True).click()
  expect(page.locator('.ordcomp-issued-order')).to_have_count(1)
  assert writes[-1]['orders'][0]['order_items'][0]['specimen_collection_requirements']=={'version':1,'collection_mode':'TIMED','requested_duration_minutes':1440}
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
  print(f'QA_CAT03B_SYNTHETIC_TIMED_INLINE_{width}x{height}=PASS',flush=True)
  assert not errors,errors
  print(f'QA_INLINE_MULTI_BRANCH_ACCORDION_REVIEW_GUARD_{width}x{height}=PASS',flush=True)
  page.close()
 browser.close()
