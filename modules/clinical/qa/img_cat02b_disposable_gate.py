import copy,html as html_tools,json,os,subprocess,urllib.request,urllib.error,urllib.parse,uuid
BASE=os.environ['IMG_CAT02B_QA_BASE'];DB=os.environ['IMG_CAT02B_QA_DB'];PATH='/api/clinical/index.php/doctors/d_imgcat02b/patients/p_imgcat02b'
def sql(q):return subprocess.check_output(['mysql','-N','-B','-r',DB,'-e',q],text=True).strip()
def req(path,body=None,pdf_upload=False):
 headers={'Cookie':'PHPSESSID=imgcat02b-owner','Accept':'application/json'};data=None
 if body is not None:
  headers['Idempotency-Key']=body.get('order_composition_batch_uuid',str(uuid.uuid4()))
  if pdf_upload:
   boundary='qa'+uuid.uuid4().hex;parts=[]
   for k,v in body.items():
    if isinstance(v,(list,dict)):v=json.dumps(v,ensure_ascii=False)
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
   parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="qa.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()+b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\r\n')
   parts.append(f'--{boundary}--\r\n'.encode());data=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
  else:data=json.dumps(body,ensure_ascii=False).encode();headers['Content-Type']='application/json'
 try:
  with urllib.request.urlopen(urllib.request.Request(BASE+path,data=data,headers=headers),timeout=90) as r:code=r.status;raw=r.read();h=r.headers
 except urllib.error.HTTPError as r:code=r.code;raw=r.read();h=r.headers
 return code,json.loads(raw) if h.get_content_type()=='application/json' else raw
def item(key,**values):return {'study_type_key':key,'imaging_order_parameters':{'version':1,**values}}
def batch(items,mixed=False):
 orders=[{'order_routing_group_key':'GENERAL_IMAGING','priority':'Rutinaria','indication':'Imagen QA','order_items':items}]
 if mixed:orders.append({'order_routing_group_key':'CLINICAL_LAB','priority':'Rutinaria','indication':'Laboratorio QA','order_items':[{'study_type_key':'glucose'}]})
 return {'order_composition_batch_uuid':str(uuid.uuid4()),'order_routing_version':1,'orders':orders}
assert sql('SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1')=='278'
cases=[item('rx_hip',laterality='RIGHT',xray_view_preset='JOINT_STANDARD'),item('ct_sinuses',contrast_intent='WITHOUT_CONTRAST'),item('mr_lumbar_spine',contrast_intent='WITHOUT_CONTRAST'),item('rx_foot',laterality='LEFT',xray_view_preset='JOINT_STANDARD'),item('rx_wrist',laterality='RIGHT',xray_view_preset='JOINT_STANDARD'),item('rx_elbow',laterality='BILATERAL',xray_view_preset='JOINT_STANDARD'),item('mr_cervical_spine',contrast_intent='WITH_AND_WITHOUT_IV_CONTRAST'),item('ct_neck',contrast_intent='WITH_IV_CONTRAST'),item('cta_head_neck',contrast_intent='WITH_IV_CONTRAST',vascular_territory='NECK'),item('mra_brain',contrast_intent='WITHOUT_CONTRAST',vascular_territory='HEAD'),item('mr_pelvis',contrast_intent='WITH_IV_CONTRAST'),item('breast_tomosynthesis',laterality='BILATERAL',breast_purpose='DIAGNOSTIC',breast_tomosynthesis_relation='WITH_2D_MAMMOGRAPHY'),item('rx_tspine',xray_view_preset='SPINE_STANDARD'),item('nm_renal_scan',physician_protocol='DYNAMIC'),item('nm_myocardial_perfusion',physician_protocol='STRESS_REST')]
assert len(cases)==15 and len({x['study_type_key'] for x in cases})==15
code,res=req(PATH+'/orders/batch',batch(cases,True));assert code==201,(code,res)
orders=res['data']['orders'];assert len(orders)==2
img=next(x for x in orders if x['order_routing_group_key']=='GENERAL_IMAGING')
payload=json.loads(sql('SELECT payload_json FROM clinical_documents WHERE id='+str(img['document_id'])))
assert len(payload['order_items'])==15 and all(x['imaging_order_parameters']['version']==1 and x['imaging_order_parameters_label'] for x in payload['order_items'])
assert len({x['order_item_id'] for x in payload['order_items']})==15
query=urllib.parse.urlencode({'uuid':img['document_uuid'],'doctor_id':'d_imgcat02b'})
code,html=req('/modules/clinical/ui/portable-order.php?'+query)
assert code==200 and isinstance(html,bytes),(code,html[:200])
portable=html_tools.unescape(html.decode())
for key,name in (line.split('\t',1) for line in sql('SELECT study_type_key,display_name_es FROM clinical_study_types WHERE seed_provenance="IMG-CAT02B:2026_10_05_24"').splitlines()):
 assert name in portable and key not in portable,(key,name)
