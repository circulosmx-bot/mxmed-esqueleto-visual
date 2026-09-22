"""Disposable LON06A migration, authenticated HTTP, concurrency, audit and boundary QA."""
import json
import os
import subprocess
import threading
from concurrent.futures import ThreadPoolExecutor
from urllib.error import HTTPError
from urllib.request import Request, urlopen

from playwright.sync_api import sync_playwright

base=os.environ['LON06A_QA_BASE']+'/api/clinical/index.php/patients'
db=os.environ['LON06A_QA_DB']
root=os.environ['LON06A_QA_ROOT']
window_path=os.environ['LON06A_QA_WINDOW_PATH']
url=base+'/p_a/longitudinal/tasks'

def check(value,name):
    if not value:raise AssertionError(name)
    print('PASS',name,flush=True)

def sql(statement):
    return subprocess.check_output(['mysql','--batch','--skip-column-names',db,'-e',statement]).decode().strip()

def count(table):
    if table not in {'clinical_patient_tasks','clinical_patient_task_audit_events','clinical_record_entries','clinical_documents'}:raise ValueError(table)
    return int(sql(f'SELECT COUNT(*) FROM {table}'))

def set_window(state):
    env=os.environ.copy();env['MXMED_CLINICAL_WRITE_WINDOW_CONTROL']='FILE';env['MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH']=window_path
    subprocess.run(['php','-r','require $argv[1];clinical_m6_write_window_set_state($argv[2]);',root+'/api/_lib/clinical_m6_write_window.php',state],env=env,check=True)

def create(client,key,**fields):
    return client.post(url,data={'task_type':'FOLLOW_UP','title':'Control clínico explícito',**fields},headers={'Content-Type':'application/json','Idempotency-Key':key})

def mutation(client,method,path,key,body):
    return getattr(client,method)(url+path,data=body,headers={'Content-Type':'application/json','Idempotency-Key':key})

