"""HIER03 browser gate against the served assets and disposable catalog/API fixture."""
import json
from urllib.parse import parse_qs, urlsplit

from playwright.sync_api import expect, sync_playwright

from lab_cat02a_browser import BASE, HTML as OLD_HTML, counts, rows


HTML = (OLD_HTML
        .replace('</head>', '<link rel="stylesheet" href="/assets/css/clinical/study-navigation-hierarchy-v2.css?v=hier03"></head>')
        .replace('<script src="/assets/js/review/classification-simulator.js',
                 '<script src="/assets/js/clinical/study-navigation-hierarchy-v2.js?v=hier03"></script>'
                 '<script src="/assets/js/clinical/patient-workspace-navigation-guard.js?v=vis24"></script>'
                 '<script src="/assets/js/review/classification-simulator.js'))


def run():
    assert len(rows) == 202
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        for width, height in [(1440, 900), (1366, 768), (390, 844)]:
            page = browser.new_page(viewport={'width': width, 'height': height})
            errors, writes = [], []
            profile = {'label': 'Médico General'}
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.route(BASE + '/__study_nav_hier03__', lambda route: route.fulfill(
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
            page.goto(BASE + '/__study_nav_hier03__', wait_until='networkidle')
            expect(page.locator('.vis06-intent-card')).to_have_count(3)
            page.locator('.vis06-intent-card').first.click()
            root = page.locator('.vis06-category-screen[data-hier-level="root"]')
            expect(root.locator('.vis06-primary-categories button')).to_have_count(5)
            assert 'LABORATORIO' in root.inner_text()
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
            page.screenshot(path=f'/tmp/study_nav_hier03_root_{width}x{height}.png', full_page=True)

            coverage = page.evaluate('''rows => {
              const h=window.mxmedStudyNavigationHierarchyV2,a=new Map(rows.map(r=>[r.study_type_key,r]));
              const counts=Object.fromEntries(h.root.map(id=>[id,h.count(id,a)]));
              const catalog=h.root.flatMap(id=>h.parts(id));
              const covered=rows.filter(r=>catalog.some(p=>p.category===r.category_key&&(!p.keys||p.keys.includes(r.study_type_key))));
              const leafIds=Object.keys(h.nodes).filter(id=>!h.nodes[id].children.length);
              const duplicateLeafIds=leafIds.filter(id=>{
                const keys=h.parts(id).flatMap(p=>rows.filter(r=>r.category_key===p.category&&(!p.keys||p.keys.includes(r.study_type_key))).map(r=>r.study_type_key));
                return keys.length!==new Set(keys).size;
              });
              const emptyRendered=h.root.flatMap(id=>h.children(id,a)).filter(id=>h.count(id,a)===0);
              return {counts,covered:covered.length,cardiac:h.count('ultrasound_cardiac',a),
                vascular:h.count('ultrasound_vascular',a),cv:h.count('cardiovascular',a),
                maxChildren:Math.max(...Object.keys(h.nodes).map(id=>h.children(id,a).length)),
                duplicateLeafIds,emptyRendered};
            }''', rows)
            assert coverage['counts'] == {'laboratory': 105, 'imaging': 44, 'pathology': 2,
                                          'functional': 35, 'procedures': 13}, coverage
            assert coverage['covered'] == 199 and coverage['cardiac'] == 3 and coverage['vascular'] == 3 and coverage['cv'] == 7, coverage
            assert coverage['maxChildren'] <= 12
            assert coverage['duplicateLeafIds'] == [] and coverage['emptyRendered'] == [], coverage

            root.locator('[data-hier-node="laboratory"]').click()
            family = page.locator('.vis06-category-screen[data-hier-parent="laboratory"]')
            expect(family.locator('.vis06-primary-categories button')).to_have_count(8)
            expect(family.locator('.vis06-secondary-categories button')).to_have_count(4)
            assert 'Citología' not in family.inner_text()
            page.screenshot(path=f'/tmp/study_nav_hier03_lab_{width}x{height}.png', full_page=True)
            if width == 1440:
                family.locator('.vis06-lower-link').filter(has_text='Todos los estudios de laboratorio').click()
                page.locator('[data-tax03c-search]').fill('cyto_pap')
                page.wait_for_timeout(350)
                expect(page.locator('[data-tax03c-id]')).to_have_count(0)
                page.locator('[data-tax03c-search]').fill('cbc')
                page.wait_for_timeout(350)
                expect(page.locator('[data-tax03c-id]')).to_have_count(1)
                page.locator('dialog header [data-tax03c-close]').click()
                family.locator('.vis06-lower-link').filter(has_text='Buscar en todo').click()
                page.locator('[data-tax03c-search]').fill('cyto_pap')
                page.wait_for_timeout(350)
                expect(page.locator('[data-tax03c-id]')).to_have_count(1)
                page.locator('dialog header [data-tax03c-close]').click()
            family.locator('[data-hier-node="immunoserology"]').click()
            expect(page.locator('.vis06-category-screen[data-hier-parent="immunoserology"] .vis06-primary-categories button')).to_have_count(5)
            page.locator('.vis06-category-back,.vis06-head .vis06-flow-back').first.click()
            family.locator('[data-hier-node="genetics"]').click()
            expect(page.locator('.vis06-category-screen[data-hier-parent="genetics"] .vis06-primary-categories button')).to_have_count(8)
            page.locator('.vis06-head .vis06-flow-back').click()

            family.locator('[data-hier-node="hematology"]').click()
            expect(page.locator('dialog.tax03c-dialog')).to_be_visible()
            page.locator('[data-tax03c-search]').fill('cbc')
            page.wait_for_timeout(350)
            page.locator('[data-tax03c-id]').first.click()
            page.locator('dialog header [data-tax03c-close]').click()
            expect(page.locator('.vis06-hier-draft-status')).to_contain_text('1 estudio')
            page.locator('.vis06-head .vis06-flow-back').click()
            root.locator('[data-hier-node="imaging"]').click()
            imaging = page.locator('.vis06-category-screen[data-hier-parent="imaging"]')
            expect(imaging.locator('.vis06-primary-categories button')).to_have_count(7)
            if width == 1440:
                imaging.locator('.vis06-lower-link').filter(has_text='Todos los estudios de imagenología').click()
                page.locator('[data-tax03c-search]').fill('dental_cbct')
                page.wait_for_timeout(350)
                expect(page.locator('[data-tax03c-id]')).to_have_count(1)
                page.locator('dialog header [data-tax03c-close]').click()
            imaging.locator('[data-hier-node="ultrasound"]').click()
            ultrasound = page.locator('.vis06-category-screen[data-hier-parent="ultrasound"]')
            expect(ultrasound.locator('.vis06-primary-categories button')).to_have_count(4)
            ultrasound.locator('[data-hier-node="ultrasound_cardiac"]').click()
            expect(page.locator('dialog.tax03c-dialog')).to_be_visible()
            page.locator('[data-tax03c-search]').fill('echo_tte')
            page.wait_for_timeout(350)
            page.locator('[data-tax03c-id]').first.click()
            expect(page.locator('[data-tax03c-selected] .tax03c-selected-row')).to_have_count(2)
            if width == 1440:
                page.locator('[data-tax03c-custom-open]').click()
                page.locator('[data-tax03c-custom-category]').select_option('OTROS')
                page.locator('[data-tax03c-custom-name]').fill('Estudio clínico personalizado HIER03')
                page.locator('[data-tax03c-custom-add]').click()
                expect(page.locator('[data-tax03c-selected] .tax03c-selected-row')).to_have_count(3)
            page.locator('dialog header [data-tax03c-close]').click()
            page.locator('.vis06-hier-draft button').click()
            expect(page.locator('[data-tax03c-selected] .tax03c-selected-row')).to_have_count(3 if width == 1440 else 2)
            if width == 1440:
                page.locator('dialog [data-tax03c-submit]').click()
                page.wait_for_timeout(250)
                assert len(writes) == 1 and len(writes[0]['payload']['order_items']) == 3, writes
                assert any(item.get('study_display_name') == 'Estudio clínico personalizado HIER03'
                           for item in writes[0]['payload']['order_items'])
                page.locator('.vis06-flow-back').first.click()
                page.locator('.vis06-intent-card').first.click()
                root.locator('[data-hier-node="pathology"]').click()
                expect(page.locator('dialog.tax03c-dialog')).to_be_visible()
                assert 'Citología cervical' in page.locator('dialog.tax03c-dialog header').inner_text()
                page.locator('[data-tax03c-search]').fill('cyto_pap')
                page.wait_for_timeout(350)
                expect(page.locator('[data-tax03c-id]')).to_have_count(1)
                page.locator('dialog header [data-tax03c-close]').click()
                root.locator('[data-hier-node="functional"]').click()
                expect(page.locator('.vis06-category-screen[data-hier-parent="functional"] .vis06-primary-categories button')).to_have_count(5)
                page.locator('[data-hier-node="cardiovascular"]').click()
                page.locator('[data-tax03c-search]').fill('ecg_12lead')
                page.wait_for_timeout(350)
                expect(page.locator('[data-tax03c-id]')).to_have_count(1)
                page.locator('dialog header [data-tax03c-close]').click()
                page.locator('.vis06-head .vis06-flow-back').click()
                root.locator('[data-hier-node="procedures"]').click()
                expect(page.locator('.vis06-category-screen[data-hier-parent="procedures"] .vis06-primary-categories button')).to_have_count(4)
                page.locator('[data-hier-node="bronchoscopy"]').click()
                page.locator('[data-tax03c-search]').fill('bronchoscopy_base')
                page.wait_for_timeout(350)
                expect(page.locator('[data-tax03c-id]')).to_have_count(1)
                page.locator('dialog header [data-tax03c-close]').click()
                page.locator('.vis06-head .vis06-flow-back').click()
                for specialty, family_id, first_id in [
                    ('Cardiología', 'imaging', 'ultrasound'),
                    ('Endocrinología', 'laboratory', 'endocrine'),
                    ('Neurología', 'functional', 'neurophysiology'),
                    ('Gastroenterología', 'procedures', 'digestive_endoscopy')]:
                    profile['label'] = specialty
                    page.evaluate("dispatchEvent(new Event('mxmed:review-classification-changed'))")
                    expect(root.locator('.vis06-primary-categories button')).to_have_count(5)
                    root.locator(f'[data-hier-node="{family_id}"]').click()
                    expect(page.locator('.vis06-primary-categories button').first).to_have_attribute('data-hier-node', first_id)
                    page.locator('.vis06-head .vis06-flow-back').click()
                profile['label'] = 'Dentista'
                page.locator('.vis06-head .vis06-flow-back').click()
                page.locator('.vis06-intent-card').first.click()
                expect(page.locator('.vis06-category-screen[data-hier-level="dental"]')).to_be_visible()
                assert not page.locator('.vis06-category-screen[data-hier-level="root"]').count()
            else:
                page.locator('dialog header [data-tax03c-close]').click()
                page.locator('.vis06-head .vis06-flow-back').click()
                page.locator('.vis06-head .vis06-flow-back').click()
                page.locator('.vis06-head .vis06-flow-back').click()
                expect(page.locator('.mx-patient-leave-dialog')).to_be_visible()
                page.locator('.mx-patient-leave-dialog [data-discard]').click()
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
            assert not errors, errors
            print(f'QA_{width}x{height}=PASS; root={coverage["counts"]}; mixed_writes={len(writes)}; horizontal_overflow=false')
            page.close()
        browser.close()


if __name__ == '__main__':
    run()
