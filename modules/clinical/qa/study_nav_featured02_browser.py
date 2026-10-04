"""FEATURED02 served-asset browser gate; reads the review catalog and blocks writes."""
import ast
import csv
import json
import pathlib
import subprocess
from collections import defaultdict
from urllib.parse import parse_qs, urlsplit

from playwright.sync_api import expect, sync_playwright

ROOT = pathlib.Path(__file__).resolve().parents[3]
BASE = 'http://127.0.0.1:18148'
MATRIX = list(csv.DictReader((ROOT / 'docs/clinical/STUDY_NAV_FEATURED01_LEAF_MATRIX.csv').open()))
MEMBERS = list(csv.DictReader((ROOT / 'docs/clinical/STUDY_NAV_FEATURED01_GROUP_MEMBERSHIP_MATRIX.csv').open()))
CONFIG = json.loads((ROOT / 'modules/clinical/catalog/study_featured_navigation_v1.json').read_text())['leaves']
AUTHORITY = list(csv.DictReader((ROOT / 'docs/clinical/LAB_CAT04A_R1_IMPLEMENTATION_AUTHORITY.csv').open()))
ADDED = defaultdict(list)
for authority in AUTHORITY:
    if authority['r1_action'] == 'CREATE_CANONICAL':
        ADDED[authority['r1_primary_leaf_key']].append(authority)
tree = ast.parse((ROOT / 'modules/clinical/qa/lab_cat02a_browser.py').read_text())
HTML = next(ast.literal_eval(node.value) for node in tree.body if isinstance(node, ast.Assign)
            if any(isinstance(target, ast.Name) and target.id == 'HTML' for target in node.targets))
HTML = HTML.replace('</head>', '<link rel="stylesheet" href="/assets/css/clinical/study-navigation-hierarchy-v2.css"><link rel="stylesheet" href="/assets/css/clinical/order-composition-v1.css"></head>')
HTML = HTML.replace('<script src="/assets/js/clinical/vis06-modules.js', '<script src="/assets/js/clinical/study-navigation-hierarchy-v2.js"></script><script src="/assets/js/clinical/patient-workspace-navigation-guard.js"></script><script src="/assets/js/clinical/order-composition-v1.js"></script><script src="/assets/js/clinical/vis06-modules.js')
raw = subprocess.check_output(['mysql', '-N', '-B', 'mxmed_director_review_lon07c', '-e',
    'SELECT study_type_id,study_type_key,display_name_es,category_key,aliases_json FROM clinical_study_types WHERE is_active=1'], text=True)
ROWS = [dict(study_type_id=int(i), study_type_key=k, display_name_es=n, category_key=c, aliases=json.loads(a))
        for i, k, n, c, a in (line.split('\t') for line in raw.splitlines())]
IDS = {row['study_type_key']:row['study_type_id'] for row in ROWS}
COUNTS = {category:sum(row['category_key'] == category for row in ROWS) for category in {row['category_key'] for row in ROWS}}
assert len(ROWS) == 252 and len(CONFIG) == len(MATRIX) == 49 and sum(map(len, ADDED.values())) == 20
assert len(MEMBERS) == 152
assert set(IDS) == set().union(*(set(leaf['study_keys']) for leaf in CONFIG.values()))
assert sum(leaf['small'] for leaf in CONFIG.values()) == 37
assert sum(len(leaf['featured']) for leaf in CONFIG.values()) == 29
assert sum(len(leaf['groups']) for leaf in CONFIG.values()) == 41
assert sum(len(group['keys']) for leaf in CONFIG.values() for group in leaf['groups']) == 169
for row in MATRIX:
    leaf = CONFIG[row['leaf_key']]
    additions = ADDED[row['leaf_key']]
    expected_keys = set(row['current_active_study_keys'].split('|')) | {item['r1_stable_key'] for item in additions}
    assert leaf['heading'] == row['heading_proposal']
    assert leaf['small'] == (len(expected_keys) <= 6)
    assert set(leaf['study_keys']) == expected_keys
    assert len(leaf['study_keys']) == len(expected_keys)
    expected = [] if row['featured_confidence'] == 'LOW' else [row[f'proposed_featured_{i}'] for i in range(1, 7) if row[f'proposed_featured_{i}']]
    assert leaf['featured'] == expected
    expected_groups = {key for key in row['proposed_catalog_groups'].split('|') if key}
    expected_groups.update(item['r1_accordion_group_key'] for item in additions if item['r1_accordion_group_key'] != 'DIRECT_DISPLAY')
    assert {group['key'] for group in leaf['groups']} == expected_groups
    assert all(group['keys'] and set(group['keys']) <= set(leaf['study_keys']) for group in leaf['groups'])
    for item in additions:
        if item['r1_accordion_group_key'] != 'DIRECT_DISPLAY':
            group = next(group for group in leaf['groups'] if group['key'] == item['r1_accordion_group_key'])
            assert group['label'] == item['r1_accordion_group_label']
            assert item['r1_stable_key'] in group['keys']
