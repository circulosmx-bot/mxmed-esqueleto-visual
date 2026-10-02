"""Local-only floating classification simulator against the physician review runtime."""
import json
from pathlib import Path
from urllib.parse import parse_qs, urlsplit
from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[3]
BASE = 'http://127.0.0.1:18148/'
manifest = json.loads((ROOT / 'modules/clinical/catalog/tax03b_source_curation.json').read_text())
studies = sorted((row for row in manifest['entries'] if row['classification'] == 'CANONICAL_CLEAR'),
                 key=lambda row: row['source_name'])
for number, row in enumerate(studies, 1):
    row['qa_id'] = number
categories = sorted({row['category_key'] for row in studies} | {'DENTAL', 'OFTALMOLOGIA', 'OTROS'})
counts = {key:sum(row['category_key'] == key for row in studies) for key in categories}
writes = []


def clinical(route):
    request = route.request
    url = urlsplit(request.url)
    if url.path.endswith('/study-types'):
        query = parse_qs(url.query)
        category = query.get('category', [''])[0]
        search = query.get('search', [''])[0].casefold()
        offset = int(query.get('offset', ['0'])[0])
        limit = int(query.get('limit', ['30'])[0])
        rows = [row for row in studies if (not category or row['category_key'] == category) and
                (not search or search in row['source_name'].casefold() or search in row['study_type_key'].casefold())]
        data = {'items':[{'study_type_id':row['qa_id'],'study_type_key':row['study_type_key'],
                          'display_name_es':row['source_name'],'category_key':row['category_key'],
                          'category_label_es':row['category_key'],'aliases':row['aliases']}
                         for row in rows[offset:offset+limit]],
                'has_more':offset+limit < len(rows),
                'categories':[{'category_key':key,'label_es':key,'active_count':counts[key]} for key in categories]}
        route.fulfill(json={'ok':True,'data':data})
    elif request.method == 'POST' and url.path.endswith('/documents'):
        writes.append(request.post_data_json)
        route.fulfill(status=201,json={'ok':True,'data':{'document_id':42}})
    else:
        route.continue_()


