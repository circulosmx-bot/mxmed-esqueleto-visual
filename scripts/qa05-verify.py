#!/usr/bin/env python3
"""Non-secret HTTP and isolation verification for the running QA05 instance."""
import http.cookiejar
import json
import os
import pathlib
import ssl
import subprocess
import urllib.error
import urllib.request

ORIGIN='https://127.0.0.1:8444'
SUPPORT=pathlib.Path.home()/'Library/Application Support/MXMED/qa05-admin'
CREDS=json.loads((SUPPORT/'credentials.json').read_text())
CTX=ssl.create_default_context();CTX.check_hostname=False;CTX.verify_mode=ssl.CERT_NONE

def client():
    jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPSHandler(context=CTX),urllib.request.HTTPCookieProcessor(jar));return opener,jar

def request(opener,path,method='GET',body=None,csrf=None,origin=True):
    data=None if body is None else json.dumps(body).encode()
    headers={'Accept':'application/json'}
    if data is not None: headers['Content-Type']='application/json'
    if csrf: headers['X-CSRF-Token']=csrf
    if origin: headers.update({'Origin':ORIGIN,'Referer':ORIGIN+'/private-app.php'})
    req=urllib.request.Request(ORIGIN+path,data=data,headers=headers,method=method)
    try: response=opener.open(req);status=response.status;raw=response.read()
    except urllib.error.HTTPError as error: status=error.code;raw=error.read()
    try: payload=json.loads(raw)
    except Exception: payload={}
    return status,payload

def login(role):
    opener,jar=client();status,pre=request(opener,'/api/identity/index.php/csrf-token');assert status==200
    entry=CREDS[role]
    status,result=request(opener,'/api/identity/index.php/login','POST',{'email':entry['email'],'password':entry['password'],'csrf_token':pre['csrf_token']},pre['csrf_token'])
    assert status==200 and result.get('authenticated') is True
    status,current=request(opener,'/api/identity/index.php/current-session');assert status==200
    context=current['application_access']['context'];assert context['professional']['role']==role and context['professional']['doctor_id']=='qa05-doctor'
    return opener,jar,current['csrf_token']

def check(label,condition):
    if not condition: raise AssertionError(label)
    print(label+'=PASS')

owner,owner_jar,owner_csrf=login('owner');check('QA05_19',True);check('QA05_22',True);check('QA05_40',True)
status,offices=request(owner,'/api/agenda/index.php/consultorios?doctor_id=qa05-doctor');check('QA05_23_READ',status==200 and offices.get('ok') and len(offices.get('data',[]))==3)
original=list(offices['meta']['presentation_order']);reverse=list(reversed(original));fingerprint=offices['meta']['presentation_order_fingerprint']
status,reordered=request(owner,'/api/agenda/index.php/consultorios/reorder','PUT',{'doctor_id':'qa05-doctor','consultorio_ids':reverse,'expected_order_fingerprint':fingerprint},owner_csrf);check('QA05_23',status==200 and reordered.get('ok'));check('QA05_26',reordered.get('data',{}).get('presentation_order')==reverse);check('QA05_27',reverse[0] == reordered['data']['presentation_order'][0])
status,hydrated=request(owner,'/api/agenda/index.php/consultorios?doctor_id=qa05-doctor');check('QA05_33',status==200 and hydrated['meta']['presentation_order']==reverse)
publication={row['consultorio_id']:bool(row['document_publication_enabled']) for row in hydrated['data']};publication_target=reverse[0]
status,published=request(owner,'/api/agenda/index.php/consultorios','PUT',{'doctor_id':'qa05-doctor','consultorio_id':publication_target,'document_publication_enabled':not publication[publication_target]},owner_csrf);assert status==200 and published.get('ok')
status,publication_read=request(owner,'/api/agenda/index.php/consultorios?doctor_id=qa05-doctor');after_publication={row['consultorio_id']:bool(row['document_publication_enabled']) for row in publication_read['data']};check('QA05_28',all(after_publication[key]==(not value if key==publication_target else value) for key,value in publication.items()))
status,_=request(owner,'/api/agenda/index.php/consultorios','PUT',{'doctor_id':'qa05-doctor','consultorio_id':publication_target,'document_publication_enabled':publication[publication_target]},owner_csrf);assert status==200
status,amenity=request(owner,'/api/agenda/index.php/consultorios','PUT',{'doctor_id':'qa05-doctor','consultorio_id':reverse[0],'local_parking':None,'accessible':1},owner_csrf);check('QA05_31',status==200 and amenity.get('data',{}).get('local_parking') is None);check('QA05_32',status==200 and amenity.get('data',{}).get('accessible') in [1,True])
phones=hydrated['meta']['emergency_contacts'];phone_fp=hydrated['meta']['emergency_contacts_fingerprint']
status,emergency=request(owner,'/api/agenda/index.php/consultorios/emergency-contacts','PUT',{'doctor_id':'qa05-doctor','emergency_contacts':phones,'expected_fingerprint':phone_fp},owner_csrf);check('QA05_29',status==200 and emergency.get('ok'));check('QA05_30',len(emergency.get('data',{}).get('emergency_contacts',[]))==2)
status,schedule=request(owner,'/api/agenda/index.php/schedule?doctor_id=qa05-doctor&consultorio_id=1');check('QA05_34',status==200 and schedule.get('ok'))
status,appointments=request(owner,'/api/agenda/index.php/appointments?doctor_id=qa05-doctor&from=2026-01-01%2000:00:00&to=2030-01-01%2000:00:00&limit=100');appointment_rows=appointments.get('data',[]) if isinstance(appointments.get('data'),list) else appointments.get('data',{}).get('items',[]);check('QA05_37',status==200 and len(appointment_rows)==5 and all(row.get('consultorio_id','') in ['1','2','3'] for row in appointment_rows))
status,cross=request(owner,'/api/agenda/index.php/consultorios?doctor_id=other-doctor');check('QA05_38',status==403 and cross.get('ok') is False)

