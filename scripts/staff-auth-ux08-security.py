#!/usr/bin/env python3
"""Supplemental expiry/revocation and excluded-governance checks in exact owned preview."""
import pathlib,importlib.util,json,sys,hashlib,hmac,socket,time,subprocess,pymysql
from playwright.sync_api import sync_playwright
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
review=module('auth_security_review',ROOT/'scripts/staff-auth-ux08-review.py');owned,approval=review.owned_configuration(sys.argv[1]);info,origin,expiry,record=review.verified_preview(owned,approval)
native=module('auth_security_native',ROOT/'scripts/ase-advisor-workspace-review.py');helper=module('auth_security_helper',ROOT/'scripts/ase-adm02-oper09-browser.py')
env=json.loads(review.private_file(owned,'review-env.json').read_text());assert env['ASE_INT02A_OWNED']==str(owned) or pathlib.Path(env['ASE_INT02A_OWNED']).resolve()==owned
conn=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=env['MXMED_DB_NAME'],autocommit=True);cur=conn.cursor();assert env['MXMED_DB_NAME'].startswith('mxmed_gate4d_preview_aseadm02imp01_')
def sql(query,args=()):cur.execute(query,args);return cur.fetchall()
results=[]
def check(name,condition):
 assert condition,name
 results.append({'name':name,'pass':True});print('PASS '+name,flush=True)
def good(p,a,b={}):
 v=helper.wf(p,a,b);assert v['status']==200,(a,v['status']);return v['body']
def resp(*parts):
 data=b'*'+str(len(parts)).encode()+b'\r\n'
 for part in parts:
  b=str(part).encode();data+=b'$'+str(len(b)).encode()+b'\r\n'+b+b'\r\n'
 with socket.create_connection(('127.0.0.1',int(env['MXMED_SESSION_STORE_PORT'])),timeout=5) as s:
  s.sendall(data);return s.recv(4096)
with sync_playwright() as pw:
 b=pw.chromium.launch();ctx=b.new_context(ignore_https_errors=True);p=ctx.new_page()
 def login():native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
 def intent():
  cid=json.loads((owned/'staff06-martha.json').read_text())['case'];c=good(p,'case',{'case_id':cid});v=good(p,'view',{'case_id':cid})
  return good(p,'authorize',{'case_id':cid,'set_id':v['sets'][0]['set_id'],'action':'READ','reason':'Consulta sintética autorizada','expected_version':c['version'],'expected_review_id':v['review']['review_id'] if v['review'] else None})
 login();i=intent()
 # Restrict to this new fixture's exact current token. Let the store actually expire it;
 # no token revival, long TTL or forged positive session is introduced.
 token=next(x['value'] for x in ctx.cookies() if x['name']=='__Host-mxmed_session')
 digest=hmac.new(env['MXMED_PREVIEW_PEPPER'].encode(),token.encode(),hashlib.sha256).hexdigest();key='mxmed:gate4d:preview:session:'+digest
 check('Exact synthetic session exists in owned Valkey',resp('EXISTS',key)==b':1\r\n')
 assert resp('PEXPIRE',key,1)==b':1\r\n';time.sleep(.1)
 check('Actually expired canonical session denies protected read',helper.wf(p,'evidence',i)['status']==401)
 login();check('Legitimate login restores access after expiry',good(p,'bootstrap')['account_id']=='qa_director')
 i=intent()
 try:
  sql("UPDATE auth_accounts SET status='disabled' WHERE account_id='qa_director'")
  check('Disabled Identity account denies operation',helper.wf(p,'evidence',i)['status']!=200)
 finally:sql("UPDATE auth_accounts SET status='active' WHERE account_id='qa_director'")
 # The failed authority check may revoke this session canonically; start a fresh login.
 login()
 before=sql("SELECT COUNT(*) FROM internal_staff WHERE governance_class='MASTER_ADMIN'")[0][0]
 status=p.evaluate("""async ()=>(await fetch('/api/internal/team/master/execute.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({case_id:'not-authorized'})})).status""")
 check('Excluded Master endpoint remains closed without privileged ceremony',status!=200 and sql("SELECT COUNT(*) FROM internal_staff WHERE governance_class='MASTER_ADMIN'")[0][0]==before)
 files=['modules/identity/services/StaffMfaService.php','modules/identity/services/GovernanceSecurityContext.php','modules/identity/services/SessionService.php','modules/identity/services/InternalGovernanceService.php','modules/identity/http/CanonicalHttpSessionResolver.php',*[str(x.relative_to(ROOT)) for x in (ROOT/'modules/identity/MasterGovernance').glob('*.php')]]
 for f in files:assert subprocess.check_output(['git','show','cd2a9ae940fa3aaa6e312bfd8294892d19bb7f04:'+f],cwd=ROOT)==(ROOT/f).read_bytes(),f
 check('Canonical login MFA TTL and excluded Master services byte-identical to baseline',True)
 i=intent()
 result=p.evaluate("""async i=>{const u='/api/internal/staff-admission/workflow.php';const b=await(await fetch(u,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'bootstrap',input:{}})})).json();const r=await fetch(u,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':b.csrf_token},body:JSON.stringify({action:'evidence',input:i})});const v=await r.json();return {status:r.status,cache:r.headers.get('Cache-Control'),pages:v.pages?.length};}""",i)
 check('Authorized private reader returns no-store and exact pages',result['status']==200 and 'no-store' in result['cache'] and result['pages']==2)
 check('Consumed read intent cannot be replayed',helper.wf(p,'evidence',i)['status']==409)
 check('Browser-supplied role is not an authorization source',helper.wf(p,'evidence',{**i,'role':'DIRECTOR'})['status']==400)
 for table in ['auth_staff_document_acknowledgements','auth_staff_admission_completions']:
  actor='reviewer_account_id' if table.endswith('acknowledgements') else 'actor_account_id'
  rows=sql('SELECT '+actor+',audit_event_id FROM '+table)
  check(table+' exact Director and physical canonical audit',bool(rows) and all(x[0]=='qa_director' and sql('SELECT COUNT(*) FROM platform_audit_events WHERE event_id=SHA2(%s,256) AND real_actor_reference=\'account:qa_director\'',(x[1],))[0][0]==1 for x in rows))
 b.close()
conn.close();(ROOT/'docs/evidence/staff-auth-ux08/security.json').write_text(json.dumps({'result':'PASS','checks':results,'excluded_sources_unchanged':files,'scope':'Owned disposable synthetic only; no restored expired token'},indent=2))
