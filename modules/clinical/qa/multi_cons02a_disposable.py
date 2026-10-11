"""MULTI-CONS02A canonical draft, preview and authority proof on a disposable DB."""
import copy
import json
import os
import re
import subprocess
import uuid
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
OUT = Path('/tmp/mxmed-multi-cons02a-qa-20261010')
OUT.mkdir(exist_ok=True)
PATIENT = 'p_plan02ux_review'
DOCTOR = '1'
COOKIE = {'Cookie': 'PHPSESSID=step3-head-neck-qa'}
URL = f'{BASE}/api/clinical/index.php/doctors/{DOCTOR}/patients/{PATIENT}/documents'
PREVIEW = f'{BASE}/api/clinical/index.php/doctors/{DOCTOR}/patients/{PATIENT}/multimedia-preview'


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', query], text=True).strip()


def check(name, condition):
    if not condition:
        raise AssertionError(name)
    print(f'{name}=PASS', flush=True)


def body():
    return {
        'document_type': 'consentimiento_multimedia', 'type': 'consentimiento_multimedia',
        'title': 'Consentimiento multimedia', 'context': {'patient_id': PATIENT},
        'actor': {'user_id': 'review-user'},
        'payload': {
            'contract_version': 1, 'status': 'draft',
            'consent': {
                'media_types': ['photo', 'video'], 'capture_allowed': True,
                'use_purposes': ['clinical_record', 'clinical_followup'],
                'identification_scope': 'identifiable', 'authorization_scope': 'specific_event',
                'custom_scope_description': 'stale hidden scope',
                'validity': {'mode': 'single_event', 'valid_from': '2026-10-01', 'valid_to': '2026-10-30'},
                'context': {'clinical_event_or_procedure': 'Caso sintético QA', 'anatomical_area': '',
                            'clinical_purpose_note': '', 'event_date': ''},
            },
            'signer': {'mode': 'patient'},
            'patient_snapshot': {'id': PATIENT, 'full_name': 'Paciente sintético QA', 'age': '36', 'sex': 'Femenino'},
            'actor_snapshot': {'user_id': 'review-user', 'full_name': 'Dra. QA', 'license': 'QA123',
                               'specialty': 'QA', 'facility': 'Consultorio QA', 'place': 'México'},
            'presentation': {'version': 1, 'professional_header': 'shown'},
            'document_date': '2026-10-10', 'signatures': {},
        },
    }


