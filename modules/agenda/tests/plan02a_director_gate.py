"""Opt-in integration gate for the dedicated PLAN02 disposable Director fixture.
Requires migrated mxmed_director_review_lon07c, p_plan02_review/open encounter,
local multi-worker PHP and two preseeded synthetic sessions. No real messages.
"""
import concurrent.futures, hashlib, json, os, time, urllib.request, urllib.error, uuid
from pathlib import Path
import pymysql
BASE = os.environ.get('PLAN02A_BASE', 'http://127.0.0.1:18143')
assert BASE.startswith('http://127.0.0.1:')
OUT = Path(os.environ['PLAN02A_ARTIFACTS']); OUT.mkdir(parents=True, exist_ok=True)
DB = os.environ.get('PLAN02A_DB', 'mxmed_director_review_lon07c')
assert DB == 'mxmed_director_review_lon07c' or (DB.startswith('plan02a_qa_') and DB.replace('_','').isalnum())
conn = pymysql.connect(unix_socket='/tmp/mysql.sock', user='root', database=DB, autocommit=True)
c = conn.cursor(); c.execute('SELECT DATABASE()'); assert c.fetchone()[0] == DB
patient = 'p_plan02_review'; report = {}
PRIMARY_DATE=os.environ.get('PLAN02A_PRIMARY_DATE','2026-10-05')
RECOVERY_DATE=os.environ.get('PLAN02A_RECOVERY_DATE','2026-10-06')
RACE_DATE=os.environ.get('PLAN02A_RACE_DATE','2026-10-09')
RUN=os.environ.get('PLAN02A_RUN','plan02a')
def request(path, body=None, key=None, session='director-lon07c-review', consume=True, method=None):
    headers={'Cookie':'PHPSESSID='+session,'Content-Type':'application/json'}
    if key: headers['Idempotency-Key']=RUN+'-'+key
    req=urllib.request.Request(BASE+path, data=json.dumps(body).encode() if body is not None else None,headers=headers,method=method)
    try: r=urllib.request.urlopen(req,timeout=20)
    except urllib.error.HTTPError as e:r=e
    if not consume: r.close();return None
    return {'http':r.code,'body':json.load(r)}
def sql(q,args=()): c.execute(q,args);return c.fetchall()
def booking(start,end):return dict(doctor_id='1',consultorio_id='1',patient_id=patient,start_at=start,end_at=end,modality='in_person',channel_origin='doctor',created_by_role='doctor',created_by_id='1')
path='/api/agenda/index.php/appointments'
enc=sql('SELECT encounter_id,status,appointment_id FROM clinical_encounters WHERE patient_id=%s',(patient,));assert len(enc)==1 and enc[0][1]=='open';eid=enc[0][0]
assert not sql("SELECT appointment_id FROM agenda_appointments WHERE doctor_id='1' AND consultorio_id='1' AND DATE(start_at)=%s AND status NOT IN ('canceled','cancelled','no_show')",(RACE_DATE,)), 'Choose a fresh PLAN02A_RACE_DATE for independent concurrent attempts'
primary=booking(PRIMARY_DATE+' 09:00:00',PRIMARY_DATE+' 09:30:00')
a=request(path,primary,'plan02a-booking-primary-v1');assert a['body']['ok'],a;aid=a['body']['data']['appointment_id'];assert a['body']['data']['status']=='tentative'
b=request(path,primary,'plan02a-booking-primary-v1');assert b['body']['meta']['idempotency_replay'] and b['body']['data']==a['body']['data'];report['exact_replay']=b
changed=booking(PRIMARY_DATE+' 09:30:00',PRIMARY_DATE+' 10:00:00');r=request(path,changed,'plan02a-booking-primary-v1');assert r['http']==409 and r['body']['error']=='idempotency_conflict';report['different_payload']=r
# Real persisted success deliberately not consumed; subsequent HTTP reconciles by key.
ambiguous=booking(RECOVERY_DATE+' 09:00:00',RECOVERY_DATE+' 09:30:00');key='plan02a-lost-response-v1';request(path,ambiguous,key,consume=False);r=request(path,ambiguous,key);assert r['body']['ok'] and r['body']['meta']['idempotency_replay'];assert sql('SELECT COUNT(*) FROM agenda_appointments WHERE patient_id=%s AND start_at=%s',(patient,ambiguous['start_at']))[0][0]==1;report['ambiguous_response']=r
# Hold the actual writer mutex until two independent PHP/MySQL requests are
# visibly waiting. This proves concurrent execution instead of sequential calls.
lock='agenda-create:'+hashlib.sha256((DB+'|agenda_appointments|1').encode()).hexdigest()[:48]
race=booking(RACE_DATE+' 09:00:00',RACE_DATE+' 09:30:00')
assert sql('SELECT GET_LOCK(%s, 5)',(lock,))[0][0]==1
try:
    with concurrent.futures.ThreadPoolExecutor(2) as pool:
        futures=[pool.submit(request,path,race,'plan02a-race-'+str(uuid.uuid4()),'plan02a-parallel-'+x) for x in ['a','b']]
        until=time.monotonic()+5;waiters=[]
        while time.monotonic()<until:
            waiters=sql("SELECT ID FROM information_schema.PROCESSLIST WHERE DB=%s AND INFO LIKE 'SELECT GET_LOCK%%'",(DB,))
            if len(waiters)>=2:break
            time.sleep(.05)
        sql('SELECT RELEASE_LOCK(%s)',(lock,));assert len(waiters)>=2,('no independent waiters',waiters)
        results=[f.result() for f in futures]
