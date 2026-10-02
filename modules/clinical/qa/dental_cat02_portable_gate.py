"""Disposable authenticated issuance, read-model, portable HTML and PDF proof."""
import json,os,re,subprocess,urllib.request,urllib.error,urllib.parse,uuid

BASE=os.environ['DENTAL_QA_BASE']
DB=os.environ['DENTAL_QA_DB']
OWNER='PHPSESSID=dental-cat02-owner'

def get(path,cookie=OWNER):
 req=urllib.request.Request(BASE+path,headers={'Cookie':cookie} if cookie else {})
 try:
  with urllib.request.urlopen(req,timeout=50) as response:return response.status,response.headers,response.read()
 except urllib.error.HTTPError as error:return error.code,error.headers,error.read()

def post(path,body):
 req=urllib.request.Request(BASE+path,method='POST',data=json.dumps(body,ensure_ascii=False).encode(),
   headers={'Cookie':OWNER,'Content-Type':'application/json','Accept':'application/json','Idempotency-Key':str(uuid.uuid4())})
 try:
  with urllib.request.urlopen(req,timeout=50) as response:return response.status,json.load(response)
 except urllib.error.HTTPError as error:return error.code,json.load(error)

def sql(query):return subprocess.check_output(['mysql','-N','-B','-r',DB,'-e',query],text=True).strip()

location={'contract_version':1,'numbering_system':'FDI_ISO_3950','dentition_mode':'PERMANENT',
 'coverage':'LOCALIZED','selected_teeth':['16','17']}
items=[{'study_type_key':'dental_cbct','dental_location':location},
 {'study_type_key':'dental_intraoral_scan','dental_location':{'contract_version':1,'numbering_system':'FDI_ISO_3950','arch':'BOTH'}},
 {'study_type_key':'glucose'}]
body={'patient_id':'p_dental_cat02','document_type':'orders','title':'Orden dental QA','summary':'Orden dental QA',
 'event_datetime':'2026-10-02 12:00:00','payload':{'source':'dental_cat02_qa','priority':'Rutinaria',
 'indication':'Validación de contrato dental','order_items':items}}
status,response=post('/api/clinical/index.php/doctors/d_dental_cat02/patients/p_dental_cat02/documents',body)
assert status==201 and response['ok'],(status,response)
id=int(response['data']['document_id']);order_uuid=sql(f'SELECT document_uuid FROM clinical_documents WHERE id={id}')
stored=json.loads(sql(f'SELECT payload_json FROM clinical_documents WHERE id={id}'))
assert stored['order_payload_version']==2 and len(stored['order_items'])==3
assert stored['order_items'][0]['dental_location']['selected_teeth']==['16','17']
assert 'Piezas 16, 17' in stored['order_items'][0]['dental_location_label']
assert 'dental_location' not in stored['order_items'][2]
print('QA_ISSUED_DENTAL_MIXED_ORDER_SNAPSHOT=PASS')

endpoint=f'/api/clinical/index.php/doctors/d_dental_cat02/portable-orders/{order_uuid}'
status,_,raw=get(endpoint);model=json.loads(raw)['data']
assert status==200 and 'Piezas 16, 17' in model['studies'][0]['dental_context']
assert model['studies'][1]['dental_context']=='Ambos maxilares' and model['studies'][2]['dental_context'] is None
assert '16 · primer molar superior derecho' in model['studies'][0]['dental_context']
print('QA_PORTABLE_DENTAL_READ_MODEL=PASS')

query=urllib.parse.urlencode({'uuid':order_uuid,'doctor_id':'d_dental_cat02'})
path='/modules/clinical/ui/portable-order.php?'+query
status,headers,html=get(path);html=html.decode()
assert status==200 and 'no-store' in headers.get('Cache-Control','')
assert 'Piezas 16, 17' in html and 'Ambos maxilares' in html and 'primer molar superior derecho' in html
assert not any(word in html for word in ('FDI_ISO_3950','selected_teeth','dental_location_label'))
assert get(path,None)[0]==403
assert get('/modules/clinical/ui/portable-order.php?'+urllib.parse.urlencode({'uuid':order_uuid,'doctor_id':'d_dental_other'}))[0]==403
print('QA_AUTHENTICATED_PORTABLE_DENTAL_HTML=PASS')

pdf_path='/modules/clinical/ui/portable-order-pdf.php?'+query
status,headers,pdf=get(pdf_path)
assert status==200 and headers.get_content_type()=='application/pdf' and pdf.startswith(b'%PDF-') and pdf.rstrip().endswith(b'%%EOF'),(status,pdf[:120])
assert get(pdf_path,None)[0]==403
assert get('/modules/clinical/ui/portable-order-pdf.php?'+urllib.parse.urlencode({'uuid':str(uuid.uuid4()),'doctor_id':'d_dental_cat02'}))[0]==403
print('QA_AUTHENTICATED_PORTABLE_DENTAL_PDF=PASS')
