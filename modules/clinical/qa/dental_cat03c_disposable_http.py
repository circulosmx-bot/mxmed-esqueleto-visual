"""DENTAL-CAT03C real authenticated HTTP gate against an isolated disposable schema."""
import json, os, subprocess, tempfile, urllib.parse, urllib.request, urllib.error, uuid
from pathlib import Path

BASE=os.environ['DENTALCAT03C_QA_BASE']; DB=os.environ['DENTALCAT03C_QA_DB']
assert DB.startswith('dentalcat03c_qa_')
DOCTOR='d_dentalcat03c'; PATIENT='p_dentalcat03c'; COOKIE='PHPSESSID=dentalcat03c-owner'
API=f'/api/clinical/index.php/doctors/{DOCTOR}/patients/{PATIENT}/orders/batch'
def sql(query): return subprocess.check_output(['mysql','-N','-B','-r',DB,'-e',query],text=True).strip()
def get(path):
    req=urllib.request.Request(BASE+path,headers={'Cookie':COOKIE})
    try:
        with urllib.request.urlopen(req,timeout=75) as response:return response.status,response.headers,response.read()
    except urllib.error.HTTPError as response:return response.code,response.headers,response.read()
def issue(items,group='DENTAL_DIAGNOSTICS',key=None):
    key=key or str(uuid.uuid4())
    body={'order_composition_batch_uuid':key,'order_routing_version':1,'orders':[{'order_routing_group_key':group,'priority':'Rutinaria','indication':'QA dental CAT03C','order_items':items}]}
    req=urllib.request.Request(BASE+API,data=json.dumps(body,ensure_ascii=False).encode(),headers={'Cookie':COOKIE,'Content-Type':'application/json','Accept':'application/json','Idempotency-Key':key})
    try:
        with urllib.request.urlopen(req,timeout=90) as response:return response.status,json.load(response)
    except urllib.error.HTTPError as response:return response.code,json.load(response)
def saved(doc):return json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(doc['document_id'])}"))
def counts():return tuple(int(sql(f'SELECT COUNT(*) FROM {table}')) for table in ['clinical_documents','clinical_idempotency_requests'])
def ok(items):
    code,data=issue(items);assert code==201,(code,data)
    doc=data['data']['orders'][0];return doc,saved(doc)
def reject(items):
    before=counts();code,data=issue(items);assert code==422,(code,data);assert counts()==before,(before,counts(),data)
def loc(kind,**extra):return {'contract_version':2,'location_type':kind,'selection_mode':{'TOOTH_LOCATION':'MULTIPLE_TEETH','REGION_LOCATION':'REGION','ARCH_LOCATION':'ARCH','TMJ_LOCATION':'TMJ_REGION'}[kind],**extra}
def tooth(codes,dentition='PERMANENT'):
    return loc('TOOTH_LOCATION',selection_mode='SINGLE_TOOTH' if len(codes)==1 else 'MULTIPLE_TEETH',numbering_system='FDI_ISO_3950',dentition_mode=dentition,tooth_fdi_codes=codes)
def bite(side,mode='REGION'):
    return loc('REGION_LOCATION',selection_mode=mode,dentition_mode='PERMANENT',region_key='POSTERIOR',arch_key='BOTH_ARCHES',side_key=side)
def arch(key):return loc('ARCH_LOCATION',dentition_mode='PERMANENT',arch_key=key)
def protocol(n):return {'contract_version':1,'protocol_key':f'FULL_MOUTH_{n}','dentition_mode':'PERMANENT'}
def item(key,location=None,proto=None):
    return {'study_type_key':key,**({'dental_location':location} if location else {}),**({'dental_acquisition_protocol':proto} if proto else {})}
def print_pages(doc,expected):
    query=urllib.parse.urlencode({'uuid':doc['document_uuid'],'doctor_id':DOCTOR})
    path='/modules/clinical/ui/portable-order.php?'+query
    code,_,body=get(path);assert code==200,(code,body[:150]);html=body.decode()
    for part in expected:assert part in html,(part,html[-800:])
    code,headers,body=get('/modules/clinical/ui/portable-order-pdf.php?'+query)
    assert code==200 and headers.get_content_type()=='application/pdf' and body.startswith(b'%PDF-'),(code,body[:180])
    with tempfile.TemporaryDirectory(prefix='dentalcat03c-pdf-') as directory:
        pdf=Path(directory)/'order.pdf';pdf.write_bytes(body)
        text=subprocess.check_output(['swift','-e',
            'import Foundation; import PDFKit; let d=PDFDocument(url: URL(fileURLWithPath: CommandLine.arguments.last!))!; print(d.string ?? "")',str(pdf)],text=True)
        for part in expected:assert part in text,(part,text[:1000])
    return html