admin,_,admin_csrf=login('administrator');check('QA05_20',True)
status,admin_write=request(admin,'/api/agenda/index.php/consultorios','PUT',{'doctor_id':'qa05-doctor','consultorio_id':'2','local_parking':0},admin_csrf);check('QA05_24',status==200 and admin_write.get('ok'));status,admin_schedule=request(admin,'/api/agenda/index.php/schedule?doctor_id=qa05-doctor&consultorio_id=2');check('QA05_35',status==200 and admin_schedule.get('ok'))
status,owner_only=request(admin,'/api/agenda/index.php/settings?doctor_id=qa05-doctor&consultorio_id=2');check('QA05_OWNER_ONLY_ADMIN_DENIAL',status==403)

collab,_,collab_csrf=login('collaborator');check('QA05_21',True)
status,denied=request(collab,'/api/agenda/index.php/consultorios/reorder','PUT',{'doctor_id':'qa05-doctor','consultorio_ids':original,'expected_order_fingerprint':hydrated['meta']['presentation_order_fingerprint']},collab_csrf);check('QA05_25',status==403 and denied.get('ok') is False and bool(denied.get('message')));check('QA05_39',denied.get('ok') is False)
status,denied_schedule=request(collab,'/api/agenda/index.php/schedule?doctor_id=qa05-doctor&consultorio_id=1');check('QA05_36',status==403 and denied_schedule.get('ok') is False)

# A QA cookie presented to localhost normal-local must not authenticate there.
qa_cookie=next(cookie.value for cookie in owner_jar if cookie.name=='__Host-mxmed_qa05_session')
normal_req=urllib.request.Request('https://localhost:8443/api/identity/index.php/current-session',headers={'Cookie':'__Host-mxmed_session='+qa_cookie,'Accept':'application/json'})
try: urllib.request.urlopen(normal_req,context=CTX);normal_status=200
except urllib.error.HTTPError as error: normal_status=error.code
check('QA05_05',normal_status==401)

# Browser-side cookie naming and write boundaries remain distinct from normal-local.
check('QA05_COOKIE_NAME',all(cookie.name!='__Host-mxmed_session' for cookie in owner_jar))
status,cross_origin=request(owner,'/api/agenda/index.php/consultorios','PUT',{'doctor_id':'qa05-doctor','consultorio_id':'1','accessible':1},owner_csrf,origin=False)
check('QA05_CROSS_ORIGIN_DENIAL',status==403 and cross_origin.get('ok') is False)
status,missing_csrf=request(owner,'/api/agenda/index.php/consultorios','PUT',{'doctor_id':'qa05-doctor','consultorio_id':'1','accessible':1},None)
check('QA05_CSRF_DENIAL',status==403 and missing_csrf.get('ok') is False)

print('QA05_HTTP_VERIFY=PASS')
