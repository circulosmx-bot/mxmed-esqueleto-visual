"""Local runner worker. No HTTP login bypass or Director review impersonation."""
import json,pathlib,subprocess,time,uuid,os,re
import pymysql
from playwright.sync_api import sync_playwright

def run(root,owned,env,origin,deadline,resume_case=None):
 def cli(action,email):
  r=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php',action],cwd=root,env=env,input=json.dumps({'actor':'staff04','email':email}),text=True,capture_output=True)
  value=json.loads(r.stdout)
  if r.returncode or not value.get('ok'):raise RuntimeError('canonical_'+action+'_unconfirmed')
  return value.get('data',{})
 def save(path,data):
  temp=path.with_suffix('.tmp');temp.write_text(json.dumps(data));temp.chmod(0o600);os.replace(temp,path)
 while time.time()<deadline and not (owned/'stop-review').exists():
  for path in owned.glob('simulation-adm_*.json'):
   if path.is_symlink():continue
   data=json.loads(path.read_text())
   if resume_case is not None and data['case_id']!=resume_case:continue
   if data['state']!=('RESUME_VERIFIED' if resume_case else 'QUEUED'):continue
   data.update(state='RUNNING',message='Cuenta ficticia e incorporación en curso.');save(path,data)
   try:
    message=json.loads((owned/('invitation-'+data['invitation_id']+'.json')).read_text());email=message['recipient']
    assert re.fullmatch('[a-z0-9_]+@example.test',email) and message['case_id']==data['case_id']
    conn=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'],autocommit=True)
    def row(sql,args):
     with conn.cursor() as c:c.execute(sql,args);return c.fetchone()
    existing=row('SELECT account_id,email_verified_at FROM auth_accounts WHERE email_address=%s',(email,))
    if existing:
     assert resume_case and data.get('account_id')==existing[0], 'existing_account_not_simulated'
     account=existing[0]
    else:
     account=cli('register',email)['account_id'];data['account_id']=account;save(path,data)
    if not existing or existing[1] is None:cli('verify',email)
    version=row('SELECT version FROM auth_staff_admission_cases WHERE case_id=%s',(data['case_id'],))[0]
    with sync_playwright() as pw:
     browser=pw.chromium.launch();context=browser.new_context(ignore_https_errors=True);p=context.new_page()
     p.goto(origin+'/public/identity/acceso.php?next=%2Fstaff-admission.php');p.locator('#login-email').fill(email);p.locator('#login-password').fill((owned/'applicant-password').read_text().strip());p.locator('[data-submit]').click();p.wait_for_url('**/staff-admission.php')
     def call(action,input):
      result=p.evaluate('''async ([action,input])=>{const u='/api/internal/staff-admission/workflow.php';const b=await(await fetch(u,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'bootstrap',input:{}})})).json();const r=await fetch(u,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':b.csrf_token},body:JSON.stringify({action,input})});return {status:r.status,body:await r.json()};}''',[action,input])
      if result['status']!=200:raise RuntimeError('canonical_'+action+'_unconfirmed')
      return result['body']
     bound=row('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s',(data['case_id'],))[0]
     if bound is not None:assert bound==account, 'exact_binding_required'
     if bound is None:call('accept',{'operation_id':str(uuid.uuid4()),'case_id':data['case_id'],'expected_version':int(version),'invitation_id':data['invitation_id'],'token':message['token']})
     if row('SELECT state FROM auth_staff_admission_cases WHERE case_id=%s',(data['case_id'],))[0]!='PERSON_CONFIRMED':call('confirm',{'operation_id':str(uuid.uuid4()),'case_id':data['case_id'],'expected_version':int(version)})
     p.reload();p.locator('#wf-cases').select_option(data['case_id']);p.wait_for_timeout(300)
     p.locator('#wf-front').set_input_files(str(owned/'synthetic.png'));p.locator('#wf-back').set_input_files(str(owned/'synthetic.png'));p.locator('#wf-justification').fill('Imágenes geométricas ficticias para revisión local, sin documento personal.');p.locator('#wf-add-document').click();p.locator('#wf-documents').get_by_text('INE: 2 cara(s) añadidas',exact=True).wait_for()
     p.locator('#wf-consent').check();p.locator('#wf-submit').click();p.wait_for_timeout(500)
     assert row('SELECT set_id FROM auth_staff_document_sets WHERE case_id=%s',(data['case_id'],)), 'submission_unconfirmed'
     browser.close()
    conn.close();data.update(state='REVIEW_REQUIRED',message='Solicitante ficticio vinculado, datos confirmados y evidencia artificial presentada. Cierra y abre la solicitud para consultar evidencia, aceptar revisión y aprobar admisión con tu contraseña y MFA. Después confirma las facultades pendientes.')
   except Exception as e:
    data.update(state='RECONCILIATION_REQUIRED',message='La simulación se detuvo sin repetir operaciones. Requiere comprobar el estado registrado; no se concedieron permisos desde la simulación.')
    (owned/'simulation-private-error.log').write_text(type(e).__name__+': '+str(e))
   save(path,data)
   if resume_case:return
  time.sleep(1)
