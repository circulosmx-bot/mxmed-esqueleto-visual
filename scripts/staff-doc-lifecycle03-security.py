#!/usr/bin/env python3
"""Focused security/recovery checks against the verified owned lifecycle preview."""
import pathlib,importlib.util,json,sys,uuid,base64,pymysql,time
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
review=module('lc_security_review',ROOT/'scripts/staff-doc-lifecycle03-review.py');owned,approval=review.owned_configuration(sys.argv[1]);info,origin,expiry,record=review.verified_preview(owned,approval)
native=module('lc_security_native',ROOT/'scripts/ase-advisor-workspace-review.py');helper=module('lc_security_helper',ROOT/'scripts/ase-adm02-oper09-browser.py')
connection=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',autocommit=True);cursor=connection.cursor();cursor.execute('SHOW DATABASES');databases=[x[0] for x in cursor.fetchall() if x[0].startswith('mxmed_gate4d_preview_aseadm02imp01_')];assert len(databases)==1;connection.select_db(databases[0])
def sql(query,args=()):cursor.execute(query,args);return cursor.fetchall()
results=[]
def check(name,condition):
 assert condition,name
 results.append({'name':name,'pass':True});print('PASS '+name,flush=True)
def good(p,action,body={}):
 r=helper.wf(p,action,body);assert r['status']==200,(action,r['status']);return r['body']
