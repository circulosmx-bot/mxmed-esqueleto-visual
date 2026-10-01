#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="mxmed_gate4d_preview_prov04e_$(openssl rand -hex 6)"
qa_tmp="$(mktemp -d /tmp/prov04e-qa-XXXXXXXX)"
qa_pepper="$(openssl rand -hex 32)"
cleanup() {
  [[ -z "${http_pid:-}" ]] || { kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; }
  [[ -z "${resp_pid:-}" ]] || { kill "$resp_pid" 2>/dev/null || true; wait "$resp_pid" 2>/dev/null || true; }
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
  rm -rf "$qa_tmp"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" < "$root_dir/modules/identity/db/migrations/2026_07_20_04_create_auth_account_credentials.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_07_provider_management_http.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_07_provider_management_http.sql"
PROV04E_QA_DB="$qa_db" PROV04E_HTTP_FIXTURE="$qa_tmp/session.json" PROV04E_HTTP_PEPPER="$qa_pepper" php "$root_dir/modules/agenda/qa/prov04e_disposable_gate.php"
python3 "$root_dir/modules/agenda/qa/prov04e_resp_stub.py" "$qa_tmp/session.json" >"$qa_tmp/resp.log" 2>&1 & resp_pid=$!
APP_ENV=local MXMED_ENVIRONMENT=local MXMED_PREVIEW_EXPLICIT=1 \
  MXMED_PREVIEW_PEPPER="$qa_pepper" MXMED_DB_NAME="$qa_db" \
  MXMED_PREVIEW_ORIGIN='https://127.0.0.1:8140' php -S 127.0.0.1:18149 -t "$root_dir" >"$qa_tmp/http.log" 2>&1 & http_pid=$!
sleep 1
session_token="$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["token"];' "$qa_tmp/session.json")"
csrf_token="$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["csrf"];' "$qa_tmp/session.json")"
endpoint='http://127.0.0.1:18149/api/provider/index.php/organizations/prov_a'
no_session="$(curl -sS -o "$qa_tmp/no-session.json" -w '%{http_code}' "$endpoint")"
[[ "$no_session" == 401 ]] && php -r '$x=json_decode(file_get_contents($argv[1]),true);exit(($x["error"]??"")==="UNAUTHENTICATED"?0:1);' "$qa_tmp/no-session.json"
echo 'QA_HTTP_NO_SESSION=PASS'
owner_status="$(curl -sS -b "__Host-mxmed_session=$session_token" -o "$qa_tmp/owner.json" -w '%{http_code}' "$endpoint")"
[[ "$owner_status" == 200 ]] && php -r '$x=json_decode(file_get_contents($argv[1]),true);exit(($x["data"]["member_role"]??"")==="owner"?0:1);' "$qa_tmp/owner.json"
echo 'QA_HTTP_AUTHENTICATED_CONTEXT=PASS'
bad_csrf="$(curl -sS -b "__Host-mxmed_session=$session_token" -H 'Origin: https://127.0.0.1:8140' -H 'Content-Type: application/json' \
  -X POST --data '{"branch_name":"HTTP QA","submission_key":"prov04e-http-create"}' -o "$qa_tmp/bad-csrf.json" -w '%{http_code}' "$endpoint/locations")"
[[ "$bad_csrf" == 403 ]]
echo 'QA_HTTP_CSRF_DENY=PASS'
good_status="$(curl -sS -b "__Host-mxmed_session=$session_token" -H 'Origin: https://127.0.0.1:8140' -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" -X POST --data '{"branch_name":"HTTP QA","submission_key":"prov04e-http-create"}' \
  -o "$qa_tmp/created.json" -w '%{http_code}' "$endpoint/locations")"
[[ "$good_status" == 201 ]] && php -r '$x=json_decode(file_get_contents($argv[1]),true);exit(($x["data"]["verification_state"]??"")==="UNVERIFIED"?0:1);' "$qa_tmp/created.json"
echo 'QA_HTTP_AUTHENTICATED_CREATE=PASS'
