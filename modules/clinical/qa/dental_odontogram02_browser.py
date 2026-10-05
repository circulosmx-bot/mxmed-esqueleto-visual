"""Read-only browser QA for the shared V2 selector served by the real physician runtime."""
import json
from pathlib import Path
from urllib.parse import parse_qs, urlsplit
from playwright.sync_api import expect, sync_playwright

BASE='http://127.0.0.1:18148'
ROOT=Path(__file__).resolve().parents[3]
HTML='''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/css/clinical/dental-odontogram-v2.css">
<link rel="stylesheet" href="/assets/css/clinical/dental-location-v1.css">
<style>body{margin:0;background:#eff9fa}#host{max-width:950px;margin:10px auto;padding:10px}</style></head><body>
<div id="host"></div><script src="/assets/js/clinical/dental-location-v1.js"></script>
<script src="/assets/js/clinical/dental-odontogram-v2.js"></script>
<script src="/assets/js/clinical/dental-location-v2-adapter.js"></script></body></html>'''
COMPOSER_HTML=HTML.replace('</body>','<script src="/assets/js/clinical/tax03c-study-composer.js"></script></body>')

def check(condition,name):
    assert condition,name
    print(name+'=PASS',flush=True)

with sync_playwright() as playwright:
    browser=playwright.chromium.launch(headless=True)
    for width,height in [(1440,900),(1366,768),(390,844)]:
        page=browser.new_page(viewport={'width':width,'height':height})
        errors=[]
        page.on('pageerror',lambda error:errors.append(str(error)))
        page.route(BASE+'/__dental_odontogram02__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
        page.goto(BASE+'/__dental_odontogram02__',wait_until='networkidle')
        page.evaluate('''window.qa=mxmedDentalOdontogramV2.mount(document.querySelector('#host'),{
            authorityVersion:2,allowedDentitionModes:['PERMANENT','PRIMARY','MIXED'],
            allowedLocationModes:['SINGLE_TOOTH','MULTIPLE_TEETH','QUADRANT','ARCH','REGION','BILATERAL_REGION'],
            minSelection:1,maxSelection:52,onChange:value=>window.latest=value})''')
        page.evaluate('qa.ready')
        expect(page.locator('.odontogram-tooth')).to_have_count(32)
        check(page.locator('[data-fdi="16"]').evaluate('(button)=>{const r=button.getBoundingClientRect();return r.width>=44&&r.height>=44}'),f'QA_{width}x{height}_TOUCH_TARGET')
        check(page.locator('[data-fdi="16"]').get_attribute('aria-label').startswith('Pieza 16, primer molar superior derecho'),f'QA_{width}x{height}_PERMANENT_FDI')
        page.locator('[data-fdi="16"]').focus()
        page.keyboard.press('Enter')
        check(page.evaluate('qa.value().tooth_fdi_codes')==['16'],f'QA_{width}x{height}_KEYBOARD')
        page.locator('[data-odontogram-clear]').click()
        page.locator('[data-odontogram-mode="MULTIPLE_TEETH"]').click()
        page.locator('[data-odontogram-dentition="PRIMARY"]').click()
        expect(page.locator('.odontogram-tooth')).to_have_count(20)
        check(page.locator('[data-fdi="55"]').count()==1 and page.locator('[data-fdi="16"]').count()==0,f'QA_{width}x{height}_PRIMARY_FDI')
        page.locator('[data-odontogram-dentition="MIXED"]').click()
        expect(page.locator('.odontogram-tooth')).to_have_count(52)
        page.locator('[data-fdi="16"]').click()
        page.locator('[data-fdi="54"]').click()
        check(page.evaluate('qa.value().tooth_fdi_codes')==['16','54'],f'QA_{width}x{height}_MIXED_SELECTION')
        page.locator('[data-odontogram-dentition="PERMANENT"]').click()
        check(page.evaluate('qa.value().dentition_mode')=='MIXED' and '54' in page.locator('.odontogram-notice').inner_text(),f'QA_{width}x{height}_NO_SILENT_DISCARD')
        page.locator('[data-odontogram-clear]').click()
        page.locator('[data-odontogram-mode="QUADRANT"]').click()
        page.locator('[data-odontogram-choice="UPPER_RIGHT"]').click()
        check(page.evaluate('qa.value().quadrant_key')=='UPPER_RIGHT',f'QA_{width}x{height}_QUADRANT')
        page.locator('[data-odontogram-clear]').click()
        page.locator('[data-odontogram-mode="ARCH"]').click()
        page.locator('[data-odontogram-choice="MAXILLARY"]').click()
        check(page.evaluate('qa.value().arch_key')=='MAXILLARY',f'QA_{width}x{height}_ARCH')
        page.locator('[data-odontogram-clear]').click()
        page.locator('[data-odontogram-mode="REGION"]').click()
        page.locator('[data-odontogram-select="region"]').select_option('POSTERIOR')
        page.locator('[data-odontogram-select="arch"]').select_option('MAXILLARY')
        page.locator('[data-odontogram-select="side"]').select_option('RIGHT')
        check(page.evaluate('qa.value().region_key')=='POSTERIOR',f'QA_{width}x{height}_REGION')
        check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),f'QA_{width}x{height}_NO_PAGE_HORIZONTAL_OVERFLOW')
        check(not errors,f'QA_{width}x{height}_NO_JS_ERRORS')
        page.close()

    page=browser.new_page()
    page.route(BASE+'/__dental_odontogram02__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
    page.goto(BASE+'/__dental_odontogram02__',wait_until='networkidle')
    page.evaluate('''window.qa=mxmedDentalLocation.mount(document.querySelector('#host'),'CBCT',null,value=>window.latest=value)''')
    page.wait_for_selector('[data-fdi="16"]')
    page.locator('[data-fdi="16"]').click()
    check(page.evaluate('latest.contract_version===2&&latest.coverage==="LOCALIZED"&&latest.tooth_fdi_codes[0]==="16"'),'QA_CBCT_V2_ADAPTER')
    check(page.evaluate('qa.valid()'),'QA_CBCT_V2_VALID')
    page.locator('[data-odontogram-clear]').click()
    page.locator('[data-odontogram-mode="TMJ_REGION"]').click()
    page.locator('[data-odontogram-choice="BILATERAL"]').click()
    check(page.evaluate('latest.location_type==="TMJ_LOCATION"&&latest.tmj_side==="BILATERAL"&&latest.coverage==="TMJ"&&qa.valid()'),'QA_CBCT_TMJ_V2_ADAPTER')
    page.close()
    page=browser.new_page()
    page.route(BASE+'/__dental_odontogram02__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
    page.goto(BASE+'/__dental_odontogram02__',wait_until='networkidle')
    page.evaluate('''window.qa=mxmedDentalOdontogramV2.mount(document.querySelector('#host'),{
      allowedDentitionModes:['PERMANENT'],allowedLocationModes:['SINGLE_TOOTH'],disabledFdiCodes:['16']})''')
    page.evaluate('qa.ready')
    check(page.locator('[data-fdi="16"]').is_disabled() and page.locator('[data-fdi="16"]').get_attribute('aria-label').startswith('Pieza 16'),'QA_DISABLED_NOT_APPLICABLE')
    page.close()
    page=browser.new_page(viewport={'width':1366,'height':768})
    errors=[]
    page.on('pageerror',lambda error:errors.append(str(error)))
    page.route(BASE+'/__dental_odontogram02__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=COMPOSER_HTML))
    row={'study_type_id':1,'study_type_key':'dental_cbct','display_name_es':'Tomografía dental','category_key':'IMAGEN','category_label_es':'Imagen'}
    page.route('**/api/clinical/index.php/doctors/qa_odonto/study-types?**',lambda route:route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'items':[row],'has_more':False,'categories':[{'category_key':'IMAGEN','label_es':'Imagen','active_count':1}]}})))
    page.goto(BASE+'/__dental_odontogram02__',wait_until='networkidle')
    page.evaluate('''window.composer=mxmedStudyComposer.mount(document.querySelector('#host'),{doctorId:'qa_odonto'})''')
    page.locator('[data-tax03c-id="1"]').click()
    page.wait_for_selector('.dental-location-dialog[open] [data-fdi="16"]')
    page.locator('.dental-location-dialog [data-fdi="16"]').click()
    check(page.evaluate('composer.orderItems()[0].dental_location.tooth_fdi_codes[0]')=='16' and not errors,'QA_COMPOSER_V2_DENTAL_LOCATION_SNAPSHOT')
    page.locator('.dental-location-dialog-close').click()
    page.locator('[data-tax03c-dental="0"]').click()
    page.wait_for_selector('.dental-location-dialog[open] [data-fdi="16"]')
    check(page.locator('.dental-location-dialog [data-fdi="16"]').get_attribute('aria-pressed')=='true','QA_COMPOSER_REOPEN_SELECTED_LOCATION')
    page.close()
    for kind,value,selector_name,expected in [
        ('TMJ',None,'[data-odontogram-choice="BILATERAL"]','BILATERAL'),
        ('SCAN',None,'[data-odontogram-choice="BOTH_ARCHES"]','BOTH_ARCHES'),
        ('MODEL',None,'[data-odontogram-choice="MAXILLARY"]','MAXILLARY'),
    ]:
        page=browser.new_page()
        page.route(BASE+'/__dental_odontogram02__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
        page.goto(BASE+'/__dental_odontogram02__',wait_until='networkidle')
        page.evaluate('''([kind,value])=>window.qa=mxmedDentalLocation.mount(document.querySelector('#host'),kind,value,location=>window.latest=location)''',[kind,value])
        page.wait_for_selector(selector_name)
        page.locator(selector_name).click()
        if kind=='TMJ':page.locator('.dental-location-aux select').select_option('PA')
        field='tmj_side' if kind=='TMJ' else 'arch_key'
        check(page.evaluate('''field=>window.latest?.[field]''',field)==expected and page.evaluate('qa.valid()'),f'QA_{kind}_V2_ADAPTER')
        page.close()
    page=browser.new_page()
    page.route(BASE+'/__dental_odontogram02__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
    page.goto(BASE+'/__dental_odontogram02__',wait_until='networkidle')
    page.evaluate('''window.qa=mxmedDentalLocation.mount(document.querySelector('#host'),'CBCT',{
        contract_version:1,numbering_system:'FDI_ISO_3950',dentition_mode:'DECIDUOUS',coverage:'LOCALIZED',selected_teeth:['54']},location=>window.latest=location)''')
    page.wait_for_selector('[data-fdi="54"]')
    check(page.locator('[data-fdi="54"]').get_attribute('aria-pressed')=='true' and page.evaluate('qa.location().contract_version')==1,'QA_V1_PROJECTION_NO_REWRITE')
    page.close()
    page=browser.new_page()
    page.route(BASE+'/__dental_odontogram02__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
    page.goto(BASE+'/__dental_odontogram02__',wait_until='networkidle')
    page.evaluate('''window.qa=mxmedDentalLocation.mount(document.querySelector('#host'),'TMJ',{
        contract_version:1,numbering_system:'FDI_ISO_3950',projection:'PA'},location=>window.latest=location)''')
    page.wait_for_selector('.dental-location-aux select')
    page.locator('.dental-location-aux select').select_option('LATERAL')
    check(page.evaluate('qa.location().contract_version===1&&qa.location().projection==="LATERAL"&&qa.valid()'),'QA_V1_TMJ_PROJECTION_EDIT_NO_SIDE_INFERENCE')
    page.close()
    for width,height in [(1440,900),(1366,768),(390,844)]:
        page=browser.new_page(viewport={'width':width,'height':height})
        writes=[];errors=[]
        page.on('pageerror',lambda error:errors.append(str(error)))
        page.route('**/api/**',lambda route:route.continue_() if route.request.method in ('GET','HEAD','OPTIONS') else (writes.append(route.request.url),route.abort()))
        page.goto(BASE+'/index.html?review_patient=plan02ux&qa_tools=hide',wait_until='networkidle')
        page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click()
        page.evaluate('''() => {
            const dialog=document.createElement('dialog');dialog.className='dental-location-dialog';
            dialog.innerHTML='<div class="dental-location-dialog-head"><strong>Ubicación dental</strong></div><div id="odonto02-live-host"></div>';
            document.body.append(dialog);dialog.showModal();
            window.odontoLive=mxmedDentalOdontogramV2.mount(dialog.querySelector('#odonto02-live-host'),{
              authorityVersion:2,allowedDentitionModes:['PERMANENT','PRIMARY','MIXED'],allowedLocationModes:['MULTIPLE_TEETH']});
        }''')
        page.evaluate('odontoLive.ready')
        page.locator('[data-odontogram-dentition="MIXED"]').click()
        page.locator('[data-fdi="16"]').click()
        page.locator('[data-fdi="54"]').click()
        check(page.locator('.dental-location-dialog[open] .odontogram-tooth').count()==52 and not writes and not errors,f'QA_REAL_PHYSICIAN_RUNTIME_{width}x{height}_READ_ONLY')
        page.screenshot(path=f'/tmp/dental_odontogram02_live_{width}x{height}.png',full_page=True)
        page.close()
    browser.close()
