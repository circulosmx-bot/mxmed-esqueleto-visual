"""Exercise NAVMAP02 through assets served by the port 18148 physician runtime."""
import json
from pathlib import Path
from urllib.parse import parse_qs, urlsplit
from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[3]
BASE = 'http://127.0.0.1:18148'
manifest = json.loads((ROOT / 'modules/clinical/catalog/tax03b_source_curation.json').read_text())
studies = sorted((row for row in manifest['entries'] if row['classification'] == 'CANONICAL_CLEAR'),
                 key=lambda row: row['source_name'])
for index, row in enumerate(studies, 1):
    row['qa_id'] = index
by_key = {row['study_type_key']: row for row in studies}
categories = ['LABORATORIO','IMAGEN','CARDIOVASCULAR','OFTALMOLOGIA','NEUROFISIOLOGIA',
              'FUNCION_PULMONAR','AUDIOLOGIA','DENTAL','PATOLOGIA','ENDOSCOPIA','SUENO','GENETICA','OTROS']
counts = {category:sum(row['category_key'] == category for row in studies) for category in categories}
assert len(studies) == 183

HARNESS = '''<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0">
<link rel="stylesheet" href="/assets/css/expediente-paciente-visual-normalization.css">
<style>body{margin:0;padding:16px;background:#f6fbfc}#p-expediente{max-width:1500px;margin:auto}#t-estudios{display:block}</style>
</head><body><div id="p-expediente" data-patient-id="p_navmap02"><div id="t-estudios" class="active"></div>
<div id="t-consent"></div><div id="t-tratamiento"></div></div>
<script>window.mxmedStore={doctor_id:'d_navmap02'};</script>
<script src="/assets/js/clinical/tax03c-study-composer.js?v=tax03c"></script>
<script src="/assets/js/clinical/or05-specialty-navigation-v1.js?v=or05-navmap02"></script>
<script src="/assets/js/clinical/vis06-modules.js?v=or05-navmap02"></script></body></html>'''


def make_page(browser, specialty, viewport=(1440, 900), title='', secondary=None, verified=None):
    page = browser.new_page(viewport={'width':viewport[0], 'height':viewport[1]})
    errors, writes, catalog_calls = [], [], []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.route(BASE + '/__or05_navmap02_qa__', lambda route: route.fulfill(status=200, content_type='text/html; charset=utf-8', body=HARNESS))

    def profile_route(route):
        data = {'identity_public':{'specialty_primary':specialty, 'professional_designation':title,
                                   'specialty_secondary':secondary or []},
                'verified_credentials':{'professional':None, 'specialties':[]},
                'primary_specialty_credential_id':None}
        if verified:
            data['verified_credentials']['specialties'] = verified['specialties']
            data['primary_specialty_credential_id'] = verified['primary_id']
        route.fulfill(status=200, content_type='application/json', body=json.dumps({'ok':True,'data':data}, ensure_ascii=False))
    page.route('**/api/profiles/index.php/private/doctor/**', profile_route)

    def clinical_route(route):
        request = route.request
        url = urlsplit(request.url)
        query = parse_qs(url.query)
        if url.path.endswith('/encounters/active'):
            data = {'doctor_id':'d_navmap02'}
        elif url.path.endswith('/study-types'):
            catalog_calls.append(request.url)
            cat = query.get('category', [''])[0]
            search = query.get('search', [''])[0].casefold()
            offset = int(query.get('offset', ['0'])[0]);limit = int(query.get('limit', ['30'])[0])
            filtered = [row for row in studies if (not cat or row['category_key'] == cat) and
                        (not search or search in row['source_name'].casefold() or search in row['study_type_key'].casefold())]
            data = {'items':[{'study_type_id':row['qa_id'],'study_type_key':row['study_type_key'],
                              'display_name_es':row['source_name'],'category_key':row['category_key'],
                              'category_label_es':row['category_key'],'aliases':row['aliases']}
                             for row in filtered[offset:offset + limit]],
                    'has_more':offset + limit < len(filtered),
                    'categories':[{'category_key':name,'label_es':name,'active_count':counts[name]} for name in categories]}
        elif request.method == 'POST' and url.path.endswith('/documents'):
            writes.append(request.post_data_json)
            data = {'document_id':42,'document_uuid':'00000000-0000-4000-8000-000000000042'}
        elif 'orders_results_mode' in query:
            data = {'items':[],'cursor_next':None,'has_more':False}
        else:
            data = {'items':[]}
        route.fulfill(status=201 if request.method == 'POST' else 200,content_type='application/json',
                      body=json.dumps({'ok':True,'data':data},ensure_ascii=False))
    page.route('**/api/clinical/index.php/**', clinical_route)
    page.goto(BASE + '/__or05_navmap02_qa__', wait_until='networkidle')
    expect(page.locator('.vis06-intent-card')).to_have_count(3)
    page.locator('.vis06-intent-card').first.click()
    expect(page.locator('.vis06-category-primary')).to_have_count(4)
    expect(page.locator('.vis06-lower-link')).to_have_text('Todos los estudios')
    return page, errors, writes, catalog_calls