for member in MEMBERS:
    group = next(group for group in CONFIG[member['leaf_key']]['groups'] if group['key'] == member['accordion_group_key'])
    assert group['label'] == member['accordion_group_label'] and member['canonical_study_key'] in group['keys']
print('QA_FEATURED02_STATIC_49_LEAVES_252_ACTIVE_41_GROUPS_169_MEMBERS=PASS', flush=True)

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height, keys in [
        (1440, 900, [row['leaf_key'] for row in MATRIX]),
        (1366, 768, ['chemistry', 'coagulation', 'urine', 'panels', 'radiography', 'cardiovascular', 'dental-cbct']),
        (390, 844, ['chemistry', 'coagulation', 'urine', 'panels', 'dental-cbct']),
    ]:
        page = browser.new_page(viewport={'width':width, 'height':height})
        errors, writes = [], []
        profile = {'label':'Médico General'}
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.route(BASE + '/__featured02__', lambda route: route.fulfill(content_type='text/html; charset=utf-8', body=HTML))
        page.route('**/api/profiles/index.php/private/doctor/**', lambda route: route.fulfill(content_type='application/json', body=json.dumps({'ok':True, 'data':{'identity_public':{'specialty_primary':profile['label']}, 'verified_credentials':{'professional':None, 'specialties':[]}}})))
        def api(route):
            url = urlsplit(route.request.url)
            if route.request.method != 'GET':
                writes.append(url.path)
                route.fulfill(status=403, content_type='application/json', body='{"ok":false}')
                return
            query = parse_qs(url.query)
            if url.path.endswith('/study-types'):
                offset = int(query.get('offset', ['0'])[0])
                limit = int(query.get('limit', ['30'])[0])
                catalog = [row for row in ROWS if not query.get('category') or row['category_key'] == query['category'][0]]
                data = {'items':catalog[offset:offset+limit], 'has_more':offset+limit<len(catalog),
                        'categories':[{'category_key':k, 'label_es':k, 'active_count':v} for k, v in COUNTS.items()]}
            elif url.path.endswith('/encounters/active'):
                data = {'doctor_id':'d_labcat02a'}
            else:
                data = {'items':[]}
            route.fulfill(content_type='application/json', body=json.dumps({'ok':True, 'data':data}, ensure_ascii=False))
        page.route('**/api/clinical/index.php/**', api)
        for key in keys:
            row = next(row for row in MATRIX if row['leaf_key'] == key)
            leaf = CONFIG[key]
            profile['label'] = ('Ortodoncia' if key == 'dental-records' else 'Odontología') if row['root_family_key'] == 'dental' else 'Médico General'
            page.goto(BASE + '/__featured02__', wait_until='networkidle')
            page.locator('.vis06-intent-card').first.click()
            if row['root_family_key'] == 'dental':
                page.locator(f'.vis06-primary-categories button').filter(has_text=row['leaf_label']).first.click()
            else:
                path = row['full_navigation_path_keys'].split(' / ')
                for node in path[:-1]:
                    page.locator(f'[data-hier-node="{node}"]').click()
                    expect(page.locator('.vis06-hier-breadcrumb')).to_be_hidden()
                    expect(page.locator('#t-estudios .vis06-head h3')).to_have_text(
                        row['root_family_label'] if node == path[0] else row['parent_group_label'])
                page.locator(f'[data-hier-node="{path[-1]}"]').click()
            expect(page.locator('.ordcomp')).to_be_visible()
            expect(page.locator('#t-estudios .vis06-head h3')).to_have_text(leaf['heading'])
            assert page.locator('.ordcomp-catalog-role, .ordcomp-breadcrumb').count() == 0
            assert page.locator('.tax03c-navigation-scope').is_hidden()
            expect(page.locator('.tax03c-status')).to_be_hidden()
            assert page.locator('[data-tax03c-global]').is_visible()
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (key, width, 'overflow')
            if leaf['small']:
                assert page.locator('.ordcomp-full-catalog, [data-ordcomp-featured]').count() == 0
                assert page.locator('.ordcomp [data-tax03c-id]').count() == len(leaf['study_keys'])
                expect(page.locator('.ordcomp [data-tax03c-custom-open]')).to_be_visible()
                if key == 'coagulation':
                    page.locator('.ordcomp [data-tax03c-custom-open]').click()
                    expect(page.locator('.ordcomp [data-tax03c-custom-name]')).to_be_visible()
                    page.locator('.ordcomp [data-tax03c-custom-cancel]').click()
            else:
                assert page.locator('.ordcomp-full-catalog').count() == 1
                assert page.locator('[data-ordcomp-featured] button').count() == len(leaf['featured'])
                if leaf['featured']:
                    expect(page.locator('.ordcomp-featured-section h5')).to_have_text('COMUNES')
                expect(page.locator('.ordcomp [data-tax03c-custom-open]')).to_be_hidden()
                page.locator('.ordcomp-full-catalog > summary').click()
                expect(page.locator('.ordcomp [data-tax03c-custom-open]')).to_be_visible()
                if key == 'chemistry':
                    page.locator('.ordcomp [data-tax03c-custom-open]').click()
                    expect(page.locator('.ordcomp [data-tax03c-custom-name]')).to_be_visible()
                    page.locator('.ordcomp [data-tax03c-custom-cancel]').click()
                assert page.locator('[data-catalog-group]').count() == len(leaf['groups'])
                assert page.locator('[data-catalog-group]').evaluate_all('(nodes)=>nodes.every(n=>!!n.querySelector("[data-tax03c-id]"))')
                if leaf['featured']:
                    expect(page.locator('.ordcomp-featured-section')).to_be_hidden()
                for group in leaf['groups']:
                    card = page.locator('[data-catalog-group="'+group['label']+'"]')
                    assert card.count() == 1
                    assert card.locator('[data-tax03c-id]').count() == len(group['keys'])
            if key in ('chemistry', 'urine', 'coagulation', 'dental-cbct'):
                if not leaf['small']:
                    page.locator('.ordcomp-full-catalog > summary').click()
                target = leaf['featured'][0] if leaf['featured'] else leaf['study_keys'][0]
                control = page.locator(f'.ordcomp [data-tax03c-id="{IDS[target]}"]').first
                control.click()
                assert page.locator('.ordcomp-summary .tax03c-selected-row').count() == 1
                assert control.get_attribute('aria-pressed') == 'true'
                assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
            if key == 'chemistry' and width == 1440:
                page.screenshot(path='/tmp/study_nav_featured02_chemistry_1440x900.png', full_page=True)
            if key == 'coagulation' and width == 390:
                page.screenshot(path='/tmp/study_nav_featured02_small_390x844.png', full_page=True)
            if errors or writes:
                raise AssertionError((key, errors, writes))
        print(f'QA_FEATURED02_BROWSER_{width}x{height}_{len(keys)}_LEAVES=PASS', flush=True)
        page.close()
    browser.close()
print('QA_BROWSER_LAUNCHER=Playwright bundled Chromium headless', flush=True)
