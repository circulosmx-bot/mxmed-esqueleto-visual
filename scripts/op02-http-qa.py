#!/usr/bin/env python3
"""Real HTTPS security QA; synthetic OP02 DB only, no token/credential output."""
import os, datetime, hashlib, http.cookiejar, importlib.util, json, pathlib, ssl, subprocess, urllib.request, urllib.error, uuid
ROOT=pathlib.Path(__file__).resolve().parents[1]
REHEARSAL=os.environ.get('MXMED_OP_UI03_REHEARSAL')=='1'
SUPPORT=pathlib.Path.home()/'Library/Application Support/MXMED'/('opui03-rehearsal' if REHEARSAL else 'op02-admin')
BASE='https://127.0.0.1:8446' if REHEARSAL else 'https://127.0.0.1:8445';DB='mxmed_gate4d_preview_opui03' if REHEARSAL else 'mxmed_gate4d_preview_op02'
CREDS=json.loads((SUPPORT/'credentials.json').read_text());RESULTS={};MATRIX=[]
spec=importlib.util.spec_from_file_location('op02env',ROOT/'scripts/op02-env.py');envmod=importlib.util.module_from_spec(spec);spec.loader.exec_module(envmod)
BEFORE=envmod.snapshot_normal()
def sql(query):
    return subprocess.run(['mysql','-uroot','-h127.0.0.1','-N','-B',DB],input=query,text=True,capture_output=True,check=True).stdout.strip()
def check(ok,label):
    if not ok:raise AssertionError(label)
def passed(n,label):RESULTS[f'OP02_{n:02}']={'result':'PASS','evidence':label};print(f'OP02_{n:02}=PASS {label}',flush=True)
class Client:
    def __init__(self):self.cookies=http.cookiejar.CookieJar();self.http=urllib.request.build_opener(urllib.request.HTTPSHandler(context=ssl._create_unverified_context()),urllib.request.HTTPCookieProcessor(self.cookies));self.csrf='';self.revision=0
    def request(self,method,path,body=None,headers=None):
        h={'Accept':'application/json','Origin':BASE,'X-CSRF-Token':self.csrf,'X-Grant-Revision':str(self.revision)};h.update(headers or {})
        data=None if body is None else json.dumps(body).encode()
        if data is not None:h['Content-Type']='application/json'
        req=urllib.request.Request(BASE+path,data=data,headers=h,method=method)
        try:r=self.http.open(req,timeout=20)
        except urllib.error.HTTPError as e:r=e
        raw=r.read().decode();v=json.loads(raw) if raw.strip().startswith('{') else {}
        return r.status,v,raw
    def login(self,role):
        status,v,_=self.request('GET','/api/identity/index.php/csrf-token');check(status==200,'preauth csrf')
        status,v,_=self.request('POST','/api/identity/index.php/login',{**CREDS[role],'csrf_token':v['csrf_token']});check(status==200 and v.get('authenticated') is True,'canonical login '+role)
        status,v,_=self.request('GET','/api/identity/index.php/current-session');check(status==200 and v.get('authenticated'),'canonical session')
        self.csrf=v['csrf_token'];return self
    def state(self):
        status,v,_=self.request('GET','/api/profile-operators/index.php/bootstrap');check(status==200,'bounded bootstrap');self.revision=v['data']['grant_revision'];return v['data']
    def cmd(self,action,values,expected=200,key=None):
        status,v,raw=self.request('POST','/api/profile-operators/index.php/command',{'action':action,'input':values,'command_id':key or str(uuid.uuid4())});check(status==expected,f'command {action} expected {expected} got {status}');return v.get('data',v)
    def invite(self,email,caps,role='collaborator'):
        return self.cmd('invite',{'email':email,'capabilities':caps,'role':role})
    def open(self,id,expected=200):
        status,v,_=self.request('POST','/api/profile-operators/index.php/open-invitation',{'invitation_id':id});check(status==expected,'recipient outbox open');return v.get('data',v)
    def respond(self,offer,action='accept',expected=200):
        return self.cmd(action,{'invitation_id':offer['invitation_id'],'token':offer['token'],'offer_revision':offer['offer_revision']},expected)
    def overview(self):
        status,v,_=self.request('GET','/api/profile-operators/index.php/overview');check(status==200,'owner overview');return v['data']
