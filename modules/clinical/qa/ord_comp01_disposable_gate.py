"""ORD-COMP01 real authenticated writes in a throwaway schema; never review data."""
import copy,json,os,pathlib,subprocess,urllib.request,urllib.error,urllib.parse,uuid
BASE=os.environ['LAB_CAT02A_QA_BASE'];DB=os.environ['LAB_CAT02A_QA_DB']
assert DB.startswith('ordcomp01_qa_')
OWNER='PHPSESSID=labcat02a-owner';ROOT=pathlib.Path(__file__).resolve().parents[3]
PATH='/api/clinical/index.php/doctors/d_labcat02a_order/patients/p_labcat02a_order'
def sql(q):return subprocess.check_output(['mysql','-N','-B','-r',DB,'-e',q],text=True).strip()
def req(path,body=None,key=None,cookie=OWNER,multipart=False):
 headers={'Cookie':cookie,'Accept':'application/json'} if cookie else {'Accept':'application/json'}
 data=None
 if body is not None:
  headers['Idempotency-Key']=key or body.get('order_composition_batch_uuid') or str(uuid.uuid4())
  if multipart:
   boundary='qa'+uuid.uuid4().hex;parts=[]
   for k,v in body.items():
    if isinstance(v,(list,dict)):v=json.dumps(v,ensure_ascii=False)
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
   parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="qa.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()+b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\r\n')
   parts.append(f'--{boundary}--\r\n'.encode());data=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
  else:data=json.dumps(body,ensure_ascii=False).encode();headers['Content-Type']='application/json'
 request=urllib.request.Request(BASE+path,data=data,headers=headers)
 try:
  with urllib.request.urlopen(request,timeout=90) as r:code=r.status;raw=r.read();h=r.headers
 except urllib.error.HTTPError as r:code=r.code;raw=r.read();h=r.headers
 return code,json.loads(raw) if h.get_content_type()=='application/json' else raw
routing=json.loads((ROOT/'modules/clinical/catalog/study_order_routing_v1.json').read_text())
keys=sql('SELECT study_type_key FROM clinical_study_types WHERE is_active=1').splitlines()
assert len(keys)==232 and len(keys)==len(set(keys))
assert all(k in routing['studies'] and routing['studies'][k] in routing['groups'] for k in keys)
assert len(routing['studies'])==232
urine=routing['catalog']['urine'];assert len(urine['featured'])<=6 and set(urine['featured'])<=set(k for g in urine['groups'] for k in g['keys'])<=set(keys)
new_keys=['urine_creatinine_spot','urine_sodium_spot','urine_potassium_spot','urine_pregnancy_qualitative','csf_glucose','csf_total_protein','post_vasectomy_semen_check']
assert set(new_keys)<=set(keys) and all(routing['studies'][k]=='CLINICAL_LAB' for k in new_keys)
assert len(urine['featured'])==6 and not set(new_keys)&set(urine['featured'])
assert sql("SELECT COUNT(*) FROM clinical_study_types WHERE study_type_key IN ("+','.join("'"+k+"'" for k in new_keys)+") AND JSON_LENGTH(aliases_json)=0")=='7'
assert sql("SELECT COUNT(*) FROM clinical_study_types WHERE study_type_key LIKE '%_24h' AND seed_provenance LIKE 'URINE-FLUIDS-CAT03A:%'")=='0'
assert sum(len(g['keys']) for g in urine['groups'])==30 and len({key for group in urine['groups'] for key in group['keys']})==26 and len(urine['groups'])==7
assert next(g for g in urine['groups'] if g['label']=='Líquido cefalorraquídeo (LCR)')['keys']==['csf_cell_count','csf_glucose','csf_total_protein','csf_lactate','csf_oligoclonal_bands']
assert next(g for g in urine['groups'] if g['label']=='Semen')['keys']==['semen_analysis','post_vasectomy_semen_check']
print('QA_URINE_CATALOG=PASS; QA_ROUTING_232=PASS; QA_FEATURED=PASS; QA_CAT03A_SEVEN=PASS',flush=True)
def order(group,keys):return {'order_routing_group_key':group,'priority':'Rutinaria','indication':'QA desechable '+group,'order_items':[{'study_type_key':k} for k in keys]}
def batch(orders):return {'order_composition_batch_uuid':str(uuid.uuid4()),'order_routing_version':1,'orders':orders}
body=batch([order('CLINICAL_LAB',['urinalysis','urine_osmolality']),order('GENERAL_IMAGING',['rx_chest']),order('PATHOLOGY_CYTOLOGY',['cyto_pap']),order('CARDIOVASCULAR_DIAGNOSTICS',['echo_tte'])])
code,result=req(PATH+'/orders/batch',body);assert code==201,(code,result)
issued=result['data']['orders'];assert len(issued)==4
assert len({r['document_id'] for r in issued})==len({r['document_uuid'] for r in issued})==4
snapshots=[];all_ids=[]
for doc in issued:
 p=json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(doc['document_id'])}"));snapshots.append(p)
 assert p['order_payload_version']==2 and p['order_routing_version']==1
 assert p['order_composition_batch_uuid']==body['order_composition_batch_uuid'] and p['order_routing_group_key']==doc['order_routing_group_key']
 assert all(routing['studies'][i['study_type_key']]==p['order_routing_group_key'] and i['study_type_id'] for i in p['order_items'])
 if p['order_routing_group_key']=='CLINICAL_LAB':
  assert all(i['specimen_collection_requirements']=={'version':1,'specimen_type_key':'URINE'} for i in p['order_items'])
 all_ids.extend(i['order_item_id'] for i in p['order_items'])
