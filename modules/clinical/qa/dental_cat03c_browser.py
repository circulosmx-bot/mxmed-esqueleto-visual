"""Read-only DENTAL-CAT03C interaction and responsive review on the running source at 18148."""
import json
from playwright.sync_api import sync_playwright, expect
BASE='http://127.0.0.1:18148'
ROWS=[
 (281,'dental_periapical_xray','Radiografía periapical'),
 (282,'dental_bitewing_xray','Radiografía interproximal de aleta de mordida'),
 (283,'dental_occlusal_xray','Radiografía oclusal dental'),
 (284,'dental_full_periapical_series','Serie radiográfica intraoral de boca completa'),
 (180,'dental_cbct','Tomografía dental y maxilofacial de haz cónico'),
]
CATALOG=[dict(study_type_id=i,study_type_key=k,display_name_es=n,category_key='IMAGEN',category_label_es='Imagenología') for i,k,n in ROWS]
HTML='''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/css/expediente-paciente-visual-normalization.css"><link rel="stylesheet" href="/assets/css/clinical/dental-location-v1.css"><link rel="stylesheet" href="/assets/css/clinical/dental-odontogram-v2.css">
<style>body{margin:0;background:#eaf8fb;padding:12px}#host{max-width:1100px;margin:auto}</style></head><body><main id="host"></main>
<script src="/assets/js/clinical/dental-location-v1.js"></script><script src="/assets/js/clinical/dental-odontogram-v2.js"></script><script src="/assets/js/clinical/dental-location-v2-adapter.js"></script><script src="/assets/js/clinical/imaging-parameters-v1.js"></script><script src="/assets/js/clinical/tax03c-study-composer.js"></script></body></html>'''
def say(name):print(name+'=PASS',flush=True)
def mount(page):
    page.route(BASE+'/__dental_cat03c__',lambda route:route.fulfill(status=200,content_type='text/html; charset=utf-8',body=HTML))
    def catalog(route):
        from urllib.parse import urlsplit,parse_qs
        q=parse_qs(urlsplit(route.request.url).query);term=q.get('search',[''])[0].casefold()
        filtered=[row for row in CATALOG if not term or term in row['display_name_es'].casefold() or term in row['study_type_key']]
        route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'items':filtered,'has_more':False,'categories':[{'category_key':'IMAGEN','label_es':'Imagenología','active_count':len(CATALOG)}]}}))
    page.route('**/api/clinical/index.php/doctors/d_cat03c/study-types?**',catalog)
    page.goto(BASE+'/__dental_cat03c__',wait_until='networkidle')
    page.evaluate("window.composer=mxmedStudyComposer.mount(document.querySelector('#host'),{doctorId:'d_cat03c'})")
    expect(page.locator('[data-tax03c-id]')).to_have_count(5)