with sync_playwright() as pw:
    api = pw.request.new_context(extra_http_headers=COOKIE)
    base = body()
    before = sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_multimedia'")
    preview = api.post(PREVIEW, data=base)
    check('A28_PREVIEW_ZERO_ROWS', preview.status == 200 and before == '0'
          and sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_multimedia'") == '0')
    html = preview.json()['data']['html']
    for text in ('Fotografías', 'Video', 'Integración al expediente clínico',
                 'Seguimiento de la evolución clínica', 'Caso sintético QA'):
        check('PREVIEW_' + re.sub(r'\W+', '_', text).upper(), text in html)
    check('A30_HIDDEN_VALUES_EXCLUDED', 'stale hidden scope' not in html and '2026-10-30' not in html)
    check('A31_DECLARATION_EXACT', 'De manera voluntaria autorizo la captura' in html
          and 'únicamente para' in html and 'difusión pública' not in html)
    check('A29_PREVIEW_EXACT_SELECTIONS', 'Fotografías, Video' in html
          and 'Audio' not in html and 'Identificable' in html and 'Este evento únicamente' in html)
    hidden = copy.deepcopy(base)
    hidden['payload']['presentation']['professional_header'] = 'hidden'
    hidden_html = api.post(PREVIEW, data=hidden).json()['data']['html']
    check('A32_A33_HEADER', 'Dra. QA' in html and 'clinical-doc-head' in html
          and 'clinical-doc-head' not in hidden_html)

    for label, field, selection, expected in (
        ('A01_PHOTO', 'media_types', ['photo'], 'Fotografías'),
        ('A02_VIDEO', 'media_types', ['video'], 'Video'),
        ('A03_AUDIO', 'media_types', ['audio'], 'Audio'),
        ('A04_MULTI_SELECT', 'media_types', ['photo', 'video', 'audio'], 'Audio, Fotografías, Video'),
        ('A07_CLINICAL_RECORD', 'use_purposes', ['clinical_record'], 'Integración al expediente clínico'),
        ('A08_CLINICAL_FOLLOWUP', 'use_purposes', ['clinical_followup'], 'Seguimiento de la evolución clínica'),
        ('A10_IDENTIFIABLE', 'identification_scope', 'identifiable', 'Identificable'),
        ('A11_REDUCED_IDENTIFICATION', 'identification_scope', 'reduced_identification', 'Identificación reducida'),
        ('A13_SPECIFIC_EVENT', 'authorization_scope', 'specific_event', 'Este evento o atención'),
        ('A14_EPISODE', 'authorization_scope', 'current_episode', 'Este episodio de atención'),
        ('A15_CONDITION_FOLLOWUP', 'authorization_scope', 'current_condition_followup', 'Seguimiento del padecimiento actual'),
        ('A17_SINGLE_EVENT', 'validity', {'mode': 'single_event'}, 'Este evento únicamente'),
        ('A18_DATE_RANGE', 'validity', {'mode': 'explicit_date_range', 'valid_from': '2026-10-10', 'valid_to': '2026-10-15'}, '2026-10-10 al 2026-10-15'),
    ):
        variant = copy.deepcopy(base)
        variant['payload']['consent'][field] = selection
        result = api.post(PREVIEW, data=variant)
        check(label, result.status == 200 and expected in result.json()['data']['html'])

    def invalid(name, mutate):
        case = copy.deepcopy(base)
        mutate(case)
        response = api.post(URL, data=case, headers={'Idempotency-Key': f'mmc-invalid-{uuid.uuid4()}'})
        check(name, response.status in (400, 422) and response.json()['ok'] is False)

    invalid('A05_ZERO_MEDIA', lambda b: b['payload']['consent'].update(media_types=[]))
    invalid('A06_CAPTURE_EXPLICIT', lambda b: b['payload']['consent'].update(capture_allowed=False))
    invalid('A09_DEFERRED_REJECTED', lambda b: b['payload']['consent'].update(use_purposes=['teaching']))
    invalid('A12_ANONYMOUS_REJECTED', lambda b: b['payload']['consent'].update(identification_scope='anonymous'))
    invalid('A16_CUSTOM_REQUIRED', lambda b: b['payload']['consent'].update(
        authorization_scope='custom_limited_scope', custom_scope_description=''))
    invalid('A19_INCOMPLETE_RANGE', lambda b: b['payload']['consent']['validity'].update(
        mode='explicit_date_range', valid_to=''))
    invalid('A20_REVERSED_RANGE', lambda b: b['payload']['consent']['validity'].update(
        mode='explicit_date_range', valid_from='2026-10-31'))
    invalid('A37_WRONG_PATIENT', lambda b: b['context'].update(patient_id='wrong'))
    invalid('A38_WRONG_DOCTOR', lambda b: b['actor'].update(user_id='wrong'))
    invalid('A36_SECOND_BODY', lambda b: b['payload'].update(rendered_text='independent body'))
    invalid('NO_EMISSION', lambda b: b['payload'].update(status='generated'))
    invalid('NO_SIGNATURE', lambda b: b['payload'].update(signatures={'doctor': {'image_data': 'x'}}))
    generic = api.post(f'{BASE}/api/clinical/index.php/encounters/1016/documents',
                       data={'document_type': 'consentimiento_multimedia', 'title': 'Bypass', 'payload': {}},
                       headers={'Idempotency-Key': f'mmc-generic-{uuid.uuid4()}'})
    check('A40_GENERIC_BLOCKED', generic.status in (400, 422)
          and 'GENERIC_WRITE_FORBIDDEN' in generic.text())
    no_session = pw.request.new_context()
    check('A39_ANONYMOUS_REJECTED', no_session.post(URL, data=base).status in (401, 403))
    no_session.dispose()

    key = 'mmc-create-' + str(uuid.uuid4())
    created = api.post(URL, data=base, headers={'Idempotency-Key': key})
    check('A21_DRAFT', created.status in (200, 201) and created.json()['data']['document']['status'] == 'draft')
    ref = created.json()['data']['document_id']
    check('A22_UUID', bool(re.fullmatch(r'[0-9a-f-]{36}', ref)))
    record = sql("SELECT CONCAT(status,'|',version,'|',payload_json) FROM clinical_documents WHERE document_uuid='" + ref + "'")
    status, version, encoded = record.split('|', 2)
    saved = json.loads(encoded)
    check('A34_A35_SNAPSHOT_PARITY', saved['frozen_snapshot']['html'] == html and status == 'draft' and version == '1')
    check('A30_NORMALIZED_CANONICAL', saved['consent']['custom_scope_description'] == ''
          and saved['consent']['validity']['valid_from'] == '' and saved['consent']['validity']['valid_to'] == '')
    replay = api.post(URL, data=base, headers={'Idempotency-Key': key})
    check('IDEMPOTENCY_REPLAY', replay.status == 200 and replay.json()['data']['document_id'] == ref
          and sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_multimedia'") == '1')
    update = copy.deepcopy(base)
    update['draft_ref'] = ref
    update['expected_version'] = 1
    update['payload']['consent']['media_types'] = ['audio', 'photo', 'video']
    update['payload']['consent']['authorization_scope'] = 'custom_limited_scope'
    update['payload']['consent']['custom_scope_description'] = 'Solo fotografía clínica de este procedimiento'
    update['payload']['consent']['validity'] = {'mode': 'explicit_date_range', 'valid_from': '2026-10-10', 'valid_to': '2026-10-15'}
    updated = api.post(URL, data=update, headers={'Idempotency-Key': 'mmc-update-' + str(uuid.uuid4())})
    check('A23_VERSION_INCREMENT', updated.status in (200, 201)
          and updated.json()['data']['document_id'] == ref
          and sql("SELECT version FROM clinical_documents WHERE document_uuid='" + ref + "'") == '2')
    stale = api.post(URL, data=update, headers={'Idempotency-Key': 'mmc-stale-' + str(uuid.uuid4())})
    check('A24_STALE_VERSION', stale.status == 409)
    detail_url = f'{BASE}/api/clinical/index.php/doctors/{DOCTOR}/documents/{ref}'
    detail = api.get(detail_url)
    check('A25_SERVER_RESUME_DATA', detail.status == 200 and detail.json()['data']['document']['version'] == 2)
    check('A40_NO_EXTRA_ROWS', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_multimedia'") == '1')
    check('A41_M6_PATIENT_COMMAND_LEDGER', sql("SELECT COUNT(*) FROM clinical_idempotency_requests "
          "WHERE operation_type='CREATE_ENCOUNTER_DOCUMENT' AND context_type='PATIENT' "
          "AND context_id='p_plan02ux_review' AND document_id IS NOT NULL") == '2')
    viewer = api.get(f'{BASE}/modules/clinical/ui/viewer.php?uuid={ref}&doctor_id={DOCTOR}')
    current_html = json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='" + ref + "'"))['frozen_snapshot']['html']
    check('A34_VIEWER_SNAPSHOT', viewer.status == 200 and current_html in viewer.text())
    check('A35_PRINT_SNAPSHOT', 'clinical-doc-base.css' in viewer.text() and current_html in viewer.text())

    browser = pw.chromium.launch(headless=True)
    for width, height in ((1366, 768), (1440, 900), (320, 844), (360, 800), (390, 844), (414, 896)):
        page = browser.new_page(viewport={'width': width, 'height': height}, extra_http_headers=COOKIE)
        errors = []
        page.on('pageerror', lambda e: errors.append(str(e)))
        page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
        page.wait_for_function('typeof window.setActivePatientId === "function"')
        page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
        page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
        page.evaluate('window.mxmedOpenMultimediaConsentDraftFoundation()')
        expect(page.locator('#modalConsentimientoMultimedia')).to_be_visible(timeout=15000)
        check(f'A26_BORRADOR_{width}', 'Borrador' in page.locator('#mmc_title').inner_text())
        expect(page.locator('#modalConsentimientoMultimedia [data-mmc-ref]')).to_have_count(1)
        page.locator('#modalConsentimientoMultimedia [data-mmc-ref]').click()
        expect(page.locator('#mmc_scope')).to_have_value('custom_limited_scope')
        expect(page.locator('#mmc_validity')).to_have_value('explicit_date_range')
        check(f'A27_CONTINUE_{width}', page.locator('#mmc_scope').input_value() == 'custom_limited_scope')
        if width == 1366:
            labelled = page.evaluate('''()=>{const modal=document.querySelector('#modalConsentimientoMultimedia');
              return modal.getAttribute('role')==='dialog' && modal.getAttribute('aria-modal')==='true'
                && [...modal.querySelectorAll('[data-mmc-capture] input,[data-mmc-capture] select,[data-mmc-capture] textarea')]
                  .every(input=>input.closest('label') || (input.id && modal.querySelector('label[for="'+input.id+'"]')))}''')
            check('ACCESSIBILITY_LABELS_DIALOG', labelled)
        page.locator('[data-mmc-preview]').click()
        page.wait_for_timeout(300)
        print('BROWSER_PREVIEW_ERROR=' + page.locator('[data-mmc-error]').inner_text(), flush=True)
        expect(page.locator('[data-mmc-review-html]')).to_contain_text('Solo fotografía clínica', timeout=15000)
        if width == 1366:
            page.locator('[data-mmc-continue]').click()
            expect(page.locator('[data-mmc-signatures]')).to_be_visible(timeout=15000)
            check('CANONICAL_DRAFT_BEFORE_SIGNATURES', page.locator('[data-mmc-status]').inner_text().startswith('Borrador guardado'))
        page.screenshot(path=str(OUT / f'preview-{width}x{height}.png'), full_page=False)
        overflow = page.evaluate('''()=>{const el=document.querySelector('[data-mmc-review]');
          return el.scrollWidth > el.clientWidth + 1 || document.documentElement.scrollWidth > innerWidth + 1}''')
        check(f'RESPONSIVE_{width}', not overflow and not errors)
        page.close()
    browser.close()
    api.dispose()
    print('MULTI_CONS02A_DISPOSABLE_QA=PASS', flush=True)
