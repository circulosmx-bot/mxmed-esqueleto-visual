"""DOC-CLOSE02C: exact Alta signature, QR and presentation on a disposable DB."""
import base64
import copy
import hashlib
import json
import os
import struct
import subprocess
import uuid
import zlib
from urllib.parse import parse_qs, urlparse
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
PATIENT = 'p_plan02ux_review'
ROOT = Path(__file__).resolve().parents[3]
ROUTE = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents'
QR = BASE + '/api/clinical/index.php/alta-qr-sessions'
VIEW = BASE + '/modules/clinical/ui/viewer.php?doctor_id=1&uuid='
SHOT = Path('/tmp/mxmed-doc-close02c-screenshots')
SHOT.mkdir(exist_ok=True)


def check(label, ok):
    if not ok:
        raise AssertionError(label)
    print(f'{label}=PASS', flush=True)


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', query], text=True).strip()


def png(blank=False):
    def chunk(kind, data):
        return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data) & 0xffffffff)
    width, height = 64, 24
    pixels = b''.join(b'\0' + b''.join(
        (b'\x10\x20\x30\xff' if not blank and 8 < x < 55 and abs(y - (7 + x // 8)) <= 1
         else b'\xff\xff\xff\xff') for x in range(width)) for y in range(height))
    data = (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 6, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(pixels)) + chunk(b'IEND', b''))
    return 'data:image/png;base64,' + base64.b64encode(data).decode()


def fingerprint(body):
    return subprocess.check_output(['php', '-r',
        'require "api/_lib/clinical_alta_binding.php";'
        'echo clinical_alta_binding_fingerprint(json_decode(file_get_contents("php://stdin"),true));'],
        input=json.dumps(body, ensure_ascii=False), text=True, cwd=ROOT).strip()


def body(marker):
    content = {'tipo_alta':'mejoria','motivo_egreso':marker,'resumen_evolucion':'Curso sintético favorable',
      'diagnostico_final':'Cuadro agudo no complicado','estado_paciente':'Clínicamente estable',
      'datos_relevantes':'Tolera líquidos','tratamiento':'Medidas de soporte individualizadas',
      'cuidados_generales':'Hidratación y reposo relativo','signos_alarma':'Empeoramiento o vómito persistente',
      'followup_date':'2026-10-12','followup_time':'10:00','followup_note':'Revaloración clínica',
      'cita_control':'12 oct 2026 · 10:00','recomendaciones':'Retomar actividades gradualmente'}
    return {'document_type':'alta_medica','type':'alta_medica','title':'Alta médica QA','summary':marker,
      'event_datetime':'2026-10-10 10:00:00','actor':{'user_id':'review-user'},
      'context':{'patient_id':PATIENT,'care_setting':'consulta'},
      'payload':{'contract_version':2,'status':'draft','workflow_step':1,
        'report':{'emission_date':'2026-10-10'},'alta':{'type':'mejoria'},
        'patient_snapshot':{'full_name':'Paciente sintético QA','age':'36','sex':'Femenino','identifier':'QA'},
        'actor_snapshot':{'user_id':'review-user','full_name':'Dra. QA','license':'QA-001',
          'specialty':'Medicina general','facility':'Consultorio sintético','place':'Ciudad de prueba'},
        'branding':{'logo_url_resolved':'','facility_visible':'Consultorio sintético',
          'location_line_visible':'Ciudad de prueba'},
        'presentation':{'version':1,'professional_header':'shown'},
        'content':content,'form_snapshot':{'fecha_alta':'2026-10-10',**content},
        'signatures':{'doctor':None}}}


def save(api, request):
    return api.post(ROUTE, data=request, headers={'Idempotency-Key':'alta-c:' + str(uuid.uuid4())})


def sign(request, source='local_canvas', image=None):
    data = copy.deepcopy(request)
    image = image or png()
    entry = {'type':'drawn','role':'doctor','source':source,'image_data':image,
      'signer_name':'Dra. QA','signed_at':'2026-10-10 10:00:00'}
    entry['binding'] = {'version':2,'document_type':'alta_medica','document_uuid':data['draft_ref'],
      'document_version':data['expected_version'],'role':'doctor','source':source,
      'authority':'1|review-user','artifact_digest':hashlib.sha256(base64.b64decode(image.split(',',1)[1])).hexdigest(),
      'content_fingerprint':fingerprint(data),'applied_at':'2026-10-10T10:00:00Z'}
    data['payload']['signatures']['doctor'] = entry
    return data


def make_draft(api, marker):
    request = body(marker)
    result = save(api, request)
    check('DRAFT_CREATE_' + marker, result.status == 201)
    request['draft_ref'] = result.json()['data']['document_id']
    request['expected_version'] = int(result.json()['data']['document']['version'])
    return request


migration = ROOT / 'modules/clinical/db/migrations/2026_10_10_36_alta_qr_sessions.sql'
for _ in range(2):
    subprocess.run(['mysql', DB], input=migration.read_bytes(), check=True)
check('MIGRATION_TWICE', sql("SHOW TABLES LIKE 'clinical_alta_qr_sessions'") == 'clinical_alta_qr_sessions')
sql("CREATE TABLE IF NOT EXISTS profiles_doctors (doctor_id VARCHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB")
sql("INSERT IGNORE INTO profiles_doctors (doctor_id) VALUES ('1')")
signature_migration = ROOT / 'modules/signatures/db/2026_09_14_physician_signatures.sql'
subprocess.run(['mysql', DB], input=signature_migration.read_bytes(), check=True)
registered_image = png()
registered_bytes = base64.b64decode(registered_image.split(',',1)[1])
sql("INSERT INTO physician_signatures (doctor_id,asset_id,storage_key,checksum_sha256,byte_size,width,height) "
  f"VALUES ('1','{uuid.uuid4()}','disposable/qa-signature.png','{hashlib.sha256(registered_bytes).hexdigest()}',"
  f"{len(registered_bytes)},64,24)")

with sync_playwright() as pw:
    api = pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    unsigned = make_draft(api, 'ALTA C01')
    check('C01_DRAFT_WITHOUT_SIGNATURE', unsigned['expected_version'] >= 1)
    attempt = copy.deepcopy(unsigned)
    attempt['payload']['status'] = 'issued'
    check('C05_MISSING_BLOCKED', save(api, attempt).status == 400)
    for name, mutate in [
        ('C04_BLANK', lambda x: x['payload']['signatures']['doctor'].update(image_data=png(True))),
        ('C06_WRONG_UUID', lambda x: x['payload']['signatures']['doctor']['binding'].update(document_uuid=str(uuid.uuid4()))),
        ('C07_WRONG_VERSION', lambda x: x['payload']['signatures']['doctor']['binding'].update(document_version=999)),
        ('C08_STALE_CONTENT', lambda x: x['payload']['content'].update(motivo_egreso='Contenido cambiado')),
        ('C09_WRONG_AUTHORITY', lambda x: x['payload']['signatures']['doctor']['binding'].update(authority='other|user')),
        ('C10_WRONG_DIGEST', lambda x: x['payload']['signatures']['doctor']['binding'].update(artifact_digest='0'*64)),
        ('C11_UNBOUND', lambda x: x['payload']['signatures']['doctor'].pop('binding')),
    ]:
        bad = sign(attempt)
        mutate(bad)
        check(name, save(api, bad).status == 400)
    local = sign(attempt)
    issued = save(api, local)
    check('C02_LOCAL_BOUND', issued.status == 201 and issued.json()['data']['document']['status'] == 'generated')
    check('C35_C36_STABLE_DRAFT_UUID', issued.json()['data']['document_id'] == unsigned['draft_ref'])
    check('EXACT_VERSION_RETAINED', int(issued.json()['data']['document']['version']) == unsigned['expected_version'])
    check('C38_C39_C40_REVIEW_VIEW_PRINT',
      local['payload']['content']['motivo_egreso'] in api.get(VIEW+unsigned['draft_ref']).text()
      and 'window.print' in api.get(VIEW+unsigned['draft_ref']+'&autoprint=1').text())

    registered = make_draft(api, 'ALTA REGISTERED')
    registered['payload']['status'] = 'issued'
    registered_bound = sign(registered, source='registered_profile', image=registered_image)
    check('C03_REGISTERED_BOUND', save(api, registered_bound).status == 201)

    header = make_draft(api, 'ALTA HEADER')
    shown = api.post(BASE+f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/alta-preview',data=header)
    check('C21_HEADER_SHOWN', shown.status == 200 and 'Dra. QA' in shown.json()['data']['html'])
    hidden = copy.deepcopy(header)
    hidden['payload']['presentation']['professional_header'] = 'hidden'
    hidden_html = api.post(BASE+f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/alta-preview',data=hidden)
    check('C22_C23_HEADER_HIDDEN_AUTHORITY', hidden_html.status == 200
      and 'clinical-doc-head' not in hidden_html.json()['data']['html']
      and hidden['payload']['actor_snapshot']['user_id'] == 'review-user')
    check('C24_HEADER_STALES_FINGERPRINT', fingerprint(header) != fingerprint(hidden))
    header_signed = sign(header)
    header_signed['payload']['status'] = 'issued'
    header_signed['payload']['presentation']['professional_header'] = 'hidden'
    check('C24_HEADER_CHANGE_REJECTS_OLD_SIGNATURE', save(api, header_signed).status == 400)
    material_fields = ['motivo_egreso','resumen_evolucion','diagnostico_final','estado_paciente',
      'tratamiento','datos_relevantes','cuidados_generales','signos_alarma',
      'followup_note','recomendaciones']
    for field in material_fields:
        modified=copy.deepcopy(header)
        modified['payload']['content'][field] += ' [cambio]'
        check('FINGERPRINT_'+field.upper(), fingerprint(modified) != fingerprint(header))
    modified=copy.deepcopy(header)
    modified['payload']['report']['emission_date']='2026-10-11'
    check('FINGERPRINT_ALTA_DATE',fingerprint(modified)!=fingerprint(header))
    modified=copy.deepcopy(header)
    modified['payload']['alta']['type']='voluntaria'
    check('FINGERPRINT_ALTA_TYPE',fingerprint(modified)!=fingerprint(header))
    check('C37_PREVIEW_UNSAVED_PARITY', shown.json()['data']['html'] ==
      json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE document_uuid='{header['draft_ref']}'"))['alta_snapshot']['html'])

    qr_draft = make_draft(api, 'ALTA QR EXACTA')
    created = api.post(QR,data={'document_uuid':qr_draft['draft_ref'],
      'document_version':qr_draft['expected_version'],'role':'doctor'})
    check('C12_C13_ALTA_QR_AUTHORITY', created.status == 201
      and '/alta-qr-sessions' not in created.json()['data']['mobile_url']
      and 'mode=alta-signature' in created.json()['data']['mobile_url'])
    token = created.json()['data']['token']
    mobile = api.get(f'{QR}/{token}/mobile-context')
    check('C14_MOBILE_EXACT_REVIEW', mobile.status == 200
      and 'ALTA QR EXACTA' in mobile.json()['data']['review_html'])
    check('QR_REVIEW_MARKED', api.post(f'{QR}/{token}/review').status == 200)
    received = api.post(f'{QR}/{token}/signature',data={'signature_data':png(),'reviewed':True})
    check('C15_REMOTE_UPLOAD', received.status == 200)
    status = api.get(f'{QR}/{token}/status')
    entry = status.json()['data']['signature']
    check('C16_REMOTE_RECEIPT_BINDING', status.status == 200 and entry['binding']['document_uuid'] == qr_draft['draft_ref']
      and entry['binding']['document_version'] == qr_draft['expected_version'])
    check('C17_UPLOAD_REPLAY_REJECTED', api.post(f'{QR}/{token}/signature',data={'signature_data':png()}).status in (409,410))
    qr_draft['payload']['signatures']['doctor'] = entry
    stored = save(api, qr_draft)
    check('C20_REMOTE_STORED_SAME_VERSION', stored.status == 201
      and int(stored.json()['data']['document']['version']) == qr_draft['expected_version'])
    check('C17_CONSUMED_REPLAY_REJECTED', api.get(f'{QR}/{token}/mobile-context').status in (409,410))
    qr_draft['payload']['status'] = 'issued'
    check('C15_REMOTE_EMIT', save(api, qr_draft).status == 201)

    stale_draft = make_draft(api, 'ALTA QR V1')
    stale_created = api.post(QR,data={'document_uuid':stale_draft['draft_ref'],
      'document_version':stale_draft['expected_version'],'role':'doctor'})
    stale_token = stale_created.json()['data']['token']
    api.post(f'{QR}/{stale_token}/review')
    stale_draft['payload']['content']['motivo_egreso'] = 'ALTA QR V2'
    changed = save(api, stale_draft)
    check('C18_VERSION_CHANGE', changed.status == 201 and
      int(changed.json()['data']['document']['version']) > stale_draft['expected_version'])
    check('C18_STALE_QR_REJECTED', api.post(f'{QR}/{stale_token}/signature',
      data={'signature_data':png()}).status in (409,410))
    check('C19_WRONG_ROLE_REJECTED', api.post(QR,data={'document_uuid':stale_draft['draft_ref'],
      'document_version':int(changed.json()['data']['document']['version']),'role':'patient'}).status != 201)
    browser = pw.chromium.launch(headless=True)
    for width, height in [(1366,768),(1440,900)]:
        page = browser.new_page(viewport={'width':width,'height':height},
          extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
        errors = []
        page.on('pageerror',lambda error: errors.append(str(error)))
        page.goto(BASE+'/index.html?qa_tools=hide',wait_until='domcontentloaded')
        page.wait_for_function('typeof window.setActivePatientId === "function"')
        page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
        page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',"
          "{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,"
          "skipUnsavedNewPatientConfirm:true,applyEntryRule:false});"
          "document.querySelector('#p-expediente').classList.remove('d-none');"
          "window.dispatchEvent(new Event('patient:selected'))}")
        page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
        page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
        page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
        page.locator('[data-action="documents-open-alta"]').click()
        expect(page.locator('#am_step_1')).to_be_visible()
        check(f'C26_HELP_OPEN_NO_MUTATION_{width}',
          page.locator('#am_motivo_egreso').input_value()=='')
        page.locator('[data-example-for="am_motivo_egreso"]').click()
        expect(page.locator('#am-example-heading')).to_be_visible()
        check(f'C26_HELP_STILL_EMPTY_{width}',page.locator('#am_motivo_egreso').input_value()=='')
        page.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').click()
        check(f'C27_ONLY_SELECTED_INSERTED_{width}',
          'cuadro gastrointestinal' in page.locator('#am_motivo_egreso').input_value()
          and page.locator('#am_resumen_evolucion').input_value()=='')
        page.locator('#am_motivo_egreso').fill('Texto propio conservado')
        page.locator('[data-example-for="am_motivo_egreso"]').click()
        page.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').click()
        expect(page.locator('#modalAltaMedica .mxeh-confirm')).to_be_visible()
        page.locator('#modalAltaMedica .mxeh-cancel').click()
        check(f'C28_C29_CANCEL_PRESERVES_{width}',page.locator('#am_motivo_egreso').input_value()=='Texto propio conservado')
        page.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').click()
        page.locator('#modalAltaMedica .mxeh-replace').click()
        check(f'C30_REPLACE_{width}','cuadro gastrointestinal' in page.locator('#am_motivo_egreso').input_value())
        page.screenshot(path=str(SHOT/f'capture-help-{width}x{height}.png'))
        for step in range(2,7):
            page.locator('#am_next').click()
            expect(page.locator(f'#am_step_{step}')).to_be_visible()
            if step==2:
                page.locator('#am_resumen_evolucion').fill('Curso sintético favorable')
                page.locator('#am_diagnostico_final').fill('Cuadro agudo no complicado')
            elif step==3: page.locator('#am_estado_paciente').fill('Estable')
            elif step==4: page.locator('#am_tratamiento').fill('Cuidados sintéticos')
        expect(page.locator('#am_preview')).to_contain_text('cuadro gastrointestinal')
        check(f'C33_HELP_IN_PREVIEW_{width}',
          'cuadro gastrointestinal' in page.locator('#am_preview').inner_text())
        page.screenshot(path=str(SHOT/f'preview-shown-{width}x{height}.png'))
        page.locator('#am_prev').click()
        expect(page.locator('#am_step_5')).to_be_visible()
        page.locator('input[name="am_professional_header"][value="hidden"]').check()
        page.screenshot(path=str(SHOT/f'header-selector-{width}x{height}.png'))
        page.locator('#am_next').click()
        expect(page.locator('#am_step_6')).to_be_visible()
        check(f'C22_HIDDEN_BROWSER_{width}',page.locator('#am_preview .clinical-doc-head').count()==0)
        page.screenshot(path=str(SHOT/f'preview-hidden-{width}x{height}.png'))
        check(f'VISUAL_NO_HORIZONTAL_OVERFLOW_{width}',page.evaluate(
          'document.documentElement.scrollWidth<=window.innerWidth && '
          'document.querySelector("#am_preview").scrollWidth<='
          'document.querySelector("#am_preview").clientWidth'))
        page.locator('#am_next').click()
        expect(page.locator('#am_step_7')).to_be_visible()
        canvas=page.locator('#am_signature_canvas')
        canvas.scroll_into_view_if_needed()
        box=canvas.bounding_box()
        page.mouse.move(box['x']+35,box['y']+40)
        page.mouse.down();page.mouse.move(box['x']+180,box['y']+85,steps=12);page.mouse.up()
        page.locator('#am_next').click()
        expect(page.locator('#am_step_8')).to_be_visible()
        check(f'C38_FINAL_REVIEW_{width}',
          'cuadro gastrointestinal' in page.locator('#am_final_preview').inner_text()
          and page.locator('#am_final_preview .clinical-doc-head').count()==0)
        page.screenshot(path=str(SHOT/f'final-review-{width}x{height}.png'))
        check(f'ACCESSIBILITY_NO_PAGE_ERROR_{width}',not errors)
        page.close()

    qr_ui = body('ALTA QR UI SYNTHETIC')
    qr_ui['payload']['workflow_step']=7
    qr_saved=save(api,qr_ui)
    check('QR_UI_DRAFT_CREATED',qr_saved.status==201)
    qr_ui_uuid=qr_saved.json()['data']['document_id']
    desktop=browser.new_page(viewport={'width':1440,'height':900},
      extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    desktop.goto(BASE+'/index.html?qa_tools=hide',wait_until='domcontentloaded')
    desktop.wait_for_function('typeof window.setActivePatientId === "function"')
    desktop.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    desktop.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',"
      "{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,"
      "skipUnsavedNewPatientConfirm:true,applyEntryRule:false});"
      "document.querySelector('#p-expediente').classList.remove('d-none');"
      "window.dispatchEvent(new Event('patient:selected'))}")
    desktop.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    desktop.locator('#t-consent .docvis-intents').first.locator('button').nth(1).click()
    draft_card=desktop.locator('#t-consent .vis06-row').filter(has_text='ALTA QR UI SYNTHETIC').first
    expect(draft_card).to_be_visible()
    draft_card.get_by_role('button',name='Continuar borrador').click()
    expect(desktop.locator('#am_step_7')).to_be_visible()
    desktop.locator('#am_signature_qr_open').click()
    expect(desktop.locator('#modalResponsivaSignatureQr')).to_be_visible()
    expect(desktop.locator('[data-role="rm-qr-status"]')).to_contain_text('Esperando firma')
    check('C12_C16_QR_MODAL_ALTA', 'Alta médica' in desktop.locator('#rm-qr-title').inner_text())
    desktop.screenshot(path=str(SHOT/'qr-waiting-1440x900.png'))
    mobile_url=desktop.locator('[data-role="rm-qr-link"]').get_attribute('href')
    ui_token=parse_qs(urlparse(mobile_url).query)['token'][0]
    phone=browser.new_page(viewport={'width':390,'height':844})
    phone.goto(mobile_url,wait_until='domcontentloaded')
    expect(phone.get_by_text('Revisar Alta médica')).to_be_visible()
    expect(phone.locator('#consentReviewFrame')).to_be_visible()
    check('C14_MOBILE_PAGE_EXACT', 'ALTA QR UI SYNTHETIC' in
      phone.locator('#consentReviewFrame').evaluate('el=>el.srcdoc'))
    phone.screenshot(path=str(SHOT/'mobile-review-390x844.png'))
    phone.locator('#consentContinue').click()
    expect(phone.locator('#signatureCanvas')).to_be_visible()
    box=phone.locator('#signatureCanvas').bounding_box()
    phone.mouse.move(box['x']+30,box['y']+30);phone.mouse.down()
    phone.mouse.move(box['x']+170,box['y']+70,steps=12);phone.mouse.up()
    phone.locator('#signatureSubmit').click()
    expect(phone.get_by_text('Firma recibida correctamente')).to_be_visible()
    expect(desktop.locator('[data-role="rm-qr-received-heading"]')).to_be_visible(timeout=12000)
    check('C16_DESKTOP_AUTO_RECEIPT', desktop.locator('[data-role="rm-qr-preview"]').get_attribute('src').startswith('data:image/png'))
    desktop.screenshot(path=str(SHOT/'qr-received-1440x900.png'))
    check('C17_PHONE_REPLAY_REJECTED',api.get(f'{QR}/{ui_token}/mobile-context').status in (409,410))
    desktop.locator('#modalResponsivaSignatureQr [data-bs-dismiss="modal"]').last.click()
    expect(desktop.locator('#am_signature_accepted')).to_be_visible()
    check('C20_MAIN_SIGNATURE_IMAGE',desktop.locator('#am_signature_accepted').get_attribute('src').startswith('data:image/png'))
    desktop.screenshot(path=str(SHOT/'main-signature-1440x900.png'))
    desktop.evaluate("bootstrap.Modal.getInstance(document.getElementById('modalAltaMedica')).hide()")
    expect(desktop.locator('#modalAltaMedica')).to_be_hidden()
    draft_card=desktop.locator('#t-consent .vis06-row').filter(has_text='ALTA QR UI SYNTHETIC').first
    draft_card.get_by_role('button',name='Continuar borrador').click()
    expect(desktop.locator('#am_signature_accepted')).to_be_visible()
    check('C20_RESUMED_REMOTE_SIGNATURE',desktop.locator('#am_signature_status').inner_text().startswith('Firma médica válida'))
    phone.close();desktop.close();browser.close();api.dispose()