def add(page,id):page.locator(f'[data-tax03c-id="{id}"]').click()
def close(page):page.locator('.dental-location-dialog[open] .dental-location-dialog-close').click()
with sync_playwright() as playwright:
    browser=playwright.chromium.launch(headless=True)
    for width,height,label in [(1440,900,'1440x900'),(1366,768,'1366x768_COMPACT'),(1366,768,'1366x768_EXPANDED'),(390,844,'390x844')]:
        page=browser.new_page(viewport={'width':width,'height':height});errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
        mount(page)
        if label.endswith('EXPANDED'):page.add_style_tag(content='body{padding-left:272px!important}')
        add(page,281);page.wait_for_selector('.dental-location-dialog[open] [data-fdi="16"]')
        page.locator('.dental-location-dialog[open] [data-odontogram-mode="MULTIPLE_TEETH"]').click()
        page.locator('.dental-location-dialog[open] [data-fdi="16"]').focus();page.keyboard.press('Enter')
        assert page.locator('.dental-location-dialog[open] [data-fdi="16"]').get_attribute('aria-label').startswith('Pieza 16,')
        assert page.locator('.dental-location-dialog[open] [data-fdi="16"]').evaluate('(el)=>getComputedStyle(el).outlineStyle')!='none'
        page.locator('.dental-location-dialog[open] [data-fdi="17"]').click()
        assert page.evaluate('composer.orderItems()[0].dental_location.tooth_fdi_codes.join(",")')=='16,17'
        assert page.evaluate('composer.valid()')
        if label in ('1440x900','390x844'):page.screenshot(path='/tmp/dental-cat03c-periapical-'+label+'.png',full_page=True)
        close(page)
        add(page,282);page.wait_for_selector('.dental-location-dialog[open] [data-odontogram-select="region"]')
        page.locator('.dental-location-dialog[open] [data-odontogram-dentition="PERMANENT"]').click()
        page.locator('.dental-location-dialog[open] [data-odontogram-select="region"]').select_option('POSTERIOR')
        page.locator('.dental-location-dialog[open] [data-odontogram-select="arch"]').select_option('BOTH_ARCHES')
        page.locator('.dental-location-dialog[open] [data-odontogram-select="side"]').select_option('RIGHT')
        assert page.locator('.dental-location-dialog[open] [data-odontogram-select="region"]').evaluate('(el)=>el.labels.length===1')
        assert page.locator('.dental-location-dialog[open] [data-odontogram-select="side"]').evaluate('(el)=>el.labels.length===1')
        assert page.evaluate('composer.orderItems()[1].dental_location.side_key')=='RIGHT'
        close(page)
        add(page,283);page.wait_for_selector('.dental-location-dialog[open] [data-odontogram-choice="MAXILLARY"]')
        page.locator('.dental-location-dialog[open] [data-odontogram-dentition="PERMANENT"]').click()
        page.locator('.dental-location-dialog[open] [data-tax03c-both-arches]').click()
        items=page.evaluate('composer.orderItems()')
        assert [r['dental_location']['arch_key'] for r in items if r['study_type_key']=='dental_occlusal_xray']==['MAXILLARY','MANDIBULAR'],items
        assert page.locator('[data-tax03c-id="283"]').is_disabled()
        assert page.evaluate('composer.valid()')
        assert page.locator('.tax03c-selected-row .dental-item-summary').all_inner_texts()[-2:]==['Maxilar superior','Mandíbula']
        add(page,284);page.locator('select[aria-label="Protocolo de serie periapical completa"]').select_option('FULL_MOUTH_16')
        assert page.evaluate('composer.orderItems().find(i=>i.study_type_key==="dental_full_periapical_series").dental_acquisition_protocol.protocol_key')=='FULL_MOUTH_16'
        add(page,180);page.wait_for_selector('.dental-location-dialog[open] [data-odontogram-mode="TMJ_REGION"]')
        page.locator('.dental-location-dialog[open] [data-odontogram-mode="TMJ_REGION"]').click()
        page.locator('.dental-location-dialog[open] [data-odontogram-choice="BILATERAL"]').click()
        assert page.evaluate('composer.orderItems().find(i=>i.study_type_key==="dental_cbct").dental_location.coverage')=='TMJ'
        assert page.evaluate('composer.valid()')
        if label in ('1440x900','390x844'):page.screenshot(path='/tmp/dental-cat03c-'+label+'.png',full_page=True)
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(label,page.evaluate('document.documentElement.scrollWidth'),width)
        assert not errors,errors
        say('QA_'+label+'_DENTAL_CONTROLS_AND_NO_OVERFLOW');page.close()
    page=browser.new_page(viewport={'width':1440,'height':900});mount(page)
    add(page,283);page.wait_for_selector('.dental-location-dialog[open] [data-odontogram-choice="MAXILLARY"]')
    page.locator('.dental-location-dialog[open] [data-odontogram-dentition="PERMANENT"]').click()
    page.locator('.dental-location-dialog[open] [data-odontogram-choice="MAXILLARY"]').click();close(page)
    assert not page.locator('[data-tax03c-id="283"]').is_disabled()
    add(page,283);page.wait_for_selector('.dental-location-dialog[open] [data-odontogram-choice="MAXILLARY"]')
    page.locator('.dental-location-dialog[open] [data-odontogram-dentition="PERMANENT"]').click()
    page.locator('.dental-location-dialog[open] [data-odontogram-choice="MAXILLARY"]').click()
    assert len([i for i in page.evaluate('composer.orderItems()') if i['study_type_key']=='dental_occlusal_xray' and 'dental_location' in i])==1
    page.locator('.dental-location-dialog[open] [data-tax03c-both-arches]').click()
    assert [i['dental_location']['arch_key'] for i in page.evaluate('composer.orderItems()')]==['MAXILLARY','MANDIBULAR']
    page.locator('[data-tax03c-dental="0"]').click();page.wait_for_selector('.dental-location-dialog[open] [data-tax03c-both-arches]')
    page.locator('.dental-location-dialog[open] [data-tax03c-both-arches]').click()
    assert len(page.evaluate('composer.orderItems()'))==2
    say('QA_OCCLUSAL_PARTIAL_REPEAT_AND_DUPLICATE_COLLAPSE');page.close()
    browser.close()
