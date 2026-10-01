"""Browser interaction and viewport QA for the shared TAX03C catalog composer."""
import json
import unicodedata
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

from playwright.sync_api import expect, sync_playwright

root = Path(__file__).resolve().parents[3]
manifest = json.loads((root / 'modules/clinical/catalog/tax03b_source_curation.json').read_text())
entries = [row for row in manifest['entries'] if row['classification'] == 'CANONICAL_CLEAR']
entries = sorted(entries, key=lambda row: row['source_name'])
for index, entry in enumerate(entries, 1):
    entry['study_type_id'] = index
ids = {entry['study_type_key']: entry['study_type_id'] for entry in entries}
categories = ['LABORATORIO', 'IMAGEN', 'CARDIOVASCULAR', 'OFTALMOLOGIA', 'NEUROFISIOLOGIA',
              'FUNCION_PULMONAR', 'AUDIOLOGIA', 'DENTAL', 'PATOLOGIA', 'ENDOSCOPIA',
              'SUENO', 'GENETICA', 'OTROS']
counts = {category: sum(row['category_key'] == category for row in entries) for category in categories}
calls = []


def normalize(value):
    return ''.join(char for char in unicodedata.normalize('NFD', value.lower()) if unicodedata.category(char) != 'Mn')


def catalog_route(route):
    calls.append(route.request.url)
    query = parse_qs(urlsplit(route.request.url).query)
    search = normalize(query.get('search', [''])[0])
    category = query.get('category', [''])[0]
    offset = int(query.get('offset', ['0'])[0])
    limit = int(query.get('limit', ['30'])[0])
    filtered = [entry for entry in entries if (not category or entry['category_key'] == category)
                and (not search or any(search in normalize(str(value)) for value in
                                       (entry['source_name'], entry['study_type_key'], *entry['aliases'])))]
    items = [{'study_type_id': row['study_type_id'], 'study_type_key': row['study_type_key'],
              'display_name_es': row['source_name'], 'category_key': row['category_key'],
              'category_label_es': row['category_key'], 'aliases': row['aliases']}
             for row in filtered[offset:offset + limit]]
    body = {'ok': True, 'data': {'items': items, 'has_more': offset + limit < len(filtered),
                                'categories': [{'category_key': name, 'label_es': name,
                                                'active_count': counts[name]} for name in categories]}}
    route.fulfill(status=200, content_type='application/json', body=json.dumps(body, ensure_ascii=False))


