#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8009}"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONSULTORIO_ID="${CONSULTORIO_ID:-1}"
DATE_NORMAL="${DATE_NORMAL:-2026-02-05}"
DATE_HOLIDAY="${DATE_HOLIDAY:-2026-01-01}"
SLOT_MINUTES="${SLOT_MINUTES:-30}"

fail=0

PYTHON_BIN=$(command -v python3 || command -v python || true)
if [[ -z "$PYTHON_BIN" ]]; then
  echo "python3/python not found in PATH"
  exit 1
fi

fail_assert() {
  echo "FAIL: $*"
  ((fail++))
}

run() {
  local title="$1"
  local url="$2"
  local windows_min="${3:-0}"
  local slots_min="${4:-0}"
  echo "=== $title ==="
  echo "GET $url"
  local resp
  resp=$(curl -s -w "\n%{http_code}" "$url")
  local code="${resp##*$'\n'}"
  resp="${resp%$'\n'*}"
  echo "HTTP $code"
  echo "Body: ${resp:0:300}"

  if [[ "${resp:0:1}" != "{" ]]; then
    echo "FAIL: not JSON"
    ((fail++))
    return
  fi
  for k in '"ok"' '"error"' '"message"' '"data"' '"meta"'; do
    if ! grep -q "$k" <<<"$resp"; then
      echo "FAIL: missing $k"
      ((fail++))
      return
    fi
  done
  for k in '"windows"' '"slots"'; do
    if ! grep -q "$k" <<<"$resp"; then
      echo "FAIL: missing $k in data"
      ((fail++))
      return
    fi
  done

  if [[ -z "$resp" ]]; then
    fail_assert "empty response body"
    return
  fi
  local counts
  counts=$(printf '%s' "$resp" | $PYTHON_BIN -c '
import json,sys
obj=json.loads(sys.stdin.read())
data=obj.get("data", {})
windows=data.get("windows", [])
slots=data.get("slots", [])
def unique_windows(arr):
    seen=set()
    for item in arr:
        key=(item.get("start_at"), item.get("end_at"), item.get("source", "A"))
        if key not in seen:
            seen.add(key)
    return len(seen)
def unique_slots(arr):
    seen=set()
    for item in arr:
        key=(item.get("start_at"), item.get("end_at"))
        if key not in seen:
            seen.add(key)
    return len(seen)
print(len(windows), len(slots), unique_windows(windows), unique_slots(slots))
')
  local windows_count="${counts%% *}"
  local slots_count="$(cut -d' ' -f2 <<<"$counts")"
  local windows_unique="$(cut -d' ' -f3 <<<"$counts")"
  local slots_unique="$(cut -d' ' -f4 <<<"$counts")"
  if (( windows_unique < windows_min )) || (( slots_unique < slots_min )); then
    fail_assert "insufficient unique windows/slots ($windows_unique/$slots_unique < $windows_min/$slots_min)"
    return
  fi
  if (( windows_unique != windows_count )); then
    echo "FAIL: windows duplicates detected ($windows_unique unique vs $windows_count total)"
    ((fail++))
    return
  fi
  if (( slots_unique != slots_count )); then
    echo "FAIL: slots duplicates detected ($slots_unique unique vs $slots_count total)"
    ((fail++))
    return
  fi

  echo "OK"
  echo
}

run "Availability normal day (weekday=2)" "$BASE_URL/api/agenda/index.php/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=2026-02-03&slot_minutes=$SLOT_MINUTES" 1 1
run "Availability holiday (should be 0 slots unless override)" "$BASE_URL/api/agenda/index.php/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=$DATE_HOLIDAY&slot_minutes=$SLOT_MINUTES"
run "Availability control day without schedule (friday)" "$BASE_URL/api/agenda/index.php/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=2026-02-06&slot_minutes=$SLOT_MINUTES" 0 0

if (( fail > 0 )); then
  echo "RESULT: FAIL ($fail issues)"
  exit 1
fi
echo "RESULT: PASS"