def quick_labels(page):
    return page.locator('.vis06-secondary-categories .vis06-category-label').all_text_contents()


with sync_playwright() as playwright:
    browser = playwright.chromium.launch()
    cases = [
        ('Medicina General',['Cardiología','Neurofisiología','Función pulmonar','Citología','Endoscopía','Sueño','Audiología','Genética']),
        ('Cardiología',['Cardiología','Función pulmonar','Sueño']),
        ('Neurología',['Neurofisiología','Sueño']),
        ('Gastroenterología',['Endoscopía']),
        ('Otorrinolaringología',['Audiología','Endoscopía','Sueño']),
        ('Neumología',['Función pulmonar','Sueño','Endoscopía']),
        ('Dentista',[]),('Ortodoncia',[]),('Implantología',[]),('Cirugía Oral y Maxilofacial',[]),
        ('Pediatría',['Audiología','Función pulmonar','Neurofisiología']),
        ('Neurología Pediátrica',['Neurofisiología','Sueño']),
        ('Nefrología Pediátrica',[]),('Neumología Pediátrica',['Función pulmonar','Sueño']),
        ('Especialidad inédita',['Cardiología','Neurofisiología','Función pulmonar','Citología','Endoscopía','Sueño','Audiología','Genética'])
    ]
    for specialty, expected in cases:
        page, errors, _, _ = make_page(browser, specialty)
        assert quick_labels(page) == expected, (specialty, quick_labels(page))
        assert page.locator('.vis06-secondary-categories button').count() == len(expected)
        assert not page.locator('.vis06-lower-link').filter(has_text='Dental').count()
        assert not page.locator('.vis06-lower-link').filter(has_text='Oftalmología').count()
        assert not errors, (specialty, errors)
        page.close()
    print('QA_PROFILE_CASES=PASS: general, five specialists, four dental, four pediatric, unknown')

    verified = {'primary_id':2,'specialties':[
        {'credential_id':1,'credential_type':'SPECIALTY','verification_status':'VERIFIED','lifecycle_status':'ACTIVE','professional_area_label':'Cardiología'},
        {'credential_id':2,'credential_type':'SPECIALTY','verification_status':'VERIFIED','lifecycle_status':'ACTIVE','professional_area_label':'Neurología'}]}
    page, errors, _, _ = make_page(browser, 'Gastroenterología', secondary=['Neumología','Neurología'], verified=verified)
    assert quick_labels(page) == ['Neurofisiología','Sueño','Cardiología','Función pulmonar','Endoscopía']
    assert not errors, errors
    page.close()
    print('QA_VERIFIED_PRIMARY_AND_SECONDARY_MERGE=PASS')

    page, errors, writes, calls = make_page(browser, 'Cardiología')
    page.locator('.vis06-category-primary', has_text='IMAGENOLOGÍA').click()
    expect(page.locator('.tax03c-dialog')).to_be_visible()
    expect(page.locator('[data-tax03c-navigation-scope]')).to_contain_text('IMAGENOLOGÍA')
    expect(page.locator('[data-tax03c-id]')).to_have_count(30)
    page.locator('[data-tax03c-search]').fill('Glucosa')
    expect(page.locator(f'[data-tax03c-id="{by_key["glucose"]["qa_id"]}"]')).to_be_visible()
    page.locator('[data-tax03c-search]').fill('')
    expect(page.locator('[data-tax03c-id]')).to_have_count(30)
    for _ in range(5):
        more = page.locator('[data-tax03c-more]')
        if not more.is_visible():
            break
        before = page.locator('[data-tax03c-id]').count()
        more.click()
        page.wait_for_function('before => document.querySelectorAll("[data-tax03c-id]").length > before || document.querySelector("[data-tax03c-more]").hidden', arg=before)
    echo = by_key['echo_tte']
    expect(page.locator(f'[data-tax03c-id="{echo["qa_id"]}"]')).to_be_visible()
    assert page.locator('[data-tax03c-id]').count() == 40
    page.locator(f'[data-tax03c-id="{echo["qa_id"]}"]').click()
    page.locator('[data-tax03c-all]').click()
    page.locator('[data-tax03c-search]').fill('Glucosa')
    glucose = by_key['glucose']
    expect(page.locator(f'[data-tax03c-id="{glucose["qa_id"]}"]')).to_be_visible()
    page.locator(f'[data-tax03c-id="{glucose["qa_id"]}"]').click()
    page.locator('[data-tax03c-submit]').click()
    expect(page.locator('.tax03c-dialog')).to_have_count(0)
    assert len(writes) == 1
    assert writes[0]['document_type'] == 'orders'
    assert {item['study_type_key'] for item in writes[0]['payload']['order_items']} == {'echo_tte','glucose'}
    assert not errors, errors
    assert any('category=IMAGEN' in url for url in calls)
    assert any('category=CARDIOVASCULAR' in url for url in calls)
    print('QA_OVERLAPPING_GROUPER_MIXED_ORDER_IDENTITY=PASS')
    page.close()

    page, errors, _, _ = make_page(browser, 'Medicina General')
    page.locator('.vis06-category-secondary', has_text='Citología').click()
    expect(page.locator('[data-tax03c-id]')).to_have_count(2)
    assert {item for item in page.locator('[data-tax03c-id]').evaluate_all('(els) => els.map(el => el.dataset.tax03cId)')} == {
        str(by_key['cyto_pap']['qa_id']), str(by_key['cyto_liquid_based']['qa_id'])}
    assert not errors, errors
    print('QA_CYTOLOGY_QUICK_GROUP=PASS')
    page.close()

    page, errors, writes, _ = make_page(browser, 'Dentista')
    page.locator('.vis06-lower-link').click()
    expect(page.locator('.tax03c-dialog')).to_be_visible()
    page.locator('[data-tax03c-custom-open]').click()
    page.locator('[data-tax03c-custom-category]').select_option('DENTAL')
    page.locator('[data-tax03c-custom-name]').fill('Estudio dental externo')
    page.locator('[data-tax03c-custom-add]').click()
    page.locator('[data-tax03c-submit]').click()
    assert writes[0]['payload']['order_items'] == [{'study_category':'DENTAL','study_display_name':'Estudio dental externo'}]
    assert not errors, errors
    print('QA_DENTAL_FULL_CATALOG_AND_CUSTOM=PASS')
    page.close()

    for width, height in [(1440,900),(1366,768),(390,844)]:
        page, errors, _, _ = make_page(browser, 'Medicina General', (width,height))
        metrics = page.evaluate('''() => ({overflow: document.documentElement.scrollWidth > innerWidth + 1,
          primary: getComputedStyle(document.querySelector('.vis06-primary-categories')).gridTemplateColumns.split(' ').length,
          quick: document.querySelectorAll('.vis06-secondary-categories button').length,
          lower: document.querySelectorAll('.vis06-lower-links button').length})''')
        assert not metrics['overflow'], (width,height,metrics)
        assert metrics['primary'] == (2 if width == 390 else 4), (width,height,metrics)
        assert metrics['quick'] == 8 and metrics['lower'] == 1, metrics
        assert not errors, errors
        print(f'QA_{width}x{height}=PASS')
        page.close()

    # The same assets must also load in the actual physician page, not only the isolated harness.
    page = browser.new_page(viewport={'width':1440,'height':900})
    page_errors = []
    page.on('pageerror', lambda error: page_errors.append(str(error)))
    def live_catalog(route):
        if urlsplit(route.request.url).path.endswith('/study-types'):
            route.fulfill(json={'ok':True,'data':{'items':[],'has_more':False,
                'categories':[{'category_key':name,'label_es':name,'active_count':counts[name]} for name in categories]}})
        else:
            route.continue_()
    page.route('**/api/clinical/index.php/doctors/*/study-types?**', live_catalog)
    page.goto(BASE + '/?qa_tools=hide', wait_until='domcontentloaded')
    page.locator('[data-bs-target="#t-estudios"]').first.click()
    pane = page.locator('#t-estudios')
    pane.get_by_role('button', name='Solicitar estudios', exact=False).first.click()
    expect(pane.locator('.vis06-category-primary')).to_have_count(4)
    expect(pane.locator('.vis06-lower-link')).to_have_text('Todos los estudios')
    assert not page_errors, page_errors
    print('QA_REAL_PHYSICIAN_PAGE_ASSETS=PASS')
    page.close()
    browser.close()
print('QA_BROWSER_LAUNCHER=Playwright bundled Chromium headless')