assert len(all_ids)==len(set(all_ids))==5
code,replay=req(PATH+'/orders/batch',body);assert code==200 and replay['data']==result['data'],(code,replay)
changed=copy.deepcopy(body);changed['orders'][0]['indication']='changed';assert req(PATH+'/orders/batch',changed)[0]==409
# Replay survives subsequent catalog deactivation; the original snapshot set is authoritative.
sql("UPDATE clinical_study_types SET is_active=0 WHERE study_type_key='urine_osmolality'")
assert req(PATH+'/orders/batch',body)[1]['data']==result['data']
sql("UPDATE clinical_study_types SET is_active=1 WHERE study_type_key='urine_osmolality'")
print('QA_MULTI_ORDER_SUCCESS=PASS; QA_MULTI_ORDER_IDEMPOTENCY=PASS',flush=True)
def counts():return sql('SELECT COUNT(*) FROM clinical_documents')+':'+sql('SELECT COUNT(*) FROM clinical_idempotency_requests')
before=counts()
specimen_invalid=batch([order('GENERAL_IMAGING',['rx_chest']),{'order_routing_group_key':'CLINICAL_LAB','priority':'Rutinaria','indication':'QA muestra inválida','order_items':[{'study_type_key':'csf_glucose','specimen_collection_requirements':{'version':1,'specimen_type_key':'SERUM'}}]}])
code,error=req(PATH+'/orders/batch',specimen_invalid)
assert code==422 and error['order_routing_group_key']=='CLINICAL_LAB' and counts()==before,(code,error)
print('QA_SPECIMEN_BATCH_REJECTION_ZERO_WRITES=PASS; QA_FIXED_SPECIMEN_SNAPSHOT=PASS',flush=True)
invalid=batch([order('CLINICAL_LAB',['glucose']),order('GENERAL_IMAGING',['not_a_study'])]);code,error=req(PATH+'/orders/batch',invalid)
assert code==422 and error['order_routing_group_key']=='GENERAL_IMAGING' and counts()==before,(code,error)
# Force a write-time failure AFTER the first document insert, to exercise actual rollback.
sql("DELIMITER $$\nCREATE TRIGGER ordcomp_fail BEFORE INSERT ON clinical_documents FOR EACH ROW BEGIN IF JSON_UNQUOTE(JSON_EXTRACT(NEW.payload_json,'$.order_routing_group_key'))='GENERAL_IMAGING' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='DISPOSABLE_FORCED_FAILURE'; END IF; END$$\nDELIMITER ;")
forced=batch([order('CLINICAL_LAB',['glucose']),order('GENERAL_IMAGING',['rx_chest'])]);assert req(PATH+'/orders/batch',forced)[0]==500
assert counts()==before
sql('DROP TRIGGER ordcomp_fail')
assert req(PATH+'/orders/batch',forced)[0]==201
print('QA_MULTI_ORDER_ATOMICITY=PASS (validation + failure after first insert)',flush=True)
for orders in [[order('CLINICAL_LAB',['rx_chest'])],[order('CLINICAL_LAB',['glucose','glucose'])],[order('CLINICAL_LAB',['glucose']),order('CLINICAL_LAB',['cbc'])]]:
 before=counts();assert req(PATH+'/orders/batch',batch(orders))[0]==422 and counts()==before
