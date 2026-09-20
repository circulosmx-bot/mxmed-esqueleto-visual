#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
LIB="$ROOT/api/_lib/clinical_m6_write_window.php"
ROUTER="$ROOT/api/clinical/index.php"
grep -q "flock(\$handle, LOCK_EX)" "$LIB"
grep -q "active_writers" "$LIB"
grep -q "register_shutdown_function('clinical_m6_write_window_release')" "$LIB"
grep -q "M6_WRITE_WINDOW_BLOCKED" "$ROUTER"
grep -q "clinical_m6_write_window_admit" "$ROOT/api/clinical-documents.php"
grep -q "clinical_m6_write_window_admit" "$ROOT/api/evolution-note-generate.php"
grep -q "clinical_m6_write_window_assert_bridge_open" "$ROOT/modules/agenda/services/ClinicalEncounterBridge.php"
grep -q "!clinical_m6_write_window_blocks_writes()" "$ROUTER"
echo "M6_WRITE_WINDOW_STATIC_CHECK=PASS"
