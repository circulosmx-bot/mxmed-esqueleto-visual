"""REC-TPL02 API, UI and safety proof on the standard disposable clinical database."""
import json
import os
import re
import subprocess
from pathlib import Path
from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
ROOT = Path(os.environ['FLOW_R1_QA_ROOT'])
OUT = Path(os.environ.get('REC_TPL02_SCREENSHOTS','/tmp/rec-tpl02-screenshots'))
OUT.mkdir(parents=True,exist_ok=True)
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}',DB)
MIGRATION = Path(__file__).resolve().parents[1]/'db/migrations/2026_10_13_41_prescription_templates.sql'
for _ in range(2):
    subprocess.run(['mysql',DB],stdin=MIGRATION.open('rb'),check=True)

def sql(query):
    return subprocess.check_output(['mysql','--raw','-N',DB,'-e',query],text=True).strip()
def check(code,condition):
    assert condition,code
    print(f'{code}=PASS',flush=True)

MED={'name':'Losartán','presentation':'Tableta 50 mg','dose':'1 tableta','route':'Oral',
     'frequency':'Cada 24 horas','duration':'30 días','quantity':'30 tabletas','instructions':'Tomar a la misma hora'}
CONTENT={'medications':[MED],'diagnostic_context':{'mode':'diagnostico','text':'Hipertensión — control habitual'},
         'observations':'Vigilar presión arterial.','professional_header':'shown'}
FORBIDDEN=['patient_id','patient_name','age','sex','encounter_id','encounter_ref_id','appointment_id','allergies',
           'current_medications','active_problems','patient_snapshot','signature','qr','fingerprint','document_uuid',
           'issued_version','private_notes','idempotency_key']

