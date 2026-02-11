#!/bin/bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8089/api/agenda/index.php}"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONSULTORIO_ID="${CONSULTORIO_ID:-1}"
APPOINTMENT_ID="${APPOINTMENT_ID:-demo}"
PATIENT_ID="${PATIENT_ID:-demo}"
DATE="${DATE:-2026-02-01}"
QA_MODE="${QA_MODE:-not_ready}"
WAITLIST_PATIENT_NAME="${WAITLIST_PATIENT_NAME:-QA Lista Espera}"
WAITLIST_PATIENT_PHONE="${WAITLIST_PATIENT_PHONE:-5512345678}"
WAITLIST_START="${WAITLIST_START:-2026-02-03 08:00:00}"
WAITLIST_END="${WAITLIST_END:-2026-02-03 08:30:00}"
WAITLIST_SLOT_MINUTES="${WAITLIST_SLOT_MINUTES:-30}"
QA_APPT_START_AT_DEFAULT="2026-02-03 09:00:00"
QA_APPT_END_AT_DEFAULT="2026-02-03 09:30:00"
QA_APPT_SLOT_MINUTES="${QA_APPT_SLOT_MINUTES:-30}"
QA_RESCHED_START_AT="${QA_RESCHED_START_AT:-}"
QA_RESCHED_END_AT="${QA_RESCHED_END_AT:-}"
QA_RESCHED_SLOT_MINUTES="${QA_RESCHED_SLOT_MINUTES:-30}"
if [[ -z "${QA_APPT_START_AT+x}" ]]; then
  QA_APPT_START_AT_PROVIDED=0
else
  QA_APPT_START_AT_PROVIDED=1
fi
if [[ -z "${QA_APPT_END_AT+x}" ]]; then
  QA_APPT_END_AT_PROVIDED=0
else
  QA_APPT_END_AT_PROVIDED=1
fi
QA_APPT_START_AT="${QA_APPT_START_AT:-$QA_APPT_START_AT_DEFAULT}"
QA_APPT_END_AT="${QA_APPT_END_AT:-$QA_APPT_END_AT_DEFAULT}"

