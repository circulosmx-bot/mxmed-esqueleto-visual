"""REC-VIS06/07 browser workflow and responsive visual evidence; no clinical emission."""
import json
import os
import re
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
SOURCE=Path('private-app.php').read_text()
APP_HTML='<!DOCTYPE html>'+SOURCE.split('<!DOCTYPE html>',1)[1]
APP_HTML=APP_HTML.replace('<html lang="es" class="mxmed-auth-checking">','<html lang="es">')
APP_HTML=re.sub(r'<script id="mxmed-private-bootstrap"[\s\S]*?</script>','',APP_HTML,count=1)
APP_HTML=re.sub(r'<script src="/assets/js/core/private-session\.js[^>]*></script>','',APP_HTML,count=1)
APP_HTML=re.sub(r'data-doctor-id="<\?=[^>]+\?>" data-user-id="<\?=[^>]+\?>" data-doctor-name="<\?=[^>]+\?>"',
                'data-doctor-id="1" data-user-id="review-user" data-doctor-name="Leticia Muñoz Romo"',APP_HTML,count=1)

with sync_playwright() as pw:
    browser=pw.webkit.launch()
    page=browser.new_page(viewport={'width':1440,'height':900},extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    errors=[];writes=[];write_payloads=[];qr_requests=[];preview_modes=[];fail_next_preview=[False]
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
            mode=payload.get('payload',{}).get('presentation',{}).get('professional_header','logo_and_data')
            preview_modes.append(mode)
            if fail_next_preview[0]:
                fail_next_preview[0]=False
                route.fulfill(status=503,content_type='application/json',body=json.dumps({'ok':False,'message':'preview_qa_failure'}));return
            logo='<img data-qa-logo src="/assets/mexico-medico.svg" alt="Logo médico">' if mode=='logo_and_data' else ''
            header='' if mode=='hidden' else f'<header data-qa-professional><strong>Leticia Muñoz Romo</strong>{logo}</header>'
            html=f'<article data-qa-preview class="rx-doc-sheet document-sheet doc-base-sheet doc-base-sheet--letter doc-base-print-safe"><div class="doc-base-sheet-inner doc-base-body">{header}<h2>RECETA MÉDICA</h2><p>Marina Solís Vega</p><p>{payload["payload"]["prescription"]["items"][0]["name"]}</p>{"<p>Firma médica aplicada</p>" if signed else ""}</div></article>'
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'html':html,'payload':payload.get('payload')}}));return
        if '/prescription-qr-sessions' in url:
            qr_requests.append((req.method,url))
            if req.method=='POST':
                data={'token':'rec-vis06-qr','mobile_url':BASE+'/signature-mobile.html?token=rec-vis06-qr','status':'pending'}
            else:data={'status':'pending'}
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data}));return
        if '/prescriptions' in url and req.method=='POST':
            writes.append(payload.get('intent'))
            write_payloads.append(payload)
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

    page.route('**/qa-rec-vis08.html*',lambda route:route.fulfill(status=200,content_type='text/html',body=APP_HTML))
    page.route('**/api/clinical/index.php/**',route_api)
    page.goto(BASE+'/qa-rec-vis08.html?qa_tools=hide',wait_until='domcontentloaded')
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
    check('ADD_ACTION_INSIDE_GROUP_HEADER',page.locator('#ne_rx_add').evaluate("e=>e.parentElement.id==='ne_rx_added_head'"))
    header_positions=page.evaluate("()=>{const h=document.querySelector('#ne_rx_added_heading').getBoundingClientRect(),b=document.querySelector('#ne_rx_add').getBoundingClientRect();return {sameRow:Math.abs(h.top-b.top)<12,right:b.left>h.left}}")
    check('ADD_ACTION_RIGHT_ALIGNED',header_positions['sameRow'] and header_positions['right'])
    expect(page.locator('[data-rx-host-next]')).to_have_text('Continuar a vista previa →')
    colors=page.evaluate("""()=>{const style=s=>getComputedStyle(document.querySelector(s));return {
      groupBackground:style('#ne_rx_medication_composition').backgroundColor,
      groupBorder:style('#ne_rx_medication_composition').borderTopColor,
      cardBackground:style('.ne-rx-row[data-ne-rx-state="collapsed"]').backgroundColor,
      addBackground:style('#ne_rx_add').backgroundColor,
      addColor:style('#ne_rx_add').color,
      nextBackground:style('[data-rx-host-next]').backgroundColor
    }}""")
    print('REC_VIS08_COLORS='+json.dumps(colors),flush=True)
    check('ADDED_GROUP_TURQUOISE',colors['groupBackground']=='rgb(234, 248, 249)' and colors['groupBorder']=='rgb(85, 189, 199)')
    check('INNER_MEDICATION_CARD_WHITE',colors['cardBackground']=='rgb(255, 255, 255)')
    check('ADD_ANOTHER_STRONG_CTA',colors['addBackground']=='rgb(0, 143, 164)' and colors['addColor']=='rgb(255, 255, 255)')
    check('CONTINUE_PREVIEW_REMAINS_PRIMARY',page.locator('[data-rx-host-next]').evaluate("e=>e.classList.contains('btn-primary')") and colors['nextBackground']!=colors['addBackground'])
    page.screenshot(path=str(OUT/'e-stage2-one-medication-1440x900.png'))
    check('ONE_MEDICATION_HIERARCHY',True)
    page.locator('[data-ne-rx-edit]').first.click()
    expect(page.locator('#ne_rx_add')).to_be_hidden()
    page.locator('[data-ne-rx-cancel]').first.click()
    expect(page.locator('#ne_rx_add')).to_be_visible()
    check('EDIT_FLOW_PRESERVED',True)
    page.locator('[data-ne-rx-dup]').first.click()
    expect(page.locator('#ne_rx_added_heading')).to_have_text('MEDICAMENTOS AGREGADOS (2)')
    page.locator('[data-ne-rx-del]').last.click()
    expect(page.locator('#ne_rx_added_heading')).to_have_text('MEDICAMENTOS AGREGADOS (1)')
    check('DUPLICATE_DELETE_FLOW_PRESERVED',True)
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
    for width,height,label in [(1366,768,'1366_768'),(820,1180,'820_1180')]:
        page.set_viewport_size({'width':width,'height':height})
        check(label+'_STAGE2_FIT',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        check(label+'_STAGE2_ACTIONS_VISIBLE',page.locator('#ne_rx_add').is_visible() and page.locator('[data-rx-host-next]').is_visible())
        check(label+'_STAGE2_CARD_WHITE',page.locator('.ne-rx-row[data-ne-rx-state="collapsed"]').first.evaluate("e=>getComputedStyle(e).backgroundColor")=='rgb(255, 255, 255)')
        if width==820: check(label+'_STAGE2_ACTION_WRAPS',page.locator('#ne_rx_added_head').evaluate("e=>getComputedStyle(e).flexDirection")=='column')
        page.locator('#ne_rx_added_head').scroll_into_view_if_needed()
        page.screenshot(path=str(OUT/f'g-stage2-template-{width}x{height}.png'))
    page.set_viewport_size({'width':1440,'height':900})
    with page.expect_request(lambda req:req.method=='POST' and '/prescriptions' in req.url and req.post_data_json.get('intent')=='draft'):
        page.locator('[data-rx-host-save]').click()
    expect(page.locator('#ne_rx_draft_status')).to_contain_text('Borrador guardado')
    check('SAVE_DRAFT_PRESERVED',writes.count('draft')==1)
    page.locator('[data-rx-host-next]').click()
    expect(page.locator('[data-qa-preview]')).to_be_visible()
    check('PRESENTATION_CONTROL_STAGE3',page.locator('#ne_rx_presentation').is_visible() and page.locator('input[name="ne_rx_professional_header"]').count()==3)
    check('DEFAULT_LOGO_AND_DATA',page.locator('input[name="ne_rx_professional_header"][value="logo_and_data"]').is_checked() and page.locator('[data-qa-logo]').is_visible())
    page.locator('input[name="ne_rx_professional_header"][value="data_only"]').check()
    expect(page.locator('[data-qa-professional]')).to_be_visible()
    expect(page.locator('[data-qa-logo]')).to_have_count(0)
    check('DATA_ONLY_PREVIEW',page.locator('[data-qa-professional]').is_visible() and page.locator('[data-qa-logo]').count()==0)
    page.locator('input[name="ne_rx_professional_header"][value="hidden"]').check()
    expect(page.locator('[data-qa-professional]')).to_have_count(0)
    check('HIDDEN_HEADER_PREVIEW',page.locator('[data-qa-professional]').count()==0)
    page.locator('input[name="ne_rx_professional_header"][value="logo_and_data"]').check()
    expect(page.locator('[data-qa-logo]')).to_be_visible()
    check('IMMEDIATE_CANONICAL_PREVIEW',preview_modes[-3:]==['data_only','hidden','logo_and_data'])
    page.locator('input[name="ne_rx_professional_header"][value="data_only"]').check()
    expect(page.locator('[data-qa-logo]')).to_have_count(0)
    page.locator('[data-rx-host-prev]').click()
    expect(page.locator('[data-rx-engine-stage="2"]')).to_be_visible()
    with page.expect_request(lambda req:req.method=='POST' and '/prescriptions' in req.url and req.post_data_json.get('intent')=='draft'):
        page.locator('[data-rx-host-save]').click()
    expect(page.locator('#ne_rx_draft_status')).to_contain_text('Borrador guardado')
    check('PRESENTATION_DRAFT_PERSISTENCE',write_payloads[-1]['payload']['presentation']=={'version':2,'professional_header':'data_only'})
    page.locator('[data-rx-host-next]').click()
    expect(page.locator('[data-qa-professional]')).to_be_visible()
    expect(page.locator('[data-qa-logo]')).to_have_count(0)
    check('PRESENTATION_NAVIGATION_PERSISTENCE',page.locator('input[name="ne_rx_professional_header"][value="data_only"]').is_checked())
    fail_next_preview[0]=True
    page.locator('input[name="ne_rx_professional_header"][value="hidden"]').check()
    expect(page.locator('#ne_rx_presentation_status')).to_contain_text('No se pudo actualizar')
    check('PREVIEW_FAILURE_FAILS_CLOSED',page.locator('#ne_rx_preview_html').inner_html()=='' and page.locator('[data-rx-host-next]').is_disabled())
    page.locator('input[name="ne_rx_professional_header"][value="logo_and_data"]').check()
    expect(page.locator('[data-qa-logo]')).to_be_visible()
    check('SELECTOR_EXCLUDED_FROM_DOCUMENT_HTML',page.locator('#ne_rx_preview_html #ne_rx_presentation').count()==0
          and 'Formato de encabezado' not in page.locator('#ne_rx_preview_html').inner_html())
    desktop_geometry=page.evaluate("""()=>{const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height}};return {
      workspace:rect('.ne-rx-preview-workspace'),card:rect('#ne_rx_presentation'),paper:rect('#ne_rx_preview_html [data-qa-preview]'),
      options:[...document.querySelectorAll('.ne-rx-presentation-options>label>span')].map(e=>rect('.ne-rx-presentation-options>label:nth-child('+([...e.parentElement.parentElement.children].indexOf(e.parentElement)+1)+')>span')),
      columns:getComputedStyle(document.querySelector('.ne-rx-preview-workspace')).gridTemplateColumns
    }}""")
    check('1440_900_SELECTOR_COMPACT',209 <= desktop_geometry['card']['width'] <= 212
          and all(36 <= item['height'] <= 40 for item in desktop_geometry['options']))
    check('1440_900_SELECTOR_LEFT_OF_PAPER',desktop_geometry['card']['right'] < desktop_geometry['paper']['left']
          and desktop_geometry['card']['top'] < desktop_geometry['paper']['bottom'])
    check('1440_900_NO_DOCUMENT_OVERLAP',desktop_geometry['card']['right'] <= desktop_geometry['paper']['left'])
    check('1440_900_PAPER_READABLE',desktop_geometry['paper']['width'] >= 800)
    selected_style=page.locator('input[name="ne_rx_professional_header"][value="logo_and_data"]+span').evaluate("e=>({background:getComputedStyle(e).backgroundColor,border:getComputedStyle(e).borderTopColor})")
    check('SELECTED_STATE_TURQUOISE',selected_style=={'background':'rgb(233, 249, 250)','border':'rgb(0, 143, 164)'})
    page.locator('input[name="ne_rx_professional_header"][value="logo_and_data"]').focus()
    page.keyboard.press('ArrowDown')
    expect(page.locator('input[name="ne_rx_professional_header"][value="data_only"]')).to_be_checked()
    expect(page.locator('[data-qa-logo]')).to_have_count(0)
    check('PRESENTATION_KEYBOARD_NAVIGATION',True)
    page.locator('input[name="ne_rx_professional_header"][value="logo_and_data"]').check()
    expect(page.locator('[data-qa-logo]')).to_be_visible()
    page.screenshot(path=str(OUT/'h-stage3-preview-1440x900.png'))
    for width,height,label in [(1366,768,'1366_768'),(820,1180,'820_1180')]:
        page.set_viewport_size({'width':width,'height':height})
        check(label+'_STAGE3_FIT',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        check(label+'_STAGE3_SELECTOR_VISIBLE',page.locator('#ne_rx_presentation').is_visible()
              and page.locator('input[name="ne_rx_professional_header"]').count()==3)
        geometry=page.evaluate("""()=>{const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height}};return {
          card:rect('#ne_rx_presentation'),paper:rect('#ne_rx_preview_html [data-qa-preview]'),
          columns:getComputedStyle(document.querySelector('.ne-rx-preview-workspace')).gridTemplateColumns
        }}""")
        check(label+'_STAGE3_NO_OVERLAP',geometry['card']['right'] <= geometry['paper']['left'] if width>950 else geometry['card']['bottom'] <= geometry['paper']['top'])
        check(label+'_STAGE3_LAYOUT',(' ' in geometry['columns']) if width>950 else (' ' not in geometry['columns']))
        check(label+'_STAGE3_PAPER_READABLE',geometry['paper']['width'] >= (800 if width>950 else 650))
        page.locator('#ne_rx_presentation').scroll_into_view_if_needed()
        page.screenshot(path=str(OUT/f'h-stage3-preview-{width}x{height}.png'))
    page.set_viewport_size({'width':1440,'height':900})
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
    expect(page.locator('#modalDocumentSignatureQr')).to_be_visible()
    check('REMOTE_SIGNATURE_EXPLICIT',len(qr_requests)>0)
    page.locator('#modalDocumentSignatureQr [data-bs-dismiss="modal"]').first.click()
    expect(page.locator('#modalDocumentSignatureQr')).to_be_hidden()
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
    print('REC_VIS07_BROWSER=PASS',flush=True)
