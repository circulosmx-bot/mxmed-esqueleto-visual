"""FIX02A navigation gate using served assets and a disposable clinical API fixture."""
import json
from urllib.parse import parse_qs, urlsplit

from playwright.sync_api import expect, sync_playwright

from lab_cat02a_browser import BASE, HTML, counts, rows


def run():
    lookup = {row['study_type_key']: row for row in rows}
    assert len(lookup) == 202
    imaging = ['echo_tte', 'echo_tes', 'stress_echo', 'carotid_doppler',
               'lower_ext_art_doppler', 'lower_ext_venous_doppler']
    functional = ['ecg_12lead', 'ecg_rhythm_strip', 'holter', 'abpm_mapa',
                  'stress_test', 'tilt_table', 'ankle_brachial_index']
    corrected = ['uric_acid', 'albumin', 'crp_hs', 'esr', 'hiv_ag_ac',
                 'stool_ova_parasites', 'cyto_pap', 'cyto_liquid_based',
                 'karyotype', 'cma_microarray', 'wes', 'wgs', 'nipt',
                 'carrier_screening', 'hereditary_cancer_germline', 'brca1_2',
                 'lynch', 'thrombophilia', 'pgx', 'somatic_tumor_ngs', *imaging]
    assert len(corrected) == len(set(corrected)) == 26

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        for width, height in [(1440, 900), (1366, 768), (390, 844)]:
            page = browser.new_page(viewport={'width': width, 'height': height})
            errors, writes = [], []
            profile = {'label': 'Médico General'}
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.route(BASE + '/__study_nav_fix02a__', lambda route: route.fulfill(
                status=200, content_type='text/html; charset=utf-8', body=HTML))
            page.route('**/api/profiles/index.php/private/doctor/**', lambda route: route.fulfill(
                status=200, content_type='application/json', body=json.dumps({'ok': True, 'data': {
                    'identity_public': {'specialty_primary': profile['label']},
                    'verified_credentials': {'professional': None, 'specialties': []},
                    'primary_specialty_credential_id': None}}, ensure_ascii=False)))

            def clinical(route):
                request = route.request
                url = urlsplit(request.url)
                query = parse_qs(url.query)
                if url.path.endswith('/study-types'):
                    category = query.get('category', [''])[0]
                    term = query.get('search', [''])[0].casefold()
                    offset = int(query.get('offset', ['0'])[0])
                    limit = int(query.get('limit', ['30'])[0])
                    filtered = [row for row in rows if
                                (not category or row['category_key'] == category) and
                                (not term or term in (row['display_name_es'] + ' ' +
                                 row['study_type_key'] + ' ' + ' '.join(row['aliases'])).casefold())]
                    data = {'items': filtered[offset:offset + limit],
                            'has_more': offset + limit < len(filtered),
                            'categories': [{'category_key': key, 'label_es': key,
                                            'active_count': count} for key, count in counts.items()]}
                elif request.method == 'POST' and url.path.endswith('/documents'):
                    writes.append(request.post_data_json)
                    data = {'document_id': 42,
                            'document_uuid': '00000000-0000-4000-8000-000000000042'}
                elif url.path.endswith('/encounters/active'):
                    data = {'doctor_id': 'd_labcat02a'}
                else:
                    data = {'items': []}
                route.fulfill(status=201 if request.method == 'POST' else 200,
                              content_type='application/json',
                              body=json.dumps({'ok': True, 'data': data}, ensure_ascii=False))

            page.route('**/api/clinical/index.php/**', clinical)
            page.goto(BASE + '/__study_nav_fix02a__', wait_until='networkidle')
            page.locator('.vis06-intent-card').first.click()
            expect(page.locator('.vis06-primary-categories button')).to_have_count(4)
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')

            # A group is only rendered when it has an active canonical study.
            page.locator('.vis06-primary-categories button').filter(has_text='LABORATORIO').click()
            expect(page.locator('.lab-cat02a-screen')).to_be_visible()
            page.wait_for_function('document.querySelectorAll(".lab-cat02a-screen button[data-lab-group]").length >= 15')
            labels = page.locator('.lab-cat02a-screen button[data-lab-group]').all_text_contents()
            assert not any('Otros' in label or 'Biología molecular / PCR' in label for label in labels)
            for label in ['Hematología', 'Química clínica', 'Coagulación',
                          'Endocrinología y hormonas', 'Inmunología', 'Microbiología',
                          'Orina y otros fluidos', 'Citopatología cervical',
                          'Citogenética', 'Citogenómica / microarreglos',
                          'Secuenciación genómica', 'Tamiz genético prenatal',
                          'Genética germinal', 'Farmacogenómica',
                          'Oncología molecular', 'Inflamación / reactantes',
                          'Serologías / Hepatitis', 'Serologías / infecciones']:
                assert any(label in shown for shown in labels), label
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')

            # Each corrected group opens its exact canonical key, with no duplicate row.
            if width == 1440:
                for group, key in [('cytopathology', 'cyto_pap'),
                                   ('cytogenetics', 'karyotype'),
                                   ('cytogenomics', 'cma_microarray'),
                                   ('genomic_sequencing', 'wes'),
                                   ('prenatal_genetics', 'nipt'),
                                   ('germline_genetics', 'brca1_2'),
                                   ('hereditary_oncology', 'lynch'),
                                   ('pharmacogenomics', 'pgx'),
                                   ('molecular_oncology', 'somatic_tumor_ngs'),
                                   ('inflammation', 'crp_hs'),
                                   ('infectious_serology', 'hiv_ag_ac')]:
                    page.locator(f'.lab-cat02a-screen button[data-lab-group="{group}"]').click()
                    page.locator('[data-tax03c-search]').fill(key)
                    expect(page.locator(f'[data-tax03c-id="{lookup[key]["study_type_id"]}"]')).to_have_count(1)
                    expect(page.locator('[data-tax03c-id]')).to_have_count(1)
                    page.locator('.tax03c-dialog [data-tax03c-close]').first.click()

            # Global search resolves every corrected canonical key from the same source.
            page.locator('.lab-cat02a-screen button').filter(has_text='Todos los estudios de laboratorio').first.click()
            page.locator('[data-tax03c-global]').click()
            for key in corrected:
                page.locator('[data-tax03c-search]').fill(key)
                expect(page.locator(f'[data-tax03c-id="{lookup[key]["study_type_id"]}"]')).to_have_count(1)
                visible_ids = page.locator('[data-tax03c-id]').evaluate_all(
                    '(items) => items.map(item => item.dataset.tax03cId)')
                assert len(visible_ids) == len(set(visible_ids)), key
            page.locator('.tax03c-dialog [data-tax03c-close]').first.click()
            if width == 1440:
                page.locator('.lab-cat02a-screen button[data-lab-group="cytopathology"]').click()
                page.locator('[data-tax03c-search]').fill('cyto_pap')
                page.locator(f'[data-tax03c-id="{lookup["cyto_pap"]["study_type_id"]}"]').click()
                page.locator('[data-tax03c-search]').fill('cyto_liquid_based')
                page.locator(f'[data-tax03c-id="{lookup["cyto_liquid_based"]["study_type_id"]}"]').click()
                page.locator('[data-tax03c-submit]').click()
                expect(page.locator('.tax03c-dialog')).to_have_count(0)
                assert len(writes) == 1
                assert writes[0]['payload']['order_items'] == [
                    {'study_type_id': lookup[key]['study_type_id'], 'study_type_key': key}
                    for key in ['cyto_pap', 'cyto_liquid_based']]
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
            assert not errors, errors
            print(f'QA_{width}x{height}=PASS; global_search=26; browser_errors=0; intercepted_writes={len(writes)}')
            page.close()

        def scope_page(specialty):
            page = browser.new_page(viewport={'width': 1440, 'height': 900})
            page.route(BASE + '/__study_nav_fix02a__', lambda route: route.fulfill(
                status=200, content_type='text/html; charset=utf-8', body=HTML))
            page.route('**/api/profiles/index.php/private/doctor/**', lambda route: route.fulfill(
                status=200, content_type='application/json', body=json.dumps({'ok': True, 'data': {
                    'identity_public': {'specialty_primary': specialty},
                    'verified_credentials': {'professional': None, 'specialties': []},
                    'primary_specialty_credential_id': None}}, ensure_ascii=False)))

            def clinical(route):
                request = route.request
                url = urlsplit(request.url)
                query = parse_qs(url.query)
                if url.path.endswith('/study-types'):
                    category = query.get('category', [''])[0]
                    term = query.get('search', [''])[0].casefold()
                    offset = int(query.get('offset', ['0'])[0])
                    limit = int(query.get('limit', ['30'])[0])
                    filtered = [row for row in rows if
                                (not category or row['category_key'] == category) and
                                (not term or term in (row['display_name_es'] + ' ' +
                                 row['study_type_key'] + ' ' + ' '.join(row['aliases'])).casefold())]
                    data = {'items': filtered[offset:offset + limit],
                            'has_more': offset + limit < len(filtered),
                            'categories': [{'category_key': key, 'label_es': key,
                                            'active_count': count} for key, count in counts.items()]}
                elif url.path.endswith('/encounters/active'):
                    data = {'doctor_id': 'd_labcat02a'}
                else:
                    data = {'items': []}
                route.fulfill(status=200, content_type='application/json',
                              body=json.dumps({'ok': True, 'data': data}, ensure_ascii=False))

            page.route('**/api/clinical/index.php/**', clinical)
            page.goto(BASE + '/__study_nav_fix02a__', wait_until='networkidle')
            page.locator('.vis06-intent-card').first.click()
            return page

        def open_scope(page, selector):
            page.locator(selector).click()
            expect(page.locator('.tax03c-dialog')).to_be_visible()
            page.wait_for_function('document.querySelector("[data-tax03c-status]")?.textContent.includes("disponibles")')
            ids = set()
            while True:
                ids.update(page.locator('[data-tax03c-id]').evaluate_all(
                    '(items) => items.map(item => item.dataset.tax03cId)'))
                more = page.locator('[data-tax03c-more]')
                if not more.is_visible():
                    break
                before = len(ids)
                more.click()
                page.wait_for_function('(before) => document.querySelectorAll("[data-tax03c-id]").length > before', arg=before)
            page.locator('.tax03c-dialog [data-tax03c-close]').first.click()
            return ids

        cardiology = scope_page('Cardiología')
        cardiac_ids = {str(lookup[key]['study_type_id']) for key in imaging}
        functional_ids = {str(lookup[key]['study_type_id']) for key in functional}
        imaging_ids = open_scope(cardiology, '.vis06-primary-categories button:has-text("IMAGENOLOGÍA")')
        function_ids = open_scope(cardiology, '.vis06-primary-categories button:has-text("ESTUDIOS FUNCIONALES")')
        quick_ids = open_scope(cardiology, '.vis06-secondary-categories button:has-text("Cardiología")')
        assert cardiac_ids <= imaging_ids and cardiac_ids.isdisjoint(function_ids)
        assert functional_ids <= function_ids and functional_ids.isdisjoint(imaging_ids)
        assert cardiac_ids | functional_ids == quick_ids
        assert {str(lookup[key]['study_type_id']) for key in imaging[:3]} == open_scope(
            cardiology, '.vis06-lower-link:has-text("Ultrasonido cardiaco")')
        assert {str(lookup[key]['study_type_id']) for key in imaging[3:]} == open_scope(
            cardiology, '.vis06-lower-link:has-text("Ultrasonido vascular / Doppler")')
        cardiology.locator('.vis06-primary-categories button').filter(has_text='LABORATORIO').click()
        cardiology.wait_for_function('document.querySelectorAll(".lab-cat02a-priority button").length === 3')
        assert cardiology.locator('.lab-cat02a-priority button').evaluate_all(
            '(items) => items.map(item => item.dataset.labGroup)') == [
                'chemistry', 'hematology', 'coagulation']
        cardiology.close()
        print('QA_CARDIOVASCULAR=PASS; imaging=6; functional=7; cardiology_quick=13')

        for specialty in ['Médico General', 'Gastroenterología', 'Hematología', 'Genética']:
            page = scope_page(specialty)
            expect(page.locator('.vis06-primary-categories button')).to_have_count(4)
            if specialty == 'Genética':
                genetic_ids = open_scope(page, '.vis06-secondary-categories button:has-text("Genética")')
                assert {str(lookup[key]['study_type_id']) for key in
                        ['karyotype', 'cma_microarray', 'wes', 'nipt', 'somatic_tumor_ngs']} <= genetic_ids
            if specialty == 'Gastroenterología':
                assert str(lookup['egd_eda_base']['study_type_id']) in open_scope(
                    page, '.vis06-secondary-categories button:has-text("Endoscopía")')
            if specialty == 'Hematología':
                page.locator('.vis06-primary-categories button').filter(has_text='LABORATORIO').click()
                expect(page.locator('.lab-cat02a-screen button[data-lab-group="hematology"]')).to_have_count(1)
            page.close()
            print(f'QA_SPECIALTY_{specialty}=PASS')

        for specialty in ['Dentista', 'Ortodoncia']:
            page = scope_page(specialty)
            expect(page.locator('.vis06-orders')).to_have_attribute('data-or-family', 'dental')
            dental_routes = page.locator('.vis06-primary-categories button')
            assert dental_routes.count() >= 3
            all_ids = set()
            for index in range(dental_routes.count()):
                all_ids |= open_scope(page, f'.vis06-primary-categories button:nth-child({index + 1})')
            expected = {str(lookup[key]['study_type_id']) for key in [
                'dental_cbct', 'dental_panoramic_xray', 'dental_cephalometric_xray',
                'tmj_comparative_xray', 'dental_intraoral_scan',
                'dental_clinical_photographs', 'dental_study_model']}
            if specialty == 'Ortodoncia':
                assert expected <= all_ids
            else:
                assert expected - {str(lookup['dental_clinical_photographs']['study_type_id'])} <= all_ids
            page.close()
            print(f'QA_DENTAL_{specialty}=PASS; reachable_keys={len(all_ids & expected)}/7')
        browser.close()


if __name__ == '__main__':
    run()
