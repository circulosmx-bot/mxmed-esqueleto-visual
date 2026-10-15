#!/usr/bin/env python3
"""Populate an EMPTY OP02 fixture through actual invitation/login/Agenda HTTP."""
import datetime, importlib.util, json, pathlib, subprocess, uuid
ROOT=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('op02qa',ROOT/'scripts/op02-http-qa.py');qa=importlib.util.module_from_spec(spec);spec.loader.exec_module(qa)
owner=qa.Client().login('owner');a=qa.Client().login('operador-a');b=qa.Client().login('operador-b')
qa.check(owner.overview()['available']==2,'requires empty OP02 fixture; never overwrite review data')
ia=owner.invite(qa.CREDS['operador-a']['email'],[qa.READ,qa.CREATE,qa.RESCHEDULE,qa.AVAIL]);ib=owner.invite(qa.CREDS['operador-b']['email'],[qa.READ,qa.CANCEL],'administrator')
a.respond(a.open(ia['invitation_id']));b.respond(b.open(ib['invitation_id']));a.state();b.state()
day=(datetime.date.today()+datetime.timedelta(days=1)).isoformat()
status,result,_=a.request('POST','/api/agenda/index.php/appointments',{'consultorio_id':'1','patient_id':'p_op02patient0001','start_at':day+' 10:00:00','end_at':day+' 10:30:00','modality':'in_person'},{'Idempotency-Key':str(uuid.uuid4())});qa.check(status==200,'review appointment through canonical existing writer')
# A real process restart proves grants, encrypted delivery metadata and the
# existing canonical Valkey session persist. No new session is minted to pass.
subprocess.run(['python3',str(ROOT/'scripts/op02-env.py'),'stop'],check=True,stdout=subprocess.DEVNULL)
subprocess.run(['python3',str(ROOT/'scripts/op02-env.py'),'start'],check=True,stdout=subprocess.DEVNULL)
qa.check(owner.overview()['occupied']==2 and a.state()['capabilities'][qa.CREATE] and b.state()['capabilities'][qa.CANCEL],'restart persistence including existing cookies')
qa.check(qa.BEFORE==qa.envmod.snapshot_normal(),'normal-local/QA05 unchanged')
(qa.SUPPORT/'evidence/review-state.json').write_text(json.dumps({'url':qa.BASE+'/private-app.php#p-ag-operadores','demo_date':day,'appointment_id':result['data']['appointment_id'],'operators':2,'restart_persistence':'PASS','normal_local_qa05':'UNCHANGED'},indent=2)+'\n')
print('OP02_REVIEW_READY=true');print('OP02_RESTART_PERSISTENCE=PASS');print('OP02_DEMO_DATE='+day)
