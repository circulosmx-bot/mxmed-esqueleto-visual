#!/usr/bin/env python3
"""Version 1 local provisioning, separate from application migrations. No medical DML.
Configuration and receipt remain private. Revoke locks ONLY principals in this receipt.
"""
import argparse,json,pathlib,os,re,secrets,pymysql,hashlib
if not __debug__:raise RuntimeError('run_without_python_optimization')
p=argparse.ArgumentParser();p.add_argument('action',choices=['provision','check','revoke']);p.add_argument('--environment',type=pathlib.Path,required=True);p.add_argument('--private-dir',type=pathlib.Path,required=True);p.add_argument('--origin',default='');p.add_argument('--ids',default='');a=p.parse_args()
os.umask(0o077);folder=a.private_dir.expanduser();folder.mkdir(mode=0o700,parents=True,exist_ok=True)
assert folder.stat().st_uid==os.getuid() and folder.stat().st_mode&0o777==0o700 and not folder.is_symlink()
receipt=folder/'reader-v1.json';c=json.loads(a.environment.expanduser().read_text())
assert c['APP_ENV']=='local' and c['MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT']=='1' and c['MXMED_DB_HOST']=='127.0.0.1' and c['MXMED_DB_NAME']=='mxmed'
d=pymysql.connect(host=c['MXMED_DB_HOST'],port=int(c['MXMED_DB_PORT']),user=c['MXMED_DB_USER'],password=c['MXMED_DB_PASS'],database=c['MXMED_DB_NAME'],autocommit=True)
with d.cursor() as q:
 q.execute('SELECT @@server_uuid,@@hostname,@@port,DATABASE()');instance=q.fetchone()
 if a.action=='provision' and not receipt.exists():
  ids=a.ids.split(',');assert 0<len(ids)<=100 and all(re.fullmatch('[A-Za-z0-9_-]{1,64}',x) for x in ids);assert re.fullmatch('[a-z][a-z0-9_-]{1,40}',a.origin)
  q.execute('SELECT doctor_id FROM profiles_doctors WHERE doctor_id IN ('+','.join(['%s']*len(ids))+')',ids);assert set(x[0] for x in q.fetchall())==set(ids)
  suffix=secrets.token_hex(4);prefix='mxmed_staff_read_v1_'+suffix
  q.execute('SELECT User FROM mysql.user WHERE User IN (%s,%s)',('mxmed_summary_'+suffix,'mxmed_relations_'+suffix));assert not q.fetchall()
  q.execute('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE %s',(prefix+'%',));assert not q.fetchall()
  record={'version':1,'server_uuid':instance[0],'host':c['MXMED_DB_HOST'],'port':int(c['MXMED_DB_PORT']),'database':c['MXMED_DB_NAME'],'origin':a.origin,'ids':ids,'prefix':prefix,'summary_user':'mxmed_summary_'+suffix,'operator_user':'mxmed_relations_'+suffix,'summary_password':secrets.token_urlsafe(32),'operator_password':secrets.token_urlsafe(32),'stage':'preparing'}
  receipt.write_text(json.dumps(record));receipt.chmod(0o600)
 else:
  assert receipt.is_file() and not receipt.is_symlink() and receipt.stat().st_mode&0o077==0
  record=json.loads(receipt.read_text());assert record['server_uuid']==instance[0] and record['database']==instance[3]
 q.execute('SELECT @@datadir');record['ssl_ca']=str(pathlib.Path(q.fetchone()[0])/'ca.pem');assert pathlib.Path(record['ssl_ca']).is_file()
 prefix=record['prefix'];assert re.fullmatch('mxmed_staff_read_v1_[a-f0-9]{8}',prefix)
 for key in ['summary_user','operator_user']:assert re.fullmatch('mxmed_(summary|relations)_[a-f0-9]{8}',record[key]) and record[key].endswith(prefix[-8:])
 assert 0<len(record['ids'])<=100 and all(re.fullmatch('[A-Za-z0-9_-]{1,64}',v) for v in record['ids'])
 if a.action=='provision' and record['stage']=='revoked':raise RuntimeError('reader_revoked_no_automatic_unlock')
 if a.action=='provision':
  # Resume only our exact recorded provisioning. No historical migration or original table change.
  ids=record['ids'];predicate=','.join(d.escape(x) for x in ids)
  views={'profiles':f'SELECT doctor_id,display_name,specialty_primary,profile_status FROM profiles_doctors WHERE doctor_id IN ({predicate})',
   'locations':f'SELECT doctor_id,consultorio_id,titulo,municipio,estado FROM consultorios WHERE doctor_id IN ({predicate})',
   'relationships':f"SELECT m.profile_doctor_id,m.membership_id,m.role_code,m.status,a.status account_status,(a.email_verified_at IS NOT NULL) contact_verified FROM auth_account_memberships m JOIN auth_accounts a ON a.account_id=m.account_id WHERE m.profile_doctor_id IN ({predicate}) AND m.entity_group_id IS NULL AND m.scope_code IN ('profile','profile_doctor')"}
  for name,sql in views.items():q.execute('CREATE OR REPLACE ALGORITHM=TEMPTABLE SQL SECURITY DEFINER VIEW `'+prefix+'_'+name+'` AS '+sql)
  for key in ['summary','operator']:
   q.execute('CREATE USER IF NOT EXISTS %s@%s IDENTIFIED BY %s',(record[key+'_user'],'127.0.0.1',record[key+'_password']))
   for name in (['profiles','locations'] if key=='summary' else ['relationships']):q.execute('GRANT SELECT ON `mxmed`.`'+prefix+'_'+name+'` TO %s@%s',(record[key+'_user'],'127.0.0.1'))
  record['stage']='ready';receipt.write_text(json.dumps(record));print('PROVISIONED_V1',prefix)
 elif a.action=='revoke':
  for key in ['summary','operator']:q.execute('ALTER USER %s@%s ACCOUNT LOCK',(record[key+'_user'],'127.0.0.1'))
  record['stage']='revoked';receipt.write_text(json.dumps(record));print('OWNED_READERS_LOCKED')
 if a.action in ['provision','check']:
  checks=[]
  for key in ['summary','operator']:
   r=pymysql.connect(host=record['host'],port=record['port'],database=record['database'],user=record[key+'_user'],password=record[key+'_password'],autocommit=True,ssl={'ca':record['ssl_ca'],'check_hostname':False})
   with r.cursor() as t:
    t.execute('SHOW GRANTS');grants=[v[0] for v in t.fetchall()];assert all('GRANT SELECT ON' in v or 'GRANT USAGE ON' in v for v in grants);assert len(grants)==(3 if key=='summary' else 2);expected={f"GRANT SELECT ON `mxmed`.`{prefix}_{name}` TO `{record[key+'_user']}`@`127.0.0.1`" for name in (['profiles','locations'] if key=='summary' else ['relationships'])};assert {g for g in grants if 'GRANT SELECT ON' in g}==expected;print(key+'_grants',json.dumps(grants))
    allowed='profiles' if key=='summary' else 'relationships';t.execute('SELECT * FROM `'+prefix+'_'+allowed+'` LIMIT 21');rows=t.fetchall();checks.append(key+'_projection_read')
    forbidden=['SELECT * FROM profiles_doctors LIMIT 0','SELECT * FROM auth_account_credentials LIMIT 0','SELECT * FROM auth_accounts LIMIT 0','SELECT * FROM auth_sessions LIMIT 0','SELECT * FROM patients LIMIT 0','SELECT * FROM appointments LIMIT 0','SELECT * FROM subscriptions LIMIT 0',
     'SELECT * FROM `'+prefix+('_relationships' if key=='summary' else '_profiles')+'` LIMIT 0',
     'UPDATE profiles_doctors SET display_name=display_name WHERE 1=0','DELETE FROM profiles_doctors WHERE 1=0','INSERT INTO profiles_doctors(doctor_id) SELECT doctor_id FROM profiles_doctors WHERE 1=0',
     'CREATE TEMPORARY TABLE mxmed_link03_denied_probe (id INT)']
    for sql in forbidden:
     try:t.execute(sql)
     except pymysql.MySQLError as e:
      assert e.args[0] in [1044,1142,1143],(e.args[0],sql);checks.append(key+'_denied_'+sql.split()[0])
     else:raise RuntimeError('unexpected_sql_authority')
   r.close()
  (folder/'verification-v1.json').write_text(json.dumps({'server_uuid':instance[0],'checks':checks,'medical_dml':False,'version':1},indent=2));print('LEAST_PRIVILEGE_PASS',len(checks))
d.close()
