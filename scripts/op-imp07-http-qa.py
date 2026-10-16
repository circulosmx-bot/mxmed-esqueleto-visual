#!/usr/bin/env python3
"""Actual canonical HTTP commands in the newly owned OP-IMP07 disposable DB only."""
import os,importlib.util,pathlib,json,datetime,subprocess,uuid
os.environ['MXMED_OP_IMP07_REHEARSAL']='1'
ROOT=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('op02',ROOT/'scripts/op02-http-qa.py');q=importlib.util.module_from_spec(spec);spec.loader.exec_module(q)
assert q.DB=='mxmed_gate4d_preview_opimp07'
OUT=pathlib.Path.home()/'Library/Application Support/MXMED/op02-admin/evidence/op-imp07';OUT.mkdir(parents=True,exist_ok=True)
RESULTS={}
def passed(label):RESULTS[label]='PASS';print('OP07_'+label+'=PASS',flush=True)
def snap(id):return json.loads(q.sql("SELECT JSON_OBJECT('account',creator_account_id,'membership',creator_membership_id,'assignment',creator_presentation_id,'alias',creator_alias,'position',creator_position,'origin',origin_visual_key,'version',snapshot_version,'created_at',created_at_utc) FROM agenda_appointment_creator_snapshots WHERE appointment_id='"+id+"';"))
def no_sql(query):
 try:q.sql(query);raise AssertionError('immutable write unexpectedly accepted')
 except subprocess.CalledProcessError:pass

