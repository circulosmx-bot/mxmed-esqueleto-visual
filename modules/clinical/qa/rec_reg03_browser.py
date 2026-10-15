"""REC-REG03 functional WebKit proof on the standard disposable clinical host."""
import json
import os
from pathlib import Path
from playwright.sync_api import expect, sync_playwright

BASE=os.environ['FLOW_R1_QA_BASE']
OUT=Path(os.environ.get('REC_REG03_SCREENSHOTS','/tmp/rec-reg03-screenshots'));OUT.mkdir(parents=True,exist_ok=True)
REPO=Path(__file__).resolve().parents[3]
ENGINE=os.environ.get('REC_REG_BROWSER_ENGINE','webkit')
def check(code,condition):
    assert condition,code
    print(f'{code}=PASS',flush=True)

V2_REGIMEN={'administration':{'pattern':'AS_NEEDED','indication':'En caso de dolor intenso','repeat_mode':'REPEATABLE',
    'minimum_interval':{'value':'8','unit':'HOUR'},'maximum_dose_per_period':{'value':'3','unit':'tabletas','period':{'value':'24','unit':'HOUR'}},
    'maximum_administrations_per_period':{'count':3,'period':{'value':'24','unit':'HOUR'}}},
    'duration':{'mode':'UNTIL_DATE','date':'2026-10-20'}}
V2_MED={'medication_schema_version':2,'name':'Medicamento V2 QA','presentation':'Tabletas 10 mg','dose':'1 tableta','route':'Oral',
    'frequency':'Según necesidad: En caso de dolor intenso','duration':'Hasta el 2026-10-20','quantity':'14 tabletas',
    'instructions':'Tomar con agua','regimen':V2_REGIMEN}
V1_MED={'name':'Medicamento heredado','presentation':'Tabletas','dose':'1 tableta','route':'Oral','frequency':'Según pauta histórica',
    'duration':'Duración histórica','quantity':'10 tabletas','instructions':'Con agua'}
CONT_MED={'medication_schema_version':2,'name':'Tratamiento continuo QA','presentation':'Tabletas','dose':'1 tableta','route':'Vía oral',
    'frequency':'Cada 24 horas','duration':'Tratamiento continuo','quantity':'30 tabletas','instructions':'Tomar con agua',
    'regimen':{'administration':{'pattern':'SCHEDULED','instruction':'Cada 24 horas'},'duration':{'mode':'CONTINUOUS'}}}

