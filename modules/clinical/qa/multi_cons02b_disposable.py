"""MULTI-CONS02B: signature, QR, emission and mobile contract on a disposable DB."""
import base64
import copy
import hashlib
import json
import os
import struct
import subprocess
import uuid
import zlib
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
ROOT = Path(__file__).resolve().parents[3]
PATIENT = 'p_plan02ux_review'
DOC = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents'
PREVIEW = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/multimedia-preview'
QR = BASE + '/api/clinical/index.php/multimedia-qr-sessions'
VIEW = BASE + '/modules/clinical/ui/viewer.php?doctor_id=1&uuid='
SHOTS = Path('/tmp/mxmed-multi-cons02b-qa-20261010')
SHOTS.mkdir(exist_ok=True)


def check(name, result):
    if not result:
        raise AssertionError(name)
    print(f'{name}=PASS', flush=True)


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', query], text=True).strip()


def png():
    def chunk(kind, data):
        return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data) & 0xffffffff)
    width, height = 64, 24
    pixels = b''.join(b'\0' + b''.join(
        (b'\x10\x20\x30\xff' if 8 < x < 55 and abs(y - (7 + x // 8)) <= 1
         else b'\xff\xff\xff\xff') for x in range(width)) for y in range(height))
    data = (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 6, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(pixels)) + chunk(b'IEND', b''))
    return 'data:image/png;base64,' + base64.b64encode(data).decode()


def body(mode='patient'):
    return {'document_type':'consentimiento_multimedia','type':'consentimiento_multimedia',
        'title':'Consentimiento multimedia','context':{'patient_id':PATIENT},
        'actor':{'user_id':'review-user'},'payload':{'contract_version':1,'status':'draft',
        'consent':{'media_types':['photo','video'],'capture_allowed':True,
          'use_purposes':['clinical_record','clinical_followup'],'identification_scope':'identifiable',
          'authorization_scope':'specific_event','custom_scope_description':'',
          'validity':{'mode':'single_event','valid_from':'','valid_to':''},
          'context':{'clinical_event_or_procedure':'MULTI-CONS02B CASO SINTÉTICO QA',
            'anatomical_area':'Brazo','clinical_purpose_note':'Documentación clínica limitada','event_date':''}},
        'signer':{'mode':mode,'name':'Representante QA' if mode=='representative' else '',
          'character':'Tutor' if mode=='representative' else '',
          'relationship':'Madre' if mode=='representative' else '',
          'authority':'Representación verificada para esta atención' if mode=='representative' else ''},
        'patient_snapshot':{'id':PATIENT,'full_name':'Paciente sintético QA','age':'36','sex':'Femenino'},
        'actor_snapshot':{'user_id':'review-user','full_name':'Dra. QA','license':'QA123',
          'specialty':'QA','facility':'Consultorio QA','place':'México'},
        'presentation':{'version':1,'professional_header':'shown'},
        'document_date':'2026-10-10','signatures':{'signer':None,'doctor':None}}}


def fingerprint(data):
    return subprocess.check_output(['php','-r',
      'require "api/_lib/clinical_multimedia_consent_binding.php";'
      'echo clinical_multimedia_binding_fingerprint(json_decode(file_get_contents("php://stdin"),true));'],
      input=json.dumps(data,ensure_ascii=False),text=True,cwd=ROOT).strip()


def authority(data, role):
    if role == 'doctor':
        return '1|review-user'
    signer = data['payload']['signer']
    if signer['mode'] == 'patient':
        return json.dumps(['patient',PATIENT,'Paciente sintético QA'],ensure_ascii=False,separators=(',',':'))
    return json.dumps(['representative',signer['name'],signer['character'],
      signer['relationship'],signer['authority']],ensure_ascii=False,separators=(',',':'))


def sign(data,role,source='local_canvas'):
    image = png()
    name = data['payload']['actor_snapshot']['full_name'] if role=='doctor' else (
      data['payload']['patient_snapshot']['full_name'] if data['payload']['signer']['mode']=='patient'
      else data['payload']['signer']['name'])
    entry = {'type':'drawn','role':role,'source':source,'image_data':image,
      'signer_name':name,'signed_at':'2026-10-10T12:00:00Z'}
    entry['binding'] = {'version':1,'document_type':'consentimiento_multimedia',
      'document_uuid':data['draft_ref'],'document_version':data['expected_version'],
      'role':role,'source':source,'authority':authority(data,role),
      'content_fingerprint':fingerprint(data),
      'artifact_digest':hashlib.sha256(base64.b64decode(image.split(',',1)[1])).hexdigest(),
      'applied_at':'2026-10-10T12:00:00Z'}
    data['payload']['signatures'][role] = entry
    return data


def save(api,data):
    return api.post(DOC,data=data,headers={'Idempotency-Key':'mmc-b-'+str(uuid.uuid4())})


def draft(api,mode='patient'):
    data=body(mode)
    response=save(api,data)
    check('DRAFT_'+mode.upper(),response.status==201)
    data['draft_ref']=response.json()['data']['document_id']
    data['expected_version']=int(response.json()['data']['document']['version'])
    return data


migration=ROOT/'modules/clinical/db/migrations/2026_10_11_37_multimedia_qr_sessions.sql'
for _ in range(2):
    subprocess.run(['mysql',DB],input=migration.read_bytes(),check=True)
check('MIGRATION_TWICE',sql("SHOW TABLES LIKE 'clinical_multimedia_qr_sessions'")=='clinical_multimedia_qr_sessions')

with sync_playwright() as pw:
    api=pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    unsigned=body()
    before=sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_multimedia'")
    preview=api.post(PREVIEW,data=unsigned)
    check('E02_PREVIEW_ZERO_ROWS',preview.status==200 and before=='0' and
      sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_multimedia'")=='0')
    unsigned['payload']['status']='generated'
    check('S05_S06_EMIT_NO_SIG_BLOCKED',save(api,unsigned).status==400)
    unsigned['payload']['status']='draft'
    data=draft(api)
    check('E01_DRAFT_WITHOUT_SIGNATURE',data['expected_version']==1)

    browser=pw.chromium.launch(headless=True)
    page=browser.new_page()
    page.goto(BASE+'/index.html',wait_until='domcontentloaded')
    page.wait_for_function('!!window.mxmedMultimediaConsentBinding')
    js_hash=page.evaluate('(body)=>window.mxmedMultimediaConsentBinding.hash(body)',data)
    check('PHP_JS_FINGERPRINT_PARITY',fingerprint(data)==js_hash)
    for key, change in [('media_types',lambda x:x['payload']['consent'].update(media_types=['audio'])),
      ('purpose',lambda x:x['payload']['consent'].update(use_purposes=['clinical_record'])),
      ('signer',lambda x:x['payload']['signer'].update(mode='representative',name='Rep QA',character='Tutor',relationship='Madre',authority='Acreditada')),
      ('header',lambda x:x['payload']['presentation'].update(professional_header='hidden')),
      ('context',lambda x:x['payload']['consent']['context'].update(clinical_purpose_note='Cambio'))]:
        variant=copy.deepcopy(data);change(variant)
        check('FINGERPRINT_'+key.upper(),fingerprint(variant)!=fingerprint(data) and
          fingerprint(variant)==page.evaluate('(body)=>window.mxmedMultimediaConsentBinding.hash(body)',variant))

    invalids=[('S07_WRONG_UUID',lambda x:x['payload']['signatures']['signer']['binding'].update(document_uuid=str(uuid.uuid4()))),
      ('S08_WRONG_VERSION',lambda x:x['payload']['signatures']['signer']['binding'].update(document_version=2)),
      ('S09_STALE_FINGERPRINT',lambda x:x['payload']['consent']['context'].update(clinical_purpose_note='Otro texto')),
      ('S10_WRONG_AUTHORITY',lambda x:x['payload']['signatures']['signer']['binding'].update(authority='wrong')),
      ('S11_DIGEST_MISMATCH',lambda x:x['payload']['signatures']['signer']['binding'].update(artifact_digest='0'*64)),
      ('S12_UNBOUND',lambda x:x['payload']['signatures']['signer'].pop('binding'))]
    for label,mutate in invalids:
        bad=sign(copy.deepcopy(data),'signer');mutate(bad)
        check(label,save(api,bad).status==400)
    patient=sign(copy.deepcopy(data),'signer')
    check('S01_PATIENT_SIGNATURE',save(api,patient).status==201)
    patient['payload']['status']='generated'
    check('S06_MISSING_DOCTOR_BLOCKED',save(api,patient).status==400)
    patient['payload']['status']='draft'
    both=sign(copy.deepcopy(patient),'doctor')
    saved=save(api,both)
    check('S04_DOCTOR_SIGNATURE',saved.status==201 and int(saved.json()['data']['document']['version'])==1)
    both['payload']['status']='generated'
    final_preview=api.post(PREVIEW,data=both)
    check('E04_FINAL_REVIEW_EXACT',final_preview.status==200)
    issued=save(api,both)
    check('E03_E05_EMITTED_SAME_UUID',issued.status==201 and issued.json()['data']['document_id']==data['draft_ref']
      and issued.json()['data']['document']['status']=='generated')
    frozen=issued.json()['data']['document']['content']['payload']['frozen_snapshot']['html']
    check('E04_FROZEN_PARITY',frozen==final_preview.json()['data']['html'])
    check('E06_ONE_ROW',sql(f"SELECT COUNT(*) FROM clinical_documents WHERE document_uuid='{data['draft_ref']}'")=='1')
    check('E07_VIEWER',frozen in api.get(VIEW+data['draft_ref']).text())
    check('E08_PRINT', 'window.print' in api.get(VIEW+data['draft_ref']+'&autoprint=1').text())

    rep=draft(api,'representative')
    rep=sign(rep,'signer')
    check('S02_REP_SIGNATURE',save(api,rep).status==201)
    missing=body('representative');missing['payload']['signer']['authority']=''
    check('S03_REP_IDENTITY',save(api,missing).status==400)

    qrdata=draft(api)
    for role in ('signer','doctor'):
        created=api.post(QR,data={'document_uuid':qrdata['draft_ref'],
          'document_version':qrdata['expected_version'],'role':role})
        check('Q01_Q03_CREATE_'+role.upper(),created.status==201)
        token=created.json()['data']['token']
        mobile=api.get(f'{QR}/{token}/mobile-context')
        check('Q04_EXACT_REVIEW_'+role.upper(),mobile.status==200 and
          'MULTI-CONS02B CASO SINTÉTICO QA' in mobile.json()['data']['review_html'] and
          'Versión 1' in mobile.json()['data']['review_html'])
        check('QR_REVIEW_'+role.upper(),api.post(f'{QR}/{token}/review').status==200)
        check('QR_UPLOAD_'+role.upper(),api.post(f'{QR}/{token}/signature',
          data={'signature_data':png()}).status==200)
        status=api.get(f'{QR}/{token}/status')
        check('Q09_DESKTOP_RECEIPT_'+role.upper(),status.status==200 and
          status.json()['data']['signature']['binding']['role']==role)
        check('Q07_UPLOAD_REPLAY_'+role.upper(),api.post(f'{QR}/{token}/signature',
          data={'signature_data':png()}).status in (409,410))
        qrdata['payload']['signatures'][role]=status.json()['data']['signature']
        check('Q10_SIGNATURE_STORED_'+role.upper(),save(api,qrdata).status==201)
        check('Q07_CONSUMED_'+role.upper(),api.get(f'{QR}/{token}/mobile-context').status in (409,410))
    qrdata['payload']['status']='generated'
    check('REMOTE_BOTH_EMIT',save(api,qrdata).status==201)

    stale=draft(api)
    created=api.post(QR,data={'document_uuid':stale['draft_ref'],
      'document_version':stale['expected_version'],'role':'signer'})
    token=created.json()['data']['token']
    api.post(f'{QR}/{token}/review')
    stale['payload']['consent']['context']['clinical_purpose_note']='Cambio de versión QA'
    changed=save(api,stale)
    check('Q06_CONTENT_CHANGE',changed.status==201 and changed.json()['data']['document']['version']==2)
    check('Q06_STALE_REJECT',api.post(f'{QR}/{token}/signature',
      data={'signature_data':png()}).status in (409,410))
    check('Q05_ROLE_MISMATCH',api.post(QR,data={'document_uuid':stale['draft_ref'],
      'document_version':2,'role':'patient'}).status!=201)
    expired=api.post(QR,data={'document_uuid':stale['draft_ref'],'document_version':2,'role':'signer'})
    expired_token=expired.json()['data']['token']
    sql(f"UPDATE clinical_multimedia_qr_sessions SET expires_at='2020-01-01 00:00:00' WHERE token='{expired_token}'")
    check('Q08_EXPIRED',api.post(f'{QR}/{expired_token}/signature',data={'signature_data':png()}).status==410)
    check('E10_GENERIC_BLOCKED',api.post(BASE+'/api/clinical/index.php/encounters/1016/documents',
      data={'document_type':'consentimiento_multimedia','title':'Bypass','payload':{}},
      headers={'Idempotency-Key':'mmc-generic-'+str(uuid.uuid4())}).status in (400,422))

    mobile_qr=api.post(QR,data={'document_uuid':rep['draft_ref'],
      'document_version':rep['expected_version'],'role':'signer'})
    check('Q02_REP_QR',mobile_qr.status==201)
    mobile_url=BASE+mobile_qr.json()['data']['mobile_url']
    for width,height in [(320,740),(360,800),(390,844),(414,896)]:
        phone=browser.new_page(viewport={'width':width,'height':height})
        errors=[]
        phone.on('pageerror',lambda error:errors.append(str(error)))
        phone.goto(mobile_url,wait_until='domcontentloaded')
        expect(phone.locator('#consentReview')).to_be_visible(timeout=15000)
        expect(phone.locator('#consentSigner')).to_contain_text('Representante')
        frame=phone.frame_locator('#consentReviewFrame')
        expect(frame.locator('.multimedia-review-version')).to_contain_text('Versión 1')
        top_overflow=phone.evaluate('document.documentElement.scrollWidth>innerWidth+1')
        frame_overflow=frame.locator('html').evaluate('(el)=>el.scrollWidth>el.clientWidth+1')
        check(f'MOBILE_{width}_NO_HORIZONTAL_SCROLL',not top_overflow and not frame_overflow and not errors)
        phone.screenshot(path=str(SHOTS/f'mobile-review-{width}x{height}.png'))
        phone.close()

    ui=browser.new_page(viewport={'width':1366,'height':768},
      extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    ui_errors=[]
    ui.on('pageerror',lambda error:ui_errors.append(str(error)))
    ui.goto(BASE+'/index.html?qa_tools=hide',wait_until='domcontentloaded')
    ui.wait_for_function('typeof window.setActivePatientId === "function"')
    ui.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    ui.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    card=ui.locator('[data-doc-code="consentimiento_multimedia"]')
    check('CATALOG_MULTIMEDIA_ACTIVE',card.get_attribute('data-action')=='documents-open-multimedia'
      and card.get_attribute('data-doc-entry')=='existing-flow'
      and card.locator('.docs-launcher-row-status').count()==0)
    card.evaluate('(el)=>el.click()')
    expect(ui.locator('#modalConsentimientoMultimedia')).to_be_visible(timeout=15000)
    ui.locator('[data-mmc-new]').click()
    expect(ui.locator('[data-mmc-capture]')).to_be_visible()
    check('H01_OPEN_WITHOUT_MUTATION',ui.locator('#mmc_note').input_value()=='')
    for field in ('mmc_identification','mmc_scope','mmc_validity','mmc_rep_authority'):
        if field=='mmc_rep_authority': ui.locator('#mmc_signer_mode').select_option('representative')
        ui.locator(f'[data-example-for="{field}"]').click()
        check('HELP_GUIDANCE_'+field.upper(),ui.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').count()==0)
        ui.locator('.mxeh-overlay:not([hidden]) .mxeh-close').click()
    ui.locator('[data-mmc-capture] details').evaluate('(node)=>node.open=true')
    ui.locator('[data-example-for="mmc_note"]').click()
    check('H07_CONTEXT_NO_AUTO_INSERT',ui.locator('#mmc_note').input_value()=='')
    ui.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').click()
    check('H07_CONTEXT_INSERTED','Autorización para obtener fotografías' in ui.locator('#mmc_note').input_value())
    ui.locator('#mmc_note').fill('Texto propio')
    ui.locator('[data-example-for="mmc_note"]').click()
    ui.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').click()
    expect(ui.locator('#modalConsentimientoMultimedia .mxeh-confirm')).to_be_visible()
    ui.locator('#modalConsentimientoMultimedia .mxeh-cancel').click()
    check('H09_CANCEL_PRESERVES',ui.locator('#mmc_note').input_value()=='Texto propio')
    ui.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').click()
    ui.locator('#modalConsentimientoMultimedia .mxeh-replace').click()
    check('H10_REPLACE_EXPLICIT','Autorización para obtener fotografías' in ui.locator('#mmc_note').input_value())
    ui.screenshot(path=str(SHOTS/'desktop-help-1366x768.png'))
    ui.locator('input[name="mmc_media"][value="photo"]').check()
    ui.locator('[data-mmc-capture-allowed]').check()
    ui.locator('input[name="mmc_purpose"][value="clinical_record"]').check()
    ui.locator('#mmc_identification').select_option('identifiable')
    ui.locator('#mmc_scope').select_option('specific_event')
    ui.locator('#mmc_validity').select_option('single_event')
    ui.locator('#mmc_rep_name').fill('Representante UI QA')
    ui.locator('#mmc_rep_character').fill('Tutor')
    ui.locator('#mmc_rep_relationship').fill('Madre')
    ui.locator('#mmc_rep_authority').fill('Representación comprobada para esta atención')
    ui.locator('[data-mmc-preview]').click()
    expect(ui.locator('[data-mmc-review]')).to_be_visible(timeout=15000)
    check('DESKTOP_REVIEW_CONTENT','Representante UI QA' in ui.locator('[data-mmc-review-html]').inner_text())
    ui.locator('[data-mmc-continue]').click()
    expect(ui.locator('[data-mmc-signatures]')).to_be_visible(timeout=15000)
    check('DESKTOP_CANONICAL_DRAFT',ui.locator('[data-mmc-status]').inner_text().startswith('Borrador guardado'))
    # The disposable gate serves HTTP loopback. Adapt only the displayed link so
    # the desktop HTTPS requirement can be exercised without exposing a phone URL.
    def visual_qr_route(route):
        response=route.fetch()
        payload=response.json()
        if response.ok and payload.get('ok') and payload.get('data',{}).get('mobile_url'):
            from urllib.parse import urlparse
            original=urlparse(payload['data']['mobile_url'])
            payload['data']['mobile_url']='https://127.0.0.1'+original.path+('?' + original.query if original.query else '')
        route.fulfill(response=response,json=payload)
    ui.route('**/api/clinical/index.php/multimedia-qr-sessions',visual_qr_route)
    ui.locator('[data-mmc-qr="signer"]').click()
    qr_modal=ui.locator('#modalMultimediaSignatureQr')
    expect(qr_modal).to_be_visible(timeout=15000)
    expect(qr_modal.locator('[data-mmc-qr-status]')).to_contain_text('Esperando firma',timeout=15000)
    ui.wait_for_function('getComputedStyle(document.querySelector("#modalMultimediaSignatureQr")).opacity === "1"')
    check('QR_SHARED_HANDOFF_PRESENTATION',qr_modal.locator('.docux-capture-steps li').count()==3
      and qr_modal.locator('.docux-phone-panel').count()==1
      and qr_modal.locator('[data-mmc-qr-image] img').count()==1
      and ui.locator('[data-mmc-qr-panel]').count()==0)
    for width,height in ((1366,768),(1440,900)):
        ui.set_viewport_size({'width':width,'height':height})
        geometry=qr_modal.evaluate('el=>({top:el.querySelector(".modal-content").getBoundingClientRect().top,bottom:el.querySelector(".modal-content").getBoundingClientRect().bottom,scroll:el.querySelector(".modal-content").scrollWidth,client:el.querySelector(".modal-content").clientWidth})')
        check(f'QR_HANDOFF_{width}X{height}_FIT',geometry['top']>=0 and geometry['bottom']<=height+1
          and geometry['scroll']<=geometry['client']+1)
        ui.screenshot(path=str(SHOTS/f'desktop-qr-handoff-{width}x{height}.png'))
    qr_modal.locator('[data-bs-dismiss="modal"]').first.click()
    expect(qr_modal).to_be_hidden()
    ui.unroute('**/api/clinical/index.php/multimedia-qr-sessions',visual_qr_route)
    ui.set_viewport_size({'width':1366,'height':768})
    for role in ('signer','doctor'):
        ui.locator(f'[data-mmc-draw="{role}"]').click()
        canvas=ui.locator('[data-mmc-canvas]')
        box=canvas.bounding_box()
        ui.mouse.move(box['x']+20,box['y']+35)
        ui.mouse.down()
        ui.mouse.move(box['x']+180,box['y']+85,steps=16)
        ui.mouse.up()
        ui.locator('[data-mmc-apply]').click()
        expect(ui.locator(f'[data-mmc-signature-status="{role}"]')).to_contain_text('vinculada',timeout=15000)
    ui.locator('[data-mmc-final-review]').click()
    expect(ui.locator('[data-mmc-final]')).to_be_visible(timeout=15000)
    check('DESKTOP_FINAL_BOTH_IMAGES',ui.locator('[data-mmc-final-html] img.informe-doc-sign-image').count()==2)
    ui.screenshot(path=str(SHOTS/'desktop-final-1366x768.png'))
    ui.locator('[data-mmc-emit]').click()
    expect(ui.locator('#modalConsentimientoMultimedia')).to_be_hidden(timeout=15000)
    check('DESKTOP_EMISSION_POST_CORE',not ui_errors)
    check('UI_NO_PAGE_ERRORS',not ui_errors)
    ui.close()
    browser.close()
    print('MULTI_CONS02B_DISPOSABLE_QA=PASS',flush=True)
