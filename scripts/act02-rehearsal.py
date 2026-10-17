#!/usr/bin/env python3
"""Authorized ACT02 full-data rehearsal only. Never applies SQL to mxmed.
Consistent dump stays in memory; evidence contains hashes/counts, not copied rows.
Own random restore/user are always deleted. No global SQL grants/settings.
"""
import argparse,pathlib,json,os,secrets,subprocess,re,hashlib,importlib.util
ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
 p=argparse.ArgumentParser();p.add_argument('--connection',required=True);p.add_argument('--evidence',required=True);a=p.parse_args();os.umask(0o077)
 out=pathlib.Path(a.evidence);out.mkdir(mode=0o700,parents=True,exist_ok=False)
 c=json.loads(pathlib.Path(a.connection).read_text());assert c['MXMED_DB_NAME']=='mxmed' and c['MXMED_DB_HOST']=='127.0.0.1' and str(c['MXMED_DB_PORT'])=='3306'
 env={**os.environ,'MYSQL_PWD':c['MXMED_DB_PASS']};args=['-h127.0.0.1','-P3306','-u'+c['MXMED_DB_USER']]
 suffix=secrets.token_hex(5);db='mxmed_gate4d_preview_act02_'+suffix;user='act02_'+suffix;password=secrets.token_urlsafe(32)
 def sql(q,target=None):
  r=subprocess.run(['mysql',*args,'-N','-B','-r',*([target] if target else [])],input=q,text=True,capture_output=True,env=env)
  if r.returncode:
   (out/'private-sql-error.log').write_text(r.stderr)
   raise RuntimeError('owned rehearsal SQL refused; inspect private checkpoint evidence')
  return r.stdout
 tables=sql("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='mxmed' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME").splitlines()
 columns={t:sql("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='mxmed' AND TABLE_NAME='"+t+"' ORDER BY ORDINAL_POSITION").splitlines() for t in tables}
 def data_hashes(target):
  result={}
  for t,cols in columns.items():
   expressions=["IF(`"+col+"` IS NULL,'N',CONCAT('V',HEX(CAST(`"+col+"` AS BINARY))))" for col in cols]
   rows=sql("SET time_zone='+00:00';SELECT CONCAT_WS(':',"+','.join(expressions)+") FROM `"+t+"`",target).splitlines()
   result[t]={'rows':len(rows),'original_columns_sha256':hashlib.sha256('\n'.join(sorted(rows)).encode()).hexdigest()}
  return result
 def metadata_hash(target):
  values=[]
  for q in ["SELECT TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,'NULL'),EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+target+"' ORDER BY TABLE_NAME,ORDINAL_POSITION", "SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,COALESCE(REFERENCED_TABLE_NAME,''),COALESCE(REFERENCED_COLUMN_NAME,'') FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='"+target+"' ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION", "SELECT TRIGGER_NAME,HEX(ACTION_STATEMENT) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='"+target+"' ORDER BY TRIGGER_NAME"]:values.append(sql(q))
  return hashlib.sha256(json.dumps(values).encode()).hexdigest()
 before=data_hashes('mxmed');manifest=json.loads(subprocess.check_output(['php',str(ROOT/'scripts/act02-deployment.php'),'--manifest'],cwd=ROOT,text=True))
 (out/'manifest.json').write_text(json.dumps(manifest,indent=2));(out/'original-data-before.json').write_text(json.dumps(before,indent=2))
 dump=subprocess.check_output(['mysqldump',*args,'--single-transaction','--routines','--skip-triggers','--events','--hex-blob','--skip-add-locks','--skip-comments','--set-gtid-purged=OFF','--column-statistics=0','mxmed'],env=env,text=True)
 assert data_hashes('mxmed')==before,'source changed during backup; refuse rehearsal'
 assert 'USE `mxmed`' not in dump
 dump=dump.replace('`mxmed`.', '`'+db+'`.');dump=re.sub(r'DEFINER=`[^`]+`@`[^`]+`',"DEFINER="+"`"+c['MXMED_DB_USER']+"`@`localhost`",dump)
 triggers=sql("SELECT TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,HEX(ACTION_STATEMENT) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='mxmed' ORDER BY TRIGGER_NAME").splitlines()
 def restore():
  sql(dump,db)
  # mysql 9.6 mysqldump emits an invalid conditional-comment terminator for
  # single SIGNAL triggers with a stored trailing semicolon. Preserve the real
  # information_schema body instead, after ALL tables/data/routines are restored.
  for line in triggers:
   name,timing,event,table,body=line.split('\t');body=bytes.fromhex(body).decode().replace('`mxmed`.','`'+db+'`.')
   assert all(re.fullmatch('[A-Za-z0-9_]+',v) for v in [name,table])
   sql('DELIMITER $$\nCREATE TRIGGER `'+name+'` '+timing+' '+event+' ON `'+table+'` FOR EACH ROW '+body+'$$\nDELIMITER ;\n',db)
 checkpoints=[];created=False;created_user=False;metadata_user=None
 try:
  sql('CREATE DATABASE `'+db+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');created=True
  restore();assert data_hashes(db)==before,'consistent clone data mismatch'
  checkpoints.append({'step':'restored_prestate','metadata_sha256':metadata_hash(db),'original_data_preserved':True})
  # Rehearse a real partial first migration, refuse blind replay, and restore
  # the COMPLETE original dump into this owned target before sequential apply.
  first=(ROOT/manifest['steps'][0]['file']).read_text();statement=first.split(';',1)[0]+';';sql(statement,db)
  try:sql(first,db);raise AssertionError('partial DDL replay unexpectedly allowed')
  except RuntimeError:pass
  checkpoints.append({'step':'partial_01','blind_replay_denied':True,'metadata_sha256':metadata_hash(db),'recovery':'full restore to owned database'})
  sql('DROP DATABASE `'+db+'`');sql('CREATE DATABASE `'+db+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');restore();assert data_hashes(db)==before
  for step in manifest['steps']:
   text=(ROOT/step['file']).read_text();assert hashlib.sha256(text.encode()).hexdigest()==step['sha256']
   if step['sequence']==10:
    metadata_user='act02meta_'+suffix;sql("CREATE USER '"+metadata_user+"'@'localhost' IDENTIFIED BY '"+secrets.token_urlsafe(32)+"' ACCOUNT LOCK");sql("GRANT TRIGGER ON `"+db+"`.* TO '"+metadata_user+"'@'localhost'")
    text=text.replace('{{METADATA_DEFINER}}',"'"+metadata_user+"'@'localhost'").replace('{{TARGET_SCHEMA_NAME}}',db)
   sql(text,db);assert data_hashes(db)==before
   checkpoints.append({'step':step['sequence'],'file':step['file'],'result':'PASS','metadata_sha256':metadata_hash(db),'original_data_preserved':True})
   (out/'checkpoints.json').write_text(json.dumps(checkpoints,indent=2))
  # Every FK in the complete integrated restore, including composite references.
  keys=sql("SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='"+db+"' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION").splitlines();groups={}
  for line in keys:
   t,k,col,ref,rcol=line.split('\t');groups.setdefault((t,k,ref),[]).append((col,rcol))
  for (t,k,ref),pairs in groups.items():
   join=' AND '.join('c.`'+x+'`=p.`'+y+'`' for x,y in pairs);nonnull=' AND '.join('c.`'+x+'` IS NOT NULL' for x,y in pairs)
   assert sql('SELECT COUNT(*) FROM `'+t+'` c LEFT JOIN `'+ref+'` p ON '+join+' WHERE '+nonnull+' AND p.`'+pairs[0][1]+'` IS NULL',db).strip()=='0','orphan reference'
  sql("CREATE USER '"+user+"'@'127.0.0.1' IDENTIFIED BY '"+password+"'");created_user=True
  sql("GRANT REFERENCES ON `"+db+"`.* TO '"+user+"'@'127.0.0.1'")
  names=sql("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+db+"' AND TABLE_TYPE='BASE TABLE'").splitlines()
  readonly=[t for t in names if t.startswith(('auth_','profiles_','subscription_','platform_audit_')) or t in ['doctor_contact_points','consultorios','consultorio_schedules','profile_subscriptions','media_assets']]
  writable=[t for t in names if t.startswith(('profiles_correction_','auth_physician_')) or t in ['profiles_contact_correction_versions','doctor_contact_points','auth_account_phone_verifications','auth_staff_mfa_challenges','auth_staff_mfa_proofs','auth_governance_cases','platform_audit_events','platform_audit_stream_heads']]
  for t in readonly:sql("GRANT SELECT ON `"+db+"`.`"+t+"` TO '"+user+"'@'127.0.0.1'")
  for t in writable:sql("GRANT INSERT,UPDATE ON `"+db+"`.`"+t+"` TO '"+user+"'@'127.0.0.1'")
  for f,name in [('05_audit_lock.sql','audit_mp01c_lock_stream_head_v1'),('06_audit_cas.sql','audit_mp01c_advance_stream_head_cas_v1')]:
   sql('DROP PROCEDURE `'+name+'`',db);text=(ROOT/'modules/identity/deployment/op-act01'/f).read_text().replace('{{TARGET_SCHEMA}}','`'+db+'`').replace('{{RESTRICTED_DEFINER}}',"'"+user+"'@'127.0.0.1'");sql('DELIMITER $$\n'+text.rstrip().rstrip(';')+'$$\nDELIMITER ;\n',db);sql("GRANT EXECUTE ON PROCEDURE `"+db+"`.`"+name+"` TO '"+user+"'@'127.0.0.1'")
  spec=importlib.util.spec_from_file_location('principal',ROOT/'scripts/act02-principal.py');principal=importlib.util.module_from_spec(spec);spec.loader.exec_module(principal);principal.restrict(sql,db,user)
  # Inspection as the restricted principal, no global SHOW_ROUTINE grant.
  conn=out/'restricted-connection.json';conn.write_text(json.dumps({'MXMED_DB_HOST':'127.0.0.1','MXMED_DB_PORT':'3306','MXMED_DB_NAME':db,'MXMED_DB_USER':user,'MXMED_DB_PASS':password}))
  inspection=json.loads(subprocess.check_output(['php',str(ROOT/'scripts/act02-deployment.php'),'--connection',str(conn)],text=True,cwd=ROOT));assert inspection['integrated_schema_ready'];(out/'restricted-inspection.json').write_text(json.dumps(inspection,indent=2))
  assert data_hashes(db)==before and data_hashes('mxmed')==before
  (out/'result.json').write_text(json.dumps({'result':'PASS','migrations':10,'foreign_key_groups':len(groups),'orphans':0,'original_tables':len(before),'data_preserved':True,'partial_DDL_restore_recovery':'PASS','restricted_principal':'PASS','global_security_changes':False,'live_data_unchanged':True},indent=2));print('ACT02_FULL_RESTORE_REHEARSAL=PASS')
 finally:
  if created:sql('DROP DATABASE `'+db+'`')
  if metadata_user:sql("DROP USER '"+metadata_user+"'@'localhost'")
  if created_user:sql("DROP USER '"+user+"'@'127.0.0.1'")
  (out/'restricted-connection.json').unlink(missing_ok=True)
  (out/'disposal.json').write_text(json.dumps({'database_deleted':created,'temporary_principal_deleted':created_user,'copied_rows_retained':False,'normal_DDL':False}))
if __name__=='__main__':main()