with sync_playwright() as playwright:
    browser = playwright.chromium.launch()
    page = browser.new_page(viewport={'width': 1440, 'height': 810})
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.route('http://mxmed.test/', lambda route: route.fulfill(status=200, content_type='text/html', body='<html><body></body></html>'))
    page.route('**/api/clinical/index.php/doctors/d_tax03c/study-types?**', catalog_route)
    page.goto('http://mxmed.test/')
    page.set_content('<dialog class="tax03c-dialog"><form><header><h4>Solicitar estudios</h4></header><div data-tax03c-host></div><footer><button class="btn">Solicitar estudios</button></footer></form></dialog>')
    page.add_style_tag(path=str(root / 'assets/css/expediente-paciente-visual-normalization.css'))
    page.add_script_tag(path=str(root / 'assets/js/clinical/tax03c-study-composer.js'))
    page.evaluate("window.qaComposer=window.mxmedStudyComposer.mount(document.querySelector('[data-tax03c-host]'),{doctorId:'d_tax03c'})")
    page.locator('dialog').evaluate('(dialog) => dialog.showModal()')
    expect(page.locator('[data-tax03c-category] option')).to_have_count(sum(count > 0 for count in counts.values()) + 1)
    assert counts['OFTALMOLOGIA'] == 0 and counts['DENTAL'] == 0
    assert page.locator('[data-tax03c-category] option[value="DENTAL"]').count() == 0
    assert page.locator('[data-tax03c-custom-category] option[value="DENTAL"]').count() == 1
    assert page.locator('[data-tax03c-custom-category] option[value="OFTALMOLOGIA"]').count() == 1

    search = page.locator('[data-tax03c-search]')
    search.fill('Biometría hemática')
    expect(page.locator('[data-tax03c-id]')).to_have_count(1)
    page.locator('[data-tax03c-id]').click()
    assert page.evaluate('qaComposer.valid()')
    assert page.evaluate('qaComposer.orderItems()[0].study_type_id') is not None
    assert page.locator('[data-tax03c-id]').is_disabled()
    assert page.locator('[data-tax03c-selected] .tax03c-selected-row').count() == 1
    page.locator('[data-tax03c-remove="0"]').click()
    assert not page.evaluate('qaComposer.valid()')
    assert not page.locator('[data-tax03c-id]').is_disabled()
    print('QA_CANONICAL_SEARCH_DUPLICATE_REMOVE=PASS')

    search.fill('Mesa inclinada')
    expect(page.locator('[data-tax03c-id]')).to_have_count(1)
    expect(page.locator('[data-tax03c-id]')).to_contain_text('mesa basculante', ignore_case=True)
    assert any('search=Mesa+inclinada' in call for call in calls)
    page.locator('[data-tax03c-category]').select_option('LABORATORIO')
    expect(page.locator('[data-tax03c-id]')).to_have_count(0)
    search.fill('Glucosa')
    expect(page.locator(f'[data-tax03c-id="{ids["glucose"]}"]')).to_be_visible()
    page.locator(f'[data-tax03c-id="{ids["glucose"]}"]').click()
    search.fill('Biometría hemática')
    expect(page.locator('[data-tax03c-id]')).to_have_count(1)
    page.locator('[data-tax03c-id]').click()
    assert page.evaluate('mxmedStudyComposer.documentType(qaComposer.selected())') == 'lab_order'
    print('QA_ALIAS_CATEGORY_MULTI_SAME_CATEGORY=PASS')

    page.locator('[data-tax03c-category]').select_option('IMAGEN')
    search.fill('RX Tórax')
    expect(page.locator('[data-tax03c-id]')).to_have_count(1)
    page.locator('[data-tax03c-id]').click()
    assert page.evaluate('mxmedStudyComposer.documentType(qaComposer.selected())') == 'orders'
    page.locator('[data-tax03c-all]').click()
    expect(page.locator('[data-tax03c-category]')).to_have_value('')
    assert page.locator('[data-tax03c-all]').get_attribute('aria-pressed') == 'true'
    print('QA_MIXED_CATEGORIES=PASS')

    search.fill('Estudio desconocido QA')
    expect(page.locator('[data-tax03c-id]')).to_have_count(0)
    expect(page.locator('[data-tax03c-status]')).to_contain_text('No encontramos')
    page.locator('[data-tax03c-custom-open]').click()
    assert page.locator('[data-tax03c-custom]').is_visible()
    page.locator('[data-tax03c-custom-add]').click()
    expect(page.locator('[data-tax03c-custom-error]')).to_have_text('Selecciona una categoría.')
    page.locator('[data-tax03c-custom-category]').select_option('DENTAL')
    page.locator('[data-tax03c-custom-name]').fill('')
    page.locator('[data-tax03c-custom-add]').click()
    expect(page.locator('[data-tax03c-custom-error]')).to_have_text('Escribe el nombre del estudio.')
    page.locator('[data-tax03c-custom-name]').fill('CBCT dental')
    page.locator('[data-tax03c-custom-note]').fill('Región maxilar')
    page.locator('[data-tax03c-custom-add]').click()
    assert page.evaluate('qaComposer.orderItems().at(-1)') == {'study_category': 'DENTAL', 'study_display_name': 'CBCT dental', 'note': 'Región maxilar'}
    page.locator('[data-tax03c-custom-open]').click()
    page.locator('[data-tax03c-custom-category]').select_option('OFTALMOLOGIA')
    page.locator('[data-tax03c-custom-name]').fill('Prueba oftalmológica especial')
    page.locator('[data-tax03c-custom-add]').click()
    assert page.evaluate('qaComposer.orderItems().at(-1).study_category') == 'OFTALMOLOGIA'
    page.locator('[data-tax03c-priority]').select_option('Urgente')
    page.locator('[data-tax03c-indication]').fill('Sospecha clínica')
    assert page.evaluate('qaComposer.priority()') == 'Urgente'
    assert page.evaluate('qaComposer.indication()') == 'Sospecha clínica'
    assert page.evaluate('Array.from(mxmedStudyComposer.title([{name:"X".repeat(200)}])).length') == 128
    print('QA_UNKNOWN_SEARCH_CUSTOM_DENTAL_OPHTHALMOLOGY_PRIORITY_INDICATION=PASS')

    for width, height in ((1440, 810), (1440, 900), (1366, 768), (390, 844)):
        page.set_viewport_size({'width': width, 'height': height})
        geometry = page.evaluate('''() => {const d=document.querySelector('dialog').getBoundingClientRect();
            return {left:d.left,right:d.right,top:d.top,bottom:d.bottom,scroll:document.documentElement.scrollWidth>innerWidth}}''')
        assert geometry['left'] >= 0 and geometry['right'] <= width + 1, geometry
        assert geometry['top'] >= 0 and geometry['bottom'] <= height + 1, geometry
        assert not geometry['scroll'], geometry
        print(f'QA_VIEWPORT_{width}x{height}=PASS')
    assert not errors, errors
    print('QA_JS_ERRORS=NONE')
    browser.close()
