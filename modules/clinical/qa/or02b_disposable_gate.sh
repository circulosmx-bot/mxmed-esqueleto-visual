#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="or02b_qa_$(openssl rand -hex 6)"
qa_root="$(mktemp -d /tmp/or02b-qa-XXXXXXXX)"
http_pid=""
cleanup(){ if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi; mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; rm -rf "$qa_root"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
OR02B_QA_DB="$qa_db" php "$root_dir/modules/clinical/qa/or02b_disposable_gate.php"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("or02b-owner");session_start();$_SESSION["doctor_id"]="d_or02b";$_SESSION["user_id"]="u_or02b";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_CLINICAL_M6_COHORT_MODE=off php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$port/index.html" 2>/dev/null; then break; fi;sleep 0.1;done
OR02B_QA_BASE="http://127.0.0.1:$port" python3 - <<'PY'
import json,os,urllib.request,urllib.error
base=os.environ['OR02B_QA_BASE']+'/api/clinical/index.php/doctors/d_or02b/patients/p_or02b/documents'
def get(query):
 req=urllib.request.Request(base+'?'+query,headers={'Cookie':'PHPSESSID=or02b-owner','Accept':'application/json'})
 try:
  with urllib.request.urlopen(req) as response:
   body=json.load(response)
   assert response.status==200 and body['ok'],body
   return body['data']
 except urllib.error.HTTPError as error:
  raise AssertionError(f'HTTP {error.code}: {error.read().decode()[:1200]}') from error
default=get('limit=200')
assert len(default['items'])==200 and 'has_more' not in default,'default document list changed'
page=get('orders_results_mode=1&limit=25&filter=orders')
assert len(page['items'])==25 and page['has_more'] and page['cursor_next']
linked=get('orders_results_mode=1&search=Three%20results')
assert len(linked['items'])==1 and linked['items'][0]['result_count']==3
results=get('orders_results_mode=1&filter=results&search=Standalone%20generic')
assert len(results['items'])==1 and results['items'][0]['result']['document_type']=='result'
print('QA_HTTP_ROUTE=PASS; QA_DEFAULT_LIST_UNCHANGED=PASS')
PY
python3 "$root_dir/modules/clinical/qa/or02b_browser_gate.py"
