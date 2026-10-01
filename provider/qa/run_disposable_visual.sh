#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../.." && pwd)"
qa_db="mxmed_gate4d_preview_prov04f_$(openssl rand -hex 6)"
qa_tmp="$(mktemp -d /tmp/prov04f-portal-XXXXXXXX)"
qa_pepper="$(openssl rand -hex 32)"
captures="$root_dir/.tmp/prov04f/captures"
cleanup() {
  [[ -z "${proxy_pid:-}" ]] || { kill "$proxy_pid" 2>/dev/null || true; wait "$proxy_pid" 2>/dev/null || true; }
  [[ -z "${front_pid:-}" ]] || { kill "$front_pid" 2>/dev/null || true; wait "$front_pid" 2>/dev/null || true; }
  [[ -z "${back_pid:-}" ]] || { kill "$back_pid" 2>/dev/null || true; wait "$back_pid" 2>/dev/null || true; }
  [[ -z "${resp_pid:-}" ]] || { kill "$resp_pid" 2>/dev/null || true; wait "$resp_pid" 2>/dev/null || true; }
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
  rm -rf "$qa_tmp"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" < "$root_dir/modules/identity/db/migrations/2026_07_20_04_create_auth_account_credentials.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_08_provider_invitee_resolution_attempts.sql"
PROV04F_QA_DB="$qa_db" PROV04F_HTTP_FIXTURE="$qa_tmp/session.json" PROV04F_HTTP_PEPPER="$qa_pepper" php "$root_dir/modules/agenda/qa/prov04f_prep_disposable_gate.php" > "$qa_tmp/prep.log"
PROV04F_QA_DB="$qa_db" PROV04F_HTTP_PEPPER="$qa_pepper" PROV04F_HTTP_EXTRA_FIXTURE="$qa_tmp/extra.json" php "$root_dir/provider/qa/disposable_fixture.php"
python3 "$root_dir/provider/qa/session_stub.py" "$qa_tmp/session.json" "$qa_tmp/extra.json" > "$qa_tmp/resp.log" 2>&1 & resp_pid=$!
APP_ENV=local MXMED_ENVIRONMENT=local MXMED_PREVIEW_EXPLICIT=1 MXMED_PREVIEW_PEPPER="$qa_pepper" MXMED_DB_NAME="$qa_db" MXMED_PREVIEW_ORIGIN='https://127.0.0.1:8140' MXMED_PREVIEW_ROLE=backend php -S 127.0.0.1:8141 -t "$root_dir" "$root_dir/scripts/gate4d-preview-router.php" > "$qa_tmp/backend.log" 2>&1 & back_pid=$!
APP_ENV=local MXMED_ENVIRONMENT=local MXMED_PREVIEW_EXPLICIT=1 MXMED_PREVIEW_PEPPER="$qa_pepper" MXMED_PREVIEW_ORIGIN='https://127.0.0.1:8140' MXMED_PREVIEW_ROLE=frontend php -S 127.0.0.1:18150 -t "$root_dir" "$root_dir/scripts/gate4d-preview-router.php" > "$qa_tmp/frontend.log" 2>&1 & front_pid=$!
openssl req -x509 -newkey rsa:2048 -nodes -keyout "$qa_tmp/key.pem" -out "$qa_tmp/cert.pem" -days 1 -subj '/CN=127.0.0.1' >/dev/null 2>&1
python3 "$root_dir/provider/qa/tls_proxy.py" "$qa_tmp/cert.pem" "$qa_tmp/key.pem" > "$qa_tmp/proxy.log" 2>&1 & proxy_pid=$!
sleep 1
status="$(curl -ksS -o /dev/null -w '%{http_code}' https://127.0.0.1:8140/provider/)"
[[ "$status" == '302' ]] || { cat "$qa_tmp/backend.log"; cat "$qa_tmp/frontend.log"; echo "AUTH_ROUTE_STATUS=$status"; exit 1; }
echo 'QA_UNAUTHENTICATED_PORTAL_REDIRECT=PASS'
python3 "$root_dir/provider/qa/visual_qa.py" "$qa_tmp/session.json" "$captures" "$qa_tmp/extra.json" || { cat "$qa_tmp/backend.log"; cat "$qa_tmp/frontend.log"; cat "$qa_tmp/proxy.log"; exit 1; }
echo "CAPTURES=$captures"
