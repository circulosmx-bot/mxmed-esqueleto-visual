"""Authenticated scoped read, demographic authority and immutable disposable DB checks."""
import json,os,subprocess,urllib.request,urllib.error
base=os.environ['VITALREF_QA_BASE'];db=os.environ['VITALREF_QA_DB']
def sql(query):return subprocess.check_output(['mysql','-N',db,'-e',query],text=True)
def request(path='',method='GET',cookie='vitalref-qa'):
 req=urllib.request.Request(base+'/api/clinical/index.php/patients/'+path,method=method,headers={'Cookie':'PHPSESSID='+cookie} if cookie else {})
 try:r=urllib.request.urlopen(req)
 except urllib.error.HTTPError as e:r=e
 return r.status,json.loads(r.read()),dict(r.headers)
before=sql('SELECT * FROM patients_patients ORDER BY patient_id');links=sql('SELECT * FROM patients_doctor_links ORDER BY link_id');tables=sql('SHOW TABLES')
status,adult,headers=request('adult/vital-references')
assert status==200 and adult['ok'] and 'no-store' in headers['Cache-Control']
assert adult['data']['patient_context']['population']=='adult'
assert adult['data']['items'][0]['source']['source_id']=='aha_acc_bp_2025'
assert request('child/vital-references')[1]['data']['items'][0]['display_reference']=='Referencia pediátrica: requiere edad, sexo y talla.'
assert request('teen/vital-references')[1]['data']['items'][0]['source']['source_id']=='aap_bp_2017'
assert request('missing/vital-references')[1]['data']['patient_context']['population']=='unknown'
assert request('future/vital-references')[1]['data']['patient_context']['population']=='unknown'
for patient in ['other','inactive','absent']:
 status,result,_=request(patient+'/vital-references');assert status==404 and result['data'] is None
status,result,_=request('adult/vital-references',cookie='');assert status in [401,403] and result['data'] is None
for method in ['POST','PUT','PATCH','DELETE']:
 status,result,_=request('adult/vital-references',method);assert status==404 and result['data'] is None
for query in ['age=36','birthdate=1990-01-01','sex=female','context=adult','review=1']:
 status,result,_=request('missing/vital-references?'+query);assert status==400 and result['data'] is None
assert sql('SELECT * FROM patients_patients ORDER BY patient_id')==before
assert sql('SELECT * FROM patients_doctor_links ORDER BY link_id')==links
assert sql('SHOW TABLES')==tables
assert len(adult['data']['items'])==9
for key in ['patient_id','birthdate','age','sex','display_name']:
 assert '"'+key+'"' not in json.dumps(adult['data'])
print('VITALREF01_HTTP_GATE=PASS authenticated scope; canonical DOB; no query override; GET only; no schema/data writes')
