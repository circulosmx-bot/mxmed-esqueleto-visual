"""DOCSEC01: explicit disposable Director only; tokens stay in memory, no HTTP traces.
Run with DOCSEC01_ARTIFACTS pointing outside the checkout. Requires the existing
Director runtime and review session. Uses the stress fixture, never clean UX data.
"""
import base64, json, os, subprocess, urllib.request, urllib.error
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

BASE = 'http://127.0.0.1:18143'
API = BASE + '/api/clinical/index.php/'
DB = 'mxmed_director_review_lon07c'
PATIENT, ENCOUNTER = 'p_plan02_review', 'enc:1015'
OUT = Path(os.environ['DOCSEC01_ARTIFACTS']); OUT.mkdir(parents=True, exist_ok=True)
REPORT, TOKENS = {}, []
IMAGE = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=')

def sql(query):
    p = subprocess.run(['mysql','-u','root','-h','localhost',DB,'-N','-B'], input=query, text=True, capture_output=True)
    assert p.returncode == 0, 'disposable SQL failed (detail withheld)'
    return p.stdout.strip()

def count(table): return int(sql('SELECT COUNT(*) FROM '+table))
def check(name, condition):
    assert condition, name
    REPORT[name] = 'PASS'; print('PASS '+name, flush=True)

def call(path='', method='GET', body=None, auth='owner'):
    headers = {'Accept':'application/json'}
    if auth: headers['Cookie'] = 'PHPSESSID=' + ('director-lon07c-review' if auth=='owner' else 'director-docsec01-foreign')
    data = None if body is None else json.dumps(body).encode()
    if data is not None: headers['Content-Type']='application/json'
    try:
        r = urllib.request.urlopen(urllib.request.Request(API+path, data=data, method=method, headers=headers))
    except urllib.error.HTTPError as e: r=e
    return r.status, json.loads(r.read())

def issue(**overrides):
    body = {'patient_id':PATIENT, 'encounter_key':ENCOUNTER, 'note_context':'nota_clinica_modal'}; body.update(overrides)
    status, data = call('note-capture-tokens','POST',body)
    assert status == 201, 'authorized issuance failed'
    token=data['data']['token']; TOKENS.append(token)
    return data['data']