with sync_playwright() as playwright:
    browser=playwright.chromium.launch(channel='chrome',headless=True)
    contexts=[]
    for user in ('a','b','foreign'):
        context=browser.new_context()
        context.add_cookies([{'name':'PHPSESSID','value':'lon06a-'+user,'url':os.environ['LON06A_QA_BASE']}])
        contexts.append(context)
    a,b,foreign=[context.request for context in contexts]
    check(a.get(url).status==200 and a.get(url).json()['data']['items']==[],'empty list read without task inference')
    check(count('clinical_patient_tasks')==0 and count('clinical_record_entries')==1,'migration preserves legacy follow-up text')
    check(count('clinical_documents')==1 and count('clinical_patient_tasks')==0,'pending order did not create task')
    sql("UPDATE clinical_encounters SET status='closed',closed_at=UTC_TIMESTAMP() WHERE encounter_id=101")
    check(count('clinical_patient_tasks')==0,'closing encounter with Plan text did not create task')
    sql("INSERT INTO agenda_appointments VALUES ('a_new','d_a','p_a','confirmed',UTC_TIMESTAMP())")
    check(count('clinical_patient_tasks')==0,'Agenda appointment creation did not create task')
    sql("UPDATE agenda_appointments SET start_at=DATE_ADD(start_at,INTERVAL 1 DAY),status='rescheduled' WHERE appointment_id='a_a'")
    sql("UPDATE agenda_appointments SET status='canceled' WHERE appointment_id='a_a'")
    check(count('clinical_patient_tasks')==0,'Agenda changes did not create or mutate tasks')

    first=create(a,'lon06a-create',source_encounter_id=101,appointment_id='a_a',due_at='2026-10-01 12:00:00',responsible_user_id='u_a')
    check(first.status==200 and first.json()['ok'],'explicit follow-up create over authenticated HTTP')
    task=first.json()['data']['item'];task_id=int(task['task_id'])
    check(task['task_type']=='FOLLOW_UP' and task['state']=='OPEN' and int(task['row_version'])==1,'follow-up state and version')
    check(task['provenance']=='ENCOUNTER_DERIVED_EXPLICIT_ENTRY' and int(task['source_encounter_id'])==101 and task['appointment_id']=='a_a','source and Agenda linkage attributable')
    check(count('clinical_patient_task_audit_events')==1,'create audit committed')
    replay=create(a,'lon06a-create',source_encounter_id=101,appointment_id='a_a',due_at='2026-10-01 12:00:00',responsible_user_id='u_a')
    check(replay.status==200 and replay.json()['data']['item']['task_id']==task['task_id'] and count('clinical_patient_tasks')==1 and count('clinical_patient_task_audit_events')==1,'create replay no duplicate')
    changed=create(a,'lon06a-create',title='Changed task',source_encounter_id=101,appointment_id='a_a',due_at='2026-10-01 12:00:00',responsible_user_id='u_a')
    check(changed.status==409 and changed.json()['error']=='IDEMPOTENCY_PAYLOAD_CONFLICT','changed payload deterministic conflict')
    check(create(a,'foreign-source',source_encounter_id=102).status==409,'foreign encounter rejected')
    check(create(a,'foreign-appointment',appointment_id='a_b').status==409,'foreign Agenda appointment rejected')
    check(create(a,'unverified-responsible',responsible_user_id='u_foreign').status==409,'unverified responsibility rejected')
    check(a.get(base+'/p_b/longitudinal/tasks').status==404,'foreign patient list hidden')
    check(create(foreign,'foreign-write').status==404,'foreign doctor cannot write patient task')
    check(foreign.get(url+f'/{task_id}').status==404,'foreign doctor cannot read task')
    check(a.get(url+f'/{task_id}/history').json()['data']['events'][0]['operation']=='CREATE','history read exposes create')

    updated=mutation(a,'patch',f'/{task_id}','lon06a-update',{'expected_version':1,'title':'Control clínico actualizado','due_at':None,'reason':'Ajuste clínico explícito'})
    check(updated.status==200 and int(updated.json()['data']['item']['row_version'])==2 and updated.json()['data']['item']['due_at'] is None,'versioned edit and NO_DUE_DATE preserved')
    update_replay=mutation(a,'patch',f'/{task_id}','lon06a-update',{'expected_version':1,'title':'Control clínico actualizado','due_at':None,'reason':'Ajuste clínico explícito'})
    check(update_replay.status==200 and count('clinical_patient_task_audit_events')==2,'update replay no duplicate audit')
    resolved=mutation(a,'post',f'/{task_id}/resolve','lon06a-resolve',{'expected_version':2,'reason':'Seguimiento realizado'})
    check(resolved.status==200 and resolved.json()['data']['item']['state']=='RESOLVED','explicit resolve terminal')
    resolve_replay=mutation(a,'post',f'/{task_id}/resolve','lon06a-resolve',{'expected_version':2,'reason':'Seguimiento realizado'})
    check(resolve_replay.status==200 and resolve_replay.json()['data']['item']['row_version']==resolved.json()['data']['item']['row_version'] and count('clinical_patient_task_audit_events')==3,'resolve replay no duplicate audit')
    stale=mutation(b,'patch',f'/{task_id}','lon06a-stale',{'expected_version':2,'title':'Sobrescritura antigua','reason':'Stale'})
    check(stale.status==409 and stale.json()['error']=='STALE_VERSION','stale edit after resolve rejected')
    race=mutation(b,'post',f'/{task_id}/cancel','lon06a-race',{'expected_version':2,'reason':'Cancelación concurrente'})
    check(race.status==409 and a.get(url+f'/{task_id}').json()['data']['item']['state']=='RESOLVED','resolve versus cancel has one winner')
    forced=mutation(a,'patch',f'/{task_id}','lon06a-reopen',{'expected_version':3,'title':'Reabrir','reason':'No permitido'})
    check(forced.status==409 and forced.json()['error']=='TERMINAL_TASK','terminal task cannot reopen')
    history=a.get(url+f'/{task_id}/history').json()['data']['events']
    check([event['operation'] for event in history]==['CREATE','UPDATE','RESOLVE'] and [int(event['entity_version']) for event in history]==[1,2,3],'create-update-resolve audit reconstructable')

    second=create(a,'lon06a-second',task_type='CLINICAL_ACTION',title='Nueva acción clínica')
    check(second.status==200 and int(second.json()['data']['item']['task_id'])!=task_id,'new clinical need creates new task')
    second_id=int(second.json()['data']['item']['task_id'])
    canceled=mutation(a,'post',f'/{second_id}/cancel','lon06a-cancel',{'expected_version':1,'reason':'Decisión clínica de cancelar'})
    check(canceled.status==200 and canceled.json()['data']['item']['state']=='CANCELED','explicit cancel terminal')
    cancel_replay=mutation(a,'post',f'/{second_id}/cancel','lon06a-cancel',{'expected_version':1,'reason':'Decisión clínica de cancelar'})
    check(cancel_replay.status==200 and cancel_replay.json()['data']['item']['row_version']==canceled.json()['data']['item']['row_version'],'cancel replay no duplicate state')
    cancel_history=a.get(url+f'/{second_id}/history').json()['data']['events']
    check([event['operation'] for event in cancel_history]==['CREATE','CANCEL'],'create-cancel audit reconstructable')
    follow_again=create(a,'lon06a-new-episode',title='Nuevo seguimiento')
    check(follow_again.status==200 and int(follow_again.json()['data']['item']['task_id'])!=task_id and a.get(url+f'/{task_id}').json()['data']['item']['state']=='RESOLVED','old terminal task retained after new explicit task')
    check(a.get(url+f'/{second_id}').status==200,'canceled task remains readable')
    check(subprocess.run(['mysql',db,'-e','DELETE FROM clinical_encounters WHERE encounter_id=101'],capture_output=True).returncode!=0,'source encounter cannot cascade-delete task')
    check(subprocess.run(['mysql',db,'-e',"DELETE FROM agenda_appointments WHERE appointment_id='a_a'"],capture_output=True).returncode!=0,'linked appointment cannot cascade-delete task')
    check(subprocess.run(['mysql',db,'-e',f'DELETE FROM clinical_patient_task_audit_events WHERE task_id={task_id}'],capture_output=True).returncode!=0,'task audit is immutable')
    check(subprocess.run(['mysql',db,'-e',f"UPDATE clinical_patient_tasks SET state='OPEN' WHERE task_id={task_id}"],capture_output=True).returncode!=0,'database rejects terminal task reopening')

    racing=create(a,'lon06a-race-task',title='Competencia terminal')
    racing_id=int(racing.json()['data']['item']['task_id'])
    barrier=threading.Barrier(2)
    def terminal_request(action,user):
        payload=json.dumps({'expected_version':1,'reason':'Competencia explícita'}).encode()
        request=Request(url+f'/{racing_id}/{action}',data=payload,method='POST',headers={
            'Content-Type':'application/json','Idempotency-Key':'lon06a-simultaneous-'+action,'Cookie':'PHPSESSID=lon06a-'+user})
        barrier.wait()
        try:
            with urlopen(request,timeout=15) as response:return response.status
        except HTTPError as error:return error.code
    with ThreadPoolExecutor(max_workers=2) as executor:
        results=list(executor.map(lambda pair:terminal_request(*pair),[('resolve','a'),('cancel','b')]))
    check(sorted(results)==[200,409] and a.get(url+f'/{racing_id}').json()['data']['item']['state'] in ('RESOLVED','CANCELED'),'simultaneous resolve/cancel has exactly one accepted transition')
    check(len(a.get(url+f'/{racing_id}/history').json()['data']['events'])==2,'race creates one terminal audit event')

    before_tasks,before_audit=count('clinical_patient_tasks'),count('clinical_patient_task_audit_events')
    sql("CREATE TRIGGER qa_task_audit_failure BEFORE INSERT ON clinical_patient_task_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA_AUDIT_FAILURE'")
    failure=create(a,'lon06a-audit-failure',title='Debe revertirse')
    check(failure.status==500 and count('clinical_patient_tasks')==before_tasks and count('clinical_patient_task_audit_events')==before_audit,'task and audit atomic rollback')
    sql('DROP TRIGGER qa_task_audit_failure')
    check(create(a,'lon06a-audit-failure',title='Debe revertirse').status==200,'failed receipt rolled back too')

    set_window('BLOCK_WRITES')
    before_tasks,before_audit=count('clinical_patient_tasks'),count('clinical_patient_task_audit_events')
    open_task=int(follow_again.json()['data']['item']['task_id'])
    blocked=[create(a,'lon06a-block-create',title='Bloqueada'),
        mutation(a,'patch',f'/{open_task}','lon06a-block-update',{'expected_version':1,'title':'Bloqueada','reason':'Bloqueo'}),
        mutation(a,'post',f'/{open_task}/resolve','lon06a-block-resolve',{'expected_version':1,'reason':'Bloqueo'}),
        mutation(a,'post',f'/{open_task}/cancel','lon06a-block-cancel',{'expected_version':1,'reason':'Bloqueo'}),
        mutation(a,'patch',f'/{open_task}','lon06a-block-link',{'expected_version':1,'appointment_id':'a_a','reason':'Bloqueo'})]
    check(all(response.status==503 for response in blocked) and count('clinical_patient_tasks')==before_tasks and count('clinical_patient_task_audit_events')==before_audit,'write window blocks all task commands and appointment link')
    set_window('OPEN')
    check(a.get(url).status==200,'safe read available after write window')
    check(count('clinical_record_entries')==1 and count('clinical_documents')==1,'legacy and pending-order evidence preserved')
    for context in contexts:context.close()
    browser.close()
    print('LON06A_DISPOSABLE_HTTP_GATE=PASS')
