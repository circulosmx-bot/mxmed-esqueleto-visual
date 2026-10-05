"""PROC-CAT02B authenticated HTTP, PDF and exact-item proof on disposable MySQL."""
import html,json,os,subprocess,tempfile,urllib.error,urllib.parse,urllib.request,uuid
from pathlib import Path

BASE=os.environ['PROCCAT02B_QA_BASE'];DB=os.environ['PROCCAT02B_QA_DB']
assert DB.startswith('proccat02b_qa_')
ROOT_DIR=Path(__file__).resolve().parents[3]
route=json.loads((ROOT_DIR/'modules/clinical/catalog/study_order_routing_v1.json').read_text())
scope=json.loads((ROOT_DIR/'modules/clinical/catalog/diagnostic_procedure_scope_v1.json').read_text())
new={'colposcopy_diagnostic':'GYNECOLOGY_DIAGNOSTICS','hysteroscopy_diagnostic':'GYNECOLOGY_DIAGNOSTICS','cystoscopy_diagnostic':'UROLOGY_DIAGNOSTICS'}
assert route['version']==2 and {k:route['studies'][k] for k in new}==new
assert set(scope['studies'])==set(new) and all(x['scope']=='DIAGNOSTIC_ONLY' and x['allowed_service_modes']==['ON_SITE'] and x['optional_biopsy_intent']=='NONE_V1' for x in scope['studies'].values())
prior=json.loads(subprocess.check_output(['git','show','02dcbc4e1a2ff5eb618de79825814a613e340e66:modules/clinical/catalog/study_order_routing_v1.json'],cwd=ROOT_DIR,text=True))
assert all(route['studies'][key]==value for key,value in prior['studies'].items())
assert all(route['groups'][key]==value for key,value in prior['groups'].items())
assert len(set(route['groups'])-set(prior['groups']))==2
print('QA_SCOPE_ROUTING_PARITY_AND_EXISTING_ASSIGNMENTS=PASS',flush=True)
DOCTOR='d_proccat02b';PATIENT='p_proccat02b';ROOT=f'/api/clinical/index.php/doctors/{DOCTOR}'
def sql(query):return subprocess.check_output(['mysql','-N','-B','-r',DB,'-e',query],text=True).strip()
def request(path,body=None,upload=False,key=None):
 headers={'Cookie':'PHPSESSID=proccat02b-owner','Accept':'application/json'};data=None
 if body is not None:
  headers['Idempotency-Key']=key or body.get('order_composition_batch_uuid',str(uuid.uuid4()))
  if upload:
   boundary='qa'+uuid.uuid4().hex;parts=[]
   for name,value in body.items():
    if isinstance(value,(dict,list)):value=json.dumps(value,ensure_ascii=False)
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
   parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="report.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()+b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\r\n')
   parts.append(f'--{boundary}--\r\n'.encode());data=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
  else:data=json.dumps(body,ensure_ascii=False).encode();headers['Content-Type']='application/json'
 try:
  with urllib.request.urlopen(urllib.request.Request(BASE+path,data=data,headers=headers),timeout=90) as res:status=res.status;raw=res.read();mime=res.headers.get_content_type()
 except urllib.error.HTTPError as res:status=res.code;raw=res.read();mime=res.headers.get_content_type()
 return status,json.loads(raw) if mime=='application/json' else raw
def order(group,keys):return {'order_routing_group_key':group,'priority':'Rutinaria','indication':'Evaluación diagnóstica QA','order_items':[{'study_type_key':k} for k in keys]}
def batch(*orders,key=None):return {'order_composition_batch_uuid':key or str(uuid.uuid4()),'order_routing_version':2,'orders':list(orders)}
def issue(command):return request(f'{ROOT}/patients/{PATIENT}/orders/batch',command)
def payload(doc):return json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(doc['document_id'])}"))
def counts():return tuple(int(sql(f'SELECT COUNT(*) FROM {table}')) for table in ['clinical_documents','clinical_idempotency_requests'])
def pdf(doc,names):
 query=urllib.parse.urlencode({'uuid':doc['document_uuid'],'doctor_id':DOCTOR})
 code,body=request('/modules/clinical/ui/portable-order.php?'+query);assert code==200,(code,body[:200])
 readable=html.unescape(body.decode())
 for name in names:assert name in readable,(name,readable[-1000:])
 for forbidden in ['GYNECOLOGY_DIAGNOSTICS','UROLOGY_DIAGNOSTICS','colposcopy_diagnostic','cystoscopy_diagnostic','hysteroscopy_diagnostic','biopsia','stent','terapéutica']:
  assert forbidden not in readable,forbidden
 code,body=request('/modules/clinical/ui/portable-order-pdf.php?'+query);assert code==200 and body.startswith(b'%PDF-') and body.rstrip().endswith(b'%%EOF'),(code,body[:100])
 with tempfile.TemporaryDirectory(prefix='proccat02b-pdf-') as directory:
  path=Path(directory)/'order.pdf';path.write_bytes(body)
  extracted=subprocess.check_output(['swift','-e','import Foundation; import PDFKit; let d=PDFDocument(url: URL(fileURLWithPath: CommandLine.arguments.last!))!; print(d.string ?? "")',str(path)],text=True)
  for name in names:assert name in extracted,(name,extracted[:1000])
 return True
