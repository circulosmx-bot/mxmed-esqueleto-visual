#!/usr/bin/env bash
set -u

UI_BASE="${UI_BASE:-http://127.0.0.1:8092}"
# API_BASE is documented for local setup; this script validates UI output contract.
API_BASE="${API_BASE:-http://127.0.0.1:8091}"

failures=0

check_contains() {
  local body="$1"
  local needle="$2"
  local label="$3"
  if printf '%s' "$body" | grep -qi -- "$needle"; then
    echo "PASS | $label | contains: $needle"
  else
    echo "FAIL | $label | missing: $needle"
    failures=$((failures + 1))
  fi
}

check_absent() {
  local body="$1"
  local needle="$2"
  local label="$3"
  if printf '%s' "$body" | grep -qi -- "$needle"; then
    echo "FAIL | $label | forbidden present: $needle"
    failures=$((failures + 1))
  else
    echo "PASS | $label | absent: $needle"
  fi
}

fetch_body() {
  local url="$1"
  curl -sS --max-time 8 "$url" 2>/dev/null || true
}

fetch_status() {
  local url="$1"
  curl -sS -o /dev/null -w "%{http_code}" --max-time 8 "$url" 2>/dev/null || echo "000"
}

run_embed_checks() {
  local name="$1"
  local path="$2"
  local url="${UI_BASE}${path}"
  local body
  local status

  echo "--- $name (embed) ---"
  echo "URL: $url"
  status="$(fetch_status "$url")"
  echo "HTTP: $status"
  body="$(fetch_body "$url")"

  if [ "$status" != "200" ]; then
    echo "DEBUG | $name embed | non-200 status: $status"
    if [ -n "$body" ]; then
      echo "DEBUG | $name embed | body (first 30 lines):"
      printf '%s\n' "$body" | sed -n '1,30p'
    fi
  fi

  if [ -z "$body" ]; then
    echo "FAIL | $name embed | empty response"
    case "$status" in
      000) echo "DEBUG | $name embed | server down / connect refused" ;;
      500) echo "DEBUG | $name embed | php error; revisar /tmp/mxmed_8092.log" ;;
      404) echo "DEBUG | $name embed | ruta no encontrada" ;;
    esac
    failures=$((failures + 1))
    return
  fi

  # A) must contain embed wrappers
  check_contains "$body" "clinical-embed" "$name embed / rule A"
  check_contains "$body" "clinical-panel" "$name embed / rule A"

  # B) must not contain shell/global html wrappers
  check_absent "$body" "<!doctype" "$name embed / rule B"
  check_absent "$body" "<html" "$name embed / rule B"
  check_absent "$body" "<head" "$name embed / rule B"
  check_absent "$body" "<body" "$name embed / rule B"
  check_absent "$body" "<div class=\"header-top\"" "$name embed / rule B"
  check_absent "$body" "<div class=\"header-mid\"" "$name embed / rule B"
  check_absent "$body" "mm-wrap" "$name embed / rule B"
  check_absent "$body" "mm-grid" "$name embed / rule B"
  check_absent "$body" "mm-main" "$name embed / rule B"
}

run_standalone_historial_check() {
  local name="historial"
  local path="/modules/clinical/ui/historial.php?patient_id=demo"
  local url="${UI_BASE}${path}"
  local body
  local status

  echo "--- $name (standalone) ---"
  echo "URL: $url"
  status="$(fetch_status "$url")"
  echo "HTTP: $status"
  body="$(fetch_body "$url")"

  if [ "$status" != "200" ]; then
    echo "DEBUG | $name standalone | non-200 status: $status"
    if [ -n "$body" ]; then
      echo "DEBUG | $name standalone | body (first 30 lines):"
      printf '%s\n' "$body" | sed -n '1,30p'
    fi
  fi

  if [ -z "$body" ]; then
    echo "FAIL | $name standalone | empty response"
    case "$status" in
      000) echo "DEBUG | $name standalone | server down / connect refused" ;;
      500) echo "DEBUG | $name standalone | php error; revisar /tmp/mxmed_8092.log" ;;
      404) echo "DEBUG | $name standalone | ruta no encontrada" ;;
    esac
    failures=$((failures + 1))
    return
  fi

  # C) standalone must contain full shell signals
  if printf '%s' "$body" | grep -Eqi "<!doctype|<html"; then
    echo "PASS | $name standalone / rule C | has doctype/html"
  else
    echo "FAIL | $name standalone / rule C | missing doctype/html"
    failures=$((failures + 1))
  fi

  check_contains "$body" "header-top" "$name standalone / rule C"
}

echo "UI_BASE=$UI_BASE"
echo "API_BASE=$API_BASE"

run_embed_checks "historial" "/modules/clinical/ui/historial.php?patient_id=demo&embed=1"
run_embed_checks "encounter" "/modules/clinical/ui/encounter.php?encounter_key=appt:demo&embed=1"
run_embed_checks "document" "/modules/clinical/ui/document.php?uuid=demo&embed=1"
run_standalone_historial_check

echo "--- summary ---"
if [ "$failures" -eq 0 ]; then
  echo "PASS | embed contract checks"
  exit 0
else
  echo "FAIL | embed contract checks | failures=$failures"
  exit 1
fi
