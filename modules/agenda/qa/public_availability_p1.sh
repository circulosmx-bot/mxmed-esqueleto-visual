#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8090}"
DOCTOR_ID="${DOCTOR_ID:-1}"

echo "============================================================"
echo "QA Agenda Public Availability P1"
echo "BASE_URL=$BASE_URL"
echo "DOCTOR_ID=$DOCTOR_ID"
echo "============================================================"

echo
echo "[1] next: 3 days (no consultorio_id)"
curl -sS "$BASE_URL/api/agenda/index.php/public/availability?doctor_id=$DOCTOR_ID&mode=next&days=3" | head -c 800; echo
echo "[OK] next responded"

echo
echo "[2] week offset 0 limit_per_day=5"
curl -sS "$BASE_URL/api/agenda/index.php/public/availability?doctor_id=$DOCTOR_ID&mode=week&week_offset=0&limit_per_day=5" | head -c 800; echo
echo "[OK] week responded"

echo
echo "[3] week offset 3 (max)"
curl -sS "$BASE_URL/api/agenda/index.php/public/availability?doctor_id=$DOCTOR_ID&mode=week&week_offset=3&limit_per_day=5" | head -c 800; echo
echo "[OK] week max responded"

echo
echo "[OK] QA finished"
