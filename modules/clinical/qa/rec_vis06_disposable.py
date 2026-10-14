"""REC-VIS06 browser workflow and responsive visual evidence; no clinical emission."""
import json
import os
from pathlib import Path
from playwright.sync_api import expect, sync_playwright

BASE=os.environ['FLOW_R1_QA_BASE']
OUT=Path(os.environ.get('REC_VIS06_SCREENSHOTS','/tmp/rec-vis06-screenshots'))
OUT.mkdir(parents=True,exist_ok=True)

def check(name,value):
    assert value,name
    print(f'{name}=PASS',flush=True)

MED={'name':'Losartán','presentation':'Tableta 50 mg','dose':'1 tableta','route':'Oral',
     'frequency':'Cada 24 horas','duration':'30 días','quantity':'30 tabletas','instructions':'Tomar a la misma hora'}
TEMPLATE={'uuid':'11111111-1111-4111-8111-111111111111','template_name':'Hipertensión — manejo habitual',
          'content':{'medications':[MED]},'status':'active','version':1,
          'created_at':'2026-10-14 08:00:00','updated_at':'2026-10-14 08:00:00'}

with sync_playwright() as pw:
    browser=pw.webkit.launch()
    page=browser.new_page(viewport={'width':1440,'height':900},extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    errors=[];writes=[];qr_requests=[]
    page.on('pageerror',lambda error:errors.append(str(error)))

    def route_api(route):
        req=route.request;url=req.url;payload=json.loads(req.post_data or '{}')
        if 'prescription-templates' in url:
            tail=url.split('prescription-templates',1)[1].split('?',1)[0].strip('/')
            data=TEMPLATE if tail else [TEMPLATE]
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data}));return
        if '/prescription-context' in url:
            data={'revision':'rec-vis06-context','patient':{'patient_id':'p_plan02ux_review','name':'Marina Solís Vega'},
                  'allergies':{'state':'PRESENT','items':[{'substance':'Penicilina','reaction':'Exantema','validation_state':'CLINICIAN_REVIEWED'}]},
                  'medications':{'state':'PRESENT','items':[{'medication_name':'Metformina','dose':'500 mg','route':'Oral','frequency':'Cada 12 horas','state':'ACTIVE_CONFIRMED'}]},
                  'problems':{'state':'PRESENT','items':[{'label':'Hipertensión arterial'}]},
                  'encounter':{'state':'OPEN','encounter_id':'1016','motive':'Control habitual'}}
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data}));return
        if '/prescriptions/drafts' in url:
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'drafts':[]}}));return
        if '/prescriptions/preview' in url:
            signed=bool(payload.get('include_signature'))
            html=f'<article data-qa-preview><h2>RECETA MÉDICA</h2><p>Marina Solís Vega</p><p>{payload["payload"]["prescription"]["items"][0]["name"]}</p>{"<p>Firma médica aplicada</p>" if signed else ""}</article>'
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'html':html,'payload':payload.get('payload')}}));return
        if '/prescription-qr-sessions' in url:
            qr_requests.append((req.method,url))
            if req.method=='POST':
                data={'token':'rec-vis06-qr','mobile_url':BASE+'/signature-mobile.html?token=rec-vis06-qr','status':'pending'}
            else:data={'status':'pending'}
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data}));return
        if '/prescriptions' in url and req.method=='POST':
            writes.append(payload.get('intent'))
            body_payload=payload.get('payload') or {}
            if payload.get('signature'):
                body_payload.setdefault('signatures',{})['doctor']=payload['signature']
            body_payload.setdefault('patient_snapshot',{'id':'p_plan02ux_review','full_name':'Marina Solís Vega'})
            body_payload.setdefault('physician_snapshot',{'user_id':'review-user','doctor_id':'1','full_name':'Leticia Muñoz Romo'})
            body_payload.setdefault('professional_context',{})
            body_payload.setdefault('issue_date','2026-10-14')
            body_payload.setdefault('encounter_reference',None)
            body_payload.setdefault('signatures',{'doctor':None})
            doc={'document_id':'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','version':1,'status':'draft','content':{'payload':body_payload}}
            route.fulfill(status=201,content_type='application/json',body=json.dumps({'ok':True,'data':{'document':doc}}));return
        route.continue_()

    page.route('**/api/clinical/index.php/**',route_api)
    page.goto(BASE+'/index.html?qa_tools=hide',wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    expect(page.locator('#p-expediente')).to_have_attribute('data-patient-id','p_plan02ux_review',timeout=25000)
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-tratamiento"]').click()
    page.wait_for_timeout(400)
    expect(page.locator('[data-rx-view="landing"] h2')).to_have_text('RECETAS')
    page.screenshot(path=str(OUT/'a-landing-1440x900.png'))
    page.locator('#t-tratamiento [data-rx-action="emit"]').click()
    expect(page.locator('#rx-editor-title')).to_have_text('NUEVA RECETA')
    page.screenshot(path=str(OUT/'b-stage1-1440x900.png'))
    check('TITLE_NORMALIZATION',True)
    page.locator('[data-rx-host-next]').click()
    expect(page.locator('[data-ne-rx-state="expanded"]')).to_be_visible()
    page.screenshot(path=str(OUT/'c-stage2-empty-1440x900.png'))
    check('EMPTY_GROUP_HIDDEN',page.locator('#ne_rx_added_heading').is_hidden())
    check('NO_COMPETING_ADD_ACTION',page.locator('#ne_rx_add').is_hidden())
    page.locator('[data-ne-rx="name"]').fill('Losartán')
    page.locator('[data-ne-rx="presentation"]').fill('Tableta 50 mg')
    page.screenshot(path=str(OUT/'d-stage2-form-open-1440x900.png'))
    page.locator('[data-ne-rx-collapse]').click()
    expect(page.locator('#ne_rx_added_heading')).to_have_text('MEDICAMENTOS AGREGADOS (1)')
    expect(page.locator('#ne_rx_add')).to_be_visible()
    expect(page.locator('[data-rx-host-next]')).to_have_text('Continuar a vista previa →')
    page.screenshot(path=str(OUT/'e-stage2-one-medication-1440x900.png'))
    check('ONE_MEDICATION_HIERARCHY',True)
    page.locator('#ne_rx_add').click()
    expect(page.locator('#ne_rx_add')).to_be_hidden()
    page.locator('.ne-rx-row[data-ne-rx-state="expanded"] [data-ne-rx="name"]').fill('Metformina')
    page.locator('.ne-rx-row[data-ne-rx-state="expanded"] [data-ne-rx="presentation"]').fill('Tableta 500 mg')
    page.locator('.ne-rx-row[data-ne-rx-state="expanded"] [data-ne-rx-collapse]').click()
    expect(page.locator('#ne_rx_added_heading')).to_have_text('MEDICAMENTOS AGREGADOS (2)')
    page.screenshot(path=str(OUT/'f-stage2-multiple-medications-1440x900.png'))
    check('MULTIPLE_MEDICATION_HIERARCHY',True)
    page.locator('#ne_rx_use_template').click()
    page.locator('#rx_tpl_selector_list [data-rx-tpl-action="use"]').click()
    expect(page.locator('[data-rx-tpl-action="confirm-use"]')).to_be_visible()
    page.locator('[data-rx-tpl-action="confirm-use"]').click()
    expect(page.locator('#modalPrescriptionTemplateFlow')).to_be_hidden()
    expect(page.locator('#ne_rx_added_heading')).to_have_text('MEDICAMENTOS AGREGADOS (1)')
    expect(page.locator('[data-rx-template-safety]')).to_contain_text('Penicilina')
    page.screenshot(path=str(OUT/'g-stage2-template-applied-1440x900.png'))
    check('TEMPLATE_AND_SAFETY_PRESERVED',True)
    page.locator('[data-rx-host-next]').click()
    expect(page.locator('[data-qa-preview]')).to_be_visible()
    page.screenshot(path=str(OUT/'h-stage3-preview-1440x900.png'))
    page.locator('[data-rx-host-next]').click()
    expect(page.locator('#ne_rx_sign')).to_be_visible()
    check('SIGNATURE_CONTROLS',all(page.locator(sel).is_visible() for sel in ['#ne_rx_apply_signature','#ne_rx_registered_signature','#ne_rx_remote_signature']))
    check('QR_NOT_AUTOMATIC',len(qr_requests)==0)
    canvas=page.locator('#ne_rx_signature_canvas');box=canvas.bounding_box();assert box
    page.mouse.move(box['x']+20,box['y']+90);page.mouse.down();page.mouse.move(box['x']+180,box['y']+35,steps=8);page.mouse.up()
    page.locator('#ne_rx_apply_signature').click()
    expect(page.locator('#ne_rx_final_review')).to_be_enabled()
    page.screenshot(path=str(OUT/'i-stage4-signature-1440x900.png'))
    check('DIRECT_SIGNATURE',writes.count('apply_signature')==1)
    page.locator('#ne_rx_remote_signature').click()
    expect(page.locator('#modalResponsivaSignatureQr')).to_be_visible()
    check('REMOTE_SIGNATURE_EXPLICIT',len(qr_requests)>0)
    page.locator('#modalResponsivaSignatureQr [data-bs-dismiss="modal"]').first.click()
    expect(page.locator('#modalResponsivaSignatureQr')).to_be_hidden()
    page.locator('[data-rx-host-next]').click()
    expect(page.locator('#ne_rx_final')).to_be_visible()
    page.screenshot(path=str(OUT/'j-stage5-final-review-1440x900.png'))
    check('NO_EMISSION',writes.count('finalize')==0)
    check('SIGNATURE_REQUIRED_BEFORE_FINAL',writes.count('apply_signature')==1)

    for width,height,label in [(1366,768,'1366_768'),(820,1180,'820_1180')]:
        page.set_viewport_size({'width':width,'height':height})
        check(label+'_FIT',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        page.screenshot(path=str(OUT/f'j-stage5-{width}x{height}.png'))
    check('NO_JS_ERRORS',not errors)
    browser.close()
    print('REC_VIS06_BROWSER=PASS',flush=True)
