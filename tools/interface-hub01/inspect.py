#!/usr/bin/env python3
"""Bounded read-only local prerequisite and preservation check; exports no credentials."""
import hashlib,json,pathlib,pymysql,subprocess
ROOT=pathlib.Path(__file__).resolve().parents[2]
home=pathlib.Path.home()/'Library/Application Support/MXMED'
e=json.loads((home/'local-identity-runtime/environment.json').read_text())
r=json.loads((home/'physician-administration-link03/reader-v1.json').read_text())
assert e['APP_ENV']=='local' and e['MXMED_DB_HOST']==r['host']=='127.0.0.1' and e['MXMED_DB_NAME']==r['database']=='mxmed' and int(e['MXMED_DB_PORT'])==3306
connection=pymysql.connect(host='127.0.0.1',port=3306,user=e['MXMED_DB_USER'],password=e['MXMED_DB_PASS'],database='mxmed',autocommit=False)
result={}
try:
 with connection.cursor() as c:
  c.execute('START TRANSACTION READ ONLY');c.execute('SELECT @@server_uuid');assert c.fetchone()[0]==r['server_uuid']
  c.execute("SELECT a.status,a.email_verified_at IS NOT NULL,m.role_code,m.scope_code,m.status,m.assignment_source FROM auth_accounts a JOIN auth_account_memberships m USING(account_id) WHERE a.email_normalized=%s AND m.profile_doctor_id=%s",('circulodigital@msn.com','1'))
  rows=c.fetchall();assert rows==(('active',1,'collaborator','profile','active','manual_review'),)
  result['preferred_account_membership']={'account_active':True,'email_verified':True,'role':'collaborator','scope':'profile','membership_active':True,'assignment_source':'manual_review','profile_ref':'mxmed-local-development:1','credential_inspected':False}
  c.execute("SELECT COUNT(*) FROM auth_account_memberships WHERE account_id=(SELECT account_id FROM auth_accounts WHERE email_normalized=%s) AND status='active' AND profile_doctor_id IS NOT NULL",('circulodigital@msn.com',));assert c.fetchone()[0]==1;result['unique_medical_membership']=True
  c.execute("SELECT status,route_enabled,canonical_enabled FROM public_profile_seo_routes WHERE entity_id='1'");result['publication_gates']=c.fetchall();assert result['publication_gates']==(('candidate',0,0),)
  prior=json.loads((home/'physician-administration-link03/ux05-projection-receipt.json').read_text())['before_records'];result['original_records']={}
  for table in ['profiles_doctors','consultorios','auth_account_memberships','media_assets']:
   column='profile_doctor_id' if table=='auth_account_memberships' else 'owner_id' if table=='media_assets' else 'doctor_id'
   c.execute('SELECT * FROM `'+table+'` WHERE `'+column+'`=%s',('1',));digest=hashlib.sha256(repr(c.fetchall()).encode()).hexdigest();assert digest==prior[table]
   result['original_records'][table]={'sha256':digest,'matches_pre_UX05':True}
finally:connection.rollback();connection.close()
original=ROOT.parent/'mxmed-step2-vitals-r1'
result['original_head']=subprocess.check_output(['git','rev-parse','HEAD'],cwd=original,text=True).strip()
assert result['original_head']=='f92f37f1958af5140a96c04cb52c5c1ce7af8e01'
result['original_worktree_clean']=not subprocess.check_output(['git','status','--porcelain'],cwd=original,text=True)
result['listeners']=subprocess.check_output(['lsof','-nP','-iTCP:8091','-iTCP:8443','-sTCP:LISTEN'],text=True)
assert '90106' in result['listeners'] and '93582' in result['listeners']
print(json.dumps(result,indent=2))