assert sql('SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1')=='284'
assert sql("SELECT COUNT(*) FROM clinical_study_types WHERE is_active=1 AND (study_type_key LIKE 'dental_%' OR study_type_key='tmj_comparative_xray')")=='11'
print('QA_CATALOG_284_DENTAL_11=PASS',flush=True)
link_targets=[]
for term,key in [('periapical','dental_periapical_xray'),('periapicales','dental_periapical_xray'),('bitewing','dental_bitewing_xray'),('aleta','dental_bitewing_xray'),('interproximal','dental_bitewing_xray'),('oclusal','dental_occlusal_xray'),('serie de 14','dental_full_periapical_series'),('serie de 16','dental_full_periapical_series'),('serie de 18','dental_full_periapical_series'),('cbct','dental_cbct'),('atm','dental_cbct')]:
    code,_,body=get(f'/api/clinical/index.php/doctors/{DOCTOR}/study-types?'+urllib.parse.urlencode({'search':term,'limit':100}))
    assert code==200,(term,code,body[:150]);rows=json.loads(body)['data']['items'];assert key in {row['study_type_key'] for row in rows},(term,key,[r['study_type_key'] for r in rows])
print('QA_SEARCH_TERMS=PASS',flush=True)
for location in [tooth(['16']),tooth(['16','17','18']),tooth(['54'],'PRIMARY'),tooth(['16','54'],'MIXED')]:
    doc,p=ok([item('dental_periapical_xray',location)]);assert p['order_items'][0]['dental_location']['tooth_fdi_codes']==location['tooth_fdi_codes']
    assert p['order_items'][0]['dental_study_policy_version']==1 and p['order_items'][0]['dental_location_authority_version']==2
    if len(location['tooth_fdi_codes'])==3:
        print_pages(doc,['Radiografía periapical','Piezas: 16, 17, 18']);link_targets.append((doc,p['order_items'][0]['order_item_id']))
print('QA_PERIAPICAL_HTTP_AND_PDF=PASS',flush=True)
for location in [bite('RIGHT'),bite('BILATERAL','BILATERAL_REGION')]:
    doc,p=ok([item('dental_bitewing_xray',location)]);assert p['order_items'][0]['dental_location']['side_key']==location['side_key']
    if location['side_key']=='BILATERAL':
        print_pages(doc,['Radiografía interproximal','Región: Posterior bilateral']);link_targets.append((doc,p['order_items'][0]['order_item_id']))
print('QA_BITEWING_HTTP_AND_PDF=PASS',flush=True)
for key in ['MAXILLARY','MANDIBULAR']:
    doc,p=ok([item('dental_occlusal_xray',arch(key))]);assert p['order_items'][0]['dental_location']['arch_key']==key
both=[item('dental_occlusal_xray',arch('MAXILLARY')),item('dental_occlusal_xray',arch('MANDIBULAR'))]
replay_key=str(uuid.uuid4());code,data=issue(both,key=replay_key);assert code==201,(code,data)
doc=data['data']['orders'][0];p=saved(doc);ids=[row['order_item_id'] for row in p['order_items']]
assert len(ids)==2 and len(set(ids))==2 and [row['dental_location']['arch_key'] for row in p['order_items']]==['MAXILLARY','MANDIBULAR']
print_pages(doc,['Arcada: Maxilar','Arcada: Mandíbula'])
code,data2=issue(both,key=replay_key);assert code in (200,201) and data2['data']['orders'][0]['document_id']==doc['document_id'] and [row['order_item_id'] for row in saved(doc)['order_items']]==ids,(code,data2)
print('QA_OCCLUSAL_BOTH_ARCHES_PDF_AND_IDEMPOTENCY=PASS',flush=True)
for n in [14,16,18]:
    doc_n,pn=ok([item('dental_full_periapical_series',proto=protocol(n))]);assert pn['order_items'][0]['dental_acquisition_protocol']['nominal_image_count']==n
    assert pn['order_items'][0]['dental_acquisition_protocol']['provider_confirmation_required'] is True and pn['order_items'][0]['dental_study_policy_version']==1
    if n==18:
        print_pages(doc_n,['Serie radiográfica intraoral de boca completa','Serie de 18 imágenes intraorales']);link_targets.append((doc_n,pn['order_items'][0]['order_item_id']))
