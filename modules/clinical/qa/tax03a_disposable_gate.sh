#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="tax03a_qa_$(openssl rand -hex 6)"
qa_root="$(mktemp -d /tmp/tax03a-qa-XXXXXXXX)"
http_pid=""
cleanup(){ if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi; mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; rm -rf "$qa_root"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_14_study_type_authority.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_14_study_type_authority.sql"
TAX03A_QA_DB="$qa_db" php "$root_dir/modules/clinical/qa/tax03a_disposable_gate.php"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("tax03a-owner");session_start();$_SESSION["doctor_id"]="d_tax03a";$_SESSION["user_id"]="u_tax03a";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_CLINICAL_M6_COHORT_MODE=off \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$port/index.html" 2>/dev/null; then break; fi; sleep 0.1; done
TAX03A_QA_BASE="http://127.0.0.1:$port" python3 - <<'PY'
import json,os,urllib.request,urllib.error,uuid
base=os.environ['TAX03A_QA_BASE']+'/api/clinical/index.php/doctors/d_tax03a/patients/p_tax03a/documents'
def post(kind,payload,title):
 boundary='tax03a-boundary'
 fields={'patient_id':'p_tax03a','document_type':kind,'title':title,'payload':json.dumps(payload,ensure_ascii=False)}
 body=b''.join(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"\r\n\r\n'+value+'\r\n').encode() for key,value in fields.items())+('--'+boundary+'--\r\n').encode()
 req=urllib.request.Request(base,data=body,headers={'Cookie':'PHPSESSID=tax03a-owner','Content-Type':'multipart/form-data; boundary='+boundary,'Accept':'application/json'},method='POST')
 try:
  with urllib.request.urlopen(req) as response:return response.status,json.load(response)
 except urllib.error.HTTPError as error:return error.code,json.load(error)
def assert_order(kind,category):
 status,data=post(kind,{'requested_studies':['Estudio QA']},'TAX03A '+kind)
 assert status==201 and data['ok'],(status,data)
 stored=data['data']['document']['content']['payload']
 assert stored['order_payload_version']==2 and stored['requested_studies']==['Estudio QA'],stored
 assert stored['order_items'][0]['study_category']==category and stored['order_items'][0]['study_type_id'] is None,stored
 uuid.UUID(stored['order_items'][0]['order_item_id'])
 return data['data']['document'],stored
lab,lab_payload=assert_order('lab_order','LABORATORIO')
print('QA_HTTP_CURRENT_LAB_WRITER=PASS')
imaging,imaging_payload=assert_order('imaging_order','IMAGEN')
print('QA_HTTP_CURRENT_IMAGING_WRITER=PASS')
def replace_order(document,studies):
 url=os.environ['TAX03A_QA_BASE']+'/api/clinical/index.php/documents/'+document['document_id']+'/replace'
 req=urllib.request.Request(url,data=json.dumps({'requested_studies':studies,'order_area':'Imagenología'}).encode(),
  headers={'Cookie':'PHPSESSID=tax03a-owner','Content-Type':'application/json','Accept':'application/json'},method='POST')
 try:
  with urllib.request.urlopen(req) as response:return response.status,json.load(response)
 except urllib.error.HTTPError as error:return error.code,json.load(error)
status,replaced=replace_order(imaging,['Estudio QA','Estudio agregado'])
assert status==200 and replaced['ok'],(status,replaced)
new_payload=replaced['data']['replacement_document']['content']['payload']
assert new_payload['order_payload_version']==2 and len(new_payload['order_items'])==2,new_payload
assert new_payload['order_items'][0]['order_item_id']!=imaging_payload['order_items'][0]['order_item_id'],new_payload
print('QA_HTTP_ORDER_REPLACEMENT=PASS')
status,free=post('orders',{'order_items':[{'study_category':'DENTAL','study_display_name':'CBCT fuera de catálogo'}]},'TAX03A free')
assert status==201 and free['ok'] and free['data']['document']['content']['payload']['order_items'][0]['study_type_key'] is None,(status,free)
print('QA_HTTP_FREE_TEXT_ITEM=PASS')
status,catalog=post('orders',{'order_items':[{'study_type_key':'ecg_12_lead'}]},'TAX03A catalog')
assert status==201 and catalog['ok'] and catalog['data']['document']['content']['payload']['order_items'][0]['study_category']=='CARDIOVASCULAR',(status,catalog)
print('QA_HTTP_CATALOG_ITEM=PASS')
status,multi=post('lab_order',{'requested_studies':['CBC','Glucosa','HbA1c']},'TAX03A combined')
assert status==201 and multi['ok'],(status,multi)
multi_payload=multi['data']['document']['content']['payload']
multi_ids=[item['order_item_id'] for item in multi_payload['order_items']]
status,combined=post('lab_result',{'related_order_document_uuid':multi['data']['document']['document_id'],'related_order_item_ids':multi_ids},'TAX03A combined result')
assert status==201 and combined['ok'],(status,combined)
req=urllib.request.Request(base+'?orders_results_mode=1&filter=orders&search=TAX03A%20combined',headers={'Cookie':'PHPSESSID=tax03a-owner','Accept':'application/json'})
with urllib.request.urlopen(req) as response: projection=json.load(response)['data']
group=next(item for item in projection['items'] if item['order']['document_uuid']==multi['data']['document']['document_id'])
assert group['result_count']==1 and group['order']['covered_item_count']==3 and group['order']['coverage_state']=='ALL_ITEMS_HAVE_RESULTS',group
print('QA_HTTP_COMBINED_RESULT_COVERAGE=PASS')
status,result=post('lab_result',{'related_order_document_uuid':lab['document_id'],'related_order_item_ids':[lab_payload['order_items'][0]['order_item_id']]},'TAX03A linked')
assert status==201 and result['ok'],(status,result)
print('QA_HTTP_RESULT_ITEM_LINK=PASS')
status,blocked=replace_order(lab,['Estudio cambiado'])
assert status==409 and not blocked['ok'],(status,blocked)
print('QA_HTTP_ORDER_WITH_RESULT_PROTECTED=PASS')
status,bad=post('lab_result',{'related_order_document_uuid':lab['document_id'],'related_order_item_ids':['00000000-0000-4000-8000-000000000001']},'TAX03A invalid')
assert status==400 and not bad['ok'],(status,bad)
print('QA_HTTP_INVALID_ITEM_REJECTED=PASS')
PY
