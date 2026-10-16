#!/usr/bin/env python3
"""Actual normal deployment composition, original UI and own restricted synthetic DB only."""
import pathlib,json,urllib.request,urllib.error,http.cookiejar,ssl,uuid,subprocess,os,datetime
ROOT=pathlib.Path(__file__).resolve().parents[1];SUPPORT=pathlib.Path.home()/'Library/Application Support/MXMED/op-act01-rehearsal';OUT=pathlib.Path.home()/'Library/Application Support/MXMED/op-act01-evidence';BASE='https://127.0.0.1:8448'
CONFIG=json.loads((SUPPORT/'environment.json').read_text());CREDS=json.loads((SUPPORT/'credentials.json').read_text());DB=CONFIG['MXMED_DB_NAME'];assert DB.startswith('mxmed_operator_deploy_')
ALL=['agenda.appointments.read','agenda.appointments.create','agenda.appointments.reschedule','agenda.appointments.cancel','agenda.availability.read'];RESULTS={}
def sql(query,admin=False):
 e=dict(os.environ);e['MYSQL_PWD']='' if admin else CONFIG['MXMED_DB_PASS'];return subprocess.check_output(['mysql','-h127.0.0.1','-u'+('root' if admin else CONFIG['MXMED_DB_USER']),'-N','-B','-r',DB],input=query,text=True,env=e,stderr=subprocess.PIPE).strip()
