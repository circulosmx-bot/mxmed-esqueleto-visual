#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
TMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/mxmed-monitor-compare.XXXXXX")"
trap 'python3 - "$TMP_ROOT" <<'"'"'PY'"'"'
import shutil,sys
shutil.rmtree(sys.argv[1],ignore_errors=True)
PY' EXIT
export MXMED_CLINICAL_M6_OBSERVABILITY_ROOT="$TMP_ROOT/authority"
php "$ROOT/scripts/clinical-m6-monitor.php" init "$MXMED_CLINICAL_M6_OBSERVABILITY_ROOT" >/dev/null
php "$ROOT/scripts/clinical-m6-monitor.php" heartbeat >/dev/null
php "$ROOT/scripts/clinical-m6-monitor.php" baseline concurrent --minutes 5 >/dev/null
pids=()
for _ in $(seq 1 8); do php "$ROOT/modules/clinical/qa/m6_monitoring_emit_worker.php" 40 & pids+=("$!"); done
for _ in $(seq 1 10); do php "$ROOT/scripts/clinical-m6-monitor.php" compare concurrent --minutes 5 >/dev/null; done
for pid in "${pids[@]}"; do wait "$pid"; done
php "$ROOT/scripts/clinical-m6-monitor.php" compare concurrent --minutes 5 >"$TMP_ROOT/compare.json"
php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(!isset($v["delta"]["event_count"],$v["comparison"]["unexpected_error_rate"],$v["comparison"]["latency"],$v["comparison"]["monitoring_health"],$v["recommended_action"]))exit(1);' "$TMP_ROOT/compare.json"
echo "M6_MONITORING_CONCURRENT_APPEND_COMPARE=PASS"