def profile(route):
    assert route.request.method == 'GET', 'classification review must not write physician profile'
    route.fulfill(json={'ok':True,'data':{'identity_public':{
        'professional_designation':'Médico General','specialty_primary':'Medicina General',
        'specialty_secondary':[]},
        'verified_credentials':{'professional':None,'specialties':[]},
        'primary_specialty_credential_id':None}})


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context(viewport={'width':1440,'height':900})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.route('**/api/clinical/index.php/**', clinical)
    page.route('**/api/profiles/index.php/private/doctor/**', profile)
    page.goto(BASE, wait_until='domcontentloaded')
    trigger = page.locator('#mxmed_qa_classification_trigger')
    expect(trigger).to_be_visible()
    assert page.locator('#mxmed_dev_role_switcher #mxmed_qa_plan_select').count() == 1
    assert page.locator('#mxmed_dev_role_switcher #mxmed_qa_classification_trigger').count() == 1
    options = page.evaluate('mxmedReviewClassification.options()')
    assert len(options) == 134
    assert len([option for option in options if option['kind'] == 'specialty']) == 124
    assert len([option for option in options if option['kind'] == 'title']) == 10
    assert page.evaluate('mxmedReviewClassification.current()') is None
    print('QA_LOCAL_CONTROL_AND_134_OPTIONS=PASS')
    plan_select = page.locator('#mxmed_qa_plan_select')
    plan_select.select_option('basic')
    assert page.evaluate('mxmedGetQaPlan().value') == 'basic'
    assert page.evaluate('mxmedReviewClassification.current()') is None
    plan_select.select_option('real')
    assert page.evaluate('mxmedGetQaPlan().value') == 'real'
    print('QA_PLAN_SIMULATOR_COEXISTENCE=PASS')

    page.locator('[data-bs-target="#t-estudios"]').first.click()
    pane = page.locator('#t-estudios')
    expect(pane.locator('.vis06-intent-card')).to_have_count(3)
    pane.get_by_role('button', name='Solicitar estudios', exact=False).first.click()
    expect(pane.locator('.vis06-category-primary')).to_have_count(4)

    def quick():
        return pane.locator('.vis06-secondary-categories .vis06-category-label').all_text_contents()

    def choose(label):
        trigger.click()
        dialog = page.locator('#mxmed_qa_classification_dialog')
        expect(dialog).to_be_visible()
        dialog.get_by_role('searchbox', name='Buscar clasificación').fill(label)
        dialog.get_by_role('option', name=label, exact=True).click()
        expect(dialog).not_to_be_visible()

    general = ['Cardiología','Neurofisiología','Función pulmonar','Citología',
               'Endoscopía','Sueño','Audiología','Genética']
    assert quick() == general
    assert trigger.inner_text().startswith('Real del perfil')
    print('QA_REAL_PROFILE=PASS')

    cases = [
        ('Médico General',general),
        ('Cardiología',['Cardiología','Función pulmonar','Sueño']),
        ('Neurología',['Neurofisiología','Sueño']),
        ('Gastroenterología',['Endoscopía']),
        ('Otorrinolaringología',['Audiología','Endoscopía','Sueño']),
        ('Neumología',['Función pulmonar','Sueño','Endoscopía']),
        ('Pediatría',['Audiología','Función pulmonar','Neurofisiología']),
        ('Neurología Pediátrica',['Neurofisiología','Sueño']),
        ('Dentista',[]),('Ortodoncia',[]),('Implantología',[]),('Cirugía Oral y Maxilofacial',[])
    ]
    for label, expected in cases:
        choose(label)
        expect(pane.locator('.vis06-category-secondary')).to_have_count(len(expected))
        assert quick() == expected, (label, quick())
        expect(pane.locator('.vis06-lower-link')).to_have_text('Todos los estudios')
        assert 'DENTAL' not in pane.locator('.vis06-lower-links').inner_text()
        assert page.evaluate('mxmedReviewClassification.current().label') == label
        assert page.evaluate('mxmedGetQaPlan().value') == 'real'
        print('QA_' + {'Médico General':'GENERALIST','Cardiología':'CARDIOLOGY','Neurología':'NEUROLOGY',
              'Gastroenterología':'GASTROENTEROLOGY','Otorrinolaringología':'OTORHINOLARYNGOLOGY',
              'Neumología':'PULMONOLOGY','Pediatría':'PEDIATRICS','Neurología Pediátrica':'PEDIATRIC_NEUROLOGY',
              'Dentista':'DENTIST','Ortodoncia':'ORTHODONTICS','Implantología':'IMPLANTOLOGY',
              'Cirugía Oral y Maxilofacial':'MAXILLOFACIAL'}[label] + '=PASS')

    page.reload(wait_until='domcontentloaded')
    expect(trigger).to_contain_text('Cirugía Oral y Maxilofacial')
    page.locator('[data-bs-target="#t-estudios"]').first.click()
    pane.get_by_role('button',name='Solicitar estudios',exact=False).first.click()
    expect(pane.locator('.vis06-category-secondary')).to_have_count(0)
    print('QA_SESSION_PERSISTENCE=PASS')

    choose('Cardiología')
    choose('Neurología')
    choose('Ortodoncia')
    choose('Real del perfil')
    expect(pane.locator('.vis06-category-secondary')).to_have_count(8)
    assert quick() == general
    assert page.evaluate('sessionStorage.getItem("mxmed.qa.classification.v1")') is None
    print('QA_SWITCH_SEQUENCE=PASS')

    trigger.click()
    dialog = page.locator('#mxmed_qa_classification_dialog')
    dialog.get_by_role('searchbox', name='Buscar clasificación').fill('neurologia pediatrica')
    expect(dialog.get_by_role('option', name='Neurología Pediátrica', exact=True)).to_be_visible()
    dialog.get_by_role('button', name='Cerrar').click()
    print('QA_ACCENT_INSENSITIVE_SEARCH=PASS')

    choose('Cardiología')
    pane.locator('.vis06-category-primary', has_text='LABORATORIO').click()
    composer = page.locator('.tax03c-dialog')
    expect(composer).to_be_visible()
    composer.locator('[data-tax03c-search]').fill('Glucosa')
    glucose_id = next(row['qa_id'] for row in studies if row['study_type_key'] == 'glucose')
    glucose = composer.locator(f'[data-tax03c-id="{glucose_id}"]')
    expect(glucose).to_be_visible()
    glucose.click()
    assert composer.locator('[data-tax03c-selected] .tax03c-selected-row').count() == 1
    neuro = next(option['value'] for option in options if option['label'] == 'Neurología')
    page.evaluate('(value) => mxmedReviewClassification.set(value, "qa_while_composer_open")', neuro)
    assert composer.locator('[data-tax03c-selected] .tax03c-selected-row').count() == 1
    page.once('dialog', lambda prompt: prompt.dismiss())
    composer.get_by_role('button', name='Volver a categorías').click()
    expect(composer).to_be_visible()
    assert composer.locator('[data-tax03c-selected] .tax03c-selected-row').count() == 1
    print('QA_DIRTY_ORDER_PRESERVED=PASS')
    composer.locator('[data-tax03c-submit]').click()
    expect(composer).to_have_count(0)
    assert len(writes) == 1
    payload = json.dumps(writes[0],ensure_ascii=False).lower()
    assert 'neurología' not in payload and 'cardiología' not in payload
    assert 'simul' not in payload and 'classification' not in payload
    assert writes[0]['payload']['order_items'][0]['study_type_key'] == 'glucose'
    print('QA_NO_SIMULATION_METADATA_IN_ORDER=PASS')

    for width,height in [(1440,900),(1366,768),(390,844)]:
        page.set_viewport_size({'width':width,'height':height})
        expect(trigger).to_be_visible()
        box = trigger.bounding_box()
        assert box and box['x'] >= 0 and box['x']+box['width'] <= width+1
        assert box['y'] >= 0 and box['y']+box['height'] <= height+1
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
        trigger.click()
        expect(page.locator('#mxmed_qa_classification_dialog')).to_be_visible()
        page.locator('#mxmed_qa_classification_dialog').get_by_role('button', name='Cerrar').click()
        print(f'QA_{width}x{height}=PASS')
    assert not errors, errors
    page.close()
    context.close()

    hidden_context = browser.new_context()
    hidden_context.add_init_script("sessionStorage.setItem('mxmed.qa.classification.v1','specialty:cardiologia')")
    hidden_page = hidden_context.new_page()
    hidden_page.route('**/api/clinical/index.php/**',clinical)
    hidden_page.route('**/api/profiles/index.php/private/doctor/**',profile)
    hidden_page.goto(BASE + '?qa_tools=hide', wait_until='domcontentloaded')
    assert hidden_page.evaluate('typeof window.mxmedReviewClassification') == 'undefined'
    hidden_page.locator('[data-bs-target="#t-estudios"]').first.click()
    hidden_pane = hidden_page.locator('#t-estudios')
    hidden_pane.get_by_role('button',name='Solicitar estudios',exact=False).first.click()
    expect(hidden_pane.locator('.vis06-category-secondary')).to_have_count(8)
    assert hidden_pane.locator('.vis06-secondary-categories .vis06-category-label').all_text_contents() == general
    print('QA_HIDDEN_REVIEW_TOOLS_IGNORE_STORED_OVERRIDE=PASS')
    hidden_page.close()
    hidden_context.close()

    production = browser.new_page()
    production.route('https://mxmed.example/',lambda route:route.fulfill(body='<html><body></body></html>',content_type='text/html'))
    production.goto('https://mxmed.example/')
    production.evaluate('window.mxmedGetQaPlan = () => ({value:"real"})')
    production.add_script_tag(path=str(ROOT/'assets/js/clinical/or05-specialty-navigation-v1.js'))
    production.add_script_tag(path=str(ROOT/'assets/js/review/classification-simulator.js'))
    assert production.evaluate('typeof window.mxmedReviewClassification') == 'undefined'
    assert production.locator('#mxmed_qa_classification_trigger').count() == 0
    print('QA_PRODUCTION_SIMULATOR_ABSENT=PASS')
    production.close()
    browser.close()
print('QA_BROWSER_LAUNCHER=Playwright bundled Chromium headless')