with sync_playwright() as pw:
    browser=pw.webkit.launch()
    page=browser.new_page(viewport={'width':1440,'height':900},extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    errors=[]
    page.on('pageerror',lambda error:errors.append(str(error)))
    active={'uuid':'11111111-1111-4111-8111-111111111111','template_name':'Hipertensión — manejo habitual',
            'content':CONTENT,'status':'active','version':2,'created_at':'2026-10-13 10:00:00','updated_at':'2026-10-13 12:00:00'}
    archived={'uuid':'22222222-2222-4222-8222-222222222222','template_name':'Plantilla archivada',
              'content':CONTENT,'status':'archived','version':2,'created_at':'2026-10-13 09:00:00','updated_at':'2026-10-13 11:00:00'}
    records=[active,archived]
    preview_payloads=[]
    def mocked_clinical(route):
        request=route.request
        if 'prescription-templates' in request.url:
            path=request.url.split('prescription-templates',1)[1].split('?',1)[0].strip('/')
            parts=path.split('/') if path else []
            payload=json.loads(request.post_data or '{}')
            record=next((item for item in records if parts and item['uuid']==parts[0]),None)
            status=200
            if request.method=='GET': data=record if parts else records
            elif request.method=='POST' and not parts:
                data={'uuid':f'33333333-3333-4333-8333-{len(records)+1:012d}','template_name':payload['template_name'],
                      'content':payload['content'],'status':'active','version':1,'created_at':'2026-10-13 13:00:00','updated_at':'2026-10-13 13:00:00'}
                records.append(data);status=201
            elif request.method=='PUT' and record:
                record.update(template_name=payload['template_name'],content=payload['content'],version=record['version']+1,updated_at='2026-10-13 14:00:00');data=record
            elif request.method=='POST' and record and len(parts)>1 and parts[1]=='duplicate':
                data={**record,'uuid':f'44444444-4444-4444-8444-{len(records)+1:012d}','template_name':'Copia de '+record['template_name'],'version':1}
                records.append(data);status=201
            elif request.method=='POST' and record and len(parts)>1 and parts[1]=='archive':
                record.update(status='archived',version=record['version']+1);data=record
            elif request.method=='DELETE' and record:
                records.remove(record);data={'uuid':record['uuid']}
            else:
                route.fulfill(status=404,content_type='application/json',body=json.dumps({'ok':False,'error':'not_found'}));return
            route.fulfill(status=status,content_type='application/json',body=json.dumps({'ok':True,'data':data}))
            return
        if '/prescription-context' in request.url:
            context={'revision':'qa-revision-1','patient':{'patient_id':'p_plan02ux_review','name':'Marina QA'},
              'allergies':{'state':'PRESENT','items':[{'substance':'Penicilina','reaction':'Exantema','validation_state':'CLINICIAN_REVIEWED'}]},
              'medications':{'state':'PRESENT','items':[{'name':'Metformina','dose':'500 mg','route':'Oral','frequency':'Cada 12 horas'}]},
              'problems':{'state':'PRESENT','items':[{'label':'Hipertensión arterial'}]},
              'encounter':{'state':'OPEN','encounter_id':'1016','motive':'Control habitual'}}
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':context}))
            return
        if '/prescriptions/preview' in request.url and request.method=='POST':
            preview_payloads.append(json.loads(request.post_data or '{}'))
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'html':'<article data-qa-preview>Vista previa QA</article>'}}))
            return
        if '/prescriptions/drafts' in request.url:
            route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'drafts':[]}}))
            return
        route.continue_()
    page.route('**/api/clinical/index.php/**',mocked_clinical)
    page.goto(BASE+'/index.html?qa_tools=hide',wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    expect(page.locator('#p-expediente')).to_have_attribute('data-patient-id','p_plan02ux_review',timeout=25000)
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-tratamiento"]').click()
    page.locator('#t-tratamiento [data-rx-action="emit"]').click()
    expect(page.locator('[data-rx-editor-workspace]')).to_be_visible()
    page.locator('[data-rx-host-next]').click()
    expect(page.locator('#ne_rx_use_template')).to_be_visible()
    page.screenshot(path=str(OUT/'stage2-empty-1440x900.png'))
    check('A01_STAGE1_FIRST',page.locator('[data-rx-host-step="1"]').get_attribute('class') is not None)
    check('A02_STAGE2_ENTRY',page.locator('[data-rx-editor-workspace]').get_attribute('data-rx-stage')=='2')
    check('STAGE2_FIT',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))

    page.locator('#ne_rx_use_template').click()
    expect(page.locator('#modalPrescriptionTemplateFlow')).to_be_visible()
    expect(page.locator('#rx_tpl_search')).to_be_visible()
    page.screenshot(path=str(OUT/'template-library-1440x900.png'))
    page.locator('#rx_tpl_search').fill('hipertension')
    check('T03_SEARCH',page.locator('#rx_tpl_selector_list .ci-template-row').count()==1)
    row=page.locator('#rx_tpl_selector_list .ci-template-row').first
    row.locator('[data-rx-tpl-action="use"]').click()
    expect(page.locator('#modalPrescriptionTemplateFlow')).to_be_hidden()
    check('A03_APPLY_EDITABLE',page.locator('[data-ne-rx="name"]').input_value()=='Losartán')
    check('A04_PATIENT_PRESERVED',page.locator('#p-expediente').get_attribute('data-patient-id')=='p_plan02ux_review')
    expect(page.locator('[data-rx-template-safety]').get_by_text(re.compile('Penicilina'))).to_be_visible()
    check('A05_SAFETY_VISIBLE',True)
    page.locator('[data-ne-rx-edit]').click()
    page.locator('[data-ne-rx="dose"]').fill('2 tabletas')
    check('A07_EDIT_APPLIED',page.locator('[data-ne-rx="dose"]').input_value()=='2 tabletas')
    page.evaluate("document.querySelectorAll('.ne-rx-suggest').forEach(node=>node.classList.add('d-none'))")
    page.screenshot(path=str(OUT/'applied-template-1440x900.png'))
    check('S09_NO_PRIVATE_NOTES',page.locator('[data-ne-rx="private_notes"]').input_value()=='')
    page.evaluate("window.mxmedPrescriptionSelectedEncounterKey='enc:1016'")
    check('A06_ENCOUNTER_VARIABLE_UNTOUCHED',page.evaluate("window.mxmedPrescriptionSelectedEncounterKey==='enc:1016'"))
    page.locator('[data-rx-host-next]').click()
    expect(page.locator('[data-qa-preview]')).to_be_visible()
    check('A08_PREVIEW_EDITED_RESULT',preview_payloads[-1]['payload']['prescription']['items'][0]['dose']=='2 tabletas')
    check('A09_NO_TEMPLATE_IN_PREVIEW','template_uuid' not in json.dumps(preview_payloads[-1]) and 'template_version' not in json.dumps(preview_payloads[-1]))
    check('A10_SIGNATURE_REQUIRED',page.locator('#ne_rx_sign').is_hidden())
    check('A11_FINAL_REVIEW_REQUIRED',page.locator('#ne_rx_final').is_hidden())
    page.locator('[data-rx-host-prev]').click()
    expect(page.locator('#ne_rx_save_template')).to_be_visible()
    page.locator('#ne_rx_use_template').click()
    page.locator('#rx_tpl_selector_list .ci-template-row').filter(has_text='Hipertensión — manejo habitual').locator('[data-rx-tpl-action="use"]').click()
    expect(page.locator('#rx_tpl_modal_title')).to_have_text('USAR PLANTILLA')
    check('EXISTING_CONTENT_CONFIRMATION',page.get_by_text(re.compile('reemplazará los medicamentos capturados')).is_visible())
    page.locator('[data-rx-tpl-action="library"]').click()
    page.locator('#modalPrescriptionTemplateFlow [data-rx-tpl-action="close"]').click()
    expect(page.locator('#modalPrescriptionTemplateFlow')).to_be_hidden()

    page.locator('#ne_rx_save_template').click()
    expect(page.locator('#rx_template_save_current')).to_be_visible()
    check('SAVE_DEFAULTS_SAFE',not page.locator('[name="include_diagnostic"]').is_checked()
          and not page.locator('[name="include_observations"]').is_checked()
          and not page.locator('[name="include_header"]').is_checked())
    page.screenshot(path=str(OUT/'save-as-template-dialog-1440x900.png'))
    page.locator('#modalPrescriptionTemplateFlow [data-rx-tpl-action="close"]').click()
    expect(page.locator('#modalPrescriptionTemplateFlow')).to_be_hidden()

    page.locator('#ne_rx_use_template').click()
    expect(page.locator('#rx_tpl_selector_list')).to_be_visible()
    managed=page.locator('#rx_tpl_selector_list .ci-template-row').filter(has_text='Hipertensión — manejo habitual')
    managed.locator('[data-rx-tpl-action="more"]').click()
    expect(managed.locator('.ci-template-row__more-menu')).to_be_visible()
    page.screenshot(path=str(OUT/'manage-archive-delete-1440x900.png'))
    check('T11_DELETE_CONFIRMATION',managed.locator('[data-rx-tpl-action="delete"]').is_visible())

    page.set_viewport_size({'width':1366,'height':768})
    check('1366_LIBRARY_FIT',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    page.screenshot(path=str(OUT/'library-1366x768.png'))
    page.set_viewport_size({'width':820,'height':1180})
    check('TABLET_LIBRARY_FIT',page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    page.screenshot(path=str(OUT/'library-820x1180.png'))
    check('ACCESSIBLE_MODAL',page.locator('#modalPrescriptionTemplateFlow').get_attribute('aria-labelledby')=='rx_tpl_modal_title'
          and page.locator('label[for="rx_tpl_search"]').count()==1)
    page.set_viewport_size({'width':1440,'height':900})
    page.locator('[data-rx-tpl-action="create"]').click()
    expect(page.locator('#rx_template_editor_form')).to_be_visible()
    page.locator('#rx_template_editor_form [name="template_name"]').fill('Plantilla UI QA')
    page.locator('[data-rx-tpl-action="save-editor"]').click()
    expect(page.get_by_text('Plantilla UI QA',exact=True)).to_be_visible()
    check('T01_UI_CREATE',any(item['template_name']=='Plantilla UI QA' for item in records))
    ui_row=page.locator('#rx_tpl_selector_list .ci-template-row').filter(has_text='Plantilla UI QA')
    ui_row.locator('[data-rx-tpl-action="edit"]').click()
    page.locator('#rx_template_editor_form [name="template_name"]').fill('Plantilla UI editada')
    page.locator('[data-rx-tpl-action="save-editor"]').click()
    expect(page.get_by_text('Plantilla UI editada',exact=True)).to_be_visible()
    check('T05_UI_EDIT',any(item['template_name']=='Plantilla UI editada' and item['version']==2 for item in records))
    ui_row=page.locator('#rx_tpl_selector_list .ci-template-row').filter(has_text='Plantilla UI editada')
    ui_row.locator('[data-rx-tpl-action="more"]').click()
    ui_row.locator('[data-rx-tpl-action="duplicate"]').click()
    expect(page.get_by_text('Copia de Plantilla UI editada',exact=True)).to_be_visible()
    check('T07_UI_DUPLICATE',any(item['template_name']=='Copia de Plantilla UI editada' for item in records))
    copy_row=page.locator('#rx_tpl_selector_list .ci-template-row').filter(has_text='Copia de Plantilla UI editada')
    copy_row.locator('[data-rx-tpl-action="more"]').click()
    copy_row.locator('[data-rx-tpl-action="archive"]').click()
    expect(page.get_by_text('Copia de Plantilla UI editada',exact=True)).to_have_count(0)
    check('T08_UI_ARCHIVE',next(item for item in records if item['template_name']=='Copia de Plantilla UI editada')['status']=='archived')
    page.locator('[data-rx-tpl-action="archived-toggle"]').click()
    copy_row=page.locator('#rx_tpl_selector_list .ci-template-row').filter(has_text='Copia de Plantilla UI editada')
    expect(copy_row).to_be_visible()
    copy_row.locator('[data-rx-tpl-action="more"]').click()
    copy_row.locator('[data-rx-tpl-action="delete"]').click()
    expect(page.locator('#rx_tpl_delete_confirm')).to_be_visible()
    check('T11_DELETE_CONFIRMATION',page.locator('[data-rx-tpl-action="confirm-delete"]').is_disabled())
    page.locator('#rx_tpl_delete_confirm').fill('ELIMINAR')
    page.locator('[data-rx-tpl-action="confirm-delete"]').click()
    expect(page.get_by_text('Copia de Plantilla UI editada',exact=True)).to_have_count(0)
    check('T12_UI_DELETE_ONLY_TEMPLATE',all(item['template_name']!='Copia de Plantilla UI editada' for item in records))
    page.locator('#modalPrescriptionTemplateFlow .btn-close').click()
    expect(page.locator('#modalPrescriptionTemplateFlow')).to_be_hidden()
    for caller,code in [('patient','PATIENT_HOST'),('global','GLOBAL_HOST'),('consultation','CONSULTA_HOST'),('activity','ACTIVIDAD_HOST')]:
        page.evaluate("""caller=>{window.mxmedPrescriptionHost?.closeEmbedded();let mount=document.querySelector('[data-rec-tpl02-host]');if(!mount){mount=document.createElement('div');mount.dataset.recTpl02Host='';document.querySelector('#p-expediente').append(mount);}mount.classList.remove('d-none');window.mxmedOpenCanonicalPrescription({host:'embedded',caller,workspaceMount:mount,patient:{name:'Marina QA'}});window.mxmedPrescriptionHost.setStage(2,false);}""",caller)
        expect(page.locator('#ne_rx_use_template')).to_be_visible()
        check(code,page.locator('[data-rx-editor-workspace]').get_attribute('data-rx-caller')==caller)
    page.evaluate("window.mxmedPrescriptionHost?.closeEmbedded()")
    check('NO_JS_ERRORS',not errors)
    browser.close()

    check('NO_CLINICAL_DOCUMENT_WRITES',sql('SELECT COUNT(*) FROM clinical_documents')=='0')
    print('REC-TPL02_BROWSER=PASS',flush=True)
