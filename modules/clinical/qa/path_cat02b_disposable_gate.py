import copy,json,os,subprocess,urllib.request,urllib.error,urllib.parse,uuid
BASE=os.environ['PATHCAT02B_QA_BASE'];DB=os.environ['PATHCAT02B_QA_DB'];PATH='/api/clinical/index.php/doctors/d_pathcat02b/patients/p_pathcat02b'
def sql(q):return subprocess.check_output(['mysql','-N','-B','-r',DB,'-e',q],text=True).strip()
def req(path,body=None,multipart=False):
 headers={'Cookie':'PHPSESSID=pathcat02b-owner','Accept':'application/json'};data=None
 if body is not None:
  headers['Idempotency-Key']=body.get('order_composition_batch_uuid',str(uuid.uuid4()))
  if multipart:
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
def item(key,material='TISSUE',site='Sitio QA',extra=None,part_extra=None):
 return {'study_type_key':key,'pathology_order_parameters':{'version':1,'specimens':[{'material_key':material,'anatomic_site_text':site,**(part_extra or {})}],**(extra or {})}}
def batch(items,other=False):
 orders=[{'order_routing_group_key':'PATHOLOGY_CYTOLOGY','priority':'Rutinaria','indication':'Patología QA','order_items':items}]
 if other:orders += [{'order_routing_group_key':'CLINICAL_LAB','priority':'Rutinaria','indication':'Laboratorio QA','order_items':[{'study_type_key':'glucose'}]},{'order_routing_group_key':'GENERAL_IMAGING','priority':'Rutinaria','indication':'Imagen QA','order_items':[{'study_type_key':'rx_chest'}]}]
 return {'order_composition_batch_uuid':str(uuid.uuid4()),'order_routing_version':1,'orders':orders}
assert sql('SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1')=='263'
cases=[item('histopath_biopsy'),item('histopath_resection',part_extra={'description':'Pieza QA'}),item('cyto_fna','CYTOLOGY_SPECIMEN'),item('cyto_bronchial_brushing','CYTOLOGY_SPECIMEN'),item('cyto_bronchial_washing','CYTOLOGY_SPECIMEN'),item('ihc_single_marker','PARAFFIN_BLOCK',extra={'marker_key':'ER'}),item('ihc_breast_profile','PARAFFIN_BLOCK',extra={'profile_version':1}),item('histochemical_special_stain',extra={'stain_key':'PAS'}),item('if_renal'),item('if_skin'),item('pathology_outside_review','GLASS_SLIDE',extra={'source_institution_name':'Hospital QA','prior_report_status':'PENDING'})]
assert len(cases)==11
for x in cases:
 if x['study_type_key'] in ('cyto_bronchial_brushing','cyto_bronchial_washing'):x['pathology_order_parameters']['specimens'][0].pop('anatomic_site_text')
 if x['study_type_key']=='ihc_breast_profile':x['pathology_order_parameters']['specimens'][0]['anatomic_site_text']='Mama QA'
 if x['study_type_key']=='if_skin':x['pathology_order_parameters']['specimens'][0]['anatomic_site_text']='Brazo QA'
code,result=req(PATH+'/orders/batch',batch(cases,True));assert code==201,(code,result)
orders=result['data']['orders'];assert len(orders)==3,(code,result)
pathdoc=next(x for x in orders if x['order_routing_group_key']=='PATHOLOGY_CYTOLOGY');payload=json.loads(sql('SELECT payload_json FROM clinical_documents WHERE id='+str(pathdoc['document_id'])))
assert len(payload['order_items'])==11 and all(x['pathology_order_parameters']['version']==1 and x['pathology_order_parameters_label'] for x in payload['order_items'])
assert len({x['order_item_id'] for x in payload['order_items']})==11
print('QA_11_POSITIVE_ISSUE_AND_3_ROUTING_ORDERS=PASS',flush=True)
query=urllib.parse.urlencode({'uuid':pathdoc['document_uuid'],'doctor_id':'d_pathcat02b'})
code,html=req('/modules/clinical/ui/portable-order.php?'+query);assert code==200 and isinstance(html,bytes),(code,html)
assert b'Estudio histopatol' in html and b'Marcador:' in html and b'Tinci' in html and b'Instituci' in html
code,pdf=req('/modules/clinical/ui/portable-order-pdf.php?'+query);assert code==200 and pdf.startswith(b'%PDF-') and pdf.rstrip().endswith(b'%%EOF'),(code,pdf[:200])
print('QA_PORTABLE_PATHOLOGY_HTML_PDF=PASS',flush=True)
result_body={'patient_id':'p_pathcat02b','document_type':'lab_result','title':'Resultado patología QA','event_datetime':'2026-10-04 12:00:00','provenance':'Patología QA','payload':{'source':'res02a_linked_result','related_order_document_uuid':pathdoc['document_uuid'],'related_order_item_ids':[payload['order_items'][0]['order_item_id']],'provenance':'Patología QA'}}
code,res=req(PATH+'/documents',result_body,multipart=True);assert code==201,(code,res)
stored=json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='"+(res['data'].get('document_uuid') or res['data']['document_id'])+"'"))
assert stored['related_order_item_ids']==[payload['order_items'][0]['order_item_id']]
print('QA_RESULT_EXACT_ITEM_LINKAGE=PASS',flush=True)
count=lambda:sql('SELECT COUNT(*) FROM clinical_documents')
bad=[]
for k in ('histopath_biopsy','cyto_fna'):
 x=copy.deepcopy(next(v for v in cases if v['study_type_key']==k));x['pathology_order_parameters']['specimens'][0].pop('anatomic_site_text');bad.append(x)
x=copy.deepcopy(cases[1]);x['pathology_order_parameters']['specimens'][0].pop('description');bad.append(x)
for ix,field,val in [(5,'marker_key',None),(5,'marker_key','INVALID'),(7,'stain_key',None),(7,'stain_key','INVALID'),(10,'source_institution_name',None),(0,'unknown_field','bad')]:
 x=copy.deepcopy(cases[ix]);p=x['pathology_order_parameters'];p.pop(field,None) if val is None else p.__setitem__(field,val);bad.append(x)
x=copy.deepcopy(cases[0]);x['pathology_order_parameters']['specimens'][0]['laterality']='INVALID';bad.append(x)
for x in bad:
 before=count();code,err=req(PATH+'/orders/batch',batch([x]));assert code==422 and count()==before,(x['study_type_key'],code,err)
print('QA_10_NEGATIVE_ZERO_WRITES=PASS',flush=True)
for n in (1,2,10):
 x=copy.deepcopy(cases[0]);x['pathology_order_parameters']['specimens']=[{'material_key':'TISSUE','anatomic_site_text':'Sitio '+str(i)} for i in range(n)]
 code,out=req(PATH+'/orders/batch',batch([x]));assert code==201,(n,code,out)
 p=json.loads(sql('SELECT payload_json FROM clinical_documents WHERE id='+str(out['data']['orders'][0]['document_id'])))
 assert [s['anatomic_site_text'] for s in p['order_items'][0]['pathology_order_parameters']['specimens']]==['Sitio '+str(i) for i in range(n)]
x['pathology_order_parameters']['specimens'].append({'material_key':'TISSUE','anatomic_site_text':'Sitio 10'})
before=count();assert req(PATH+'/orders/batch',batch([x]))[0]==422 and count()==before
print('QA_MULTI_SPECIMEN_1_2_10_AND_11_REJECTED=PASS',flush=True)
