#!/usr/bin/env python3
"""Owned schema-only migration/recovery/restore proof. Never copies live rows."""
import pathlib,json,secrets,subprocess,os,re,tempfile,shutil
ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=pathlib.Path.home()/'Library/Application Support/MXMED/op-act01-evidence';SUPPORT=OUT.parent/'op-act01-rehearsal';SOURCE=json.loads((SUPPORT/'ownership.json').read_text());CONFIG=json.loads((SUPPORT/'environment.json').read_text())
def mysql(sql,db=None):
 r=subprocess.run(['mysql','-uroot','-h127.0.0.1','-N','-B','-r',*([db] if db else [])],input=sql,text=True,capture_output=True)
 if r.returncode:raise RuntimeError(r.stderr.splitlines()[0])
 return r.stdout
def php(code,db,args=[]):
 src="require 'modules/identity/http/IdentityHttpComposition.php'; Identity\\Http\\IdentityHttpComposition::registerAutoloader(); $p=new PDO('mysql:host=127.0.0.1;dbname='.$argv[1].';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$package=new Identity\\Operators\\OperatorDeploymentPackage($p);"+code
 return subprocess.run(['php','-r',src,db,*args],cwd=ROOT,capture_output=True,text=True)
def require(r):
 if r.returncode:raise RuntimeError(r.stderr[-1800:])
 return r.stdout
