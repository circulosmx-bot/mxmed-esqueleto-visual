#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8009}"

fail_count=0

run_test() {
  local title="$1"
  local url="$2"
  local method="${3:-GET}"

  echo "=== $title ==="
  echo "URL: $url"

  local body
  body=$(curl -s -w "\n%{http_code}" -X "$method" "$url")
  local http_code="${body##*$'\n'}"
  body="${body%$'\n'*}"

  echo "HTTP $http_code"
  echo "Body: ${body:0:300}"

  if [[ "${body:0:1}" != "{" ]]; then
    echo "FAIL: body is not JSON"
    ((fail_count++))
    return
  fi

  for key in '"ok"' '"error"' '"message"' '"data"' '"meta"'; do
    if ! grep -q "$key" <<<"$body"; then
      echo "FAIL: missing key $key"
      ((fail_count++))
      return
    fi
  done

  echo "OK"
  echo
}

# Tests
run_test "Route not implemented" "$BASE_URL/api/agenda/loquesea"
run_test "Appointments without params" "$BASE_URL/api/agenda/appointments"
run_test "Appointments with range" "$BASE_URL/api/agenda/appointments?from=2026-02-01T00:00:00&to=2026-02-02T00:00:00"
run_test "Appointment show invalid id string" "$BASE_URL/api/agenda/appointments/abc"
run_test "Appointment show not found id" "$BASE_URL/api/agenda/appointments/999999"
run_test "Appointment events not found or not ready" "$BASE_URL/api/agenda/appointments/999999/events"
run_test "Consultorios without params" "$BASE_URL/api/agenda/consultorios"
run_test "Availability without params" "$BASE_URL/api/agenda/availability"
run_test "Patient flags invalid patient id" "$BASE_URL/api/agenda/patients/abc/flags"
run_test "Patient flags invalid active_only" "$BASE_URL/api/agenda/patients/1/flags?active_only=2"

if (( fail_count > 0 )); then
  echo "RESULT: FAIL ($fail_count issues)"
  exit 1
else
  echo "RESULT: PASS"
fi