def run():
 owner=q.Client().login('owner');a=q.Client().login('operador-a');b=q.Client().login('operador-b');c=q.Client().login('operador-c')
 initial=owner.overview();assert initial['available']==2 and initial['presentation_ready'] and initial['members']==[], 'fresh fixture required; never reset';passed('01_empty')
 ia=owner.cmd('invite',{'email':q.CREDS['operador-a']['email'],'alias':'Mary','capabilities':q.ALL});ib=owner.cmd('invite',{'email':q.CREDS['operador-b']['email'],'capabilities':q.ALL,'role':'administrator'})
 assert ia['seat_position']==1 and ib['seat_position']==2 and ib['personal_alias'] is None;passed('02_03_05_06_positions_alias_reservations')
 owner.cmd('invite',{'email':q.CREDS['operador-c']['email'],'alias':'Tere','capabilities':[]},403);passed('04_quota')
 oa=a.open(ia['invitation_id']);assert oa['personal_alias']=='Mary' and oa['seat_position']==1
 a.cmd('accept',{'invitation_id':oa['invitation_id'],'token':oa['token'],'offer_revision':1,'alias':'Forged'},409)
 ma=a.respond(oa);ob=b.open(ib['invitation_id']);mb=b.cmd('accept',{'invitation_id':ob['invitation_id'],'token':ob['token'],'offer_revision':1,'alias':'Lupita'})
 assert ma['personal_alias']=='Mary' and mb['personal_alias']=='Lupita';a.state();b.state();passed('07_accept_alias_exact_owner_offer')
 day=(datetime.date.today()+datetime.timedelta(days=4)).isoformat();start=day+' 14:00:00';end=day+' 14:30:00';payload={'consultorio_id':'1','patient_id':'p_op02patient0001','start_at':start,'end_at':end,'modality':'in_person'}
 for field,value in [('creator_alias','Forged'),('creator_snapshot',{'alias':'Forged'}),('operator_slot',2),('created_by_id','op02-owner'),('snapshot_version',1)]:
  assert a.request('POST','/api/agenda/index.php/appointments',payload|{field:value},{'Idempotency-Key':str(uuid.uuid4())})[0]==403
 passed('22_no_creator_forgery')
 creation_key=str(uuid.uuid4())
 status,result,_=a.request('POST','/api/agenda/index.php/appointments',payload,{'Idempotency-Key':creation_key});assert status==200,(status,result)
 appointment=result['data']['appointment_id'];original=snap(appointment)
 status,detail_result,_=a.request('POST','/api/agenda/index.php/appointments',payload|{'start_at':day+' 13:00:00','end_at':day+' 13:30:00'},{'Idempotency-Key':str(uuid.uuid4())});assert status==200
 detail_appointment=detail_result['data']['appointment_id']
 assert original['account']=='op02-operador-a' and original['membership']==ma['membership_id'] and original['assignment']==ma['presentation_id'];assert original['alias']=='Mary' and original['position']==1 and original['origin']=='operator-01' and original['version']==1;passed('18_19_20_21_actual_writer_snapshot')
 no_sql("UPDATE agenda_appointment_creator_snapshots SET creator_alias='Forged' WHERE appointment_id='"+appointment+"';")
 no_sql("DELETE FROM agenda_appointment_creator_snapshots WHERE appointment_id='"+appointment+"';")
 no_sql("UPDATE agenda_appointments SET created_by_id='op02-operador-b' WHERE appointment_id='"+appointment+"';");passed('26_physical_immutability')
 no_sql((ROOT/'modules/agenda/db/migrations/2026_10_15_12_rollback_appointment_creator_snapshots.sql').read_text());assert snap(appointment)==original;passed('populated_snapshot_recovery_refused')
 renamed=owner.cmd('rename',{'membership_id':ma['membership_id'],'expected_presentation_revision':ma['presentation_revision'],'alias':'Tere'})
 status,replayed,_=a.request('POST','/api/agenda/index.php/appointments',payload,{'Idempotency-Key':creation_key});assert status==200 and replayed['data']['appointment_id']==appointment and snap(appointment)==original;passed('create_replay_after_rename_preserves_snapshot')
 owner.cmd('rename',{'membership_id':mb['membership_id'],'expected_presentation_revision':mb['presentation_revision'],'alias':'TERE'},409)
 a.cmd('rename',{'membership_id':ma['membership_id'],'expected_presentation_revision':renamed['presentation_revision'],'alias':'Evil'},403)
 owner.cmd('rename',{'membership_id':ma['membership_id'],'expected_presentation_revision':renamed['presentation_revision'],'alias':'Two Words'},422);passed('08_09_12_owner_rename_collisions')
 owner.cmd('suspend',{'membership_id':mb['membership_id'],'expected_revision':1});assert owner.overview()['available']==0;owner.cmd('reactivate',{'membership_id':mb['membership_id'],'expected_revision':1});b.state();passed('15_suspend_retains')
 moved=day+' 16:00:00';movedend=day+' 16:30:00'
 status,result,_=b.request('PATCH','/api/agenda/index.php/appointments/'+appointment+'/reschedule',{'from_start_at':start,'from_end_at':end,'to_start_at':moved,'to_end_at':movedend});assert status==200,(status,result);assert snap(appointment)==original;passed('23_reschedule_retains_snapshot')
 status,result,_=b.request('POST','/api/agenda/index.php/appointments/'+appointment+'/cancel',{'expected_start_at':moved});assert status==200,(status,result);assert snap(appointment)==original;passed('24_cancel_retains_snapshot')
 assert q.sql("SELECT COUNT(*) FROM agenda_appointment_events WHERE appointment_id='"+appointment+"' AND actor_id='op02-operador-b';")=='2';passed('separate_lifecycle_actor')
 owner.cmd('revoke',{'membership_id':ma['membership_id'],'expected_revision':1})
 ic=owner.cmd('invite',{'email':q.CREDS['operador-c']['email'],'alias':'Tere','capabilities':q.ALL});oc=c.open(ic['invitation_id']);mc=c.respond(oc);c.state()
 assert mc['seat_position']==1 and mc['presentation_id']!=ma['presentation_id'] and snap(appointment)==original;passed('14_17_26_replacement_preserves_old_person')
 state=owner.state();assert state['operator_presentations']==[{'seat_position':1,'personal_alias':'Tere'},{'seat_position':2,'personal_alias':'Lupita'}];passed('25_verified_current_legend_projection')
 status,rows,_=owner.request('GET','/api/agenda/index.php/appointments?from='+day+'%2000:00:00&to='+day+'%2023:59:59');assert status==200
 row=next(r for r in rows['data'] if r['appointment_id']==appointment);assert row['creator_snapshot']['alias']=='Mary' and row['creator_snapshot']['position']==1 and 'account' not in row['creator_snapshot'] and 'creator_account_id' not in row['creator_snapshot'];passed('historical_reader_snapshot_minimal')
 for path in ['/api/clinical/index.php/patients/p_op02patient0001/history','/api/media/profile-photo.php','/api/profile-operators/index.php/overview','/api/identity/governance.php']:
  assert c.request('GET',path)[0]==403
 passed('30_31_33_scoped_nonclinical_authority')
 # Alias update and its audit append must commit or roll back together.
 q.sql("CREATE TRIGGER op07_synthetic_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure';")
 try:owner.cmd('rename',{'membership_id':mc['membership_id'],'expected_presentation_revision':mc['presentation_revision'],'alias':'Rollback'},503)
 finally:q.sql('DROP TRIGGER op07_synthetic_audit_failure;')
 assert next(r for r in owner.overview()['members'] if r['membership_id']==mc['membership_id'])['personal_alias']=='Tere';passed('32_audit_failure_rolls_back_alias')
 (OUT/'review-state.json').write_text(json.dumps({'appointment_id':detail_appointment,'canceled_appointment_id':appointment,'day':day,'original':original,'members':[mb,mc],'old_membership':ma['membership_id']},indent=2)+'\n')
 (OUT/'focused-http.json').write_text(json.dumps(RESULTS,indent=2)+'\n');print('OP07_FOCUSED_HTTP=PASS',flush=True)
if __name__=='__main__':run()
