#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
TMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/mxmed-monitor-mp.XXXXXX")"
trap 'python3 - "$TMP_ROOT" <<'"'"'PY'"'"'
import shutil,sys
shutil.rmtree(sys.argv[1],ignore_errors=True)
PY' EXIT
export MXMED_CLINICAL_M6_OBSERVABILITY_ROOT="$TMP_ROOT/authority"
php "$ROOT/scripts/clinical-m6-monitor.php" init "$MXMED_CLINICAL_M6_OBSERVABILITY_ROOT" >/dev/null
pids=()
for _ in $(seq 1 12); do php "$ROOT/modules/clinical/qa/m6_monitoring_emit_worker.php" 50 & pids+=("$!"); done
for pid in "${pids[@]}"; do wait "$pid"; done
php -r '
$path=getenv("MXMED_CLINICAL_M6_OBSERVABILITY_ROOT")."/events.ndjson";$lines=file($path,FILE_IGNORE_NEW_LINES);
if(count($lines)!==612)throw new RuntimeException("event loss: ".count($lines));
foreach($lines as $line){$row=json_decode($line,true,512,JSON_THROW_ON_ERROR);if(($row["schema"]??"")!=="mxmed.m6.telemetry.v1")throw new RuntimeException("bad schema");}
echo "M6_MONITORING_MULTIPROCESS_TESTS=PASS events=".count($lines)."\n";'