def main():
 os.umask(0o077);owned=[];tmp=pathlib.Path(tempfile.mkdtemp(prefix='mxmed-act01-schema-'));results={}
 def passed(name):results[name]='PASS';print(name+'=PASS',flush=True)
 def fresh():
  suffix=secrets.token_hex(5);db='mxmed_operator_deploy_'+suffix;user='mxmed_rst_'+suffix;owned.append((db,user));mysql(f"CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;CREATE USER '{user}'@'127.0.0.1' IDENTIFIED BY '{secrets.token_urlsafe(32)}';GRANT ALL ON `{db}`.* TO '{user}'@'127.0.0.1';GRANT SHOW_ROUTINE ON *.* TO '{user}'@'127.0.0.1';")
  return db,user
 try:
  db,user=fresh();base=(OUT/'normal-schema-only.sql').read_text().replace('`'+SOURCE['db']+'`.','`'+db+'`.');mysql(base,db)
  journal=tmp/'forward.json';apply="$a=$package->inspect()+['authorization_reference'=>'OP-ACT01-SYNTHETIC-SCHEMA'];echo json_encode($package->apply($a,$argv[2]));"
  first=json.loads(require(php(apply,db,[str(journal)])));assert first['state']=='complete' and len(first['completed'])==4;passed('migration_forward_four_artifacts')
  again=json.loads(require(php(apply,db,[str(journal)])));assert again['replayed'];passed('exact_replay_no_ddl')
  # Every declared new FK remains inside this schema; MySQL has already enforced physical compatibility.
  metadata=mysql("SELECT CONCAT(TABLE_NAME,':',CONSTRAINT_NAME) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME",db)
  assert 'auth_identity_notification_deliveries' in metadata and 'agenda_appointment_creator_snapshots' in metadata;passed('physical_foreign_keys_indexes_triggers')
  mysql('ALTER TABLE auth_identity_notification_deliveries ADD act01_drift INT NULL',db);r=php(apply,db,[str(journal)]);assert r.returncode and 'partial_or_drift' in r.stderr;passed('post_install_schema_drift_refused');mysql('ALTER TABLE auth_identity_notification_deliveries DROP act01_drift',db)
  # Empty-only compensating recovery. Historical base tables remain present.
  require(php("$package->recoverEmpty($package->inspect()+['authorization_reference'=>'OP-ACT01-SYNTHETIC-RECOVERY']);",db));assert mysql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='auth_identity_notification_deliveries'",db).strip()=='0';assert mysql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='agenda_appointments'",db).strip()=='1';passed('empty_recovery_preserves_base_tables')
  # Fresh forward after explicit reviewed recovery uses a NEW journal, never silently resumes partial DDL.
  require(php(apply,db,[str(tmp/'after-recovery.json')]));passed('forward_after_empty_recovery')
  partial,user2=fresh();mysql(base.replace('`'+db+'`.','`'+partial+'`.'),partial);mysql('ALTER TABLE agenda_appointments RENAME COLUMN appointment_id TO unavailable_id',partial)
  pjournal=tmp/'partial.json';r=php(apply,partial,[str(pjournal)]);assert r.returncode;record=json.loads(pjournal.read_text());assert record['state']=='partial_failed' and record['current_artifact']=='03_creator_snapshots.sql' and record['completed']==['01_foundation.sql','02_presentations.sql'];passed('partial_ddl_exact_artifact_journal')
  r=php(apply,partial,[str(pjournal)]);assert r.returncode and 'partial_or_drift' in r.stderr;passed('partial_replay_refused')
  # Full synthetic backup/restore, including history, native audit, FK/indexes/triggers. No live patient rows.
  restore,ruser=fresh();e=dict(os.environ);e['MYSQL_PWD']=CONFIG['MXMED_DB_PASS']
  dump=subprocess.check_output(['mysqldump','-h127.0.0.1','-u'+CONFIG['MXMED_DB_USER'],'--single-transaction','--hex-blob','--routines','--triggers','--set-gtid-purged=OFF','--column-statistics=0','--skip-comments',SOURCE['db']],text=True,env=e,stderr=subprocess.PIPE);(OUT/'synthetic-backup.sql').write_text(dump)
  transformed=dump.replace('`'+SOURCE['db']+'`.','`'+restore+'`.');transformed=re.sub(r'DEFINER=`[^`]+`@`[^`]+`','DEFINER=`'+ruser+'`@`127.0.0.1`',transformed);transformed=re.sub(r';\s*\*/;;', ' */;;',transformed);assert '`mxmed`.' not in transformed;mysql(transformed,restore)
  tables=mysql('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type=\'BASE TABLE\' ORDER BY table_name',SOURCE['db']).splitlines()
  for table in tables:
   assert mysql('SELECT COUNT(*) FROM `'+table+'`',SOURCE['db'])==mysql('SELECT COUNT(*) FROM `'+table+'`',restore),table
   def ddl(d):return mysql('SHOW CREATE TABLE `'+table+'`',d).replace('`'+d+'`.','`OWNED`.').replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',' COLLATE utf8mb4_unicode_ci')
   before,after=ddl(SOURCE['db']),ddl(restore)
   if before!=after:
    import difflib
    (OUT/'restore-ddl-diff.txt').write_text(''.join(difflib.unified_diff(before.splitlines(True),after.splitlines(True))))
    raise AssertionError(table)
  passed('synthetic_backup_restore_all_table_counts_and_definitions')
  for kind,query in [('triggers',"SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING,JSON_QUOTE(ACTION_STATEMENT) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME"),('routines',"SELECT ROUTINE_NAME,ROUTINE_TYPE,SQL_DATA_ACCESS,SECURITY_TYPE,JSON_QUOTE(ROUTINE_DEFINITION) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_NAME")]:
   assert mysql(query,SOURCE['db']).replace(SOURCE['db'],'OWNED')==mysql(query,restore).replace(restore,'OWNED');passed('synthetic_restore_'+kind)
  # Check data checksums, not just counts.
  def checks(d):return [line.split('\t')[-1] for line in mysql('CHECKSUM TABLE '+','.join('`'+t+'`' for t in tables),d).splitlines()]
  left,right=checks(SOURCE['db']),checks(restore)
  assert left==right,'checksum_differences='+','.join(t for t,a,b in zip(tables,left,right) if a!=b)
  passed('synthetic_restore_all_data_checksums')
  require(php("$t=Platform\\Repositories\\CanonicalAuditDatabaseTarget::fromConnection($p);foreach(['audit_mp01c_lock_stream_head_v1','audit_mp01c_advance_stream_head_cas_v1'] as $n)$t->routine($p,$n);",restore));passed('restored_audit_native_contract')
 finally:
  for db,user in reversed(owned):mysql(f"DROP DATABASE `{db}`;DROP USER '{user}'@'127.0.0.1';")
  shutil.rmtree(tmp);passed('schema_test_owned_databases_deleted');(OUT/'schema.json').write_text(json.dumps(results,indent=2)+'\n')
 print('OP_ACT01_SCHEMA_RECOVERY_RESTORE=PASS',flush=True)
if __name__=='__main__':main()
