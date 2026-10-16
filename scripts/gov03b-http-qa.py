#!/usr/bin/env python3
"""Real PHP route + canonical Identity/Valkey + disposable TLS proxy. No normal port."""
import os,pathlib,json,socket,subprocess,tempfile,ssl,http.client,threading,signal,time,importlib.util
ROOT=pathlib.Path(__file__).resolve().parents[1]
evidence=pathlib.Path(os.environ['GOV03B_QA_EVIDENCE'])
fixture=json.loads((evidence/'http-fixture.json').read_text())
config={**os.environ,**fixture['environment'],'MXMED_IDENTITY_GOVERNANCE_ENABLED':'1','MXMED_GOVERNANCE_PROXY_KEY':'synthetic-proxy-'+'p'*40,'HTTPS':'on'}
with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
spec=importlib.util.spec_from_file_location('gov_proxy',ROOT/'scripts/normal-local-https-proxy.py');proxy=importlib.util.module_from_spec(spec);spec.loader.exec_module(proxy)
with tempfile.TemporaryDirectory(prefix='mxmed-gov03b-http-') as d, (evidence/'http-private-server.log').open('wb') as log:
 base=pathlib.Path(d);base.chmod(0o700)
 subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(base/'key.pem'),'-out',str(base/'cert.pem'),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
 sessions=base/'php-sessions';sessions.mkdir(mode=0o700)
 (sessions/'sess_sim02legacy').write_text('user_id|s:1:"1";doctor_id|s:1:"1";actor_role|s:6:"doctor";entity_type|s:6:"doctor";entity_id|s:1:"1";')
 php=subprocess.Popen(['php','-d','session.save_path='+str(sessions),'-S','127.0.0.1:'+str(port),'-t',str(ROOT),str(ROOT/'scripts/canonical-private-router.php')],cwd=ROOT,env=config,stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
 tls=None
 try:
  for _ in range(50):
   if php.poll() is not None: raise RuntimeError('owned PHP server failed')
   try:
    with socket.create_connection(('127.0.0.1',port),timeout=.1): break
   except OSError: time.sleep(.1)
  else: raise RuntimeError('owned PHP server unavailable')
  tls=proxy.make_server(str(base/'cert.pem'),str(base/'key.pem'),port=0,backend_port=port)
  tls.governance_transport_key=config['MXMED_GOVERNANCE_PROXY_KEY'].encode()
  threading.Thread(target=tls.serve_forever,daemon=True).start()
  context=ssl.create_default_context(cafile=str(base/'cert.pem'))
  def request(method,path,body=None,origin='https://localhost:8443',cookie=True,csrf=True,direct=False):
   client=http.client.HTTPConnection('127.0.0.1',port,timeout=10) if direct else http.client.HTTPSConnection('localhost',tls.server_port,context=context,timeout=10)
   headers={'Host':'localhost:8443','Origin':origin,'Content-Type':'application/json','X-Forwarded-Proto':'https'}
   if cookie: headers['Cookie']='__Host-mxmed_session='+fixture['cookie']
   value=dict(body or {})
   if csrf: value['csrf_token']=fixture['csrf']
   client.request(method,'/api/identity/governance.php/'+path,body=json.dumps(value),headers=headers)
   response=client.getresponse();status=response.status;response.read();client.close();return status
  cases=[('GET_no_mutation',405,lambda:request('GET','cases/initial-owner')),
   ('bootstrap_not_public',404,lambda:request('POST','bootstrap/first-director',cookie=False,csrf=False)),
   ('anonymous',401,lambda:request('POST','cases/initial-owner',cookie=False)),
   ('wrong_origin',403,lambda:request('POST','cases/initial-owner',origin='https://wrong.invalid')),
   ('missing_CSRF',403,lambda:request('POST','cases/initial-owner',csrf=False)),
   ('cross_case_step_up',403,lambda:request('POST','cases/govcase_other_profile/beneficiary-step-up',{'profile_doctor_id':'another-profile'})),
   ('environment_HTTPS_flag_and_forwarded_header_insufficient',503,lambda:request('POST','cases/initial-owner',direct=True))]
  for name,expected,fn in cases:
   actual=fn();assert actual==expected,(name,actual,expected);print('PASS HTTP_'+name+'_'+str(actual))
  private_paths=[
   '/api/identity/index.php/current-session',
   '/api/patients/index.php/doctors/1/patients/search?q=synthetic',
   '/api/clinical/index.php/patients/p_sim02missing/history',
   '/api/clinical-documents.php?action=list&patient_id=p_sim02missing',
   '/api/clinical-documents.php?action=get&id=1',
   '/api/hospital-stays.php?action=current&patient_id=p_sim02missing',
   '/api/evolution-note-generate.php',
   '/api/profiles/index.php/private/doctor/1',
   '/api/profiles/professional-information.php',
   '/api/media/physician-signature.php','/api/media/gallery.php',
   '/api/media/owner-review.php','/api/media/signature-handoff.php',
   '/api/billing/issuance.php','/api/billing/invoices.php','/api/billing/patient-profiles.php',
   '/api/subscriptions/index.php/context/current',
   '/api/subscriptions/index.php/entities/doctor/1/current',
   '/api/agenda/index.php/appointments?doctor_id=1']
  for kind,token in fixture.get('expired_cookies',{}).items():
   for path in private_paths:
    client=http.client.HTTPSConnection('localhost',tls.server_port,context=context,timeout=10)
    client.request('GET',path,headers={'Host':'localhost:8443','Cookie':'__Host-mxmed_session='+token+'; PHPSESSID=sim02legacy','X-User-Id':'1','X-Doctor-Id':'1'})
    response=client.getresponse();status=response.status;response.read();client.close()
    assert status==401,('SIM02_'+kind,path,status)
   print('PASS SIM02_HTTP_'+kind+'_'+str(len(private_paths))+'_private_routes_legacy_cookie_and_headers_denied')
   for path in ['/api/clinical-documents.php?action=save','/api/clinical/index.php/patients/p_sim02missing/history','/api/hospital-stays.php?action=start','/api/hospital-stays.php?action=close','/api/evolution-note-generate.php']:
    client=http.client.HTTPSConnection('localhost',tls.server_port,context=context,timeout=10)
    client.request('PUT' if '/history' in path else 'POST',path,body='{}',headers={'Host':'localhost:8443','Content-Type':'application/json','Cookie':'__Host-mxmed_session='+token+'; PHPSESSID=sim02legacy'})
    response=client.getresponse();status=response.status;response.read();client.close();assert status==401,(kind,path,status)
   print('PASS SIM02_HTTP_'+kind+'_legacy_mutations_denied_before_writer')
  client=http.client.HTTPSConnection('localhost',tls.server_port,context=context,timeout=10)
  client.request('GET','/api/identity/index.php/current-session',headers={'Host':'localhost:8443','Cookie':'__Host-mxmed_session='+fixture['observation_cookie']})
  response=client.getresponse();status=response.status;response.read();client.close();assert status==200
  with socket.create_connection(('127.0.0.1',int(config['MXMED_SESSION_STORE_PORT'])),timeout=5) as stream:
   key=fixture['observation_key'].encode();stream.sendall(b'*2\r\n$3\r\nGET\r\n$'+str(len(key)).encode()+b'\r\n'+key+b'\r\n');reader=stream.makefile('rb');line=reader.readline();assert line.startswith(b'$');record=json.loads(reader.read(int(line[1:-2])))
  assert record['last_seen_at']==fixture['observation_last_seen_at'],'current-session must not keep idle session alive'
  print('PASS SIM02_HTTP_current_session_is_observation_not_activity')
 finally:
  if tls is not None: tls.shutdown();tls.server_close()
  if php.poll() is None: os.killpg(php.pid,signal.SIGTERM);php.wait(timeout=10)
  (evidence/'http-fixture.json').unlink(missing_ok=True)
print('GOV03B_REAL_HTTP_SECURITY=PASS')