custom=order('CLINICAL_LAB',[]);custom['order_items']=[{'study_category':'LABORATORIO','study_display_name':'Estudio personalizado QA'}]
assert req(PATH+'/orders/batch',batch([custom]))[0]==422
custom['order_items'][0]['custom_routing_confirmed']=True
assert req(PATH+'/orders/batch',batch([custom]))[0]==201
assert req(PATH+'/orders/batch',batch([order('CLINICAL_LAB',['glucose'])]),cookie='')[0] in (401,403)
assert req(PATH+'/orders/batch',batch([order('CLINICAL_LAB',['glucose'])]),cookie='PHPSESSID=labcat02a-foreign')[0]==403
print('QA_CUSTOM_ROUTING_AUTH_AND_REJECTION=PASS',flush=True)
# Legacy mixed orders stay on the original single-order endpoint.
legacy={'patient_id':'p_labcat02a_order','document_type':'orders','title':'Orden mixta histórica QA','payload':{'source':'tax03c_catalog_composer','order_items':[{'study_type_key':'glucose'},{'study_type_key':'rx_chest'}]}}
code,old=req(PATH+'/documents',legacy);assert code==201,(code,old)
old=old['data'];old_json=sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(old['document_id'])}")
old_payload=json.loads(old_json)
# Real RES02A upload against exact independent and historical mixed order items.
for doc,p in [(issued[0],snapshots[0]),(old,old_payload)]:
 result_body={'patient_id':'p_labcat02a_order','document_type':'lab_result','title':'Resultado QA','event_datetime':'2026-10-03 12:00:00','provenance':'Laboratorio QA','payload':{'source':'res02a_linked_result','related_order_document_uuid':doc['document_uuid'],'related_order_item_ids':[p['order_items'][0]['order_item_id']],'provenance':'Laboratorio QA'}}
 code,res=req(PATH+'/documents',result_body,multipart=True);assert code==201,(code,res)
 uuid_=res['data'].get('document_uuid') or res['data']['document_id']
 stored=json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='"+uuid_+"'"))
 assert stored['related_order_document_uuid']==doc['document_uuid'] and stored['related_order_item_ids']==[p['order_items'][0]['order_item_id']]
code,projection=req(PATH+'/documents?orders_results_mode=1&limit=100&filter=all');assert code==200,(code,projection)
raw=json.dumps(projection)
assert 'result_source_order_document_uuid' in raw and issued[0]['document_uuid'] in raw and old['document_uuid'] in raw
assert sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(old['document_id'])}")==old_json
print('QA_RESULT_REGRESSION=PASS; QA_HISTORICAL_MIXED_ORDER_LINKAGE=PASS',flush=True)
new_body=batch([order('CLINICAL_LAB',new_keys),order('GENERAL_IMAGING',['rx_chest'])])
code,new_issue=req(PATH+'/orders/batch',new_body);assert code==201,(code,new_issue)
new_docs=new_issue['data']['orders'];assert len(new_docs)==2
new_lab=next(d for d in new_docs if d['order_routing_group_key']=='CLINICAL_LAB')
new_payload=json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(new_lab['document_id'])}"))
assert [i['study_type_key'] for i in new_payload['order_items']]==new_keys
assert len({i['order_item_id'] for i in new_payload['order_items']})==7
assert all(i['study_type_id'] for i in new_payload['order_items'])
result_body={'patient_id':'p_labcat02a_order','document_type':'lab_result','title':'Resultado CAT03A QA','event_datetime':'2026-10-03 12:00:00','provenance':'Laboratorio QA','payload':{'source':'res02a_linked_result','related_order_document_uuid':new_lab['document_uuid'],'related_order_item_ids':[new_payload['order_items'][0]['order_item_id']],'provenance':'Laboratorio QA'}}
code,new_result=req(PATH+'/documents',result_body,multipart=True);assert code==201,(code,new_result)
new_result_uuid=new_result['data'].get('document_uuid') or new_result['data']['document_id']
stored_result=json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='"+new_result_uuid+"'"))
assert stored_result['related_order_item_ids']==[new_payload['order_items'][0]['order_item_id']]
print('QA_CAT03A_SEVEN_SAME_ROUTING_MULTI_ORDER_RESULT_LINKAGE=PASS',flush=True)
dental=order('DENTAL_DIAGNOSTICS',['dental_cbct','dental_panoramic_xray'])
dental['order_items'][0]['dental_location']={'contract_version':1,'numbering_system':'FDI_ISO_3950','dentition_mode':'PERMANENT','coverage':'LOCALIZED','selected_teeth':['16']}
code,d=req(PATH+'/orders/batch',batch([dental]));assert code==201,(code,d)
dental_doc=d['data']['orders'][0]
p=json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(dental_doc['document_id'])}"))
assert p['order_items'][0]['dental_location']['selected_teeth']==['16']
# Static integration proof uses the actual interop source gates and independently issued snapshots.
interop=(ROOT/'api/_lib/healthcare_study_interop.php').read_text()
assert 'function sendReferral' in interop and 'REFERRAL_ITEM_NOT_CANONICAL_SOURCE' in interop and 'source_order_version' in interop
for doc,p in zip(issued,snapshots):
 row=sql(f"SELECT version,status,generated_at FROM clinical_documents WHERE id={int(doc['document_id'])}").split('\t')
 assert row[0]=='1' and row[1] in ('generated','signed') and row[2]!='NULL'
 assert p['order_payload_version']==2 and all(i['study_type_id'] and i['order_item_id'] for i in p['order_items'])