martha=json.loads((owned/'staff06-martha.json').read_text());cid=martha['case']
with sync_playwright() as pw:
 b=pw.chromium.launch();ctx=b.new_context(ignore_https_errors=True);p=ctx.new_page()
 def login():native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
 login();p.get_by_role('button',name='Cerrar sesión',exact=True).click();expect(p.locator('#login-email')).to_be_visible();login();check('Actual Director login logout relogin',True)
 p.locator('[data-admission-case="'+cid+'"]').click();p.locator('#identity-evidence summary').click();p.locator('#advisor-detail [data-consult-set]').first.click();p.locator('#staff-password').fill('incorrect-synthetic-password');p.locator('#staff-challenge').click();expect(p.locator('#staff-stepup-status')).to_contain_text('No se autorizó');check('Incorrect password cannot open protected images',p.locator('#staff-evidence img').count()==0)
 p.locator('#staff-password').fill((owned/'fixture-password').read_text().strip());p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible();p.locator('#staff-code').fill('12345');p.locator('#staff-execute').click();expect(p.locator('#staff-stepup-status')).to_contain_text('No se validó');check('Invalid MFA cannot open protected images',not p.locator('#staff-evidence').is_visible());p.locator('#cancel-staff-stepup').click()
 v=good(p,'view',{'case_id':cid});old=v['sets'][1]
 for action in ['ACK','ACCEPT']:
  check('Historical '+action+' cannot target obsolete set',helper.wf(p,'challenge',{'case_id':cid,'set_id':old['set_id'],'action':action,'reason':'Revisión sintética de versión histórica','password':(owned/'fixture-password').read_text().strip()})['status']!=200)
 def finish():
  p.locator('#staff-password').fill((owned/'fixture-password').read_text().strip());p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible()
  last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
  while last is not None and int(time.time())//30<=last:p.wait_for_timeout(300)
  p.locator('#staff-code').fill(helper.review.totp(owned));p.locator('#staff-execute').click();expect(p.locator('#staff-stepup')).not_to_be_visible(timeout=20000)
 p.locator('#advisor-detail [data-consult-set]').first.click();finish();expect(p.locator('#staff-evidence')).to_be_visible();p.locator('#ack-staff-evidence').click();finish()
 check('Active replacement obtains its own explicit acknowledgement',good(p,'view',{'case_id':cid})['document_ack_current'])
 p.locator('#identity-evidence summary').click() if not p.locator('#identity-evidence').evaluate('(e)=>e.open') else None
 p.locator('#advisor-detail [data-review-action=ACCEPT]').click();finish()
 check('Active replacement can receive separate acceptance without new admission',good(p,'view',{'case_id':cid})['review_current'] and good(p,'view',{'case_id':cid})['review']['decision']=='ACCEPT')
 ap=b.new_context(ignore_https_errors=True).new_page();native.login(ap,origin,martha['email'],(owned/'applicant-password').read_text().strip(),'/staff-admission.php');v=good(ap,'view',{'case_id':cid});current=v['sets'][0]
 check('Applicant does not acquire reviewer authority for own evidence',not v['can_consult'] and helper.wf(ap,'challenge',{'case_id':cid,'set_id':current['set_id'],'action':'READ','reason':'Consulta sintética propia','password':(owned/'applicant-password').read_text().strip()})['status']!=200)
 documents=[]
 for d in json.loads(current['manifest']):documents.append({'document_id':d['document_id'],'kind':d['kind'],'justification':d['justification'],'pages':[{'side':x['side'],'asset_id':x['asset_id']} for x in d['pages']]})
 body={'case_id':cid,'expected_version':int(current['case_version']),'operation_id':str(uuid.UUID(current['set_id'][4:])),'documents':documents,'legal_reference':v['legal_reference'],'accepted':True}
 count=sql('SELECT COUNT(*) FROM auth_staff_document_sets WHERE case_id=%s',(cid,))[0][0];r=good(ap,'submit',body)
 check('Exact submission retry returns same set without duplication',r['set_id']==current['set_id'] and sql('SELECT COUNT(*) FROM auth_staff_document_sets WHERE case_id=%s',(cid,))[0][0]==count)
 asset=sql('SELECT a.asset_id,a.asset_version,a.case_version,a.purpose,a.source_mime,o.operation_id FROM auth_staff_media_assets a JOIN auth_staff_media_operations o USING(asset_id) WHERE a.case_id=%s AND a.purpose=%s ORDER BY asset_version DESC LIMIT 1',(cid,'STAFF_IDENTITY_EVIDENCE'))[0]
 count=sql('SELECT COUNT(*) FROM auth_staff_media_assets WHERE case_id=%s',(cid,))[0][0]
 meta={'operation_id':asset[5],'case_id':cid,'expected_case_version':int(asset[2]),'expected_asset_version':int(asset[1])-1,'purpose':asset[3],'declared_mime':asset[4]};upload={'metadata':meta,'base64':base64.b64encode((owned/'synthetic.png').read_bytes()).decode()}
 r=good(ap,'upload',upload);receipt=good(ap,'media_result',{'case_id':cid,'operation_id':asset[5],'purpose':asset[3]})
 check('Media replay and reconciliation retain exact committed asset',r['asset_id']==asset[0] and receipt['reconciliation']=='COMMITTED_AVAILABLE' and sql('SELECT COUNT(*) FROM auth_staff_media_assets WHERE case_id=%s',(cid,))[0][0]==count)
 upload['metadata']['operation_id']=str(uuid.uuid4());upload['metadata']['expected_asset_version']=int(asset[1]);check('Director cannot impersonate applicant upload',helper.wf(p,'upload',upload)['status']!=200)
 check('Director cannot submit applicant document set',helper.wf(p,'submit',body)['status']!=200)
 check('Staff remains active with zero grants and one admission',sql('SELECT status FROM internal_staff WHERE account_id=%s',(martha['account'],))[0][0]=='ACTIVE' and sql('SELECT COUNT(*) FROM internal_operator_grants WHERE account_id=%s',(martha['account'],))[0][0]==0 and sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(cid,))[0][0]==1)
 ap.locator('#wf-cases').select_option(cid);expect(ap.locator('#wf-review-state')).to_contain_text('Admisión registrada');ap.locator('#wf-update-mode').select_option('ADD');ap.locator('#wf-kind').select_option('PASSPORT');ap.locator('#wf-justification').fill('Documento adicional sintético justificado para el ensayo del expediente.');ap.locator('#wf-front').set_input_files(str(owned/'synthetic.png'));ap.locator('#wf-add-document').click();expect(ap.locator('#wf-documents')).to_contain_text('PASSPORT: 1 cara(s) añadidas');ap.locator('#wf-consent').check();ap.locator('#wf-submit').click();expect(ap.locator('#wf-review-state')).to_contain_text('Nueva entrega pendiente de revisión')
 v=good(p,'view',{'case_id':cid});docs=json.loads(v['sets'][0]['manifest'])
 check('Justified added type preserves unchanged INE logical version',len(docs)==2 and docs[0]['document_id']==documents[0]['document_id'] and docs[0]['document_version']==json.loads(current['manifest'])[0]['document_version'] and docs[1]['kind']=='PASSPORT' and not v['review_current'] and not v['document_ack_current'])
 p.reload();p.locator('[data-admission-case="'+cid+'"]').click();p.locator('#identity-evidence summary').click();p.locator('#advisor-detail [data-consult-set]').first.click();finish();expect(p.locator('.evidence-page-grid img')).to_have_count(3);expect(p.locator('.evidence-page-grid figcaption')).to_have_text(['Anverso','Reverso','Página biográfica']);check('Director UI renders all preserved and added authorized pages',p.locator('.evidence-page-grid img').evaluate_all('(xs)=>xs.every(x=>x.complete&&x.naturalWidth>0)'));p.screenshot(path=str(ROOT/'docs/evidence/staff-doc-lifecycle03/screens/current-ine-passport.png'));p.locator('#close-staff-evidence').click()
 check('Addition preserves one active membership and zero grants',sql('SELECT status FROM internal_staff WHERE account_id=%s',(martha['account'],))[0][0]=='ACTIVE' and sql('SELECT COUNT(*) FROM internal_operator_grants WHERE account_id=%s',(martha['account'],))[0][0]==0 and sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(cid,))[0][0]==1)
 b.close()
connection.close();(ROOT/'docs/evidence/staff-doc-lifecycle03/security.json').write_text(json.dumps({'result':'PASS','checks':results},indent=2))
