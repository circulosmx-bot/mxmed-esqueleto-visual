"""Actual batch endpoint with QA-only timed rule in a disposable schema."""
import json,os,subprocess,urllib.error,urllib.parse,urllib.request,uuid

BASE=os.environ['CAT03B_QA_BASE'];DB=os.environ['CAT03B_QA_DB']
assert DB.startswith('ordcomp01_qa_')
PATH='/api/clinical/index.php/doctors/d_labcat02a_order/patients/p_labcat02a_order/orders/batch'
def sql(query):return subprocess.check_output(['mysql','-N','-B','-r',DB,'-e',query],text=True).strip()
def request(path,body=None,key=None):
 headers={'Cookie':'PHPSESSID=labcat02a-owner','Accept':'application/json'}
 data=None
 if body is not None:
  data=json.dumps(body,ensure_ascii=False).encode();headers['Content-Type']='application/json';headers['Idempotency-Key']=key or body['order_composition_batch_uuid']
 req=urllib.request.Request(BASE+path,data=data,headers=headers)
 try:
  with urllib.request.urlopen(req,timeout=90) as response:status=response.status;raw=response.read();content_type=response.headers.get_content_type()
 except urllib.error.HTTPError as response:status=response.code;raw=response.read();content_type=response.headers.get_content_type()
 return status,json.loads(raw) if content_type=='application/json' else raw
def count():return int(sql('SELECT COUNT(*) FROM clinical_documents'))
def batch(items):return {'order_composition_batch_uuid':str(uuid.uuid4()),'order_routing_version':1,'orders':items}
imaging={'order_routing_group_key':'GENERAL_IMAGING','priority':'Rutinaria','indication':'QA imagen','order_items':[{'study_type_key':'rx_chest'}]}
def lab(requested):return {'order_routing_group_key':'CLINICAL_LAB','priority':'Rutinaria','indication':'QA duración','order_items':[{'study_type_key':'urine_osmolality',**({'specimen_collection_requirements':requested} if requested is not None else {})}]}
before=count();incomplete=batch([imaging,lab(None)])
status,error=request(PATH,incomplete)
assert status==422 and error['order_routing_group_key']=='CLINICAL_LAB' and count()==before,(status,error,count(),before)
print('QA_INCOMPLETE_BATCH_REJECTION_ZERO_ORDERS=PASS',flush=True)
complete=batch([imaging,lab({'version':1,'collection_mode':'TIMED','requested_duration_minutes':1440})])
status,result=request(PATH,complete)
assert status==201 and count()==before+2 and len(result['data']['orders'])==2,(status,result)
issued=result['data']['orders'];lab_doc=next(item for item in issued if item['order_routing_group_key']=='CLINICAL_LAB')
snapshot=json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(lab_doc['document_id'])}"))['order_items'][0]
assert snapshot['specimen_collection_requirements']=={'version':1,'specimen_type_key':'URINE','collection_mode':'TIMED','requested_duration_minutes':1440}
assert snapshot['order_item_id']
print('QA_COMPLETE_BATCH_TIMED_SNAPSHOT=PASS',flush=True)
status,replay=request(PATH,complete)
assert status==200 and replay['data']==result['data'] and count()==before+2
print('QA_BATCH_IDEMPOTENCY_EXACT_SNAPSHOT=PASS',flush=True)
query=urllib.parse.urlencode({'uuid':lab_doc['document_uuid'],'doctor_id':'d_labcat02a_order'})
status,html=request('/modules/clinical/ui/portable-order.php?'+query)
assert status==200 and 'Recolección: 24 horas'.encode() in html,(status,html[:200])
status,pdf=request('/modules/clinical/ui/portable-order-pdf.php?'+query)
assert status==200 and pdf.startswith(b'%PDF-') and pdf.rstrip().endswith(b'%%EOF')
print('QA_PORTABLE_TIMED_HTML_PDF=PASS',flush=True)