READ='agenda.appointments.read';CREATE='agenda.appointments.create';RESCHEDULE='agenda.appointments.reschedule';CANCEL='agenda.appointments.cancel';AVAIL='agenda.availability.read';ALL=[READ,CREATE,RESCHEDULE,CANCEL,AVAIL]
def run():
    try:
        owner=Client().login('owner');a=Client().login('operador-a');b=Client().login('operador-b');wrong=Client().login('operador-c');passed(1,'canonical_login_real_valkey')
        check(owner.overview()['available']==2,'initial two seats');passed(2,'owner_two_seats')
        ia=owner.invite(CREDS['operador-a']['email'],[READ]);passed(3,'first_invitation_http')
        ib=owner.invite(CREDS['operador-b']['email'],[CREATE],'administrator');check(owner.overview()['reserved']==2,'last reserved');passed(4,'second_reserves_last')
        owner.cmd('invite',{'email':CREDS['operador-c']['email'],'capabilities':[]},403);passed(5,'third_denied_http')
        check(sql('SELECT COUNT(*) FROM auth_operator_invitation_outbox WHERE ciphertext IS NOT NULL;')=='2','durable outbox');passed(6,'encrypted_durable_outbox')
        wrong.open(ia['invitation_id'],403);passed(8,'wrong_recipient_denied')
        oa=a.open(ia['invitation_id']);ma=a.respond(oa);check(a.state()['capabilities'][READ],'read accepted');passed(7,'exact_offer_acceptance')
        a.respond(oa,expected=403);passed(9,'token_replay_denied')
        ob=b.open(ib['invitation_id']);new=owner.cmd('reissue',{'invitation_id':ib['invitation_id'],'email':CREDS['operador-b']['email'],'capabilities':[CREATE],'role':'administrator'});b.respond(ob,expected=403);passed(10,'revised_old_offer_denied')
        mb=b.respond(b.open(new['invitation_id']));a.state();b.state()
        owner.cmd('grants',{'membership_id':ma['membership_id'],'expected_revision':1,'capabilities':[READ,AVAIL]});check(a.state()['capabilities'][AVAIL],'edit persisted');passed(11,'owner_grant_editor_backend')
        check(Client().login('operador-a').state()['capabilities'][AVAIL],'fresh login persistent grant');passed(12,'reload_new_session_persistence')
        # Existing read command; a clinical canary is present in the underlying patient.
        day=(datetime.date.today()+datetime.timedelta(days=7)).isoformat();start=day+' 10:00:00';end=day+' 10:30:00';newstart=day+' 12:00:00';newend=day+' 12:30:00'
        import urllib.parse
        def appointments(client):return client.request('GET','/api/agenda/index.php/appointments?'+urllib.parse.urlencode({'from':day+' 00:00:00','to':day+' 23:59:59'}))
        check(appointments(a)[0]==200,'read works');passed(18,'read_actual_repository')
        payload={'consultorio_id':'1','patient_id':'p_op02patient0001','start_at':start,'end_at':end,'modality':'in_person'}
        status,created,_=b.request('POST','/api/agenda/index.php/appointments',payload,{'Idempotency-Key':str(uuid.uuid4())});check(status==200,f'create actual writer {status}');appointment=created['data']['appointment_id']
        check(appointments(b)[0]==403,'create no read');passed(19,'create_without_read_http')
        check(b.request('POST',f'/api/agenda/index.php/appointments/{appointment}/cancel',{'expected_start_at':start})[0]==403,'create no cancel');passed(20,'create_not_cancel')
        move={'from_start_at':start,'from_end_at':end,'to_start_at':newstart,'to_end_at':newend}
        check(b.request('PATCH',f'/api/agenda/index.php/appointments/{appointment}/reschedule',move)[0]==403,'reschedule not granted')
        owner.cmd('grants',{'membership_id':mb['membership_id'],'expected_revision':1,'capabilities':[RESCHEDULE]});b.state()
        check(appointments(b)[0]==403,'reschedule no read');check(b.request('PATCH',f'/api/agenda/index.php/appointments/{appointment}/reschedule',move)[0]==200,'existing reschedule writer');passed(21,'reschedule_independent_actual_writer')
        check(b.request('POST',f'/api/agenda/index.php/appointments/{appointment}/cancel',{'expected_start_at':newstart})[0]==403,'reschedule no cancel')
        owner.cmd('grants',{'membership_id':mb['membership_id'],'expected_revision':2,'capabilities':[CANCEL]});b.state()
        check(b.request('POST',f'/api/agenda/index.php/appointments/{appointment}/cancel',{'expected_start_at':newstart})[0]==200,'existing cancel writer');passed(22,'cancel_independent_actual_writer')
        avail='/api/agenda/index.php/availability?'+urllib.parse.urlencode({'consultorio_id':'1','date':day});check(a.request('GET',avail)[0]==200 and b.request('GET',avail)[0]==403,'availability independent');passed(23,'availability_actual_controller')
        owner.cmd('grants',{'membership_id':ma['membership_id'],'expected_revision':2,'capabilities':[READ]});passed(13,'grant_revoked_by_new_revision')
        check(a.request('GET',avail)[0]==403,'old grant immediate denies');a.state();check(a.request('GET',avail)[0]==403,'new revision no availability');passed(14,'immediate_no_refresh_revocation')
        owner.cmd('suspend',{'membership_id':ma['membership_id'],'expected_revision':3});passed(15,'suspend_http')
        check(appointments(a)[0]==403,'suspended deny');check(owner.overview()['available']==0,'suspended seat kept');passed(16,'suspended_denied_seat_retained')
        owner.cmd('reactivate',{'membership_id':ma['membership_id'],'expected_revision':3});a.state()
        # Every negative is an actual HTTPS request with a genuine accepted operator cookie.
        categories={24:['/api/agenda/index.php/schedule','/api/agenda/index.php/settings','/api/agenda/index.php/availability/blocks'],25:['/api/agenda/index.php/consultorios'],26:['/api/profile-operators/index.php/overview','/api/profile-operators/index.php/command'],27:['/api/clinical/index.php/patients/p_op02patient0001/history','/api/patients/index.php/patients/p_op02patient0001','/api/clinical/index.php/patients/p_op02patient0001/diagnoses'],28:['/api/clinical/index.php/patients/p_op02patient0001/prescriptions','/api/clinical/index.php/prescriptions/op02-document/emit'],29:['/api/media/physician-signature.php','/api/clinical/index.php/prescriptions/op02-document/sign','/api/clinical/index.php/consents/op02-document/grant'],30:['/api/clinical-documents.php','/api/clinical/index.php/documents/op02-document/download','/uploads/private/op02-document.pdf'],31:['/api/billing/invoices.php','/api/billing/issuance.php','/api/billing/patient-profiles.php','/api/subscriptions/index.php'],32:['/api/identity/governance.php','/api/internal/media-review/approve.php','/api/internal/media-review/source-download.php','/api/profiles/index.php/private/credentials']}
        for number,paths in categories.items():
            for path in paths:
                for method in ['GET','POST','PUT','PATCH','DELETE']:
                    # The command route is allowed only for legitimate owner writes.
                    body={'action':'invite','command_id':str(uuid.uuid4()),'input':{'email':CREDS['operador-c']['email'],'capabilities':[]}}
                    status,v,raw=a.request(method,path,None if method=='GET' else body)
                    check(status==403,f'deny matrix {method} {path} {status}');check('OP02_PRIVATE_CANARY' not in raw,'no clinical canary');MATRIX.append({'method':method,'path':path,'status':status})
            passed(number,'actual_https_denial_'+str(len(paths)*5)+'_requests')
        owner.cmd('grants',{'membership_id':ma['membership_id'],'expected_revision':3,'capabilities':ALL});a.state()
        check(a.request('POST','/api/agenda/index.php/appointments/op02-appointment-1/cancel',{'expected_start_at':start})[0]==403,'cross physician resource');check(a.request('GET','/api/agenda/index.php/appointments?doctor_id=op02-other')[0]==403,'profile switching')
        forged=payload|{'doctor_id':'op02-other'};check(a.request('POST','/api/agenda/index.php/appointments',forged)[0]==403,'forged body scope');check(a.request('POST','/api/agenda/index.php/appointments',payload|{'patient_id':'p_op02patient0002'},{'Idempotency-Key':str(uuid.uuid4())})[0]==403,'cross physician booking subject');passed(33,'cross_profile_server_scope')
        status,_,html=a.request('GET','/private-app.php');check(status==200 and 'id="mmSidebar"' in html and 'id="ag_calendar"' in html and 'id="p-expediente"' not in html and 'OP02_PRIVATE_CANARY' not in html and 'clinical_record' not in html and '/assets/js/app.js' not in html,'bounded shell')
        bootstrap=a.state();check('notes_admin' not in json.dumps(bootstrap) and 'birthdate' not in json.dumps(bootstrap),'minimal hydration');passed(34,'bounded_shell_no_clinical_hydration')
        aliases=['/api/agenda/index.php/operators','/api/agenda/index.php/operators/migration/apply','/api/agenda/index.php/operators/legacy/pause','/api/agenda/index.php/operators/legacy/reactivate','/api/agenda/index.php/operators/legacy/restore','/api/agenda/index.php/appointments/'+appointment+'/confirm','/api/agenda/index.php/appointments/'+appointment+'/no_show','/api/agenda/index.php/appointments/'+appointment+'/no-show','/api/agenda/index.php/waitlist','/modules/agenda/controllers/OperatorsController.php','/private-app.php/alternate','/api/_lib/clinical_documents.php','/api/media/index.php','/api/profiles/index.php/private/doctor-contact-points']
        aliases+=['/assets/js/app.js','/partials/private-app/agenda.php','/partials/private-app/operators.php','/partials/private-app/operator-inbox.php','/modules/identity/operators/operator-workspace.php']
        # All existing PHP API entry files, including alternate dispatch/download adapters.
        aliases+=['/'+str(p.relative_to(ROOT)) for p in (ROOT/'api').rglob('*.php') if p.name!='mxmed-db.config.php']
        for path in sorted(set(aliases)):
            for method in ['GET','POST','PUT','PATCH','DELETE']:
                status,_,raw=a.request(method,path,None if method=='GET' else {'forged':True});check(status==403,f'alias {method} {path} {status}');check('OP02_PRIVATE_CANARY' not in raw,'alias clinical leak');MATRIX.append({'method':method,'path':path,'status':status})
        # Even the owner cannot invoke the legacy operator credential writers in OP02.
        for path in aliases[:5]:check(owner.request('POST',path,{'full_name':'forged','password':'not-a-credential'})[0]==403,'owner legacy writer closed')
        passed(35,'all_existing_api_php_entries_and_legacy_aliases_denied')
        check(sql("SELECT COUNT(*) FROM platform_audit_events WHERE action='PROFILE_OPERATOR_AGENDA_ACTION' AND real_actor_reference='account:op02-operador-b';")!='0','actual operator attribution')
        check(sql("SELECT COUNT(*) FROM agenda_appointment_events WHERE actor_id='op02-operador-b' AND actor_role='operator';")!='0','existing lifecycle actor attribution');passed(36,'canonical_and_lifecycle_actor_attribution')
        check(a.request('POST','/api/agenda/index.php/appointments',payload,{'X-CSRF-Token':'bad'})[0]==403,'bad csrf');check(a.request('GET',avail,headers={'Origin':'https://hostile.invalid'})[0]==403,'bad origin');check(a.request('POST','/api/agenda/index.php/appointments',payload,{'Origin':''})[0]==403,'missing write origin')
        alien=Client();check(alien.request('GET','/api/profile-operators/index.php/bootstrap',headers={'Cookie':'; '.join('__Host-mxmed_session='+c.value for c in a.cookies)})[0]==401,'canonical normal cookie not accepted');passed(37,'csrf_origin_cookie_isolation')
        check(sql('SELECT COUNT(*) FROM agenda_operators;')=='0' and sql('SELECT COUNT(*) FROM auth_accounts;')=='5','no legacy credentials');passed(38,'canonical_accounts_only')
        owner.cmd('grants',{'membership_id':ma['membership_id'],'expected_revision':4,'capabilities':ALL});a.state()
        sql("UPDATE profile_subscriptions SET contracted_plan_code='free',plan_code='free' WHERE entity_id='op02-doctor';")
        check(appointments(a)[0]==403,'entitlement denial');sql("UPDATE profile_subscriptions SET contracted_plan_code='optimum',plan_code='optimum' WHERE entity_id='op02-doctor';");check(appointments(a)[0]==200,'entitlement restore');passed(41,'real_subscription_readmodel')
        # Inject SQL insert failure solely inside the isolated schema; no HTTP bypass/fault flag.
        count=sql('SELECT COUNT(*) FROM agenda_appointments;')
        sql("CREATE TRIGGER op02_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure';")
        try:
            failpayload=payload|{'start_at':day+' 15:00:00','end_at':day+' 15:30:00'}
            check(a.request('POST','/api/agenda/index.php/appointments',failpayload,{'Idempotency-Key':str(uuid.uuid4())})[0]==503,'audit failure denies');check(count==sql('SELECT COUNT(*) FROM agenda_appointments;'),'audit failure rollback')
        finally:sql('DROP TRIGGER IF EXISTS op02_audit_failure;')
        passed(42,'existing_writer_and_audit_rollback')
        owner.cmd('revoke',{'membership_id':mb['membership_id'],'expected_revision':3});check(owner.overview()['available']==1,'revocation frees');check(appointments(b)[0]==403,'revoked no action');passed(17,'revoke_frees_seat')
        check(sql('SELECT COUNT(*) FROM auth_operator_invitation_outbox WHERE ciphertext IS NOT NULL;')=='0','closed tokens no longer retained in outbox')
        passed(45,'local_encrypted_outbox_no_mail_provider')
        listeners=subprocess.run(['lsof','-nP',*[('-iTCP:'+str(p)) for p in envmod.PORTS.values()],'-sTCP:LISTEN'],capture_output=True,text=True,check=True).stdout
        check('*:' not in listeners and '127.0.0.1' in listeners,'loopback listeners');passed(46,'loopback_actual_listeners')
        # Retain the original physical SQL concurrency + dual-approval owner transition gates.
        regression=subprocess.run(['php',str(ROOT/'modules/identity/tests/OperatorFoundationTest.php')],capture_output=True,text=True)
        check(regression.returncode==0,'OP01 physical regression '+regression.stderr[-300:]);passed(39,'OP01_independent_process_concurrency');passed(40,'OP01_governed_owner_commit_race')
        for test in ['modules/clinical/qa/clin_auth02a_contract.php','modules/agenda/tests/Cut01AAuthorityCompositionRootsTest.php','modules/agenda/tests/Gate8DAppointmentLifecycleIdempotencyTest.php','modules/agenda/tests/PublicDailyAvailabilityTest.php']:
            r=subprocess.run(['php',str(ROOT/test)]+(['--security-regression'] if 'clin_auth02a_contract' in test else ['--semantic-regression'] if 'Gate8DAppointment' in test else []),capture_output=True,text=True);check(r.returncode==0,'regression '+test+' '+r.stderr[-300:])
        passed(47,'clinical_containment_contract');passed(48,'OP01_nine_Identity_GOV03A_and_Agenda_regressions')
        check(BEFORE==envmod.snapshot_normal(),'normal_local_qa05_all_table_checksums');passed(43,'normal_local_unchanged');passed(44,'qa05_unchanged')
        check(len(RESULTS)==48,'all 48 recorded')
        print('OP02_HTTP_SECURITY_QA=PASS',flush=True)
    finally:
        evidence=SUPPORT/'evidence';evidence.mkdir(mode=0o700,exist_ok=True)
        (evidence/'http-qa.json').write_text(json.dumps({'results':RESULTS,'denial_matrix':MATRIX},indent=2)+'\n')

if __name__=='__main__':
    if not REHEARSAL:raise SystemExit('Mutation QA requires MXMED_OP_UI03_REHEARSAL=1; persistent OP02 review data must be preserved.')
    run()
