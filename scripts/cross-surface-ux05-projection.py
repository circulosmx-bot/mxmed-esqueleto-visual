#!/usr/bin/env python3
"""Explicit local v2 view expansion. No original record, grant or credential changes."""
import json,pathlib,pymysql,re,os,hashlib
home=pathlib.Path.home();secret=home/'Library/Application Support/MXMED/physician-administration-link03/reader-v1.json';s=json.loads(secret.read_text());c=json.loads((home/'Library/Application Support/MXMED/local-identity-runtime/environment.json').read_text())
assert c['APP_ENV']=='local' and c['MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT']=='1' and s['host']==c['MXMED_DB_HOST']=='127.0.0.1' and s['database']==c['MXMED_DB_NAME']=='mxmed'
assert s['stage']=='ready' and re.fullmatch('mxmed_staff_read_v1_[a-f0-9]{8}',s['prefix']) and all(re.fullmatch('[A-Za-z0-9_-]{1,64}',v) for v in s['ids'])
d=pymysql.connect(host=c['MXMED_DB_HOST'],port=int(c['MXMED_DB_PORT']),user=c['MXMED_DB_USER'],password=c['MXMED_DB_PASS'],database=c['MXMED_DB_NAME'],autocommit=True)
with d.cursor() as q:
 q.execute('SELECT @@server_uuid');assert q.fetchone()[0]==s['server_uuid']
 ids=','.join(d.escape(v) for v in s['ids']);prefix=s['prefix'];before={}
 for table in ['profiles_doctors','consultorios','auth_account_memberships','media_assets']:
  col='profile_doctor_id' if table=='auth_account_memberships' else 'owner_id' if table=='media_assets' else 'doctor_id'
  q.execute('SELECT * FROM `'+table+'` WHERE `'+col+'` IN ('+ids+')');before[table]=hashlib.sha256(repr(q.fetchall()).encode()).hexdigest()
 views={'profiles':f"SELECT p.doctor_id,p.display_name,p.specialty_primary,p.profile_status,m.public_url portrait_path FROM profiles_doctors p LEFT JOIN media_assets m ON m.public_url=p.photo_url AND m.owner_type='PHYSICIAN' AND m.owner_id=p.doctor_id AND m.purpose='DOCTOR_PROFILE_PHOTO' AND m.classification='PUBLIC' AND m.status='READY' AND m.deleted_at IS NULL WHERE p.doctor_id IN ({ids})",
 'locations':f'SELECT doctor_id,consultorio_id,titulo,municipio,estado,calle,num_ext,num_int,cp,colonia,document_publication_enabled FROM consultorios WHERE doctor_id IN ({ids})'}
 rollback={}
 for key,sql in views.items():
  q.execute('SHOW CREATE VIEW `'+prefix+'_'+key+'`');rollback[key]=q.fetchone()[1]
 receipt=secret.parent/'ux05-projection-receipt.json';os.umask(0o077)
 if not receipt.exists():receipt.write_text(json.dumps({'prior_views':rollback,'before_records':before,'server_uuid':s['server_uuid']}))
 for key,sql in views.items():q.execute('CREATE OR REPLACE ALGORITHM=TEMPTABLE SQL SECURITY DEFINER VIEW `'+prefix+'_'+key+'` AS '+sql)
 for table,digest in before.items():
  col='profile_doctor_id' if table=='auth_account_memberships' else 'owner_id' if table=='media_assets' else 'doctor_id'
  q.execute('SELECT * FROM `'+table+'` WHERE `'+col+'` IN ('+ids+')');assert hashlib.sha256(repr(q.fetchall()).encode()).hexdigest()==digest
s['projection_version']=2;secret.write_text(json.dumps(s));secret.chmod(0o600);d.close();print('PROJECTION_V2_READY; original_records_unchanged=true; view_metadata_changed=true; grants_unchanged=true')