with sync_playwright() as pw:
    browser=getattr(pw,ENGINE).launch()
    page=browser.new_page(viewport={'width':1440,'height':900},extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    errors=[];writes=[];state={'saved_draft':None,'preview_requests':0}
    page.on('pageerror',lambda error:errors.append(str(error)))
    templates=[
      {'uuid':'33333333-3333-4333-8333-333333333333','template_name':'Pauta V2 QA','content':{'content_schema_version':2,'medications':[V2_MED]},'status':'active','version':1,'created_at':'2026-10-14 10:00:00','updated_at':'2026-10-14 10:00:00'},
      {'uuid':'22222222-2222-4222-8222-222222222222','template_name':'Pauta continua QA','content':{'content_schema_version':2,'medications':[CONT_MED]},'status':'active','version':1,'created_at':'2026-10-14 10:00:00','updated_at':'2026-10-14 10:00:00'},
      {'uuid':'11111111-1111-4111-8111-111111111111','template_name':'Pauta heredada QA','content':{'medications':[V1_MED]},'status':'active','version':1,'created_at':'2026-10-13 10:00:00','updated_at':'2026-10-13 10:00:00'}]
    shell=(REPO/'private-app.php').read_text().split('?>',1)[1]
    shell=shell.replace('class="mxmed-auth-checking"','').replace('<script src="/assets/js/core/private-session.js?v=identity-session02b"></script>','')
    shell=shell.replace('<?= $privateBootstrapJson ?>',json.dumps({'authenticated':True,'account':{'id':'review-user'},'professional':{'doctor_id':'1','display_name':'Dra. QA'}}))
    shell=shell.replace('<?= $html((string)$professional[\'doctor_id\']) ?>','1').replace('<?= $html((string)$account[\'id\']) ?>','review-user').replace('<?= $html((string)$professional[\'display_name\']) ?>','Dra. QA')
    page.route('**/__rec_reg03_shell.php**',lambda route:route.fulfill(status=200,content_type='text/html',body=shell))
    def route_api(route):
        req=route.request;url=req.url
        if 'prescription-templates' in url:
            tail=url.split('prescription-templates',1)[1].split('?',1)[0].strip('/');parts=tail.split('/') if tail else []
            if req.method=='GET':
                data=next((x for x in templates if parts and x['uuid']==parts[0]),None) if parts else templates
                route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data}));return
            payload=json.loads(req.post_data or '{}');writes.append(payload)
            if req.method=='POST' and not parts:
                record={'uuid':'44444444-4444-4444-8444-444444444444','template_name':payload['template_name'],'content':payload['content'],
                    'status':'active','version':1,'created_at':'2026-10-14 11:00:00','updated_at':'2026-10-14 11:00:00'}
                templates.append(record);route.fulfill(status=201,content_type='application/json',body=json.dumps({'ok':True,'data':record}));return
        if '/prescription-context' in url:
            data={'revision':'reg03-browser','patient':{'patient_id':'p_plan02ux_review','name':'Paciente QA'},'allergies':{'state':'NONE','items':[]},
                'medications':{'state':'NONE','items':[]},'problems':{'state':'NONE','items':[]},'encounter':{'state':'OPEN','encounter_id':'1016','motive':'QA'}}
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data}));return
        if '/prescriptions/drafts' in url:
            drafts=[] if state['saved_draft'] is None else [{'document_uuid':'55555555-5555-4555-8555-555555555555','version':1,'payload':state['saved_draft']}]
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'drafts':drafts}}));return
        if '/prescriptions/preview' in url and req.method=='POST':
            state['preview_requests']+=1
            payload=json.loads(req.post_data or '{}');writes.append(payload)
            regimen=payload.get('payload',{}).get('prescription',{}).get('items',[{}])[0].get('regimen',{})
            duration=regimen.get('duration',{})
            if not duration.get('mode') or (duration.get('mode')=='DEFINED_DURATION' and not duration.get('value')):
                route.fulfill(status=400,content_type='application/json',body=json.dumps({'ok':False,'message':'Captura la duración del tratamiento.'}));return
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'html':'<article data-reg03-preview>Vista previa estructurada QA</article>'}}));return
        if '/prescriptions' in url and req.method=='POST':
            payload=json.loads(req.post_data or '{}');writes.append(payload);state['saved_draft']=payload['payload']
            route.fulfill(status=201,content_type='application/json',body=json.dumps({'ok':True,'data':{'document':{
                'document_id':'55555555-5555-4555-8555-555555555555','version':1,'content':{'payload':state['saved_draft']}}}}));return
        route.continue_()
    page.route('**/api/clinical/index.php/**',route_api)
    page.goto(BASE+'/__rec_reg03_shell.php?qa_tools=hide',wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    expect(page.locator('#p-expediente')).to_have_attribute('data-patient-id','p_plan02ux_review',timeout=25000)
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-tratamiento"]').click()
    page.locator('#t-tratamiento [data-rx-action="emit"]').click();expect(page.locator('[data-rx-editor-workspace]')).to_be_visible()
    page.locator('[data-rx-host-next]').click();row=page.locator('#ne_rx_grid .ne-rx-row').first
    check('BROWSER_STRUCTURED_DEFAULT',row.get_attribute('data-ne-rx-schema')=='2' and row.locator('[data-ne-rx-regimen="administration.pattern"]').input_value()=='SCHEDULED')
    check('BROWSER_NO_DUPLICATE_CONTROLS',row.locator('[data-ne-rx-regimen="administration.pattern"]').count()==1 and row.locator('[data-ne-rx-regimen="duration.mode"]').count()==1)
    boxes={key:row.locator(selector).bounding_box() for key,selector in {
      'name':'[data-ne-rx="name"]','presentation':'[data-ne-rx="presentation"]','dose':'[data-ne-rx="dose"]','route':'[data-ne-rx="route"]',
      'frequency':'[data-ne-rx="frequency"]','duration':'[data-ne-rx="duration"]','quantity':'[data-ne-rx="quantity"]',
      'instructions':'[data-ne-rx="instructions"]','private':'[data-ne-rx="private_notes"]'}.items()}
    same=lambda *keys:max(boxes[k]['y'] for k in keys)-min(boxes[k]['y'] for k in keys)<12
    check('REG04_01_COMPACT_FOUR_ROWS',same('name','presentation') and same('dose','route','frequency','duration')
        and same('quantity','instructions') and boxes['private']['y']>boxes['quantity']['y'])
    check('REG04_DEFAULT_CONTEXT_HIDDEN',page.locator('#ne_rx_grid [data-rx-regimen-context]').evaluate('e=>getComputedStyle(e).display==="none"'))
    row.locator('[data-ne-rx="name"]').fill('Medicamento V2 QA');row.locator('[data-ne-rx="presentation"]').fill('Tabletas 10 mg')
    expect(row.get_by_role('button',name='1 tableta',exact=True)).to_be_visible();row.get_by_role('button',name='1 tableta',exact=True).click()
    expect(row.get_by_role('button',name='Vía oral',exact=True)).to_be_visible();row.get_by_role('button',name='Vía oral',exact=True).click()
    row.get_by_role('button',name='Cada 12 horas',exact=True).click();row.get_by_role('button',name='7 días',exact=True).click()
    row.locator('[data-ne-rx="quantity"]').fill('14 tabletas');row.get_by_role('button',name='Tomar con agua',exact=True).click()
    check('REG04_02_06_ONE_CLICK_CHIPS',row.locator('[data-ne-rx="dose"]').input_value()=='1 tableta'
        and row.locator('[data-ne-rx="route"]').input_value()=='Vía oral' and row.locator('[data-ne-rx="frequency"]').input_value()=='Cada 12 horas'
        and row.locator('[data-ne-rx="duration"]').input_value()=='7 días' and row.locator('[data-ne-rx="instructions"]').input_value()=='Tomar con agua')
    page.locator('[data-rx-host-save]').click();expect(page.locator('#ne_rx_feedback')).to_contain_text('Borrador guardado')
    check('REG04_SCHEDULED_CHIPS_CANONICAL',state['saved_draft']['prescription']['items'][0]['regimen']=={
      'administration':{'pattern':'SCHEDULED','instruction':'Cada 12 horas'},'duration':{'mode':'DEFINED_DURATION','value':'7','unit':'DAY'}})
    frequency_select=row.locator('[data-ne-rx-regimen="administration.pattern"]');duration_select=row.locator('[data-ne-rx-regimen="duration.mode"]')
    check('REG04A_01_02_SELECTOR_PARITY',frequency_select.evaluate('e=>e.className')==duration_select.evaluate('e=>e.className')
        and abs(frequency_select.bounding_box()['width']-duration_select.bounding_box()['width'])<2)
    row.locator('[data-ne-rx-regimen="administration.pattern"]').select_option('PHYSICIAN_DEFINED')
    custom=row.locator('[data-ne-rx-regimen="administration.instruction"]');initial_height=custom.bounding_box()['height']
    custom.fill('Línea uno\nLínea dos\nLínea tres\nLínea cuatro')
    check('REG04A_03_04_CUSTOM_AUTOEXPAND',initial_height<=62 and custom.bounding_box()['height']>initial_height)
    check('REG04_13_CUSTOM_SCHEDULE',row.locator('[data-rx-regimen-section="physician-administration"]').evaluate('e=>!e.hidden'))
    row.locator('[data-ne-rx-regimen="administration.pattern"]').select_option('SCHEDULED')
    check('REG04_MODE_RETURN_PRESERVES_SCHEDULED',row.locator('[data-ne-rx="frequency"]').input_value()=='Cada 12 horas')
    row.locator('[data-ne-rx="quantity"]').fill('')
    duration_select.select_option('CONTINUOUS')
    check('REG04A_05_07_CONTINUOUS_STAGE2',row.locator('[data-rx-stage2-quantity]').evaluate('e=>e.hidden')
        and row.locator('[data-rx-regimen-section="continuous"]').inner_text()=='Sin fecha de finalización predeterminada.')
    page.locator('#ne_rx_add').evaluate('e=>e.click()');second=page.locator('#ne_rx_grid .ne-rx-row').nth(1)
    second.locator('[data-ne-rx="name"]').fill('Medicamento continuo B');second.locator('[data-ne-rx="presentation"]').fill('Cápsulas')
    second.locator('[data-ne-rx="dose"]').fill('1 cápsula');second.locator('[data-ne-rx="route"]').fill('Vía oral')
    second.locator('[data-ne-rx="frequency"]').fill('Cada 24 horas')
    second.locator('[data-ne-rx-regimen="duration.mode"]').select_option('CONTINUOUS')
    previews_before=state['preview_requests'];page.locator('[data-rx-host-next]').click()
    expect(page.locator('#ne_rx_continuous_quantity')).to_be_visible()
    completions=page.locator('[data-rx-continuous-quantity-input]');quantity_completion=completions.first
    check('REG04A_08_10_COMPLETION_GATE',quantity_completion.input_value()=='' and state['preview_requests']==previews_before
        and page.locator('.ne-rx-preview-workspace').is_hidden())
    check('REG04A_13_MULTIPLE_CONTINUOUS',completions.count()==2 and completions.nth(1).input_value()=='')
    for width,height in [(1440,900),(1366,768),(820,1180)]:
        page.set_viewport_size({'width':width,'height':height});page.locator('#ne_rx_continuous_quantity').scroll_into_view_if_needed()
        check(f'REG04A_QA_{width}_{height}',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        page.screenshot(path=str(OUT/f'continuous-completion-{ENGINE}-{width}x{height}.png'),full_page=True)
    page.set_viewport_size({'width':1440,'height':900})
    quantity_completion.fill('30 tabletas');completions.nth(1).fill('60 cápsulas');page.locator('[data-rx-continuous-quantity-confirm]').click()
    expect(page.locator('.ne-rx-preview-workspace')).to_be_visible();expect(page.locator('#ne_rx_preview_html')).to_contain_text('Vista previa estructurada QA')
    check('REG04A_11_12_CANONICAL_QUANTITY',state['saved_draft']['prescription']['items'][0]['quantity']=='30 tabletas'
        and state['saved_draft']['prescription']['items'][1]['quantity']=='60 cápsulas'
        and writes[-1]['payload']['prescription']['items'][0]['quantity']=='30 tabletas')
    page.locator('[data-rx-host-prev]').click();row=page.locator('#ne_rx_grid .ne-rx-row').first
    check('REG04A_15_BACK_NAVIGATION',row.locator('[data-ne-rx="quantity"]').input_value()=='30 tabletas'
        and row.locator('[data-rx-stage2-quantity]').evaluate('e=>e.hidden'))
    row.locator('[data-ne-rx-regimen="duration.mode"]').select_option('DEFINED_DURATION')
    check('REG04A_16_CONTINUOUS_TO_DEFINED',not row.locator('[data-rx-stage2-quantity]').evaluate('e=>e.hidden')
        and row.locator('[data-ne-rx="quantity"]').input_value()=='30 tabletas')
    row.locator('[data-ne-rx="duration"]').fill('7 días')
    page.locator('#ne_rx_grid .ne-rx-row').nth(1).locator('[data-ne-rx-del]').evaluate('e=>e.click()')
    for mode,label in [('CONTINUOUS','Tratamiento continuo'),('UNTIL_REASSESSMENT','Hasta nueva valoración médica'),('PHYSICIAN_DEFINED','Duración personalizada')]:
        row.locator('[data-ne-rx-regimen="duration.mode"]').select_option(mode)
        check('REG04_DURATION_'+mode,row.locator('[data-ne-rx="duration"]').input_value()==label)
    row.locator('[data-ne-rx-regimen="duration.mode"]').select_option('DEFINED_DURATION')
    check('REG04_MODE_RETURN_PRESERVES_DURATION',row.locator('[data-ne-rx="duration"]').input_value()=='7 días')
    row.locator('[data-ne-rx-regimen="administration.pattern"]').select_option('AS_NEEDED')
    row.locator('[data-ne-rx-regimen="administration.pattern"]').dispatch_event('change')
    conditional=page.evaluate("""()=>({rows:document.querySelectorAll('#ne_rx_grid .ne-rx-row').length,
      schemas:[...document.querySelectorAll('#ne_rx_grid .ne-rx-row')].map(e=>e.dataset.neRxSchema),
      sections:[...document.querySelectorAll('#ne_rx_grid [data-rx-regimen-section]')].map(e=>[e.dataset.rxRegimenSection,e.hidden])})""")
    check('BROWSER_PRN_CONDITIONAL',conditional['rows']==1 and ['prn',False] in conditional['sections'] and ['scheduled-frequency',True] in conditional['sections'])
    row.locator('[data-ne-rx-regimen="administration.indication"]').fill('En caso de dolor intenso')
    row.locator('[data-ne-rx-regimen="administration.repeat_mode"]').select_option('SINGLE_ADMINISTRATION')
    check('REG04_12_SINGLE_PRN',row.locator('[data-rx-regimen-section="prn-interval"]').evaluate('e=>e.hidden'))
    row.locator('[data-ne-rx-regimen="administration.repeat_mode"]').select_option('REPEATABLE')
    row.locator('[data-ne-rx-regimen="administration.minimum_interval.value"]').fill('8')
    row.locator('[data-ne-rx-regimen="administration.minimum_interval.unit"]').select_option('HOUR')
    row.locator('[data-ne-rx-regimen-toggle="max-dose"]').check();row.locator('[data-ne-rx-regimen-toggle="max-administrations"]').check()
    check('REG04_11_OPTIONAL_LIMITS',not row.locator('[data-rx-regimen-section="max-dose"]').evaluate('e=>e.hidden')
        and not row.locator('[data-rx-regimen-section="max-administrations"]').evaluate('e=>e.hidden'))
    row.locator('[data-ne-rx-regimen-toggle="max-dose"]').uncheck();row.locator('[data-ne-rx-regimen-toggle="max-administrations"]').uncheck()
    row.locator('[data-ne-rx="duration"]').fill('')
    page.locator('[data-rx-host-next]').click();expect(page.locator('#ne_rx_feedback')).to_contain_text('Captura la duración del tratamiento')
    check('BROWSER_SPANISH_VALIDATION',True)
    row.locator('[data-ne-rx-regimen="duration.mode"]').select_option('UNTIL_DATE')
    row.locator('[data-ne-rx-regimen="duration.date"]').fill('2026-10-20')
    check('BROWSER_UNTIL_DATE_CONDITIONAL',not page.locator('#ne_rx_grid [data-rx-regimen-section="until-date"]').evaluate('e=>e.hidden'))
    check('BROWSER_MANUAL_QUANTITY',row.locator('[data-ne-rx="quantity"]').get_attribute('placeholder')=='Ej. 20 tabletas')
    page.locator('[data-rx-host-save]').click();expect(page.locator('#ne_rx_feedback')).to_contain_text('Borrador guardado')
    check('BROWSER_DRAFT_SCHEMA_V2',state['saved_draft']['prescription']['medication_schema_version']==2 and state['saved_draft']['prescription']['items'][0]['regimen']['administration']['pattern']=='AS_NEEDED')
    page.reload(wait_until='domcontentloaded');page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-tratamiento"]').click()
    page.locator('#t-tratamiento [data-rx-action="emit"]').click();page.locator('[data-rx-host-next]').click()
    page.wait_for_function("!document.querySelector('#ne_rx_continue_draft').classList.contains('d-none')")
    page.locator('#ne_rx_continue_draft').evaluate('e=>e.click()')
    resumed=page.locator('#ne_rx_grid .ne-rx-row').first
    check('BROWSER_DRAFT_EXACT_RESUME',resumed.locator('[data-ne-rx-regimen="administration.indication"]').input_value()=='En caso de dolor intenso'
        and resumed.locator('[data-ne-rx-regimen="administration.minimum_interval.value"]').input_value()=='8'
        and resumed.locator('[data-ne-rx-regimen="duration.date"]').input_value()=='2026-10-20'
        and resumed.locator('[data-ne-rx="quantity"]').input_value()=='30 tabletas')
    page.locator('#ne_rx_save_template').click();page.locator('#rx_template_save_current [name="template_name"]').fill('Guardada V2 QA')
    page.locator('#modalPrescriptionTemplateFlow [data-rx-tpl-action="save-current"]').click();expect(page.locator('#modalPrescriptionTemplateFlow')).to_be_hidden()
    saved_template=writes[-1]
    check('BROWSER_TEMPLATE_V2_SAVE',saved_template['content']['content_schema_version']==2 and saved_template['content']['medications'][0]['regimen']['duration']['mode']=='UNTIL_DATE')
    check('BROWSER_TEMPLATE_EXCLUDES_PRIVATE','private_notes' not in json.dumps(saved_template) and 'patient_id' not in json.dumps(saved_template)
        and 'legacy_frequency_reference' not in json.dumps(saved_template) and 'legacy_duration_reference' not in json.dumps(saved_template))
    page.locator('#ne_rx_use_template').click();page.locator('#rx_tpl_search').fill('Pauta heredada')
    page.locator('#rx_tpl_selector_list [data-rx-tpl-action="use"]').click();page.locator('[data-rx-tpl-action="confirm-use"]').click()
    legacy=page.locator('#ne_rx_grid .ne-rx-row').first
    check('BROWSER_V1_NO_INFERENCE',legacy.get_attribute('data-ne-rx-schema')=='1' and legacy.locator('[data-ne-rx="frequency"]').input_value()=='Según pauta histórica')
    legacy.locator('[data-ne-rx-edit]').click()
    expect(legacy.get_by_text('Convertir a pauta estructurada')).to_be_visible()
    legacy.get_by_text('Convertir a pauta estructurada').click();converted=page.locator('#ne_rx_grid .ne-rx-row').first
    check('BROWSER_EXPLICIT_CONVERSION',converted.get_attribute('data-ne-rx-schema')=='2' and converted.locator('[data-ne-rx-regimen="duration.mode"]').input_value()=='')
    for width,height in [(1440,900),(1366,768),(820,1180)]:
        page.set_viewport_size({'width':width,'height':height});converted.scroll_into_view_if_needed()
        check(f'QA_{width}_{height}',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        page.screenshot(path=str(OUT/f'regimen-{ENGINE}-{width}x{height}.png'),full_page=True)
    # Reapply V2 and verify exact editable fields and safe preview navigation.
    page.locator('#ne_rx_use_template').click();page.locator('#rx_tpl_search').fill('Pauta V2')
    page.locator('#rx_tpl_selector_list [data-rx-tpl-action="use"]').click();page.locator('[data-rx-tpl-action="confirm-use"]').click()
    applied=page.locator('#ne_rx_grid .ne-rx-row').first
    check('BROWSER_V2_APPLY_EXACT',applied.locator('[data-ne-rx-regimen="administration.indication"]').input_value()=='En caso de dolor intenso'
        and applied.locator('[data-ne-rx-regimen="duration.date"]').input_value()=='2026-10-20')
    page.locator('[data-rx-host-next]').click();expect(page.locator('[data-reg03-preview]')).to_be_visible()
    check('BROWSER_STAGE3_USABLE',page.locator('[data-rx-editor-workspace]').get_attribute('data-rx-stage')=='3' and page.locator('#ne_rx_sign').is_hidden())
    page.locator('[data-rx-host-prev]').click();page.locator('#ne_rx_use_template').click();page.locator('#rx_tpl_search').fill('Pauta continua')
    page.locator('#rx_tpl_selector_list [data-rx-tpl-action="use"]').click();page.locator('[data-rx-tpl-action="confirm-use"]').click()
    continuous_template=page.locator('#ne_rx_grid .ne-rx-row').first
    check('REG04A_18_TEMPLATE_REVIEW',continuous_template.locator('[data-rx-stage2-quantity]').evaluate('e=>e.hidden'))
    page.locator('[data-rx-host-next]').click();expect(page.locator('#ne_rx_continuous_quantity')).to_be_visible()
    check('REG04A_18_TEMPLATE_QUANTITY_NOT_SILENT',page.locator('[data-rx-continuous-quantity-input]').input_value()=='30 tabletas'
        and page.locator('[data-rx-host-next]').is_disabled())
    check('BROWSER_NO_JS_ERRORS',not errors)
    browser.close()
    print(f'REC_REG03_BROWSER_{ENGINE.upper()}=PASS',flush=True)
