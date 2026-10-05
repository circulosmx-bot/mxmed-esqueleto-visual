"""FUNC-CAT02B authenticated disposable MySQL, real PHP and PDF regression."""
import html,json,os,subprocess,tempfile,urllib.error,urllib.parse,urllib.request,uuid
from pathlib import Path

BASE=os.environ['FUNCCAT02B_QA_BASE'];DB=os.environ['FUNCCAT02B_QA_DB']
assert DB.startswith('funccat02b_qa_')
ROOT=Path(__file__).resolve().parents[3]
ROUTING=json.loads((ROOT/'modules/clinical/catalog/study_order_routing_v1.json').read_text())
DOCTOR='d_funccat02b';PATIENT='p_funccat02b';API=f'/api/clinical/index.php/doctors/{DOCTOR}'
def sql(query):return subprocess.check_output(['mysql','-N','-B','-r',DB,'-e',query],text=True).strip()
def request(path,body=None,cookie=True,key=None):
 headers={'Accept':'application/json'}
 if cookie:headers['Cookie']='PHPSESSID=funccat02b-owner'
 data=None
 if body is not None:
  data=json.dumps(body,ensure_ascii=False).encode();headers['Content-Type']='application/json';headers['Idempotency-Key']=key or body.get('order_composition_batch_uuid',str(uuid.uuid4()))
 try:
  with urllib.request.urlopen(urllib.request.Request(BASE+path,data=data,headers=headers),timeout=90) as response:status=response.status;raw=response.read();mime=response.headers.get_content_type()
 except urllib.error.HTTPError as response:status=response.code;raw=response.read();mime=response.headers.get_content_type()
 return status,json.loads(raw) if mime=='application/json' else raw
def upload_result(path,body):
 boundary='qa'+uuid.uuid4().hex;parts=[]
 for name,value in body.items():
  if isinstance(value,(dict,list)):value=json.dumps(value,ensure_ascii=False)
  parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
 parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="report.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()+b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\r\n')
 parts.append(f'--{boundary}--\r\n'.encode())
 headers={'Cookie':'PHPSESSID=funccat02b-owner','Accept':'application/json','Content-Type':'multipart/form-data; boundary='+boundary,'Idempotency-Key':str(uuid.uuid4())}
 try:
  with urllib.request.urlopen(urllib.request.Request(BASE+path,data=b''.join(parts),headers=headers),timeout=90) as response:status=response.status;raw=response.read();mime=response.headers.get_content_type()
 except urllib.error.HTTPError as response:status=response.code;raw=response.read();mime=response.headers.get_content_type()
 return status,json.loads(raw) if mime=='application/json' else raw
def order(group,items):return {'order_routing_group_key':group,'priority':'Rutinaria','indication':'Evaluación diagnóstica QA','order_items':items}
def batch(*orders,key=None):return {'order_composition_batch_uuid':key or str(uuid.uuid4()),'order_routing_version':ROUTING['version'],'orders':list(orders)}
def item(key,params=None):return {'study_type_key':key,**({'functional_order_parameters':{'version':1,**params}} if params is not None else {})}
def issue(command):return request(f'{API}/patients/{PATIENT}/orders/batch',command)
def payload(doc):return json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(doc['document_id'])}"))
def counts():return tuple(int(sql(f'SELECT COUNT(*) FROM {table}')) for table in ['clinical_documents','clinical_idempotency_requests'])
def check_pdf(doc,labels):
 query=urllib.parse.urlencode({'uuid':doc['document_uuid'],'doctor_id':DOCTOR})
 code,body=request('/modules/clinical/ui/portable-order.php?'+query);assert code==200,(code,body[:200]);markup=html.unescape(body.decode())
 for label in labels:assert label in markup,(label,markup[:1000])
 code,body=request('/modules/clinical/ui/portable-order-pdf.php?'+query);assert code==200 and body.startswith(b'%PDF-') and b'%%EOF' in body[-1024:],(code,body[:100])
 with tempfile.TemporaryDirectory(prefix='funccat02b-pdf-') as directory:
  path=Path(directory)/'order.pdf';path.write_bytes(body)
  extracted=subprocess.check_output(['swift','-e','import Foundation; import PDFKit; let d=PDFDocument(url: URL(fileURLWithPath: CommandLine.arguments.last!))!; print(d.string ?? "")',str(path)],text=True)
  for label in labels:assert label in extracted,(label,extracted[:1200])
  for forbidden in ['HIGH_RESOLUTION','PH_IMPEDANCE','PRE_POST_BRONCHODILATOR','UPPER_EXTREMITY']:
   assert forbidden not in extracted,forbidden

