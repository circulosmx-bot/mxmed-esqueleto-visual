#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
TMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/mxmed-monitor-profile.XXXXXX")"
trap 'python3 - "$TMP_ROOT" <<'"'"'PY'"'"'
import shutil,sys
shutil.rmtree(sys.argv[1],ignore_errors=True)
PY' EXIT
export MXMED_CLINICAL_M6_OBSERVABILITY_ROOT="$TMP_ROOT/authority"
cat >"$TMP_ROOT/profile.json" <<'JSON'
{"schema":"mxmed.m6.threshold-profile.v1","profile_id":"DISPOSABLE_TEST_THRESHOLD","profile_version":1,"status":"active","rollout_stage":"internal_exact_pair","rules":[{"id":"unexpected_error_rate","maximum_rate":0.1,"minimum_requests":1,"unit":"ratio","action":"DEACTIVATE"},{"id":"route_latency","mode":"both","maximum_ms":1000,"maximum_multiplier":2,"minimum_samples":1,"unit":"milliseconds","action":"HOLD"},{"id":"visibility","maximum_age_seconds":300,"unit":"seconds","action":"HOLD"}]}
JSON
php "$ROOT/scripts/clinical-m6-monitor.php" init "$MXMED_CLINICAL_M6_OBSERVABILITY_ROOT" >/dev/null
php "$ROOT/scripts/clinical-m6-monitor.php" heartbeat >/dev/null
php "$ROOT/scripts/clinical-m6-monitor.php" status --minutes 5 >"$TMP_ROOT/no-profile.json"
php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(($v["rollout_evaluation_valid"]??true)!==false||($v["rules"]["recommended_action"]??"")!=="HOLD"||($v["rules"]["blocking_identity"]??"")!=="MONITORING_THRESHOLD_PROFILE_REQUIRED")exit(1);' "$TMP_ROOT/no-profile.json"
args=(--profile "$TMP_ROOT/profile.json" --stage internal_exact_pair --minutes 5)
php "$ROOT/scripts/clinical-m6-monitor.php" baseline profiled "${args[@]}" >/dev/null
python3 - "$TMP_ROOT/profile.json" "$TMP_ROOT/profile-v2.json" <<'PY'
import json,sys
p=json.load(open(sys.argv[1]));p['profile_version']=2
json.dump(p,open(sys.argv[2],'w'),separators=(',',':'))
PY
if php "$ROOT/scripts/clinical-m6-monitor.php" compare profiled --profile "$TMP_ROOT/profile-v2.json" --stage internal_exact_pair --minutes 5 >/dev/null 2>&1; then exit 1; fi
php "$ROOT/scripts/clinical-m6-monitor.php" status "${args[@]}" >"$TMP_ROOT/status.json"
php "$ROOT/scripts/clinical-m6-monitor.php" compare profiled "${args[@]}" >"$TMP_ROOT/compare.json"
php "$ROOT/scripts/clinical-m6-monitor.php" snapshot profiled --baseline profiled "${args[@]}" >"$TMP_ROOT/snapshot.json"
php "$ROOT/scripts/clinical-m6-monitor.php" decision HOLD PROFILE_TEST --profile "$TMP_ROOT/profile.json" --stage internal_exact_pair >"$TMP_ROOT/decision.json"
php -r '
$files=array_slice($argv,1);foreach($files as $file){$v=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$json=json_encode($v);if(str_contains($json,"patient-secret")||str_contains($json,"raw-token"))exit(1);}
$status=json_decode(file_get_contents($files[0]),true);$snapshot=json_decode(file_get_contents($files[2]),true);$decision=json_decode(file_get_contents($files[3]),true);
if(($status["threshold_profile"]["id"]??"")!=="DISPOSABLE_TEST_THRESHOLD"||strlen($status["threshold_profile"]["hash"]??"")!==64)exit(1);
if(($snapshot["evidence"]["status"]["threshold_profile"]["id"]??"")!=="DISPOSABLE_TEST_THRESHOLD"||!is_array($snapshot["evidence"]["comparison"]??null))exit(1);
if(($decision["threshold_profile"]["id"]??"")!=="DISPOSABLE_TEST_THRESHOLD"||($decision["feature_gate_changed"]??true)!==false)exit(1);
' "$TMP_ROOT/status.json" "$TMP_ROOT/compare.json" "$TMP_ROOT/snapshot.json" "$TMP_ROOT/decision.json"
grep -q 'DISPOSABLE_TEST_THRESHOLD' "$MXMED_CLINICAL_M6_OBSERVABILITY_ROOT/events.ndjson"
echo "M6_MONITORING_THRESHOLD_PROFILE_CLI_TESTS=PASS"