def upload(token):
    # No physician session. Unknown extra scope fields must never become authority.
    boundary='docsec01-synthetic-boundary'
    pieces=[]
    for key,value in {'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','encounter_id':'1016','doctor_id':'2','summary':'DOCSEC01 synthetic capture'}.items():
        pieces.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
    pieces += [f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="docsec01.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+IMAGE+b'\r\n', f'--{boundary}--\r\n'.encode()]
    req=urllib.request.Request(API+'note-capture-tokens/'+token+'/upload?patient_id=p_plan02ux_review&encounter_key=enc%3A1016&doctor_id=2',data=b''.join(pieces),headers={'Content-Type':'multipart/form-data; boundary='+boundary},method='POST')
    try: r=urllib.request.urlopen(req)
    except urllib.error.HTTPError as e: r=e
    return r.status,json.loads(r.read())

def run():
    runtime=subprocess.check_output(['ps','eww','-p','4024'],text=True)
    assert 'MXMED_DB_NAME='+DB in runtime and 'MXMED_DB_HOST=localhost' in runtime
    assert sql('SELECT DATABASE()')==DB
    assert sql("SELECT CONCAT(patient_id,'|',doctor_id,'|',status) FROM clinical_encounters WHERE encounter_id=1015")==PATIENT+'|1|open'
    clean_before=sql("SELECT COUNT(*) FROM clinical_documents WHERE patient_id='p_plan02ux_review'")
    subprocess.run(['php','-r', 'session_save_path("/Users/circulodigital/.codex/artifacts/director-expediente-review/sessions"); session_id("director-docsec01-foreign"); session_start(); $_SESSION=["doctor_id"=>"2","user_id"=>"docsec01-synthetic"]; session_write_close();'],check=True)
    for name,auth,extra,expected in [
        ('unauthenticated issuance',None,{'actor':{'doctor_id':'1'}},401),
        ('foreign doctor issuance','foreign',{},404),
        ('patient encounter mismatch','owner',{'patient_id':'p_plan02ux_review'},403),
        ('unknown encounter','owner',{'encounter_key':'enc:999999999'},404),
        ('patient-only without active link','foreign',{'encounter_key':None},404)]:
        n=count('clinical_note_capture_tokens'); body={'patient_id':PATIENT,'encounter_key':ENCOUNTER};body.update(extra)
        status,data=call('note-capture-tokens','POST',body,auth)
        check(name,status==expected and data['data'] is None and count('clinical_note_capture_tokens')==n)
    for context in ['nota_clinica_modal','consentimiento_identidad_firmante:ine','consentimiento_firma_remota:patient','consentimiento_firma_remota:doctor']:
        d=issue(encounter_key=None,note_context=context)
        check('patient-scope '+context,d['status']=='pending')
        call('note-capture-tokens/'+d['token']+'/cancel','POST',{})
    for seconds,expected in [(None,900),(1,60),(9999,3600)]:
        d=issue(**({} if seconds is None else {'expires_in_sec':seconds}));t=d['token']
        actual=int(sql("SELECT TIMESTAMPDIFF(SECOND,created_at,expires_at) FROM clinical_note_capture_tokens WHERE token='"+t+"'"))
        check('expiry bound '+str(expected),actual==expected and len(t)==32)
        call('note-capture-tokens/'+t+'/cancel','POST',{})
    d=issue();t=d['token']
    check('authorized issuance contract',set(d)=={'token','status','expires_at','mobile_url','qr_value'})
    for auth in [None,'foreign']:
        for suffix,method,body in [('', 'GET',None),('/cancel','POST',{}),('/consume','POST',{'note_document_id':1})]:
            status,data=call('note-capture-tokens/'+t+suffix,method,body,auth)
            check('management '+str(auth)+' '+(suffix or 'status'),status==(401 if auth is None else 404) and data['data'] is None)
    before=count('clinical_documents')
    with sync_playwright() as p:
        browser=p.chromium.launch(headless=True)
        mobile=browser.new_context(viewport={'width':390,'height':844});page=mobile.new_page()
        page.goto(BASE+d['mobile_url'],wait_until='networkidle')
        check('mobile has no physician cookie',not mobile.cookies())
        page.locator('#captureFile').set_input_files({'name':'docsec01.png','mimeType':'image/png','buffer':IMAGE})
        page.locator('#captureSummary').fill('DOCSEC01 synthetic mobile image')
        with page.expect_response(lambda r:'/upload' in r.url and r.request.method=='POST') as response:
            page.locator('#captureSubmit').click()
        r=response.value;body=r.json()
        check('anonymous mobile page upload',r.status==201 and body['ok'] is True)
        expect(page.locator('#captureMsg')).to_contain_text('Imagen enviada correctamente')
        check('mobile response minimal',not(set(body['data']) & {'patient_id','doctor_id','encounter_id','encounter_key','patient','demographics','allergies','medications','appointments'}))
        mobile.close()
        # Read-only regression of Plan collector, badge, existing orders/results/upload.
        desktop=browser.new_context(viewport={'width':1440,'height':900});page=desktop.new_page()
        page.goto(BASE+'/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide',wait_until='commit')
        page.wait_for_function('document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId==="1015"',timeout=55000)
        page.locator('[data-m7-section="plan"]').click();expect(page.locator('[data-plan02b]')).to_be_visible()
        expect(page.locator('[data-plan02b-count]')).not_to_be_visible()
        page.locator('[data-m7-section="documents"]').click()
        for selector in ['[data-plan02b-collector]','[data-m7-doc-upload-form]','[data-m7-order-form]','[data-m7-result-form]']:
            expect(page.locator(selector)).to_be_visible()
        check('PLAN02BR2 collector badge orders results upload preserved',True)
        check('consultation remains OPEN',page.locator('#m7-workspace [data-m7-body]').get_attribute('data-encounter-state')=='open')
        desktop.close();browser.close()
    status,data=call('note-capture-tokens/'+t)
    doc=data['data']['document_id'];uuid=data['data']['document_uuid']
    check('canonical document binding',count('clinical_documents')==before+1 and sql(f"SELECT CONCAT(patient_id,'|',encounter_ref_id) FROM clinical_documents WHERE id={int(doc)}")==PATIENT+'|1015')
    status,docs=call('doctors/1/patients/'+PATIENT+'/documents?limit=200')
    check('canonical desktop readback',status==200 and any(x['document_uuid']==uuid for x in docs['data']['items']))
    status,_=upload(t);check('second upload rejected without duplicate',status==409 and count('clinical_documents')==before+1)
    status,_=call('note-capture-tokens/'+t+'/consume','POST',{'note_document_id':doc,'note_document_uuid':uuid})
    check('authorized desktop consume',status==200)
    d=issue();t=d['token'];before=count('clinical_documents');status,body=upload(t)
    check('mobile cannot override persisted binding',status==201 and sql("SELECT CONCAT(d.patient_id,'|',d.encounter_ref_id) FROM clinical_documents d JOIN clinical_note_capture_tokens t ON t.document_id=d.id WHERE t.token='"+t+"'")==PATIENT+'|1015' and count('clinical_documents')==before+1)
    for kind in ['expired','cancelled','unknown']:
        before=count('clinical_documents')
        if kind=='unknown': token='docsec01-unknown'
        else:
            token=issue()['token']
            if kind=='expired': sql("UPDATE clinical_note_capture_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token='"+token+"'")
            else: call('note-capture-tokens/'+token+'/cancel','POST',{})
        status,_=upload(token)
        check(kind+' upload rejected zero documents',status=={'expired':410,'cancelled':409,'unknown':404}[kind] and count('clinical_documents')==before)
    token=issue(encounter_key=None,note_context='consentimiento_firma_remota:patient')['token']
    status,_=call('note-capture-tokens/'+token+'/signature','POST',{'signature_data':'data:image/png;base64,'+base64.b64encode(IMAGE).decode(),'signer_name':'DOCSEC01 synthetic'},None)
    check('anonymous signature preserved',status==200)
    status,_=call('note-capture-tokens/'+token+'/signature','POST',{'signature_data':'data:image/png;base64,'+base64.b64encode(IMAGE).decode()},None)
    check('signature terminal rejection',status==409)
    check('clean UX unchanged',sql("SELECT COUNT(*) FROM clinical_documents WHERE patient_id='p_plan02ux_review'")==clean_before)

try:
    run()
finally:
    for token in TOKENS:
        call('note-capture-tokens/'+token+'/cancel','POST',{})
    # Existing PHP access log includes bearer paths. Sanitize only this test's tokens.
    log=Path('/Users/circulodigital/.codex/artifacts/director-expediente-review/launch-error.log')
    if log.exists():
        text=log.read_text()
        for token in TOKENS: text=text.replace(token,'[DOCSEC01_REDACTED]'.ljust(len(token), '_'))
        log.write_text(text)
    (OUT/'security-results.json').write_text(json.dumps(REPORT,indent=2)+'\n')