assert ROUTING['groups']['FUNCTIONAL_GI_PHYSIOLOGY']=='Fisiología gastrointestinal'
assert sql('SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1')=='289'
assert sql("SELECT COUNT(*) FROM clinical_study_types WHERE category_key='FUNCION_DIGESTIVA' AND is_active=1")=='2'
print('QA_CATALOG_287_TO_289_FUNCTIONAL_35_TO_37=PASS',flush=True)
positive={
 'holter':[{'duration':v} for v in ['24_HOURS','48_HOURS','72_HOURS']],
 'video_eeg':[{'duration':'48_HOURS'}],
 'emg_ncs':[{'body_site':'UPPER_EXTREMITY','side':'RIGHT'}],
 'evoked_auditory_baep':[{'side':'BILATERAL'}],
 'evoked_visual':[{'side':'LEFT'}],
 'evoked_ssep':[{'body_site':'LOWER_EXTREMITY','side':'LEFT'}],
 'spirometry':[{'spirometry_protocol':v} for v in ['BASELINE','PRE_POST_BRONCHODILATOR']],
 'vng':[{'caloric_intent':v} for v in ['WITH_CALORIC','WITHOUT_CALORIC']],
 'audiometry_tonal':[{'side':'RIGHT'}],
 'audiometry_speech':[{'side':'LEFT'}],
 'otoacoustic_emissions':[{'side':'BILATERAL'}],
 'esophageal_manometry':[{'gi_technique':v} for v in ['CONVENTIONAL','HIGH_RESOLUTION']],
 'esophageal_ph_monitoring':[{'gi_technique':v,'duration':'24_HOURS','acid_suppression':'OFF_THERAPY'} for v in ['PH_ONLY','PH_IMPEDANCE']],
}
for key,cases in positive.items():
 for params in cases:
  code,out=issue(batch(order(ROUTING['studies'][key],[item(key,params)])));assert code==201,(key,params,code,out)
  snap=payload(out['data']['orders'][0])['order_items'][0]
  assert snap['functional_order_parameters']=={'version':1,**params} and snap['functional_order_parameters_label']
print('QA_PARAMETER_POSITIVE_HTTP=PASS',flush=True)
for key in ['holter','video_eeg','emg_ncs','spirometry','vng','audiometry_tonal']:
 code,out=issue(batch(order(ROUTING['studies'][key],[item(key)])));assert code==201,(key,code,out)
 assert 'functional_order_parameters' not in payload(out['data']['orders'][0])['order_items'][0]
print('QA_OPTIONAL_OMISSION=PASS',flush=True)
negative=[
 ('holter',{'duration':'96_HOURS'}),('abpm_mapa',{'duration':'24_HOURS'}),('eeg_routine',{'duration':'24_HOURS'}),
 ('emg_ncs',{'body_site':'ARM','side':'RIGHT'}),('emg_ncs',{'side':'RIGHT'}),
 ('spirometry',{'spirometry_protocol':'BRAND_X'}),('vng',{'caloric_intent':'CALORIC_ONLY'}),
 ('esophageal_manometry',None),('esophageal_manometry',{'gi_technique':'PH_ONLY'}),
 ('esophageal_ph_monitoring',None),('esophageal_ph_monitoring',{'gi_technique':'PH_ONLY','duration':'48_HOURS','acid_suppression':'OFF_THERAPY'}),
 ('esophageal_ph_monitoring',{'gi_technique':'PH_ONLY','duration':'24_HOURS','acid_suppression':'UNKNOWN'}),
 ('holter',{'duration':'48_HOURS','unexpected':'value'}),('holter',{'duration':'48_HOURS','version':2}),
]
for key,params in negative:
 command=batch(order(ROUTING['studies'][key],[item(key,params)]));before=counts();code,out=issue(command)
 assert code==422 and counts()==before,(key,params,code,out,before,counts())