assert 'order_composition_batch_uuid' not in interop
print('QA_PROVIDER_INTEROP_STATIC=PASS (exact version/items; provider eligibility unchanged)',flush=True)
for doc in issued+[old,dental_doc,new_lab]:
 query=urllib.parse.urlencode({'uuid':doc['document_uuid'],'doctor_id':'d_labcat02a_order'})
 code,model=req('/api/clinical/index.php/doctors/d_labcat02a_order/portable-orders/'+doc['document_uuid']);assert code==200
 code,html=req('/modules/clinical/ui/portable-order.php?'+query);assert code==200
 assert all(item['name'].encode() in html for item in model['data']['studies'])
 assert all(item['specimen_context'].encode() in html for item in model['data']['studies'] if item.get('specimen_context'))
 code,pdf=req('/modules/clinical/ui/portable-order-pdf.php?'+query)
 assert code==200 and pdf.startswith(b'%PDF-') and pdf.rstrip().endswith(b'%%EOF'),(code,pdf[:200])
 print('QA_PORTABLE_'+doc.get('order_routing_group_key','HISTORICAL_MIXED')+'=PASS',flush=True)
cat03c=json.loads((ROOT/'modules/clinical/catalog/study_specimen_requirements_v1.json').read_text())
cat03c_keys=sql("SELECT study_type_key FROM clinical_study_types WHERE seed_provenance='URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21' ORDER BY study_type_key").splitlines()
assert len(cat03c_keys)==17
for key in cat03c_keys:
 name=sql("SELECT display_name_es FROM clinical_study_types WHERE study_type_key='"+key+"'")
 code,found=req('/api/clinical/index.php/doctors/d_labcat02a_order/study-types?'+urllib.parse.urlencode({'search':name,'limit':100}))
 assert code==200 and any(row['study_type_key']==key and row['display_name_es']==name for row in found['data']['items']),(key,code,found)
print('QA_CAT03C_SEARCH_17=PASS',flush=True)
def selected(key,specimen=None):
 return {'study_type_key':key,**({'specimen_collection_requirements':{'version':1,'specimen_type_key':specimen}} if specimen else {})}
def prepared(group,items):return {'order_routing_group_key':group,'priority':'Rutinaria','indication':'CAT03C QA desechable','order_items':items}
triple=batch([
 prepared('CLINICAL_LAB',[selected('body_fluid_glucose','PLEURAL_FLUID'),selected('body_fluid_albumin','ASCITIC_PERITONEAL_FLUID')]),
 prepared('PATHOLOGY_CYTOLOGY',[selected('serous_fluid_cytology','PERICARDIAL_FLUID')]),
 prepared('GENETICS_MOLECULAR',[selected('csf_meningitis_encephalitis_panel')])])
incomplete=copy.deepcopy(triple);del incomplete['orders'][0]['order_items'][0]['specimen_collection_requirements'];incomplete['order_composition_batch_uuid']=str(uuid.uuid4())
before=counts();code,error=req(PATH+'/orders/batch',incomplete)
assert code==422 and counts()==before,(code,error)
code,created=req(PATH+'/orders/batch',triple)
assert code==201 and len(created['data']['orders'])==3,(code,created)
documents=created['data']['orders'];assert len({doc['document_uuid'] for doc in documents})==3
assert sorted(doc['order_routing_group_key'] for doc in documents)==['CLINICAL_LAB','GENETICS_MOLECULAR','PATHOLOGY_CYTOLOGY']
snapshots={doc['order_routing_group_key']:json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(doc['document_id'])}")) for doc in documents}
lab=snapshots['CLINICAL_LAB']['order_items'];assert len(lab)==2 and lab[0]['specimen_collection_requirements']['specimen_type_key']=='PLEURAL_FLUID' and lab[1]['specimen_collection_requirements']['specimen_type_key']=='ASCITIC_PERITONEAL_FLUID'
assert len({item['order_item_id'] for p in snapshots.values() for item in p['order_items']})==4
assert snapshots['PATHOLOGY_CYTOLOGY']['order_items'][0]['specimen_collection_requirements']['specimen_type_key']=='PERICARDIAL_FLUID'
assert len(snapshots['GENETICS_MOLECULAR']['order_items'])==1 and snapshots['GENETICS_MOLECULAR']['order_items'][0]['specimen_collection_requirements']['specimen_type_key']=='CSF'
code,replayed=req(PATH+'/orders/batch',triple);assert code==200 and replayed['data']==created['data']
for doc in documents:
 query=urllib.parse.urlencode({'uuid':doc['document_uuid'],'doctor_id':'d_labcat02a_order'})
 code,model=req('/api/clinical/index.php/doctors/d_labcat02a_order/portable-orders/'+doc['document_uuid']);assert code==200
 code,html=req('/modules/clinical/ui/portable-order.php?'+query);assert code==200
 assert all(item['name'].encode() in html for item in model['data']['studies'])
 assert all(item['specimen_context'].encode() in html for item in model['data']['studies'] if item.get('specimen_context'))
 code,pdf=req('/modules/clinical/ui/portable-order-pdf.php?'+query);assert code==200 and pdf.startswith(b'%PDF-') and pdf.rstrip().endswith(b'%%EOF')
