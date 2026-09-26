"""Rehearse only in a newly created, uniquely named disposable database."""
import pymysql,subprocess,json,uuid
from pathlib import Path
root=Path(__file__).resolve().parents[3];db='plan02a_rehearsal_'+uuid.uuid4().hex[:12];conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',autocommit=True);c=conn.cursor();c.execute('CREATE DATABASE '+db);c.execute('USE '+db)
old=subprocess.check_output(['git','show','897d7962931dd9e16815506498b7b5109d6f0375:modules/agenda/db/ready_schema.sql'],cwd=root,text=True)
subprocess.run(['mysql',db],input=old,text=True,check=True)
insert="INSERT INTO agenda_appointments(appointment_id,doctor_id,consultorio_id,patient_id,start_at,end_at,modality,status) VALUES(%s,'1','1','p_qa_test','2026-10-05 09:00','2026-10-05 09:30','in_person',%s)"
c.execute(insert,('legacy-a','tentative'));c.execute(insert,('legacy-b','tentative'))
migration=(root/'modules/agenda/db/migrations/2026_09_26_01_appointment_create_integrity.sql').read_text();r=subprocess.run(['mysql',db],input=migration,text=True,capture_output=True);assert r.returncode and 'Duplicate entry' in r.stderr
c.execute("SHOW COLUMNS FROM agenda_appointments LIKE 'create_request_key'");assert not c.fetchall();c.execute('SELECT COUNT(*) FROM agenda_appointments');assert c.fetchone()[0]==2
c.execute("UPDATE agenda_appointments SET status='canceled' WHERE appointment_id='legacy-b'");subprocess.run(['mysql',db],input=migration,text=True,check=True)
c.execute('SELECT appointment_id,status FROM agenda_appointments ORDER BY appointment_id');assert c.fetchall()==(('legacy-a','tentative'),('legacy-b','canceled'))
try:c.execute(insert,('duplicate','tentative'));raise AssertionError('duplicate accepted')
except pymysql.err.IntegrityError:pass
print(json.dumps({'duplicate_preflight_atomic_failure':'PASS','existing_rows_preserved':'PASS','tentative_unique_constraint':'PASS','database':db}));c.execute("DROP DATABASE "+db);conn.close()
