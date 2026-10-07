import json
import os
import re
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

base = os.environ['FLOW_R1_QA_BASE']
db = os.environ['FLOW_R1_QA_DB']
root = Path(os.environ['FLOW_R1_QA_ROOT'])
screenshots = Path(os.environ.get('CONS_TPL02_SCREENSHOTS','/tmp/cons-tpl02-screenshots'))
screenshots.mkdir(parents=True,exist_ok=True)
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', db)
migration = Path(__file__).resolve().parents[1] / 'db/migrations/2026_10_06_29_consent_templates.sql'
def sql(query):
    return subprocess.check_output(['mysql','--raw','-N',db,'-e',query],text=True).strip()
def check(label, assertion):
    assert assertion, label
    print('PASS '+label,flush=True)
values = {
    'title':'Consentimiento para biopsia clínica',
    'procedimiento':'Se tomará una muestra\ncon técnica estéril; “exploración” y apóstrofe: O\'Brien.',
    'template_key':'procedimiento',
    'objetivo':'Confirmar diagnóstico clínico.',
    'riesgos':'Dolor, sangrado e infección.',
    'risk_comunes':'Molestia leve.',
    'risk_poco_frecuentes':'Sangrado persistente.',
    'risk_raros_graves':'Reacción alérgica grave.',
    'beneficios_esperados':'Diagnóstico oportuno.',
    'alternativas':'Observación y seguimiento.',
    'consecuencias_no_aceptar':'Retraso de diagnóstico.',
    'autorizacion_contingencias':True,
}
with sync_playwright() as pw:
    browser = pw.webkit.launch()
    page = browser.new_page(viewport={'width':1440,'height':900},extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    console=[]; pageerrors=[]; requests=[]; responses=[]
    page.on('console',lambda m: console.append({'type':m.type,'text':m.text}) if m.type=='error' else None)
    page.on('pageerror',lambda e: pageerrors.append(str(e)))
    page.on('request',lambda r: requests.append({'method':r.method,'url':r.url,'body':r.post_data}) if 'consent-templates' in r.url else None)
    page.on('response',lambda r: responses.append({'status':r.status,'url':r.url,'body':r.text()}) if 'consent-templates' in r.url else None)
    page.goto(base+'/index.html?qa_tools=hide',wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    expect(page.locator('#p-expediente')).to_have_attribute('data-patient-id','p_plan02ux_review',timeout=25000)
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentTemplateFlow')).to_be_visible()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="manage"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="create"]').click()
    expect(page.locator('#ci_template_editor_form')).to_be_visible()
    page.locator('#ci_tpl_name').fill('Plantilla clínicamente completa')
    for key,value in values.items():
        if key=='autorizacion_contingencias': page.locator('#ci_tpl_'+key).check()
        elif key=='template_key': page.locator('#ci_tpl_'+key).select_option(value)
        else: page.locator('#ci_tpl_'+key).fill(value)
    with page.expect_response(lambda r: r.request.method=='POST' and '/consent-templates' in r.url) as failed_event:
        page.locator('#modalConsentTemplateFlow [data-tpl-action="save"]').click()
    expect(page.locator('.ci-template-save-error')).to_be_visible()
    failed_post = next(item for item in requests if item['method']=='POST')
    failed_http = failed_event.value
    failed_response = {'status':failed_http.status,'body':failed_http.text()}
    payload = json.loads(failed_post['body'])
    check('T06 missing migration produces server_error', json.loads(failed_response['body'])['error']=='server_error'
          and sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_consent_templates'")=='0')
    missing_table = subprocess.run(['mysql',db,'-e','SELECT COUNT(*) FROM clinical_consent_templates'],
                                   capture_output=True,text=True)
    check('missing table SQL error captured', missing_table.returncode!=0 and '1146' in missing_table.stderr)
    check('server logged failed POST', '[500]: POST /api/clinical/index.php/doctors/1/consent-templates' in
          root.joinpath('server.log').read_text())
    check('T06 exact request whitelist', set(payload)=={'template_name','content'}
          and set(payload['content'])==set(values) and payload['content']==values)
    check('T06 input preserved on failure', page.locator('#ci_template_editor_form').is_visible()
          and page.locator('#ci_tpl_name').input_value()=='Plantilla clínicamente completa'
          and all(page.locator('#ci_tpl_'+key).is_checked() if key=='autorizacion_contingencias'
                  else page.locator('#ci_tpl_'+key).input_value()==value for key,value in values.items())
          and 'No se pudo guardar la plantilla' in page.locator('.ci-template-save-error').inner_text())
    check('T06 error visible above modal footer', page.locator('.ci-template-save-error').evaluate(
          '(el) => { const r=el.getBoundingClientRect(); return r.height >= 30 && r.top >= 0 && r.bottom <= innerHeight; }'))
    page.screenshot(path=str(screenshots/'failure-preserved-1440x900.png'))
    print('FAILED_POST='+json.dumps({'method':failed_post['method'],'route':failed_post['url'].split('/api/',1)[1],
          'status':failed_response['status'],'response':json.loads(failed_response['body'])},ensure_ascii=False),flush=True)
    with migration.open('rb') as file:
        subprocess.run(['mysql',db],stdin=file,check=True)
    page.locator('#modalConsentTemplateFlow [data-tpl-action="save"]').click()
    expect(page.locator('#modalConsentTemplateFlow').get_by_text('Plantilla guardada',exact=True)).to_be_visible()
    page.screenshot(path=str(screenshots/'success-1440x900.png'))
    check('T07 visible success', True)
    check('T01 template written once', sql('SELECT COUNT(*) FROM clinical_consent_templates')=='1'
          and sql('SELECT COUNT(*) FROM clinical_documents')=='0')
    saved = json.loads(sql('SELECT content_json FROM clinical_consent_templates LIMIT 1'))
    check('T02 accents quotes multiline exact', saved==values)
    uuid = sql('SELECT template_uuid FROM clinical_consent_templates LIMIT 1')
    page.locator('#modalConsentTemplateFlow [data-tpl-action="back"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    expect(page.locator('#modalConsentTemplateFlow').get_by_text('Plantilla clínicamente completa',exact=True)).to_be_visible()
    check('T03 reload list', True)
    page.locator('#modalConsentTemplateFlow [data-tpl-action="use"]').click()
    expect(page.locator('#modalConsentimientoInformado')).to_be_visible()
    check('T04 copied reusable fields', page.locator('#ci_title').input_value()==values['title']
          and page.locator('#ci_procedimiento').input_value()==values['procedimiento']
          and page.locator('#ci_risk_common').input_value()==values['risk_comunes'])
    page.screenshot(path=str(screenshots/'template-reused-1440x900.png'))
    check('T05 patient fields not copied', page.locator('#ci_motivo').input_value()==''
          and not page.locator('#ci_confirm_informed').is_checked()
          and page.locator('#ci_identity_files_list').locator('li').count()==0)
    page.locator('#ci_next').click()
    page.locator('#ci_save').click()
    page.wait_for_function("() => !!document.querySelector('#ci_action_feedback')?.textContent?.trim()",timeout=20000)
    check('T12 canonical draft', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft'")=='1')
    draft_payload = json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft' LIMIT 1"))
    check('T12 draft copy', draft_payload['form_snapshot']['title']==values['title']
          and draft_payload['form_snapshot']['procedimiento']==values['procedimiento']
          and 'source_template_id' not in draft_payload)
    route = base+'/api/clinical/index.php/doctors/1/consent-templates'
    api = pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    def call(method,url,body=None):
        response=api.fetch(url,method=method,data=json.dumps(body,ensure_ascii=False) if body is not None else None,
                           headers={'Content-Type':'application/json'} if body is not None else {})
        return response.status,response.json()
    updated={**values,'title':'Biopsia clínica actualizada'}
    edit=call('PUT',route+'/'+uuid,{'template_name':'Plantilla actualizada','content':updated,'expected_version':1})
    check('T08 edit', edit[0]==200 and edit[1]['data']['version']==2)
    check('T13 existing draft unchanged', json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft' LIMIT 1"))==draft_payload)
    duplicated=call('POST',route+'/'+uuid+'/duplicate',{})
    check('T09 duplicate', duplicated[0]==201 and duplicated[1]['data']['uuid']!=uuid and duplicated[1]['data']['content']==updated)
    archived=call('POST',route+'/'+uuid+'/archive',{'expected_version':2})
    check('T10 archive', archived[0]==200 and archived[1]['data']['status']=='archived'
          and len(call('GET',route)[1]['data'])==1)
    subprocess.run(['php','-d',f'session.save_path={root / "sessions"}','-r',
                    'session_id("cons-tpl-other");session_start();$_SESSION["doctor_id"]="2";$_SESSION["user_id"]="other-doctor";session_write_close();'],check=True)
    other=pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=cons-tpl-other'})
    check('T11 physician isolation', other.get(base+'/api/clinical/index.php/doctors/2/consent-templates').json()['data']==[]
          and other.get(base+'/api/clinical/index.php/doctors/2/consent-templates/'+uuid).status==404
          and other.get(route).status==403)
    check('validation and encoding', call('POST',route,{'template_name':' ','content':values})[0]==400
          and call('POST',route,{'template_name':'bad','content':{**values,'title':'x'*181}})[0]==400
          and call('POST',route,{'template_name':'bad','content':{**values,'riesgos':None}})[0]==400
          and call('POST',route,{'template_name':'bad','content':{**values,'patient_id':'forbidden'}})[0]==400)
    check('M6 legacy writer unchanged', call('POST',base+'/api/clinical-documents.php?action=save',
          {'context':{'patient_id':'p_plan02ux_review'}})[0]==409)
    check('no browser page errors',pageerrors==[])
    print('QA_ROWS='+json.dumps({'templates':sql('SELECT COUNT(*) FROM clinical_consent_templates'),
          'documents':sql('SELECT COUNT(*) FROM clinical_documents')},ensure_ascii=False),flush=True)
    print('CONSOLE_ERROR_COUNT='+str(len(console)),flush=True)
    print('SCREENSHOTS='+str(screenshots),flush=True)
    browser.close()
