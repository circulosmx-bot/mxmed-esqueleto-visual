#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="mxmed_gate4d_preview_prov04f_$(openssl rand -hex 6)"
qa_tmp="$(mktemp -d /tmp/prov04f-prep-XXXXXXXX)"
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
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_08_provider_invitee_resolution_attempts.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_08_provider_invitee_resolution_attempts.sql"
PROV04F_QA_DB="$qa_db" PROV04F_HTTP_FIXTURE="$qa_tmp/session.json" PROV04F_HTTP_PEPPER="$qa_pepper" php "$root_dir/modules/agenda/qa/prov04f_prep_disposable_gate.php"
python3 "$root_dir/modules/agenda/qa/prov04e_resp_stub.py" "$qa_tmp/session.json" >"$qa_tmp/resp.log" 2>&1 & resp_pid=$!
APP_ENV=local MXMED_ENVIRONMENT=local MXMED_PREVIEW_EXPLICIT=1 MXMED_PREVIEW_PEPPER="$qa_pepper" \
  MXMED_DB_NAME="$qa_db" MXMED_PREVIEW_ORIGIN='https://127.0.0.1:8140' \
  php -S 127.0.0.1:18150 -t "$root_dir" >"$qa_tmp/http.log" 2>&1 & http_pid=$!
sleep 1
session_token="$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["token"];' "$qa_tmp/session.json")"
csrf_token="$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["csrf"];' "$qa_tmp/session.json")"
base='http://127.0.0.1:18150/api/provider/index.php'
status="$(curl -sS -o "$qa_tmp/no-session.json" -w '%{http_code}' "$base/me/organizations")"
[[ "$status" == 401 ]]; echo 'QA_HTTP_NO_SESSION=PASS'
status="$(curl -sS -b "__Host-mxmed_session=$session_token" -o "$qa_tmp/orgs.json" -w '%{http_code}' "$base/me/organizations")"
[[ "$status" == 200 ]] && php -r '$x=json_decode(file_get_contents($argv[1]),true);exit(count($x["data"]["organizations"]??[])===2?0:1);' "$qa_tmp/orgs.json"
echo 'QA_HTTP_MULTI_ORGANIZATION=PASS'
status="$(curl -sS -b "__Host-mxmed_session=$session_token" -o "$qa_tmp/catalog.json" -w '%{http_code}' "$base/study-types?search=BH")"
[[ "$status" == 200 ]] && php -r '$x=json_decode(file_get_contents($argv[1]),true);exit(count($x["data"]["items"]??[])===1?0:1);' "$qa_tmp/catalog.json"
echo 'QA_HTTP_CATALOG_ALIAS=PASS'
status="$(curl -sS -b "__Host-mxmed_session=$session_token" -H 'Origin: https://127.0.0.1:8140' -H 'Content-Type: application/json' \
  -X POST --data '{"email":"eligible2@example.invalid"}' -o "$qa_tmp/bad-csrf.json" -w '%{http_code}' \
  "$base/organizations/prov_a/invitee-resolution")"
[[ "$status" == 403 ]];echo 'QA_HTTP_CSRF_DENY=PASS'
status="$(curl -sS -b "__Host-mxmed_session=$session_token" -H 'Origin: https://127.0.0.1:8140' -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" -X POST --data '{"email":"eligible2@example.invalid"}' \
  -o "$qa_tmp/resolution.json" -w '%{http_code}' "$base/organizations/prov_a/invitee-resolution")"
[[ "$status" == 200 ]] && php -r '$x=json_decode(file_get_contents($argv[1]),true);exit(($x["data"]["state"]??"")==="FOUND_ELIGIBLE" && !isset($x["data"]["account_id"])?0:1);' "$qa_tmp/resolution.json"
echo 'QA_HTTP_EXACT_EMAIL_NO_ID=PASS'
status="$(curl -sS -b "__Host-mxmed_session=$session_token" -H 'Origin: https://127.0.0.1:8140' -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" -X POST \
  --data '{"invitee_email":"eligible2@example.invalid","role":"collaborator","submission_key":"prov04f-http-email-invite"}' \
  -o "$qa_tmp/invite.json" -w '%{http_code}' "$base/organizations/prov_a/invitations")"
[[ "$status" == 201 ]] && php -r '$x=json_decode(file_get_contents($argv[1]),true);exit(($x["data"]["status"]??"")==="PENDING" && !isset($x["data"]["invitee_account_id"])?0:1);' "$qa_tmp/invite.json"
echo 'QA_HTTP_EMAIL_INVITE_NO_ID=PASS'
status="$(curl -sS -b "__Host-mxmed_session=$session_token" -H 'Origin: https://127.0.0.1:8140' -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $csrf_token" -X POST --data '{"email":"unknown@example.invalid"}' \
  -o "$qa_tmp/cross-org.json" -w '%{http_code}' "$base/organizations/prov_c/invitee-resolution")"
[[ "$status" == 404 ]];echo 'QA_HTTP_CROSS_ORG_DENY=PASS'