finally:sql('SELECT RELEASE_LOCK(%s)',(lock,))
assert sum(r['body']['ok'] for r in results)==1,results
loser=next(r for r in results if not r['body']['ok']);assert loser['http']==409 and loser['body']['error']=='collision',loser
assert sql('SELECT COUNT(*) FROM agenda_appointments WHERE doctor_id=\'1\' AND consultorio_id=\'1\' AND start_at=%s',(race['start_at'],))[0][0]==1
report['concurrency']={'independent_connection_ids':[r[0] for r in waiters],'responses':results,'rows':1}
# A second, same-key concurrent pair reconciles to one result.
replayrace=booking(RACE_DATE+' 10:00:00',RACE_DATE+' 10:30:00');shared='plan02a-same-key-race-v2'
with concurrent.futures.ThreadPoolExecutor(2) as pool:
    rr=list(pool.map(lambda sid:request(path,replayrace,shared,sid),['plan02a-parallel-a','plan02a-parallel-b']))
assert all(r['body']['ok'] for r in rr) and rr[0]['body']['data']==rr[1]['body']['data'];assert sum(bool(r['body']['meta']['idempotency_replay']) for r in rr)==1;report['concurrent_same_key']=rr
(OUT/'booking-integrity-results.json').write_text(json.dumps(report,indent=2))
# Persisted tentative context, unchanged by creation AND update of a follow-up.
tasks='/api/clinical/index.php/patients/'+patient+'/longitudinal/tasks'
before=request(path+'/'+aid)['body']['data']
f=request(tasks,dict(task_type='FOLLOW_UP',title='PLAN02A revisar resultados — SINTÉTICO',source_encounter_id=eid,appointment_id=aid,due_at=None),'plan02a-followup-v1');assert f['body']['ok'],f
task=f['body']['data']['item'];tid=task['task_id'];assert task['appointment_id']==aid and task['due_at'] is None
read=request(tasks+'/'+str(tid));assert read['body']['data']['item']['appointment_id']==aid
u=request(tasks+'/'+str(tid),dict(title=task['title'],appointment_id=aid,expected_version=task['row_version'],reason='QA sintética: conservar vínculo tentativo'),'plan02a-followup-update-v1',method='PATCH')
report['followup_update']=u
assert u['body']['ok'],u
assert request(path+'/'+aid)['body']['data']==before
projection=request('/api/clinical/index.php/longitudinal/follow-ups/agenda');report['linked_projection']=projection
flat=[row for group in projection['body']['data']['groups'].values() for row in group];projected=next(row for row in flat if row['task_id']==tid);assert projected['linked_appointment_display'],projected
report['followup']={'create':f,'read':read,'appointment_before':before,'appointment_after':request(path+'/'+aid)['body']['data']}
# Canonical cancellation must not cascade the clinical task lifecycle.
cancel=request(path+'/'+aid+'/cancel',dict(actor_role='doctor',actor_id='1',channel_origin='doctor',motivo_code='other',motivo_text='QA PLAN02A',notify_patient=False));assert cancel['body']['ok'],cancel
terminal=request(tasks+'/'+str(tid))['body']['data']['item'];assert terminal['state']=='OPEN' and terminal['appointment_id']==aid
report['cancel_without_cascade']={'appointment':cancel,'task':terminal}
post_cancel=request('/api/clinical/index.php/longitudinal/follow-ups/agenda')
terminal_projection=next(row for rows in post_cancel['body']['data']['groups'].values() for row in rows if row['task_id']==tid)
assert terminal_projection['linked_appointment_display'] is None
report['terminal_projection']=terminal_projection
assert sql('SELECT encounter_id,status,appointment_id FROM clinical_encounters WHERE patient_id=%s',(patient,))==enc
baseline=request('/api/agenda/index.php/availability?doctor_id=1&consultorio_id=1&date=2026-09-28');assert len(baseline['body']['data']['slots'])==6;report['baseline']=baseline
report['patient']=patient;report['encounter']=eid
(OUT/'canonical-results.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));print('PASS PLAN02A canonical prerequisites',aid,tid,flush=True)
