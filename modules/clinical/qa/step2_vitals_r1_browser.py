"""WebKit Step 2 preview against the synthetic Director runtime; no fixture writes."""
import json
import os
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ.get('STEP2_VITALS_ARTIFACTS', '/tmp/mxmed-step2-vitals-r1'))
OUT.mkdir(parents=True, exist_ok=True)
BASE = 'http://127.0.0.1:18143'
STAMP = '2026-09-27 07:17:00'
CATALOG = [
    ('blood_pressure', 'mmHg', None, 120, 80),
    ('heart_rate', 'bpm', 78, None, None),
    ('respiratory_rate', 'rpm', 16, None, None),
    ('temperature', '°C', 36.5, None, None),
    ('oxygen_saturation', '%', 98, None, None),
    ('pain', 'score', 2, None, None),
    ('weight', 'kg', 70, None, None),
    ('height', 'cm', 170, None, None),
    ('waist', 'cm', 85, None, None),
]


def row(index):
    code, unit, numeric, systolic, diastolic = CATALOG[index]
    return {
        'observation_id': 90001 + index, 'encounter_id': 1016,
        'code': code, 'unit': unit, 'value_numeric': numeric,
        'systolic_mm_hg': systolic, 'diastolic_mm_hg': diastolic,
        'source': 'direct_measurement', 'provenance_json': '{"capture_time_mode":"SERVER_AT_SAVE"}',
        'effective_at': STAMP, 'recorded_at': STAMP,
        'effective_at_authority': 'EXPLICIT_EFFECTIVE_TIME', 'row_version': 1,
        'invalidated_at': None,
    }


