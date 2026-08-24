#!/bin/sh
set -eu

fail() {
  printf '%s\n' "C3_RUNNER_FAIL_CLOSED:$1" >&2
  exit 1
}

[ "${APP_ENV:-}" = 'staging' ] || fail 'NON_STAGING_ENVIRONMENT'
[ "${SESSION_PREFIX:-}" = 'mxmed:stg:session:' ] || fail 'SESSION_PREFIX_MISMATCH'
[ "${SESSION_PORT:-}" = '6379' ] || fail 'SESSION_PORT_MISMATCH'
[ -n "${SESSION_HOST:-}" ] || fail 'SESSION_HOST_MISSING'
[ -n "${SESSION_AUTH_SECRET_ARN:-}" ] || fail 'SESSION_SECRET_ARN_MISSING'
[ "${C3_TASK_TIMEOUT_SECONDS:-}" = '900' ] || fail 'TIMEOUT_CONTRACT_MISMATCH'

case "$SESSION_AUTH_SECRET_ARN" in
  arn:aws:secretsmanager:mx-central-1:875691018466:secret:/mxmed/staging/application/session-store-auth-*) ;;
  *) fail 'SESSION_SECRET_ARN_OUT_OF_SCOPE' ;;
esac

secret_json="$(aws secretsmanager get-secret-value \
  --region mx-central-1 \
  --secret-id "$SESSION_AUTH_SECRET_ARN" \
  --query SecretString \
  --output text)" || fail 'SESSION_SECRET_ACQUISITION_FAILED'

credentials="$(printf '%s' "$secret_json" | php -r '
  $value = json_decode(stream_get_contents(STDIN), true, 8, JSON_THROW_ON_ERROR);
  $username = $value["username"] ?? null;
  $password = $value["password"] ?? null;
  if (!is_string($username) || $username !== "mxmed_session_app" || !is_string($password) || strlen($password) < 32) { exit(42); }
  echo base64_encode($username), "\n", base64_encode($password);
')" || fail 'SESSION_SECRET_SCHEMA_INVALID'
unset secret_json

SESSION_STORE_USERNAME="$(printf '%s\n' "$credentials" | sed -n '1p' | base64 -d)"
SESSION_STORE_PASSWORD="$(printf '%s\n' "$credentials" | sed -n '2p' | base64 -d)"
unset credentials
SESSION_SIGNING_KEY="$(head -c 48 /dev/urandom | base64)"
MXMED_C3_PHYSICAL_VALKEY_TEST_AUTHORIZED='DIRECTOR_AUTHORIZED_ISOLATED_C3'
export SESSION_STORE_USERNAME SESSION_STORE_PASSWORD SESSION_SIGNING_KEY
export MXMED_C3_PHYSICAL_VALKEY_TEST_AUTHORIZED

printf '%s\n' 'C3_RUNNER_START:staging:tls-valkey'
exec timeout --signal=TERM --kill-after=10s 900 \
  php /app/modules/identity/tests/C3ValkeySessionStoreIntegrationTest.php