assert sql('SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1')=='287'
assert sql("SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1 AND category_key IN ('ENDOSCOPIA','PROCEDIMIENTOS_DIAGNOSTICOS')")=='16'
assert sql("SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1 AND category_key='PROCEDIMIENTOS_DIAGNOSTICOS'")=='3'
print('QA_CATALOG_284_TO_287_AND_PROCEDURES_13_TO_16=PASS',flush=True)
for term,key in [('colp','colposcopy_diagnostic'),('colpos','colposcopy_diagnostic'),('video colpo','colposcopy_diagnostic'),('cisto','cystoscopy_diagnostic'),('cistou','cystoscopy_diagnostic'),('video cisto','cystoscopy_diagnostic'),('histero','hysteroscopy_diagnostic'),('histeroscopia','hysteroscopy_diagnostic'),('panendo','egd_eda_base'),('colon','colonoscopy_base'),('bronco','bronchoscopy_base'),('ebus','ebus_base'),('laringo','laryngoscopy_base')]:
 code,data=request(ROOT+'/study-types?'+urllib.parse.urlencode({'search':term,'limit':100}));assert code==200,(term,code,data)
 assert key in {row['study_type_key'] for row in data['data']['items']},(term,key,[r['study_type_key'] for r in data['data']['items']])
print('QA_NEW_AND_EXISTING_SEARCH=PASS',flush=True)
gyne=order('GYNECOLOGY_DIAGNOSTICS',['colposcopy_diagnostic','hysteroscopy_diagnostic']);uro=order('UROLOGY_DIAGNOSTICS',['cystoscopy_diagnostic']);lab=order('CLINICAL_LAB',['glucose'])
for group,key in [('GYNECOLOGY_DIAGNOSTICS','colposcopy_diagnostic'),('GYNECOLOGY_DIAGNOSTICS','hysteroscopy_diagnostic'),('UROLOGY_DIAGNOSTICS','cystoscopy_diagnostic')]:
 code,out=issue(batch(order(group,[key])));assert code==201,(group,key,code,out)
 assert [x['study_type_key'] for x in payload(out['data']['orders'][0])['order_items']]==[key]
print('QA_THREE_SINGLE_ORDERS=PASS',flush=True)
code,out=issue(batch(gyne));assert code==201,(code,out)
gdoc=out['data']['orders'][0];gp=payload(gdoc)
assert gdoc['order_routing_group_key']=='GYNECOLOGY_DIAGNOSTICS' and len(gp['order_items'])==2 and len({x['order_item_id'] for x in gp['order_items']})==2
pdf(gdoc,['Colposcopia diagnóstica','Histeroscopia diagnóstica']);print('QA_GYNECOLOGY_ONE_ORDER_TWO_ITEMS_PDF=PASS',flush=True)
code,out=issue(batch(uro));assert code==201,(code,out)
udoc=out['data']['orders'][0];pdf(udoc,['Cistoscopia diagnóstica']);print('QA_UROLOGY_SEPARATE_ORDER_PDF=PASS',flush=True)
for orders,expected in [(batch(gyne,uro),2),(batch(gyne,uro,lab),3)]:
 code,out=issue(orders);assert code==201,(code,out)
 docs=out['data']['orders'];assert len(docs)==expected and len({x['order_routing_group_key'] for x in docs})==expected
 assert sum(len(payload(doc)['order_items']) for doc in docs)==(3 if expected==2 else 4)
print('QA_MIXED_ROUTING_ATOMIC_BATCH=PASS',flush=True)
for good in [gyne,uro]:
 command=batch(good);before=counts();code,first=issue(command);assert code==201,(code,first)
 middle=counts();code,second=issue(command);assert code in (200,201) and second['data']['orders'][0]['document_id']==first['data']['orders'][0]['document_id'] and counts()==middle and middle>before,(code,first,second,before,middle,counts())
print('QA_GYNECOLOGY_UROLOGY_IDEMPOTENCY=PASS',flush=True)
result={'patient_id':PATIENT,'document_type':'lab_result','title':'Reporte diagnóstico QA','event_datetime':'2026-10-05 12:00:00','provenance':'Proveedor QA','payload':{'source':'res02a_linked_result','related_order_document_uuid':gdoc['document_uuid'],'related_order_item_ids':[gp['order_items'][0]['order_item_id']],'provenance':'Proveedor QA'}}
code,out=request(f'{ROOT}/patients/{PATIENT}/documents',result,upload=True);assert code==201,(code,out)
result_id=out['data'].get('document_uuid') or out['data']['document_id']
stored=json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='"+str(result_id)+"'"))
assert stored['related_order_item_ids']==[gp['order_items'][0]['order_item_id']]
print('QA_RESULT_EXACT_ITEM_LINK=PASS',flush=True)
for name in ['colposcopy_biopsy','operative_hysteroscopy','cystoscopy_stent','therapeutic_action','unknown_procedure_parameter','sedation','preparation']:
 key='colposcopy_diagnostic' if name.startswith('colposcopy') else 'hysteroscopy_diagnostic' if name.startswith('operative') else 'cystoscopy_diagnostic'
 item={'study_type_key':key,name:True};before=counts();code,out=issue(batch(order('GYNECOLOGY_DIAGNOSTICS' if key!='cystoscopy_diagnostic' else 'UROLOGY_DIAGNOSTICS',[key]) | {'order_items':[item]}))
 assert code==422 and counts()==before,(name,code,out)
print('QA_UNSUPPORTED_PARAMETERS_ZERO_WRITES=PASS',flush=True)
