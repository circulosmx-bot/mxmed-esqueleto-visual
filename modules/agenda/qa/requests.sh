#!/bin/bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8089/api/agenda/index.php}"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONSULTORIO_ID="${CONSULTORIO_ID:-1}"
APPOINTMENT_ID="${APPOINTMENT_ID:-demo}"
PATIENT_ID="${PATIENT_ID:-demo}"
DATE="${DATE:-2026-02-01}"
QA_MODE="${QA_MODE:-not_ready}"
QA_HEADER=(-H "X-QA-Mode: $QA_MODE")
LAST_METHOD=""
LAST_URL=""

curl_request() {
  local method="GET"
  local url=""
  local i=1
  while [[ $i -le $# ]]; do
    local arg="${!i}"
    if [[ "$arg" == "-X" ]]; then
      ((i++))
      method="${!i}"
    elif [[ "$arg" == http*://* ]]; then
      url="$arg"
    fi
    ((i++))
  done
  LAST_METHOD="$method"
  LAST_URL="$url"
  curl -sS "${QA_HEADER[@]}" "$@"
}

need() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Dependency '$1' is required" >&2
    exit 1
  fi
}

need curl
need jq

print_header() {
  local title="$1"
  echo
  echo "=== $title ==="
}

assert_contract() {
  local body="$1"
  if ! echo "$body" | jq -e 'has("ok") and has("error") and has("message") and has("data") and has("meta")' >/dev/null; then
    echo "Response is missing JSON contract fields" >&2
    echo "$body" | jq . >&2 || true
    exit 1
  fi
}

assert_meta_object() {
  local body="$1"
  if ! echo "$body" | jq -e '.meta | type == "object"' >/dev/null; then
    echo "meta is not an object" >&2
    echo "$body" | jq .meta >&2 || true
    exit 1
  fi
}

assert_qa_mode_seen() {
  local body="$1"
  local expected="${QA_MODE:-}"
  if [[ -z "$expected" ]]; then
    return 0
  fi
  local seen
  seen=$(echo "$body" | jq -r '.meta.qa_mode_seen // empty')
  if [[ "$seen" != "$expected" ]]; then
    echo "Expected meta.qa_mode_seen='$expected' but got: '$seen'" >&2
    echo "$body" | jq . >&2 || true
    if [[ -n "$LAST_URL" ]]; then
      echo "Hint: reproduce with:" >&2
      echo "curl -i -H 'X-QA-Mode: ${QA_MODE}' -X ${LAST_METHOD} '${LAST_URL}'" >&2
    fi
    exit 1
  fi
}

assert_ok() {
  local body="$1"
  if ! echo "$body" | jq -e '.ok == true' >/dev/null; then
    echo "Expected ok:true but got:" >&2
    echo "$body" | jq . >&2 || true
    exit 1
  fi
}

assert_error_exact() {
  local body="$1" code="$2" message="$3"
  if ! echo "$body" | jq -e --arg code "$code" --arg msg "$message" '(.ok == false) and (.error == $code) and (.message == $msg) and (.meta | type == "object")' >/dev/null; then
    echo "Expected error=$code message='$message' but got:" >&2
    echo "$body" | jq . >&2 || true
    exit 1
  fi
}

assert_error_any_of() {
  local body="$1"
  shift
  local err
  local msg
  err=$(echo "$body" | jq -r '.error // empty')
  msg=$(echo "$body" | jq -r '.message // empty')
  local pairs=()
  while (( $# )); do
    local code="$1"
    shift
    local message="$1"
    shift
    pairs+=("error=$code message='$message'")
    if [[ "$err" == "$code" && "$msg" == "$message" ]]; then
      return 0
    fi
  done
  echo "Expected one of: ${pairs[*]} but got: error=$err message='$msg'" >&2
  echo "$body" | jq . >&2 || true
  exit 1
}

run_error_test() {
  local title="$1"
  local code="$2"
  local message="$3"
  shift 3
  print_header "$title"
  local response
  response="$("$@")"
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  assert_qa_mode_seen "$response"
  assert_error_exact "$response" "$code" "$message"
}

assert_error_one_of() {
  local response="$1"
  shift
  local match=0
  while (( $# )); do
    local code="$1"; shift
    local message="$1"; shift
    if echo "$response" | jq -e --arg code "$code" --arg msg "$message" '(.ok == false) and (.error == $code) and (.message == $msg) and (.meta | type == "object")' >/dev/null; then
      match=1
      break
    fi
  done
  if (( match == 0 )); then
    echo "Response did not match any expected error pair" >&2
    echo "$response" | jq . >&2 || true
    exit 1
  fi
}

run_error_expect_two() {
  local title="$1"
  local code1="$2"
  local message1="$3"
  local code2="$4"
  local message2="$5"
  shift 5
  print_header "$title"
  local response
  response="$("$@")"
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  if echo "$response" | jq -e --arg code "$code1" --arg msg "$message1" '(.ok == false) and (.error == $code) and (.message == $msg) and (.meta | type == "object")' >/dev/null; then
    return
  fi
  if echo "$response" | jq -e --arg code "$code2" --arg msg "$message2" '(.ok == false) and (.error == $code) and (.message == $msg) and (.meta | type == "object")' >/dev/null; then
    return
  fi
  echo "Expected error=$code1/$code2 message='$message1' or '$message2' but got:" >&2
  echo "$response" | jq . >&2 || true
  exit 1
}

run_success_test() {
  local title="$1"
  shift
  print_header "$title"
  local response
  response="$("$@")"
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  assert_qa_mode_seen "$response"
  assert_ok "$response"
  LAST_RESPONSE="$response"
}

count_events() {
  local body="$1"
  echo "$body" | jq -r '
    .data as $data |
    if $data == null then 0
    elif ($data | type) == "array" then ($data | length)
    elif ($data | type) == "object" and ($data | has("events")) then ($data.events | length)
    else 0
    end'
}

assert_event_increment() {
  local before="$1"
  local after="$2"
  if (( after <= before )); then
    echo "Expected event count to increase (before=$before after=$after)" >&2
    exit 1
  fi
}

assert_flags_contains() {
  local body="$1"
  local matches
  matches=$(echo "$body" | jq -r '
    .data as $data |
    if $data == null then 0
    elif ($data | type) == "array" then ($data | map(select(.reason_code == "no_show" or .reason_code == "late_cancel")) | length)
    else 0
    end')
  if (( matches == 0 )); then
    echo "Expected at least one patient flag with reason_code no_show or late_cancel" >&2
    echo "$body" | jq . >&2 || true
    exit 1
  fi
}

print_header "QA mode"
echo "QA_MODE=$QA_MODE"
echo
if [[ "$QA_MODE" == "not_ready" ]]; then
  response=$(curl_request -X GET "$BASE_URL/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=$DATE")
  print_header "Given agenda tables absent / GET availability"
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  assert_error_exact "$response" "db_not_ready" "availability base schedule not ready"

  run_error_test "Given appointment events missing / GET events" \
    db_not_ready "appointment events not ready" \
    curl_request -X GET "$BASE_URL/appointments/$APPOINTMENT_ID/events"

  run_error_test "Given patient flags missing / GET flags" \
    db_not_ready "patient flags not ready" \
    curl_request -X GET "$BASE_URL/patients/$PATIENT_ID/flags"

  run_error_test "Given appointments missing / POST create" \
    invalid_params "invalid payload for create" \
    curl_request -X POST "$BASE_URL/appointments" -H 'Content-Type: application/json' -d '{}'

  print_header "Given no appointment / PATCH reschedule"
  response=$(curl_request -X PATCH "$BASE_URL/appointments/unknown/reschedule" -H 'Content-Type: application/json' \
    -d '{"motivo_code":"test","from_start_at":"2026-02-01 09:00:00","from_end_at":"2026-02-01 09:30:00","to_start_at":"2026-02-02 09:00:00","to_end_at":"2026-02-02 09:30:00"}')
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  assert_error_any_of "$response" \
    db_error "database error" \
    db_not_ready "appointments table not ready" \
    db_not_ready "appointment events not ready" \
    not_found "appointment not found"

  print_header "Given no appointment / POST cancel"
  response=$(curl_request -X POST "$BASE_URL/appointments/unknown/cancel" -H 'Content-Type: application/json' -d '{"motivo_code":"test"}')
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  assert_error_any_of "$response" \
    db_error "database error" \
    db_not_ready "appointments table not ready" \
    db_not_ready "appointment events not ready" \
    not_found "appointment not found"

  run_error_test "Given invalid payload / POST no_show" \
    invalid_params "invalid payload for no_show" \
    curl_request -X POST "$BASE_URL/appointments/unknown/no_show" -H 'Content-Type: application/json' -d '{}'

  print_header "QA script finished (not_ready mode)"
  echo "Use QA_MODE=ready to exercise the write flow once tables are available."
  exit 0
fi

if [[ "$QA_MODE" == "ready" ]]; then
  print_header "READY MODE: verifying writes"

  create_payload=$(cat <<EOF
{
  "doctor_id": "$DOCTOR_ID",
  "consultorio_id": "$CONSULTORIO_ID",
  "patient_id": "$PATIENT_ID",
  "start_at": "2026-03-01 09:00:00",
  "end_at": "2026-03-01 09:30:00",
  "modality": "presencial",
  "channel_origin": "qa_script",
  "created_by_role": "system",
  "created_by_id": "qa"
}
EOF
  )
  run_success_test "Given tables ready / POST create appointment" \
    curl -s -X POST "$BASE_URL/appointments" -H 'Content-Type: application/json' -d "$create_payload"
  created_appointment_id=$(echo "$LAST_RESPONSE" | jq -r '.data.appointment_id // empty')
  if [[ -z "$created_appointment_id" ]]; then
    echo "Create response missing appointment_id" >&2
    exit 1
  fi

  run_success_test "GET events after create" \
    curl -s -X GET "$BASE_URL/appointments/$created_appointment_id/events"
  events_count=$(count_events "$LAST_RESPONSE")
  if (( events_count < 1 )); then
    echo "Expected at least 1 event after creation" >&2
    exit 1
  fi

  reschedule_payload=$(cat <<EOF
{
  "motivo_code": "qa_reschedule",
  "motivo_text": "QA reschedule",
  "from_start_at": "2026-03-01 09:00:00",
  "from_end_at": "2026-03-01 09:30:00",
  "to_start_at": "2026-03-01 10:00:00",
  "to_end_at": "2026-03-01 10:30:00",
  "channel_origin": "qa_script",
  "actor_role": "system",
  "actor_id": "qa"
}
EOF
  )
  run_success_test "PATCH reschedule appointment" \
    curl -s -X PATCH "$BASE_URL/appointments/$created_appointment_id/reschedule" -H 'Content-Type: application/json' -d "$reschedule_payload"

  run_success_test "GET events after reschedule" \
    curl -s -X GET "$BASE_URL/appointments/$created_appointment_id/events"
  events_after_reschedule=$(count_events "$LAST_RESPONSE")
  assert_event_increment "$events_count" "$events_after_reschedule"
  events_count=$events_after_reschedule

  cancel_payload=$(cat <<EOF
{
  "motivo_code": "qa_cancel",
  "motivo_text": "QA cancel",
  "channel_origin": "qa_script",
  "actor_role": "system",
  "actor_id": "qa"
}
EOF
  )
  run_success_test "POST cancel appointment" \
    curl -s -X POST "$BASE_URL/appointments/$created_appointment_id/cancel" -H 'Content-Type: application/json' -d "$cancel_payload"

  run_success_test "GET events after cancel" \
    curl -s -X GET "$BASE_URL/appointments/$created_appointment_id/events"
  events_after_cancel=$(count_events "$LAST_RESPONSE")
  assert_event_increment "$events_count" "$events_after_cancel"

  no_show_payload=$(cat <<EOF
{
  "doctor_id": "$DOCTOR_ID",
  "consultorio_id": "$CONSULTORIO_ID",
  "patient_id": "$PATIENT_ID",
  "start_at": "2026-03-02 11:00:00",
  "end_at": "2026-03-02 11:30:00",
  "modality": "presencial",
  "channel_origin": "qa_script",
  "created_by_role": "system",
  "created_by_id": "qa"
}
EOF
  )
  run_success_test "POST create appointment for no_show" \
    curl -s -X POST "$BASE_URL/appointments" -H 'Content-Type: application/json' -d "$no_show_payload"
  no_show_appointment_id=$(echo "$LAST_RESPONSE" | jq -r '.data.appointment_id // empty')
  if [[ -z "$no_show_appointment_id" ]]; then
    echo "no_show create response missing appointment_id" >&2
    exit 1
  fi

  run_success_test "GET events before no_show" \
    curl -s -X GET "$BASE_URL/appointments/$no_show_appointment_id/events"
  events_before_no_show=$(count_events "$LAST_RESPONSE")

  no_show_action_payload=$(cat <<EOF
{
  "motivo_code": "qa_no_show",
  "motivo_text": "QA no show",
  "notify_patient": 1,
  "contact_method": "whatsapp",
  "channel_origin": "qa_script",
  "actor_role": "system",
  "actor_id": "qa",
  "observed_at": "2026-03-02 12:00:00"
}
EOF
  )
  run_success_test "POST no_show (appointment)" \
    curl -s -X POST "$BASE_URL/appointments/$no_show_appointment_id/no_show" -H 'Content-Type: application/json' -d "$no_show_action_payload"
  flag_appended=$(echo "$LAST_RESPONSE" | jq -r '.meta.flag_appended // 0')

  run_success_test "GET events after no_show" \
    curl -s -X GET "$BASE_URL/appointments/$no_show_appointment_id/events"
  events_after_no_show=$(count_events "$LAST_RESPONSE")
  assert_event_increment "$events_before_no_show" "$events_after_no_show"

  run_success_test "GET patient flags" \
    curl -s -X GET "$BASE_URL/patients/$PATIENT_ID/flags"
  assert_flags_contains "$LAST_RESPONSE"

  print_header "QA script finished (ready mode)"
  echo "Flag appended: $flag_appended (0=disabled, 1=created)"
  echo "Use QA_MODE=not_ready to re-run the not-ready validations."
  exit 0
fi

print_header "QA mode"
echo "Unknown QA_MODE '$QA_MODE'. Valid values: not_ready, ready."
exit 1
