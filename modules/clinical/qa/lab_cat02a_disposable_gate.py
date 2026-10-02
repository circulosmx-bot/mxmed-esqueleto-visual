"""Disposable authenticated order, exact catalog identity and portable-output proof."""
import json,os,subprocess,urllib.request,urllib.error,urllib.parse,uuid
BASE=os.environ['LAB_CAT02A_QA_BASE'];DB=os.environ['LAB_CAT02A_QA_DB']
OWNER='PHPSESSID=labcat02a-owner'
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
body={'patient_id':'p_labcat02a_order','document_type':'orders','title':'Orden mixta de estudios QA','summary':'4 estudios · Rutinaria',
 'event_datetime':'2026-10-02 12:00:00','payload':{'source':'tax03c_catalog_composer','order_area':'Estudios diagnósticos','priority':'Rutinaria',
 'indication':'Validación desechable LAB-CAT02A','order_items':[{'study_type_key':'lab_coombs_directo'},{'study_type_key':'glucose'},
 {'study_type_key':'rx_chest'},{'study_category':'LABORATORIO','study_display_name':'Prueba personalizada QA'}]}}
status,response=post('/api/clinical/index.php/doctors/d_labcat02a_order/patients/p_labcat02a_order/documents',body)
assert status==201 and response['ok'],(status,response)
id=int(response['data']['document_id']);order_uuid=sql(f'SELECT document_uuid FROM clinical_documents WHERE id={id}')
stored=json.loads(sql(f'SELECT payload_json FROM clinical_documents WHERE id={id}'))
assert stored['order_payload_version']==2 and len(stored['order_items'])==4
assert [item.get('study_type_key') for item in stored['order_items']]==['lab_coombs_directo','glucose','rx_chest',None]
assert len({item['order_item_id'] for item in stored['order_items']})==4
print('QA_MIXED_ORDER_AND_CANONICAL_IDENTITY=PASS')
endpoint=f'/api/clinical/index.php/doctors/d_labcat02a_order/portable-orders/{order_uuid}'
status,_,raw=get(endpoint);model=json.loads(raw)['data']
assert status==200 and len(model['studies'])==4
assert all(any(name in study['name'] for study in model['studies']) for name in ['Coombs directo','Glucosa','RX Tórax','Prueba personalizada QA'])
print('QA_PORTABLE_READ_MODEL=PASS')
query=urllib.parse.urlencode({'uuid':order_uuid,'doctor_id':'d_labcat02a_order'})
path='/modules/clinical/ui/portable-order.php?'+query
status,headers,html=get(path);html=html.decode()
assert status==200 and 'no-store' in headers.get('Cache-Control','')
assert all(name in html for name in ['Coombs directo','Glucosa','RX Tórax','Prueba personalizada QA'])
assert get(path,None)[0]==403
assert get('/modules/clinical/ui/portable-order.php?'+urllib.parse.urlencode({'uuid':order_uuid,'doctor_id':'d_labcat02a_foreign'}))[0]==403
print('QA_AUTHENTICATED_PORTABLE_HTML=PASS')
pdf_path='/modules/clinical/ui/portable-order-pdf.php?'+query
status,headers,pdf=get(pdf_path)
assert status==200 and headers.get_content_type()=='application/pdf' and pdf.startswith(b'%PDF-') and pdf.rstrip().endswith(b'%%EOF'),(status,pdf[:120])
assert get(pdf_path,None)[0]==403
print('QA_AUTHENTICATED_PORTABLE_PDF=PASS')