print('QA_FULL_MOUTH_PROTOCOLS_AND_PDF=PASS',flush=True)
cbct=loc('TMJ_LOCATION',tmj_side='BILATERAL',coverage='TMJ');cbct_doc,cbct_saved=ok([item('dental_cbct',cbct)])
print_pages(cbct_doc,['Tomografía dental','Región: ATM bilateral'])
link_targets.append((cbct_doc,cbct_saved['order_items'][0]['order_item_id']))
print('QA_CBCT_TMJ_HTTP_AND_PDF=PASS',flush=True)
for bad in [
 [item('dental_periapical_xray')], [item('dental_periapical_xray',tooth(['11','12','13','14','15','16','17','18','21']))],
 [item('dental_periapical_xray',tooth(['54']))], [item('dental_bitewing_xray',tooth(['16']))],
 [item('dental_bitewing_xray',{**bite('RIGHT'),'region_key':'ANTERIOR'})],
 [item('dental_bitewing_xray',{**bite('RIGHT'),'side_key':'MIDLINE'})],
 [item('dental_occlusal_xray')], [both[0],both[0]],
 [item('dental_full_periapical_series',proto=protocol(13))],
 [item('dental_full_periapical_series',proto={**protocol(14),'dentition_mode':'PRIMARY'})],
 [item('dental_full_periapical_series',proto={**protocol(14),'dentition_mode':'MIXED'})],
 [item('dental_cbct',{k:v for k,v in cbct.items() if k!='tmj_side'})],
 [item('dental_cbct',{**cbct,'tooth_fdi_codes':['16']})],
 [item('dental_occlusal_xray',{**arch('MAXILLARY'),'unknown':'x'})],
 [item('dental_occlusal_xray',{**arch('MAXILLARY'),'contract_version':1})],
 [item('glucose'),item('glucose')],
 ]:reject(bad)
print('QA_NEGATIVE_ZERO_WRITES_AND_DEDUPE=PASS',flush=True)
# Existing study and other-family read/write paths retain their established rules.
for row in [item('dental_panoramic_xray'),item('dental_cephalometric_xray'),item('dental_intraoral_scan',loc('ARCH_LOCATION',arch_key='MAXILLARY')),item('dental_study_model'),item('dental_clinical_photographs',{'contract_version':1,'numbering_system':'FDI_ISO_3950','photograph_scope':'BOTH'}),item('tmj_comparative_xray',loc('TMJ_LOCATION',tmj_side='LEFT',projection='LATERAL'))]:ok([row])
print('QA_EXISTING_DENTAL=PASS',flush=True)
for key,group in [('glucose','CLINICAL_LAB'),('echo_tte','CARDIOVASCULAR_DIAGNOSTICS'),('anoscopy_base','DIGESTIVE_ENDOSCOPY')]:
    code,_=issue([item(key)],group=group);assert code==201,(key,code)
code,_=issue([{'study_type_key':'histopath_biopsy','pathology_order_parameters':{'version':1,'specimens':[{'material_key':'TISSUE','anatomic_site_text':'Sitio QA'}]}}],group='PATHOLOGY_CYTOLOGY');assert code==201,code
code,_=issue([{'study_type_key':'rx_hip','imaging_order_parameters':{'version':1,'laterality':'RIGHT','xray_view_preset':'JOINT_STANDARD'}}],group='GENERAL_IMAGING');assert code==201,code
print('QA_LAB_PATHOLOGY_IMAGING_FUNCTIONAL_PROCEDURE=PASS',flush=True)
# Exact item linkage: separate maxillary and mandibular results remain tied to their own order_item_id.
link_targets.extend((doc,order_item_id) for order_item_id in ids)
for index,(source,order_item_id) in enumerate(link_targets):
    body={'patient_id':PATIENT,'document_type':'imaging_result','title':f'Resultado dental {index}','event_datetime':'2026-10-05 12:00:00','provenance':'QA dental','payload':{'source':'res02a_linked_result','related_order_document_uuid':source['document_uuid'],'related_order_item_ids':[order_item_id],'provenance':'QA dental'}}
    target=f'/api/clinical/index.php/doctors/{DOCTOR}/patients/{PATIENT}/documents';boundary='qa'+uuid.uuid4().hex;parts=[]
    for name,value in body.items():
        if isinstance(value,(dict,list)):value=json.dumps(value,ensure_ascii=False)
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="qa.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()+b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    req=urllib.request.Request(BASE+target,data=b''.join(parts),headers={'Cookie':COOKIE,'Content-Type':'multipart/form-data; boundary='+boundary,'Accept':'application/json','Idempotency-Key':str(uuid.uuid4())})
    with urllib.request.urlopen(req,timeout=60) as response:r=json.load(response);assert response.status==201,(response.status,r)
    linked=json.loads(sql(f"SELECT payload_json FROM clinical_documents WHERE id={int(r['data']['document_id'])}"));assert linked['related_order_item_ids']==[order_item_id] and linked['related_order_document_uuid']==source['document_uuid']
print('QA_EXACT_RESULT_LINKAGE=PASS',flush=True)
code,_,body=get(f'/api/clinical/index.php/doctors/{DOCTOR}/patients/{PATIENT}/documents?orders_results_mode=1&limit=100&filter=all')
assert code==200,(code,body[:180]);rows=json.loads(body)['data']['items']
occlusal=next(row for row in rows if row.get('kind')=='ORDER' and row.get('order',{}).get('document_uuid')==doc['document_uuid'])
assert occlusal['result_count']==2 and occlusal['order']['covered_item_count']==2 and occlusal['order']['coverage_state']=='ALL_ITEMS_HAVE_RESULTS',occlusal
print('QA_OCCLUSAL_SEPARATE_ITEM_COVERAGE=PASS',flush=True)
