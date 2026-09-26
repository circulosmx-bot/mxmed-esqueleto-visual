"""Insert-only clean synthetic Director fixture. Existing evidence is never edited."""
import json
import pymysql

DB='mxmed_director_review_lon07c';PATIENT='p_plan02ux_review'
connection=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',database=DB,autocommit=False)
try:
    with connection.cursor() as c:
        c.execute('SELECT DATABASE()');assert c.fetchone()[0]==DB
        c.execute('SELECT patient_id FROM patients_patients WHERE patient_id=%s',(PATIENT,))
        if not c.fetchone():
            c.execute('INSERT INTO patients_patients (patient_id,display_name,birthdate,sex,notes_admin) VALUES (%s,%s,%s,%s,%s)',(PATIENT,'Elena Rivera Demostración','1990-01-01','F','Paciente sintético para revisión de interfaz; sin datos reales.'))
            c.execute('INSERT INTO patients_profiles (profile_id,patient_id,first_name,paternal_last_name,maternal_last_name) VALUES (%s,%s,%s,%s,%s)',('plan02ux-profile',PATIENT,'Elena','Rivera','Demostración'))
            c.execute('INSERT INTO patients_doctor_links (link_id,doctor_id,patient_id,status) VALUES (%s,%s,%s,%s)',('plan02ux-link','1',PATIENT,'active'))
            c.execute('INSERT INTO clinical_encounters (doctor_id,patient_id,encounter_dt,status,opened_by_user_id) VALUES (%s,%s,NOW(),%s,%s)',('1',PATIENT,'open','review-user'));eid=c.lastrowid
            c.execute('INSERT INTO clinical_encounter_sections (encounter_id,section_type,payload_schema_version,payload_json,narrative_text,created_by_user_id,updated_by_user_id) VALUES (%s,%s,1,%s,%s,%s,%s)',(eid,'plan','{}','Solicitar estudios de control y revisar los resultados en la próxima consulta.','review-user','review-user'))
        c.execute('SELECT encounter_id,status FROM clinical_encounters WHERE patient_id=%s',(PATIENT,));rows=c.fetchall();assert len(rows)==1 and rows[0][1]=='open'
        c.execute('SELECT COUNT(*) FROM patients_doctor_links WHERE patient_id=%s AND doctor_id=%s AND status=%s',(PATIENT,'1','active'));assert c.fetchone()[0]==1
        connection.commit();print(json.dumps({'patient_id':PATIENT,'encounter_id':rows[0][0],'database':DB}))
except Exception:
    connection.rollback();raise
finally:
    connection.close()