print('QA_ALL_15_AUTHENTICATED_ISSUE_AND_MIXED_ROUTING=PASS',flush=True)
for key in ('rx_hip','ct_neck','breast_tomosynthesis'):
 case=next(x for x in cases if x['study_type_key']==key)
 code,out=req(PATH+'/orders/batch',batch([case]));assert code==201,(key,code,out)
 doc=out['data']['orders'][0];query=urllib.parse.urlencode({'uuid':doc['document_uuid'],'doctor_id':'d_imgcat02b'})
 code,html=req('/modules/clinical/ui/portable-order.php?'+query);assert code==200 and isinstance(html,bytes),(key,code)
 assert b'imaging' not in html.lower() and b'imaging_order_parameters' not in html
 code,pdf=req('/modules/clinical/ui/portable-order-pdf.php?'+query);assert code==200 and pdf.startswith(b'%PDF-') and pdf.rstrip().endswith(b'%%EOF'),(key,code,pdf[:100])
 print('QA_REAL_HTML_PDF_'+key+'=PASS',flush=True)
all_item_ids=[x['order_item_id'] for x in payload['order_items']]
result={'patient_id':'p_imgcat02b','document_type':'imaging_result','title':'Resultado imagen QA','event_datetime':'2026-10-05 12:00:00','provenance':'Imagen QA','payload':{'source':'res02a_linked_result','related_order_document_uuid':img['document_uuid'],'related_order_item_ids':all_item_ids,'provenance':'Imagen QA'}}
code,out=req(PATH+'/documents',result,pdf_upload=True);assert code==201,(code,out)
resultid=out['data'].get('document_uuid') or out['data']['document_id']
stored=json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='"+str(resultid)+"'"))
assert stored['related_order_item_ids']==all_item_ids
print('QA_EXACT_RESULT_ITEM_LINKAGE=PASS',flush=True)
count=lambda:sql('SELECT COUNT(*) FROM clinical_documents')
bad=[item('rx_hip'),item('rx_hip',laterality='UNKNOWN'),item('rx_hip',vascular_territory='HEAD'),item('ct_neck',contrast_intent='WITH_IV_CONTRAST',contrast_routes=['ORAL']),item('mra_brain',contrast_intent='WITHOUT_CONTRAST',vascular_territory='RENAL'),item('breast_tomosynthesis',laterality='LEFT',breast_tomosynthesis_relation='WITH_2D_MAMMOGRAPHY'),item('rx_tspine',xray_views=['UNKNOWN']),item('ct_sinuses',contrast_intent='WITHOUT_CONTRAST',unknown='X'),{'study_type_key':'ct_sinuses','imaging_order_parameters':{'version':2,'contrast_intent':'WITHOUT_CONTRAST'}}]
for x in bad:
 before=count();code,out=req(PATH+'/orders/batch',batch([x]));assert code==422 and count()==before,(x,code,out)
print('QA_9_NEGATIVE_ZERO_WRITES=PASS',flush=True)
for x in cases:
 invalid=copy.deepcopy(x);invalid['imaging_order_parameters']['unapproved_parameter']='X'
 before=count();code,out=req(PATH+'/orders/batch',batch([invalid]));assert code==422 and count()==before,(x['study_type_key'],code,out)
print('QA_ALL_15_INVALID_PARAMETER_ZERO_WRITES=PASS',flush=True)