def ok(name):RESULTS[name]='PASS';print(name+'=PASS',flush=True)
class Client:
 def __init__(self):self.http=urllib.request.build_opener(urllib.request.HTTPSHandler(context=ssl._create_unverified_context()),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf='';self.revision=0
 def request(self,method,path,body=None,headers=None):
  h={'Accept':'application/json','Origin':BASE,'X-CSRF-Token':self.csrf,'X-Grant-Revision':str(self.revision),**(headers or {})};data=None if body is None else json.dumps(body).encode()
  if data is not None:h['Content-Type']='application/json'
  try:r=self.http.open(urllib.request.Request(BASE+path,data=data,headers=h,method=method),timeout=40)
  except urllib.error.HTTPError as e:r=e
  raw=r.read().decode();v=json.loads(raw) if raw.lstrip().startswith('{') else {};return r.status,v,raw
 def login(self,role):
  status,v,_=self.request('GET','/api/identity/index.php/csrf-token');assert status==200,(status,v)
  status,v,_=self.request('POST','/api/identity/index.php/login',{**CREDS[role],'csrf_token':v['csrf_token']});assert status==200,(status,v)
  status,v,_=self.request('GET','/api/identity/index.php/current-session');assert status==200 and v.get('authenticated'),(status,v);self.csrf=v['csrf_token'];return self
 def state(self):
  status,v,_=self.request('GET','/api/profile-operators/index.php/bootstrap');assert status==200,(status,v);self.revision=v['data']['grant_revision'];return v['data']
 def cmd(self,action,input,expected=200,key=None):
  status,v,_=self.request('POST','/api/profile-operators/index.php/command',{'action':action,'input':input,'command_id':key or str(uuid.uuid4())});assert status==expected,(action,status,v);return v.get('data',v)
 def overview(self):
  status,v,_=self.request('GET','/api/profile-operators/index.php/overview');assert status==200,(status,v);return v['data']
def token(invitation):
 # Only the separately owned synthetic fixture may decrypt its queue in tests.
 # Same port used by the worker; clear material stays in process and never output.
 code='''<?php require 'modules/identity/http/IdentityHttpComposition.php'; Identity\\Http\\IdentityHttpComposition::registerAutoloader(); $p=new PDO('mysql:host=127.0.0.1;dbname='.getenv('MXMED_DB_NAME'),getenv('MXMED_DB_USER'),getenv('MXMED_DB_PASS'));$s=$p->prepare('SELECT ciphertext FROM auth_identity_notification_deliveries WHERE invitation_id=?');$s->execute([$argv[1]]);$c=$s->fetchColumn();echo sodium_crypto_secretbox_open(substr($c,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($c,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),base64_decode(getenv('MXMED_OPERATOR_DELIVERY_KEY')));'''
 e={**{k:v for k,v in os.environ.items() if k in ['PATH','HOME']},**CONFIG};return subprocess.check_output(['php','-r',code.removeprefix('<?php '),invitation],text=True,env=e)
def accept(client,i,alias=None):
 raw=token(i['invitation_id']);ident,secret=raw.split('.',1)
 status,v,_=client.request('POST','/api/profile-operators/index.php/inspect-invitation',{'invitation_id':ident,'token':secret});assert status==200,(status,v)
 data={'invitation_id':ident,'token':secret,'offer_revision':v['data']['offer_revision']}
 if alias is not None:data['alias']=alias
 return client.cmd('accept',data)
def run():
 owner=Client().login('owner');a=Client().login('operador-a');b=Client().login('operador-b');c=Client().login('operador-c');ok('canonical_identity_login')
 assert owner.overview()['available']==2
 assert Client().request('GET','/api/profile-operators/index.php/overview')[0]==401
 i=owner.cmd('invite',{'email':CREDS['operador-a']['email'],'alias':'Mary','capabilities':ALL});j=owner.cmd('invite',{'email':CREDS['operador-b']['email'],'capabilities':ALL,'role':'administrator'})
 owner.cmd('invite',{'email':CREDS['operador-c']['email'],'capabilities':[]},403);assert i['seat_position']==1 and j['seat_position']==2;ok('quota_pending_two_positions')
 assert sql("SELECT COUNT(*) FROM auth_identity_notification_deliveries WHERE state='queued' AND attempt_count=0;")=='2';ok('durable_encrypted_enqueue_no_dispatch')
 # Snapshot the browser-review offer privately before acceptance, never print secrets.
 pending=token(j['invitation_id']);(OUT/'pending-offer.json').write_text(json.dumps({'invitation':j['invitation_id'],'raw':pending}));os.chmod(OUT/'pending-offer.json',0o600)
 ma=accept(a,i);mb=accept(b,j,'Lupita');a.state();b.state();assert ma['personal_alias']=='Mary' and mb['personal_alias']=='Lupita';ok('verified_recipient_exact_offer_acceptance')
 assert a.request('GET','/api/profile-operators/index.php/overview')[0]==403
 for field,value in [('seat_position',2),('creator_snapshot',{}),('created_by_id','op02-owner')]:
  assert a.request('POST','/api/agenda/index.php/appointments',{field:value})[0]==403
 day=(datetime.date.today()+datetime.timedelta(days=3)).isoformat();payload={'consultorio_id':'1','patient_id':'p_op02patient0001','start_at':day+' 10:00:00','end_at':day+' 10:30:00','modality':'in_person'};key=str(uuid.uuid4())
 status,v,_=a.request('POST','/api/agenda/index.php/appointments',payload,{'Idempotency-Key':key});assert status==200,(status,v);appointment=v['data']['appointment_id']
 snapshot=sql("SELECT JSON_OBJECT('account',creator_account_id,'alias',creator_alias,'position',creator_position,'origin',origin_visual_key) FROM agenda_appointment_creator_snapshots WHERE appointment_id='"+appointment+"';");assert json.loads(snapshot)=={'account':'op02-operador-a','alias':'Mary','position':1,'origin':'operator-01'}
 active_payload={**payload,'start_at':day+' 13:00:00','end_at':day+' 13:30:00'};status,v,_=a.request('POST','/api/agenda/index.php/appointments',active_payload,{'Idempotency-Key':str(uuid.uuid4())});assert status==200,(status,v);historical_active=v['data']['appointment_id']
 owner.cmd('rename',{'membership_id':ma['membership_id'],'expected_presentation_revision':ma['presentation_revision'],'alias':'Tere'});owner.cmd('rename',{'membership_id':mb['membership_id'],'expected_presentation_revision':mb['presentation_revision'],'alias':'TERE'},409)
 status,v,_=a.request('POST','/api/agenda/index.php/appointments',payload,{'Idempotency-Key':key});assert status==200 and v['data']['appointment_id']==appointment;ok('alias_snapshot_and_create_idempotency')
 start=day+' 12:00:00';end=day+' 12:30:00'
 assert b.request('PATCH','/api/agenda/index.php/appointments/'+appointment+'/reschedule',{'from_start_at':payload['start_at'],'from_end_at':payload['end_at'],'to_start_at':start,'to_end_at':end})[0]==200
 assert b.request('POST','/api/agenda/index.php/appointments/'+appointment+'/cancel',{'expected_start_at':start})[0]==200
 assert snapshot==sql("SELECT JSON_OBJECT('account',creator_account_id,'alias',creator_alias,'position',creator_position,'origin',origin_visual_key) FROM agenda_appointment_creator_snapshots WHERE appointment_id='"+appointment+"';");ok('reschedule_cancel_keep_original_creator')
 matrix=[]
 paths=['/api/clinical/index.php/patients/p_op02patient0001/history','/api/clinical/index.php/prescriptions/a/sign','/api/clinical/index.php/consents/a/grant','/api/billing/invoices.php','/api/identity/governance.php','/api/media/physician-signature.php','/uploads/private/a.pdf','/api/agenda/index.php/operators','/api/agenda/index.php/operators/migration/apply','/modules/agenda/controllers/OperatorsController.php','/assets/js/app.js','/api/profiles/index.php/private/credentials']
 for path in paths:
  for method in ['GET','POST','PUT','PATCH','DELETE']:
   status,v,raw=a.request(method,path,None if method=='GET' else {});assert status==403,(method,path,status);assert 'OP02_PRIVATE_CANARY' not in raw;matrix.append([method,path,status])
 status,v,html=a.request('GET','/private-app.php');assert status==200 and 'id="ag_calendar"' in html and 'id="p-expediente"' not in html and 'OP02_PRIVATE_CANARY' not in html
 assert a.request('GET','/api/agenda/index.php/appointments?doctor_id=op02-other')[0]==403;ok('private_shell_and_cross_module_denial')
 owner.cmd('suspend',{'membership_id':ma['membership_id'],'expected_revision':1});assert owner.overview()['available']==0;assert a.request('GET','/api/agenda/index.php/appointments?from='+day+'%2000:00:00&to='+day+'%2023:59:59')[0]==403
 owner.cmd('reactivate',{'membership_id':ma['membership_id'],'expected_revision':1});a.state();ok('suspension_immediate_keeps_seat')
 # Test all five operation grants independently with the same deployed adapter.
 rev=1
 for grant in ALL:
  rev+=1;owner.cmd('grants',{'membership_id':ma['membership_id'],'expected_revision':rev-1,'capabilities':[grant]});state=a.state();assert state['capabilities']=={k:k==grant for k in ALL}
  cases=[('agenda.appointments.read','GET','/api/agenda/index.php/appointments?from='+day+'%2000:00:00&to='+day+'%2023:59:59',None),('agenda.availability.read','GET','/api/agenda/index.php/availability?consultorio_id=1&date='+day,None),('agenda.appointments.create','POST','/api/agenda/index.php/appointments',payload),('agenda.appointments.reschedule','PATCH','/api/agenda/index.php/appointments/'+appointment+'/reschedule',{'from_start_at':start,'from_end_at':end,'to_start_at':day+' 16:00:00','to_end_at':day+' 16:30:00'}),('agenda.appointments.cancel','POST','/api/agenda/index.php/appointments/'+appointment+'/cancel',{'expected_start_at':start})]
  for cap,method,path,body in cases:
   status,v,_=a.request(method,path,body,{'Idempotency-Key':str(uuid.uuid4())});assert (status!=403 if cap==grant else status==403),(grant,cap,status)
 ok('five_independent_grants')
 owner.cmd('grants',{'membership_id':ma['membership_id'],'expected_revision':rev,'capabilities':ALL});rev+=1;a.state()
 owner.cmd('revoke',{'membership_id':ma['membership_id'],'expected_revision':rev});assert owner.overview()['available']==1;assert a.request('GET','/api/agenda/index.php/appointments?from='+day+'%2000:00:00&to='+day+'%2023:59:59')[0]==403;ok('revocation_immediate')
 # Retain a pending canonical encrypted delivery for the original email-link UI test.
 k=owner.cmd('invite',{'email':CREDS['operador-c']['email'],'alias':'Tere','capabilities':ALL});raw=token(k['invitation_id']);(OUT/'browser-review.json').write_text(json.dumps({'invitation_id':k['invitation_id'],'raw':raw,'day':day,'appointment_id':historical_active}));os.chmod(OUT/'browser-review.json',0o600)
 (OUT/'http.json').write_text(json.dumps({'results':RESULTS,'denial_matrix':matrix},indent=2)+'\n');print('OP_ACT01_NORMAL_COMPOSITION_HTTP=PASS',flush=True)
def security_inventory():
 b=Client().login('operador-b');b.state();paths=sorted(set('/'+str(p.relative_to(ROOT)) for base in ['api','modules/clinical'] for p in (ROOT/base).rglob('*.php') if p.is_file()))
 paths=[p for p in paths if p!='/api/identity/index.php']
 matrix=[]
 for path in paths:
  for method in ['GET','POST','PUT','PATCH','DELETE']:
   status,v,raw=b.request(method,path,None if method=='GET' else {})
   assert status==403,(method,path,status)
   assert 'OP02_PRIVATE_CANARY' not in raw;matrix.append([method,path,status])
 for path in ['/api/clinical/index.php/patients/x/history','/api/agenda/index.php/operators/migration/apply','/uploads/private/x.pdf','/api/billing/invoices.php']:
  for prefix in ['/%61'+path[2:],'//'+path.lstrip('/')]:
   assert b.request('GET',prefix)[0]==403,prefix
 assert b.request('GET','/api/profile-operators/index.php/bootstrap',headers={'Origin':'https://evil.example'})[0]==403
 assert b.request('POST','/api/profile-operators/index.php/command',{'action':'invite','input':{},'command_id':str(uuid.uuid4())},headers={'X-CSRF-Token':'invalid'})[0]==403
 (OUT/'security-inventory.json').write_text(json.dumps(matrix,indent=2)+'\n');print('ACT01_DIRECT_HTTP_DENIALS='+str(len(matrix))+' PASS',flush=True)
def release():
 owner=Client().login('owner')
 for m in owner.overview()['members']:
  if m['status'] in ['active','suspended']:owner.cmd('revoke',{'membership_id':m['membership_id'],'expected_revision':m['revision']})
 for i in owner.overview()['invitations']:owner.cmd('cancel',{'invitation_id':i['invitation_id']})
 print('OWNED_REHEARSAL_CANONICAL_RELEASE=PASS')
if __name__=='__main__':
 import sys
 security_inventory() if '--security-inventory' in sys.argv else (release() if '--release' in sys.argv else run())