with sync_playwright() as playwright:
    browser = playwright.webkit.launch(headless=True)
    page = browser.new_page(viewport={'width': 1440, 'height': 900})
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    state = {'rows': [], 'fail': False}

    def serve_index(route):
        response = route.fetch()
        html = response.text().replace('<span>Mediciones</span></button>', '<span>Signos vitales</span></button>')
        html = html.replace('<label>Fecha y hora de medición<input data-m7-measurement-time type="datetime-local" required></label>', '')
        route.fulfill(response=response, body=html)

    def serve_detail(route):
        response = route.fetch()
        payload = response.json()
        payload['data']['observations'] = state['rows']
        route.fulfill(response=response, body=json.dumps(payload), content_type='application/json')

    def serve_create(route):
        payload = route.request.post_data_json
        assert 'effective_at' not in payload
        assert payload.get('capture_time_mode') == 'SERVER_AT_SAVE'
        if state['fail']:
            route.fulfill(status=422, content_type='application/json', body=json.dumps({'ok': False, 'error': {'code': 'TEST_FAILURE'}}))
            return
        new = row(3)
        new['value_numeric'] = payload['value_numeric']
        state['rows'] = [new]
        route.fulfill(status=201, content_type='application/json', body=json.dumps({'ok': True, 'data': new}))

    page.route('**/index.html?*', serve_index)
    for relative in (
        'assets/js/clinical/m7-ws03.js',
        'assets/js/clinical/vis04-consultation.js',
        'assets/css/expediente-paciente-visual-normalization.css',
    ):
        path = ROOT / relative
        content_type = 'text/javascript' if path.suffix == '.js' else 'text/css'
        page.route('**/' + relative + '*', lambda route, request, path=path, content_type=content_type: route.fulfill(status=200, body=path.read_bytes(), content_type=content_type))
    page.route('**/api/clinical/index.php/encounters/enc%3A1016', serve_detail)
    page.route('**/api/clinical/index.php/encounters/enc%3A1016/observations', serve_create)

    def ready(width=1440, height=900):
        page.set_viewport_size({'width': width, 'height': height})
        page.goto(BASE + '/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide', wait_until='commit')
        expect(page.locator('[data-m7-body]')).to_have_attribute('data-encounter-id', '1016', timeout=55000)
        page.locator('[data-m7-section="measurements"]').click()
        expect(page.locator('[data-m7-step-title]')).to_have_text('Signos vitales y otros valores clínicos')
        assert page.locator('[data-m7-measurement-time]').count() == 0

    ready()
    assert page.locator('[data-m7-measurements-list] .vis29-empty').count() == 1
    assert page.evaluate('document.documentElement.scrollHeight <= innerHeight')
    page.screenshot(path=str(OUT / 'step2-empty-1440-webkit.png'))

    selector = page.locator('[data-m7-measurement-code]')
    assert 'temperature' in selector.locator('option').evaluate_all('(items) => items.map(item => item.value)')
    selector.select_option('temperature')
    page.locator('[data-m7-measurement-value]').fill('36.5')
    page.locator('[data-m7-measurement-source]').select_option('direct_measurement')
    state['fail'] = True
    page.locator('[data-m7-measurement-save]').click()
    assert 'temperature' in selector.locator('option').evaluate_all('(items) => items.map(item => item.value)')
    assert page.locator('[data-m7-measurement-value]').input_value() == '36.5'
    state['fail'] = False
    page.locator('[data-m7-measurement-save]').click()
    expect(page.locator('[data-m7-measurements-list] .vis-step2-chip')).to_have_count(1)
    assert 'temperature' not in selector.locator('option').evaluate_all('(items) => items.map(item => item.value)')
    page.screenshot(path=str(OUT / 'step2-one-1440-webkit.png'))
    ready()
    assert 'temperature' not in selector.locator('option').evaluate_all('(items) => items.map(item => item.value)')
    page.screenshot(path=str(OUT / 'step2-used-selector-1440-webkit.png'))

    state['rows'] = [row(0), row(3), row(6)]
    ready()
    chips = page.locator('[data-m7-measurements-list] .vis-step2-chip')
    expect(chips).to_have_count(3)
    assert '120/80 mmHg' in chips.first.inner_text()
    page.screenshot(path=str(OUT / 'step2-multiple-1440-webkit.png'))

    report = {}
    for width, height in ((1440, 900), (1366, 768), (820, 1180), (390, 844)):
        ready(width, height)
        metrics = page.evaluate('''() => {
          const grid=document.querySelector('[data-m7-measurements-list]');
          const nav=document.querySelector('.m7-workspace-sections');
          const chips=[...grid.querySelectorAll('.vis-step2-chip')];
          const footer=document.querySelector('.vis04-progression');
          return {columns:getComputedStyle(grid).gridTemplateColumns.split(' ').length,
            horizontalOverflow:document.documentElement.scrollWidth>innerWidth,
            pageScroll:document.documentElement.scrollHeight>innerHeight,
            chipOverflow:chips.some(chip=>chip.scrollWidth>chip.clientWidth+1),
            chipFooterOverlap:chips.some(chip=>chip.getBoundingClientRect().bottom>footer.getBoundingClientRect().top),
            chipBottom:Math.max(...chips.map(chip=>chip.getBoundingClientRect().bottom)),footerTop:footer.getBoundingClientRect().top,
            chipTop:Math.min(...chips.map(chip=>chip.getBoundingClientRect().top)),
            formBottom:document.querySelector('.vis29-register').getBoundingClientRect().bottom,
            currentTop:document.querySelector('.vis29-current').getBoundingClientRect().top,
            navX:nav.getBoundingClientRect().x,navY:nav.getBoundingClientRect().y};
        }''')
        assert metrics['columns'] == (9 if width >= 1200 else 4 if width >= 576 else 2), metrics
        assert not metrics['horizontalOverflow'] and not metrics['chipOverflow'], metrics
        if width >= 1200:
            assert not metrics['pageScroll'] and not metrics['chipFooterOverlap'], metrics
        report[f'{width}x{height}'] = metrics
        page.screenshot(path=str(OUT / f'step2-multiple-{width}-webkit.png'))

    state['rows'] = [row(index) for index in range(9)]
    ready()
    expect(chips).to_have_count(9)
    assert not page.evaluate('document.documentElement.scrollWidth > innerWidth')
    assert page.evaluate('''() => [...document.querySelectorAll('[data-m7-measurements-list] .vis-step2-chip')].every(chip=>chip.getBoundingClientRect().bottom<=document.querySelector('.vis04-progression').getBoundingClientRect().top)''')
    page.screenshot(path=str(OUT / 'step2-nine-1440-webkit.png'))
    state['rows'] = []
    ready()
    assert 'temperature' in selector.locator('option').evaluate_all('(items) => items.map(item => item.value)')
    assert not errors, errors
    (OUT / 'results.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
    print('STEP2_VITALS_R1_WEBKIT_GATE=PASS', report, flush=True)
    browser.close()