panel_doc=next(doc for doc in documents if doc['order_routing_group_key']=='GENETICS_MOLECULAR')
panel_item=snapshots['GENETICS_MOLECULAR']['order_items'][0]
result_body={'patient_id':'p_labcat02a_order','document_type':'lab_result','title':'Panel LCR CAT03C QA','event_datetime':'2026-10-03 12:00:00','provenance':'Laboratorio QA','payload':{'source':'res02a_linked_result','related_order_document_uuid':panel_doc['document_uuid'],'related_order_item_ids':[panel_item['order_item_id']],'provenance':'Laboratorio QA'}}
code,res=req(PATH+'/documents',result_body,multipart=True);assert code==201,(code,res)
stored=json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='"+(res['data'].get('document_uuid') or res['data']['document_id'])+"'"))
assert stored['related_order_item_ids']==[panel_item['order_item_id']]
print('QA_CAT03C_INCOMPLETE_ZERO_WRITES=PASS; QA_CAT03C_THREE_ROUTING_BATCH=PASS; QA_CAT03C_EXACT_REPLAY=PASS',flush=True)
print('QA_CAT03C_PORTABLE_HTML_PDF=PASS; QA_CAT03C_PANEL_RESULT_EXACT_ITEM=PASS',flush=True)
for fluid,items in [('PLEURAL_FLUID',[selected('sterile_body_fluid_bacterial_culture','PLEURAL_FLUID'),selected('body_fluid_ldh','SYNOVIAL_FLUID')]),('SYNOVIAL_FLUID',[selected('sterile_body_fluid_bacterial_culture','SYNOVIAL_FLUID')])]:
 code,issued_fluid=req(PATH+'/orders/batch',batch([prepared('CLINICAL_LAB',items)]))
 assert code==201,(fluid,code,issued_fluid)
 p=json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(issued_fluid['data']['orders'][0]['document_id'])}"))
 culture=next(item for item in p['order_items'] if item['study_type_key']=='sterile_body_fluid_bacterial_culture')
 assert culture['specimen_collection_requirements']['specimen_type_key']==fluid
 assert culture['study_type_id']==int(sql("SELECT study_type_id FROM clinical_study_types WHERE study_type_key='sterile_body_fluid_bacterial_culture'"))
 if fluid=='PLEURAL_FLUID':assert next(item for item in p['order_items'] if item['study_type_key']=='body_fluid_ldh')['specimen_collection_requirements']['specimen_type_key']=='SYNOVIAL_FLUID'
cytology=batch([prepared('PATHOLOGY_CYTOLOGY',[selected('urine_cytology'),selected('csf_cytology')])])
code,issued_cytology=req(PATH+'/orders/batch',cytology);assert code==201,(code,issued_cytology)
p=json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(issued_cytology['data']['orders'][0]['document_id'])}"))
assert [item['study_type_key'] for item in p['order_items']]==['urine_cytology','csf_cytology']
assert [item['specimen_collection_requirements']['specimen_type_key'] for item in p['order_items']]==['URINE','CSF']
print('QA_CAT03C_STERILE_CULTURE_TWO_FLUID_SNAPSHOTS=PASS; QA_CAT03C_SYNOVIAL_LDH=PASS; QA_CAT03C_URINE_CSF_CYTOLOGY=PASS',flush=True)
print('ORD_COMP01_DISPOSABLE_GATE=PASS',flush=True)