print('QA_PARAMETER_NEGATIVE_ZERO_WRITES=PASS',flush=True)
gi=batch(order('FUNCTIONAL_GI_PHYSIOLOGY',[item('esophageal_manometry',{'gi_technique':'HIGH_RESOLUTION'}),item('esophageal_ph_monitoring',{'gi_technique':'PH_IMPEDANCE','duration':'24_HOURS','acid_suppression':'ON_THERAPY'})]))
code,out=issue(gi);assert code==201 and len(out['data']['orders'])==1,(code,out)
doc=out['data']['orders'][0];snap=payload(doc)['order_items'];assert len(snap)==2 and len({x['order_item_id'] for x in snap})==2
print('QA_GI_ONE_ORDER_TWO_EXACT_ITEMS=PASS',flush=True)
result={'patient_id':PATIENT,'document_type':'lab_result','title':'Reporte fisiológico QA','event_datetime':'2026-10-05 12:00:00','provenance':'Proveedor QA','payload':{'source':'res02a_linked_result','related_order_document_uuid':doc['document_uuid'],'related_order_item_ids':[snap[0]['order_item_id']],'provenance':'Proveedor QA'}}
code,out=upload_result(f'{API}/patients/{PATIENT}/documents',result);assert code==201,(code,out)
result_id=out['data'].get('document_uuid') or out['data']['document_id']
stored=json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='"+str(result_id)+"'"))
assert stored['related_order_item_ids']==[snap[0]['order_item_id']] and snap[1]['order_item_id'] not in stored['related_order_item_ids']
print('QA_RESULT_EXACT_GI_ORDER_ITEM_LINK=PASS',flush=True)
middle=counts();code,replay=issue(gi);assert code in (200,201) and replay['data']['orders'][0]['document_id']==doc['document_id'] and counts()==middle,(code,replay)
print('QA_GI_IDEMPOTENCY=PASS',flush=True)
mixed=batch(gi['orders'][0],order('CARDIOVASCULAR_DIAGNOSTICS',[item('holter',{'duration':'48_HOURS'})]))
code,out=issue(mixed);assert code==201 and len(out['data']['orders'])==2,(code,out)
assert {x['order_routing_group_key'] for x in out['data']['orders']}=={'FUNCTIONAL_GI_PHYSIOLOGY','CARDIOVASCULAR_DIAGNOSTICS'}
print('QA_MIXED_ROUTING_BATCH=PASS',flush=True)
pdf_cases=[('holter',{'duration':'48_HOURS'},['Holter','Duración: 48 horas']),('spirometry',{'spirometry_protocol':'PRE_POST_BRONCHODILATOR'},['Espirometría','Protocolo: Pre y post broncodilatador']),('emg_ncs',{'body_site':'UPPER_EXTREMITY','side':'RIGHT'},['Región: Extremidad superior','Lado: Derecho']),('esophageal_manometry',{'gi_technique':'HIGH_RESOLUTION'},['Manometría esofágica','Técnica: Alta resolución']),('esophageal_ph_monitoring',{'gi_technique':'PH_IMPEDANCE','duration':'24_HOURS','acid_suppression':'ON_THERAPY'},['Monitoreo de pH esofágico','Técnica: pH + impedancia','Duración: 24 horas','Tratamiento antisecretor: Con tratamiento antisecretor'])]
for key,params,labels in pdf_cases:
 code,out=issue(batch(order(ROUTING['studies'][key],[item(key,params)])));assert code==201,(key,code,out)
 check_pdf(out['data']['orders'][0],labels)
print('QA_REAL_PORTABLE_HTML_AND_PDF_FIVE_CASES=PASS',flush=True)
for term,key in [('ECG','ecg_12lead'),('EKG','ecg_12lead'),('Holter','holter'),('Holter de presión','abpm_mapa'),('MAPA','abpm_mapa'),('ergometría','stress_test'),('EEG','eeg_routine'),('EMG','emg_ncs'),('PEATC','evoked_auditory_baep'),('ABR','evoked_auditory_baep'),('PFP','full_pft'),('logoaudiometría','audiometry_speech'),('manometría esofágica','esophageal_manometry'),('phmetria','esophageal_ph_monitoring'),('impedancia-pH','esophageal_ph_monitoring')]:
 code,out=request(API+'/study-types?'+urllib.parse.urlencode({'search':term,'limit':100}));assert code==200,(term,code,out)
 assert key in [x['study_type_key'] for x in out['data']['items']],(term,key,[x['study_type_key'] for x in out['data']['items']])
code,holter=request(API+'/study-types?search=holter&limit=100');assert code==200 and 'abpm_mapa' not in [x['study_type_key'] for x in holter['data']['items']]
code,pft=request(API+'/study-types?search=PFT&limit=100');assert code==200 and {'full_pft','spirometry'}<={x['study_type_key'] for x in pft['data']['items']}
assert pft['data']['items'][0]['study_type_key']=='full_pft'
print('QA_SEARCH_TERMS=PASS',flush=True)
