#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="tax03b_qa_$(openssl rand -hex 6)"
qa_root="$(mktemp -d /tmp/tax03b-qa-XXXXXXXX)"
http_pid=""
cleanup(){ if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi; mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; rm -rf "$qa_root"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_14_study_type_authority.sql"
for _ in 1 2; do mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql"; done
TAX03B_QA_DB="$qa_db" php "$root_dir/modules/clinical/qa/tax03b_catalog_gate.php"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("tax03b-owner");session_start();$_SESSION["doctor_id"]="d_tax03b";$_SESSION["user_id"]="u_tax03b";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_CLINICAL_M6_COHORT_MODE=off \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$port/index.html" 2>/dev/null; then break; fi; sleep 0.1; done
TAX03B_QA_BASE="http://127.0.0.1:$port" python3 - <<'PY'
import json,os,urllib.request,urllib.error
base=os.environ['TAX03B_QA_BASE']+'/api/clinical/index.php/doctors/d_tax03b/study-types'
def get(query='',cookie='PHPSESSID=tax03b-owner'):
 request=urllib.request.Request(base+query,headers={'Cookie':cookie,'Accept':'application/json'})
 try:
  with urllib.request.urlopen(request) as response:return response.status,json.load(response)
 except urllib.error.HTTPError as error:return error.code,json.load(error)
status,all_rows=get('?limit=100')
assert status==200 and all_rows['ok'] and len(all_rows['data']['items'])==100 and all_rows['data']['has_more']
status,second=get('?limit=100&offset=100')
assert status==200 and len(second['data']['items'])==83 and not second['data']['has_more']
status,genetics=get('?category=GENETICA&search=microarray')
assert status==200 and [row['study_type_key'] for row in genetics['data']['items']]==['cma_microarray']
status,alias=get('?category=CARDIOVASCULAR&search=Mesa%20inclinada')
assert status==200 and [row['study_type_key'] for row in alias['data']['items']]==['tilt_table']
status,name=get('?category=GENETICA&search=Cariotipo')
assert status==200 and [row['study_type_key'] for row in name['data']['items']]==['karyotype']
status,invalid=get('?category=INVALID')
assert status==400 and not invalid['ok']
status,unauthorized=get('',cookie='')
assert status==401 and not unauthorized['ok']
request=urllib.request.Request(base.replace('/d_tax03b/','/other_doctor/'),headers={'Cookie':'PHPSESSID=tax03b-owner'})
try:
 urllib.request.urlopen(request)
 raise AssertionError('doctor scope mismatch accepted')
except urllib.error.HTTPError as error:
 assert error.code==403
print('QA_CATALOG_HTTP_AUTH_SEARCH_CATEGORY_PAGINATION=PASS')
PY
