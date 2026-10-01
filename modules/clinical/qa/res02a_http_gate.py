import json, os, subprocess, urllib.error, urllib.parse, urllib.request, uuid
base=os.environ['RES02A_QA_BASE']+'/api/clinical/index.php'
db=os.environ['RES02A_QA_DB']; encounter=int(os.environ['RES02A_QA_ENCOUNTER'])
cookie='PHPSESSID=res02a-owner'
def sql(query): return subprocess.check_output(['mysql','-N','-B','-r',db,'-e',query],text=True).strip()
def request(path,body, multipart=False, key=None):
    headers={'Cookie':cookie,'Accept':'application/json','Idempotency-Key':key or str(uuid.uuid4())}
    if multipart:
        boundary='----res02a'+uuid.uuid4().hex
        parts=[]
        for name,value in body.items():
            if name=='file':continue
            if isinstance(value,(list,dict)):value=json.dumps(value,ensure_ascii=False)
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="qa.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()+b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\r\n')
        parts.append(f'--{boundary}--\r\n'.encode());data=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
    else:data=json.dumps(body,ensure_ascii=False).encode();headers['Content-Type']='application/json'
    req=urllib.request.Request(base+path,data=data,headers=headers,method='POST')
    try:
        with urllib.request.urlopen(req) as reply:return reply.status,json.load(reply)
    except urllib.error.HTTPError as error:return error.code,json.load(error)
def order(patient,items,kind='orders',enc=False):
    path=f'/encounters/{urllib.parse.quote("enc:"+str(encounter),safe="")}/documents' if enc else f'/doctors/d_res02a/patients/{patient}/documents'
    status,data=request(path,{'patient_id':patient,'document_type':kind,'title':'QA orden','event_datetime':'2026-09-30 12:00:00','payload':{'source':'res02a_qa','order_items':items}})
    assert status==201 and data['ok'],(status,data)
    id=int(data['data']['document_id']);uuid_=data['data']['document_uuid'];payload=json.loads(sql(f'SELECT payload_json FROM clinical_documents WHERE id={id}'))
    return id,uuid_,payload
def result(patient,order_uuid,item_ids,kind='result',enc=False,key=None):
    path=f'/encounters/{urllib.parse.quote("enc:"+str(encounter),safe="")}/documents' if enc else f'/doctors/d_res02a/patients/{patient}/documents'
    payload={'source':'res02a_linked_result','related_order_document_uuid':order_uuid,'provenance':'Laboratorio QA'}
    if item_ids is not None:payload['related_order_item_ids']=item_ids
    return request(path,{'patient_id':patient,'document_type':kind,'title':'Resultado QA','event_datetime':'2026-09-30 13:00:00','provenance':'Laboratorio QA','payload':payload,'file':True},True,key)
def item(name,category):return {'study_category':category,'study_display_name':name}

main=order('p_res02a_open',[item('Glucosa','LABORATORIO'),item('RX tórax','IMAGEN'),item('ECG','CARDIOVASCULAR')])
ids=[row['order_item_id'] for row in main[2]['order_items']]
other=order('p_res02a_open',[item('Otro','LABORATORIO')],kind='lab_order')
foreign=order('p_res02a_other',[item('Ajeno','LABORATORIO')],kind='lab_order')
for name,values in [('duplicate',[ids[0],ids[0]]),('unknown',[str(uuid.uuid4())]),('cross_order',[other[2]['order_items'][0]['order_item_id']]),('cross_patient',[foreign[2]['order_items'][0]['order_item_id']])]:
    status,data=result('p_res02a_open',main[1],values)
    assert status in (400,409) and not data['ok'],(name,status,data)
    print('QA_'+name.upper()+'=PASS')
key=str(uuid.uuid4());status,first=result('p_res02a_open',main[1],[ids[0]],'lab_result',key=key)
assert status==201 and first['ok'],(status,first)
status,second=result('p_res02a_open',main[1],[ids[1],ids[2]],'result')
assert status==201 and second['ok'],(status,second)
assert sql("SELECT COUNT(*) FROM clinical_documents WHERE patient_id='p_res02a_open' AND document_type IN ('lab_result','result')")=='2'
assert sql("SELECT COUNT(*) FROM clinical_documents WHERE patient_id='p_res02a_open' AND document_type IN ('lab_result','result') AND (encounter_ref_id IS NOT NULL OR encounter_id IS NOT NULL OR appointment_id IS NOT NULL)")=='0'
assert sql("SELECT COUNT(*) FROM clinical_document_binaries b JOIN clinical_documents d ON d.id=b.document_id WHERE d.patient_id='p_res02a_open'")=='2'
binary_path=base+'/documents/'+first['data']['document_uuid']+'/binary/ORIGINAL'
with urllib.request.urlopen(urllib.request.Request(binary_path,headers={'Cookie':cookie})) as downloaded:
    assert downloaded.status==200 and downloaded.read().startswith(b'%PDF-')
