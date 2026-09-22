#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
service="$root_dir/api/_lib/clinical_longitudinal_tasks.php"
route="$root_dir/api/clinical/index.php"
migration="$root_dir/modules/clinical/db/migrations/2026_09_21_09_longitudinal_tasks.sql"
php -l "$service" >/dev/null
php -l "$route" >/dev/null
grep -q "MXMED_LON06A_WRITE_ENABLED" "$service"
grep -q "clinical_m6_write_window_blocks_writes" "$service"
grep -q "clinical_longitudinal_idempotency" "$service"
grep -q "patients/{patient_id}/longitudinal/tasks" "$route"
grep -q "CREATE TRIGGER trg_task_terminal_no_update" "$migration"
grep -q "CREATE TRIGGER trg_task_audit_no_delete" "$migration"
grep -q "fk_task_appointment" "$migration"
! rg -q 'ClinicalLongitudinalTasks|clinical_patient_tasks' "$root_dir/assets/js" "$root_dir/index.html" "$root_dir/api/agenda" "$root_dir/assets/js/clinical/m7-ws05.js"
echo 'LON06A_STATIC_GATE=PASS'