QA_HEADER=(-H "X-QA-Mode: $QA_MODE")
LAST_METHOD=""
LAST_URL=""
LAST_RESPONSE=""

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

  # -sS: silencioso pero muestra errores
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

  local err msg
  err=$(echo "$body" | jq -r '.error // empty')
  msg=$(echo "$body" | jq -r '.message // empty')

  local pairs=()
  while (( $# )); do
    local code="$1"; shift
    local message="$1"; shift
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
  print_header "Given agenda tables absent / GET availability"
  response=$(curl_request -X GET "$BASE_URL/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=$DATE")
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  assert_qa_mode_seen "$response"
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
  assert_qa_mode_seen "$response"
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
  assert_qa_mode_seen "$response"
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

  if [[ "$QA_APPT_START_AT_PROVIDED" -eq 0 && "$QA_APPT_END_AT_PROVIDED" -eq 0 ]]; then
    availability_response=$(curl_request -X GET "$BASE_URL/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=$DATE&slot_minutes=$QA_APPT_SLOT_MINUTES")
    if ! echo "$availability_response" | jq -e '.ok == true' >/dev/null; then
      echo "$availability_response" | jq .
      echo "No available slots for QA appointment" >&2
      exit 1
    fi

    slot_candidates=$(printf '%s\n' "$availability_response" | jq -r '
      (.data.slots // .slots // []) as $slots |
      (.data.available // .available // []) as $available |
      (.data.windows // .windows // []) as $windows |
      (($slots | length) + ($available | length) + ($windows | length))
    ')

    if [[ -z "$slot_candidates" || "$slot_candidates" -eq 0 ]]; then
      echo "$availability_response" | jq .
      echo "No available slots for QA appointment" >&2
      exit 1
    fi

    slot_entry=$(printf '%s\n' "$availability_response" | jq -c '
      (
        (.data.slots // .slots // []) as $slots |
        (.data.available // .available // []) as $available |
        (.data.windows // .windows // []) as $windows
      ) |
      if ($slots | length) > 0 then $slots[0]
      elif ($available | length) > 0 then $available[0]
      elif ($windows | length) > 0 then $windows[0]
      else null end
    ')

    if [[ -z "$slot_entry" || "$slot_entry" == "null" ]]; then
      echo "$availability_response" | jq .
      echo "Cannot parse availability response for slots" >&2
      exit 1
    fi

    slot_start=$(printf '%s\n' "$slot_entry" | jq -r '(.start_at // .start // .from // empty)')
    slot_end=$(printf '%s\n' "$slot_entry" | jq -r '(.end_at // .end // .to // empty)')
    if [[ -z "$slot_start" || -z "$slot_end" ]]; then
      echo "$availability_response" | jq .
      echo "Cannot parse availability response for slots" >&2
      exit 1
    fi

    QA_APPT_START_AT="$slot_start"
    QA_APPT_END_AT="$slot_end"
    echo "Selected slot from availability: $QA_APPT_START_AT -> $QA_APPT_END_AT"
  fi

  create_payload=$(cat <<EOF
{
  "doctor_id": "$DOCTOR_ID",
  "consultorio_id": "$CONSULTORIO_ID",
  "patient_id": "$PATIENT_ID",
  "start_at": "$QA_APPT_START_AT",
  "end_at": "$QA_APPT_END_AT",
  "slot_minutes": $QA_APPT_SLOT_MINUTES,
  "modality": "presencial",
  "channel_origin": "qa_script",
  "created_by_role": "system",
  "created_by_id": "qa"
}
EOF
  )

  print_header "Given tables ready / POST create appointment"
  response=$(curl_request -X POST "$BASE_URL/appointments" -H 'Content-Type: application/json' -d "$create_payload")
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  assert_qa_mode_seen "$response"

  # Si la DB no está disponible en ready, salimos limpio (comportamiento anterior).
  if echo "$response" | jq -e '.ok == false and .error == "db_error"' >/dev/null; then
    print_header "QA script finished (ready mode)"
    echo "DB not available: write flow skipped (expected db_error)."
    exit 0
  fi

  # Si sí pudo escribir, seguimos con el flujo completo.
  assert_ok "$response"
  LAST_RESPONSE="$response"

  created_appointment_id=$(echo "$LAST_RESPONSE" | jq -r '.data.appointment_id // empty')
  if [[ -z "$created_appointment_id" ]]; then
    echo "Create response missing appointment_id" >&2
    exit 1
  fi

  run_success_test "GET events after create" \
    curl_request -X GET "$BASE_URL/appointments/$created_appointment_id/events"

  events_count=$(count_events "$LAST_RESPONSE")
  if (( events_count < 1 )); then
    echo "Expected at least 1 event after creation" >&2
    exit 1
  fi

  resched_start="$QA_RESCHED_START_AT"
  resched_end="$QA_RESCHED_END_AT"
  resched_slot_minutes="$QA_RESCHED_SLOT_MINUTES"

  if [[ -z "$resched_start" || -z "$resched_end" ]]; then
    availability_response=$(curl_request -X GET "$BASE_URL/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=$DATE&slot_minutes=$QA_RESCHED_SLOT_MINUTES")
    if ! echo "$availability_response" | jq -e '.ok == true' >/dev/null; then
      echo "$availability_response" | jq .
      echo "No available slots for QA appointment" >&2
      exit 1
    fi

    slot_candidates=$(printf '%s\n' "$availability_response" | jq -c '
      (
        [
          (.data.slots // .slots // []),
          (.data.available // .available // []),
          (.data.windows // .windows // [])
        ] | flatten(1)
      ) | map({
        start: (.start_at // .start // .from // empty),
        end: (.end_at // .end // .to // empty)
      }) | map(select(.start != "" and .end != ""))
    ')

    slot_count=$(printf '%s\n' "$slot_candidates" | jq 'length')
    if [[ "$slot_count" -eq 0 ]]; then
      echo "$availability_response" | jq .
      echo "No available slots for QA appointment" >&2
      exit 1
    fi

    slot_entry=$(printf '%s\n' "$slot_candidates" | jq -c --arg start "$QA_APPT_START_AT" --arg end "$QA_APPT_END_AT" '
      map(select(.start != $start or .end != $end)) | .[0]
    ')

    if [[ -z "$slot_entry" || "$slot_entry" == "null" ]]; then
      echo "$availability_response" | jq .
      echo "No alternate slot available for reschedule" >&2
      exit 1
    fi

    resched_start=$(printf '%s\n' "$slot_entry" | jq -r '.start // empty')
    resched_end=$(printf '%s\n' "$slot_entry" | jq -r '.end // empty')
    if [[ -z "$resched_start" || -z "$resched_end" ]]; then
      echo "$availability_response" | jq .
      echo "Cannot parse availability response for reschedule slots" >&2
      exit 1
    fi
  fi

  echo "Selected reschedule slot: $resched_start -> $resched_end"

  reschedule_payload=$(cat <<EOF
{
  "motivo_code": "qa_reschedule",
  "motivo_text": "QA reschedule",
  "from_start_at": "$QA_APPT_START_AT",
  "from_end_at": "$QA_APPT_END_AT",
  "to_start_at": "$resched_start",
  "to_end_at": "$resched_end",
  "slot_minutes": $resched_slot_minutes,
  "channel_origin": "qa_script",
  "actor_role": "system",
  "actor_id": "qa"
}
EOF
  )

  run_success_test "PATCH reschedule appointment" \
    curl_request -X PATCH "$BASE_URL/appointments/$created_appointment_id/reschedule" -H 'Content-Type: application/json' -d "$reschedule_payload"

  run_success_test "GET events after reschedule" \
    curl_request -X GET "$BASE_URL/appointments/$created_appointment_id/events"

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
    curl_request -X POST "$BASE_URL/appointments/$created_appointment_id/cancel" -H 'Content-Type: application/json' -d "$cancel_payload"

  run_success_test "GET events after cancel" \
    curl_request -X GET "$BASE_URL/appointments/$created_appointment_id/events"

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
    curl_request -X POST "$BASE_URL/appointments" -H 'Content-Type: application/json' -d "$no_show_payload"

  no_show_appointment_id=$(echo "$LAST_RESPONSE" | jq -r '.data.appointment_id // empty')
  if [[ -z "$no_show_appointment_id" ]]; then
    echo "no_show create response missing appointment_id" >&2
    exit 1
  fi

  run_success_test "GET events before no_show" \
    curl_request -X GET "$BASE_URL/appointments/$no_show_appointment_id/events"

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
    curl_request -X POST "$BASE_URL/appointments/$no_show_appointment_id/no_show" -H 'Content-Type: application/json' -d "$no_show_action_payload"

  flag_appended=$(echo "$LAST_RESPONSE" | jq -r '.meta.flag_appended // 0')

  run_success_test "GET events after no_show" \
    curl_request -X GET "$BASE_URL/appointments/$no_show_appointment_id/events"

  events_after_no_show=$(count_events "$LAST_RESPONSE")
  assert_event_increment "$events_before_no_show" "$events_after_no_show"

  run_success_test "GET patient flags" \
    curl_request -X GET "$BASE_URL/patients/$PATIENT_ID/flags"

  assert_flags_contains "$LAST_RESPONSE"

  print_header "Waitlist minimal flow"

  waitlist_payload=$(cat <<EOF
{
  "doctor_id": "$DOCTOR_ID",
  "consultorio_id": "$CONSULTORIO_ID",
  "patient_name": "$WAITLIST_PATIENT_NAME",
  "patient_phone": "$WAITLIST_PATIENT_PHONE"
}
EOF
  )

  run_success_test "POST waitlist entry" \
    curl_request -X POST "$BASE_URL/waitlist" -H 'Content-Type: application/json' -d "$waitlist_payload"

  waitlist_entry_id=$(echo "$LAST_RESPONSE" | jq -r '.data.id // empty')
  if [[ -z "$waitlist_entry_id" ]]; then
    echo "waitlist create response missing id" >&2
    exit 1
  fi

  run_success_test "GET waitlist active entries" \
    curl_request -X GET "$BASE_URL/waitlist?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&status=active"

  if ! echo "$LAST_RESPONSE" | jq -e --arg id "$waitlist_entry_id" '.data | type=="array" and any(.[]; .id == $id)' >/dev/null; then
    echo "Created waitlist entry not listed" >&2
    exit 1
  fi

  run_success_test "PATCH waitlist status to contacted" \
    curl_request -X PATCH "$BASE_URL/waitlist/$waitlist_entry_id" -H 'Content-Type: application/json' -d '{"status":"contacted"}'

  run_success_test "POST assign waitlist entry" \
    curl_request -X POST "$BASE_URL/waitlist/$waitlist_entry_id/assign" -H 'Content-Type: application/json' -d "$(
cat <<EOF
{
  "doctor_id": "$DOCTOR_ID",
  "consultorio_id": "$CONSULTORIO_ID",
  "start_at": "$WAITLIST_START",
  "end_at": "$WAITLIST_END",
  "slot_minutes": $WAITLIST_SLOT_MINUTES,
  "override": false,
  "override_reason": "QA assign",
  "linked_cancelled_appointment_id": null,
  "actor_role": "operator",
  "actor_id": "qa",
  "channel_origin": "qa_waitlist"
}
EOF
)"

  assigned_appointment_id=$(echo "$LAST_RESPONSE" | jq -r '.data.appointment_id // empty')
  if [[ -z "$assigned_appointment_id" ]]; then
    echo "Waitlist assign response missing appointment_id" >&2
    exit 1
  fi

  status_after_assign=$(echo "$LAST_RESPONSE" | jq -r '.data.entry.status // empty')
  if [[ "$status_after_assign" != "confirmed" ]]; then
    echo "Expected confirmed status after assign, got '$status_after_assign'" >&2
    exit 1
  fi

  echo "Waitlist assigned appointment: $assigned_appointment_id"

  print_header "QA script finished (ready mode)"
  echo "Flag appended: $flag_appended (0=disabled, 1=created)"
  echo "Use QA_MODE=not_ready to re-run the not-ready validations."
  exit 0
fi

print_header "QA mode"
echo "Unknown QA_MODE '$QA_MODE'. Valid values: not_ready, ready."
exit 1