try:
    urllib.request.urlopen(binary_path)
    raise AssertionError('unauthorized binary read succeeded')
except urllib.error.HTTPError as error:
    assert error.code in (401,403,404)
print('QA_GENERAL_WITH_OPEN=PASS; QA_MULTIPLE_RESULTS=PASS; QA_PRIVATE_BINARY=PASS')
query=urllib.parse.urlencode({'orders_results_mode':'1','filter':'orders','limit':'25'})
req=urllib.request.Request(base+f'/doctors/d_res02a/patients/p_res02a_open/documents?{query}',headers={'Cookie':cookie,'Accept':'application/json'})
with urllib.request.urlopen(req) as reply:projection=json.load(reply)['data']
selected=next(row for row in projection['items'] if row['kind']=='ORDER' and row['order']['document_uuid']==main[1])
assert selected['result_count']==2 and selected['order']['covered_item_count']==3 and selected['order']['coverage_state']=='ALL_ITEMS_HAVE_RESULTS',selected
print('QA_OR02B_COVERAGE=PASS')
amend_path='/documents/'+second['data']['document_uuid']+'/amendments'
replacement={'document_type':'result','title':'Resultado corregido QA','summary':'',
    'event_datetime':'2026-09-30 14:00:00','payload':{'related_order_document_uuid':main[1],
    'related_order_item_ids':[ids[1],ids[2]],'source':'res02a_replacement'}}
status,rejected=request(amend_path,{'reason':'Corrección QA de cobertura','replacement':{**replacement,'payload':{
    **replacement['payload'],'related_order_item_ids':[ids[0]]}},'file':True},True)
assert status in (400,409) and not rejected['ok'],(status,rejected)
status,amended=request(amend_path,{'reason':'Corrección QA de archivo','replacement':replacement,'file':True},True)
assert status==201 and amended['ok'],(status,amended)
print('QA_RESULT_REPLACEMENT_COVERAGE_IMMUTABLE=PASS')
ambiguous=order('p_res02a_open',[item('Hemograma','LABORATORIO'),item('Glucosa','LABORATORIO')],kind='lab_order')
status,data=result('p_res02a_open',ambiguous[1],None,'lab_result')
assert status==201 and data['ok'],(status,data)
with urllib.request.urlopen(req) as reply:projection=json.load(reply)['data']
selected=next(row for row in projection['items'] if row['kind']=='ORDER' and row['order']['document_uuid']==ambiguous[1])
assert selected['result_count']==1 and selected['order']['covered_item_count']==0 and selected['order']['coverage_state']=='UNKNOWN_COVERAGE',selected
print('QA_AMBIGUOUS_UNKNOWN=PASS')
enc_order=order('p_res02a_open',[item('Glucosa','LABORATORIO'),item('RX tórax','IMAGEN')],enc=True)
enc_ids=[row['order_item_id'] for row in enc_order[2]['order_items']]
status,data=result('p_res02a_open',enc_order[1],enc_ids,'result',enc=True)
assert status==201 and data['ok'],(status,data)
assert sql(f"SELECT encounter_ref_id FROM clinical_documents WHERE document_uuid='{data['data']['document_uuid']}'")==str(encounter)
print('QA_CONSULTATION_GENERIC_RESULT=PASS')
legacy_uuid=str(uuid.uuid4())
sql("INSERT INTO clinical_documents(document_uuid,document_type,title,version,status,patient_id,care_setting,payload_json,event_datetime,created_at,generated_at,created_by_user_id) "
    f"VALUES('{legacy_uuid}','order','Orden V1',1,'generated','p_res02a_plain','consulta','{{\\\"requested_studies\\\":[\\\"Estudio legado\\\"]}}',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),'u_res02a')")
status,legacy_result=result('p_res02a_plain',legacy_uuid,None,'result')
assert status==201 and legacy_result['ok'],(status,legacy_result)
print('QA_V1_ORDER_RESULT=PASS')
appointment_order=order('p_res02a_plain',[item('Perfil tiroideo','LABORATORIO')],kind='lab_order')
sql(f"UPDATE clinical_documents SET appointment_id='qa-selected-order-appointment' WHERE id={appointment_order[0]}")
status,appointment_result=result('p_res02a_plain',appointment_order[1],
    [appointment_order[2]['order_items'][0]['order_item_id']],'lab_result')
assert status==201 and appointment_result['ok'],(status,appointment_result)
assert sql(f"SELECT appointment_id FROM clinical_documents WHERE document_uuid='{appointment_result['data']['document_uuid']}'")=='qa-selected-order-appointment'
assert sql(f"SELECT COUNT(*) FROM clinical_documents WHERE document_uuid='{appointment_result['data']['document_uuid']}' AND encounter_ref_id IS NULL AND encounter_id IS NULL")=='1'
print('QA_SELECTED_ORDER_APPOINTMENT_INHERITED=PASS')
