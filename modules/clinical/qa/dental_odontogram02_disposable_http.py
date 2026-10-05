"""Authenticated V2 dental writes and invalid zero-write cases in a throwaway schema."""
import json,os,subprocess,urllib.request,urllib.error,urllib.parse,uuid

BASE=os.environ['ODONTO02_QA_BASE'];DB=os.environ['ODONTO02_QA_DB']
assert DB.startswith('odonto02_qa_')
PATH='/api/clinical/index.php/doctors/d_odonto02/patients/p_odonto02/orders/batch'
def sql(query):return subprocess.check_output(['mysql','-N','-B','-r',DB,'-e',query],text=True).strip()
def request(body):
    req=urllib.request.Request(BASE+PATH,data=json.dumps(body,ensure_ascii=False).encode(),headers={'Cookie':'PHPSESSID=odonto02-owner','Content-Type':'application/json','Accept':'application/json','Idempotency-Key':body['order_composition_batch_uuid']})
    try:
        with urllib.request.urlopen(req,timeout=90) as response:return response.status,json.load(response)
    except urllib.error.HTTPError as response:return response.code,json.load(response)
def batch(item):return {'order_composition_batch_uuid':str(uuid.uuid4()),'order_routing_version':1,'orders':[{'order_routing_group_key':'DENTAL_DIAGNOSTICS','priority':'Rutinaria','indication':'QA odontograma V2','order_items':[item]}]}
def count():return sql('SELECT COUNT(*) FROM clinical_documents')+':'+sql('SELECT COUNT(*) FROM clinical_idempotency_requests')
tooth={'contract_version':2,'location_type':'TOOTH_LOCATION','selection_mode':'MULTIPLE_TEETH','numbering_system':'FDI_ISO_3950','dentition_mode':'MIXED','tooth_fdi_codes':['16','54'],'coverage':'LOCALIZED'}
before=count();code,data=request(batch({'study_type_key':'dental_cbct','dental_location':tooth}));assert code==201,(code,data)
doc=data['data']['orders'][0];saved=json.loads(sql('SELECT payload_json FROM clinical_documents WHERE id='+str(doc['document_id'])))
assert saved['order_items'][0]['dental_location']['tooth_fdi_codes']==['16','54']
assert saved['order_items'][0]['dental_location']['contract_version']==2
print('QA_AUTHENTICATED_CBCT_V2_WRITE=PASS',flush=True)
query=urllib.parse.urlencode({'uuid':doc['document_uuid'],'doctor_id':'d_odonto02'})
with urllib.request.urlopen(urllib.request.Request(BASE+'/modules/clinical/ui/portable-order.php?'+query,headers={'Cookie':'PHPSESSID=odonto02-owner'}),timeout=30) as response:
    html=response.read().decode()
assert 'Piezas 16, 54' in html and 'Zona localizada' in html
print('QA_V2_PORTABLE_HTML_READ=PASS',flush=True)
for bad in [
    {**tooth,'tooth_fdi_codes':['19']},
    {**tooth,'dentition_mode':'PERMANENT'},
    {**tooth,'tooth_fdi_codes':[]},
    {**tooth,'tooth_fdi_codes':['16','16']},
    {**tooth,'selection_mode':'ARCH'},
    {**tooth,'debug':True},
]:
    before=count();code,error=request(batch({'study_type_key':'dental_cbct','dental_location':bad}));assert code==422 and count()==before,(bad,code,error)
print('QA_INVALID_COMBINATIONS_ZERO_WRITES=PASS',flush=True)
for study,location in [
    ('dental_intraoral_scan',{'contract_version':2,'location_type':'ARCH_LOCATION','selection_mode':'ARCH','arch_key':'BOTH_ARCHES'}),
    ('tmj_comparative_xray',{'contract_version':2,'location_type':'TMJ_LOCATION','selection_mode':'TMJ_REGION','tmj_side':'BILATERAL','projection':'PA'}),
    ('dental_study_model',{'contract_version':2,'location_type':'ARCH_LOCATION','selection_mode':'ARCH','arch_key':'MAXILLARY'}),
]:
    code,data=request(batch({'study_type_key':study,'dental_location':location}));assert code==201,(study,code,data)
print('QA_SCAN_TMJ_MODEL_V2_WRITE=PASS',flush=True)
for study in ['dental_panoramic_xray','dental_cephalometric_xray','dental_clinical_photographs']:
    if study=='dental_clinical_photographs':continue # V1 photograph scope remains required.
    code,data=request(batch({'study_type_key':study}));assert code==201,(study,code,data)
print('QA_PANORAMIC_CEPHALOMETRIC_NO_LOCATION=PASS',flush=True)
photo={'contract_version':1,'numbering_system':'FDI_ISO_3950','photograph_scope':'BOTH'}
code,data=request(batch({'study_type_key':'dental_clinical_photographs','dental_location':photo}));assert code==201,(code,data)
print('QA_PHOTOGRAPHY_V1_UNCHANGED=PASS',flush=True)
legacy={'contract_version':1,'numbering_system':'FDI_ISO_3950','dentition_mode':'DECIDUOUS','coverage':'LOCALIZED','selected_teeth':['54']}
code,data=request(batch({'study_type_key':'dental_cbct','dental_location':legacy}));assert code==201,(code,data)
saved=json.loads(sql('SELECT payload_json FROM clinical_documents WHERE id='+str(data['data']['orders'][0]['document_id'])))
assert saved['order_items'][0]['dental_location']['contract_version']==1
print('QA_V1_WRITE_READ_COMPATIBILITY=PASS',flush=True)
orders=[
    {'order_routing_group_key':'CLINICAL_LAB','priority':'Rutinaria','indication':'Regresión laboratorio','order_items':[{'study_type_key':'glucose'}]},
    {'order_routing_group_key':'PATHOLOGY_CYTOLOGY','priority':'Rutinaria','indication':'Regresión patología','order_items':[{'study_type_key':'histopath_biopsy','pathology_order_parameters':{'version':1,'specimens':[{'material_key':'TISSUE','anatomic_site_text':'Sitio QA'}]}}]},
    {'order_routing_group_key':'GENERAL_IMAGING','priority':'Rutinaria','indication':'Regresión imagen','order_items':[{'study_type_key':'rx_hip','imaging_order_parameters':{'version':1,'laterality':'RIGHT','xray_view_preset':'JOINT_STANDARD'}}]},
    {'order_routing_group_key':'CARDIOVASCULAR_DIAGNOSTICS','priority':'Rutinaria','indication':'Regresión funcional','order_items':[{'study_type_key':'echo_tte'}]},
    {'order_routing_group_key':'DIGESTIVE_ENDOSCOPY','priority':'Rutinaria','indication':'Regresión procedimiento diagnóstico','order_items':[{'study_type_key':'anoscopy_base'}]},
]
code,data=request({'order_composition_batch_uuid':str(uuid.uuid4()),'order_routing_version':1,'orders':orders})
assert code==201 and len(data['data']['orders'])==5,(code,data)
print('QA_LAB_PATHOLOGY_IMAGING_FUNCTIONAL_DIAGNOSTIC_PROCEDURE_MIXED_ORDER=PASS',flush=True)
