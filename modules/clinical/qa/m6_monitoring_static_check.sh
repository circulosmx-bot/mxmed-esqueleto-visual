#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
OBS="$ROOT/api/_lib/clinical_m6_observability.php"
CLI="$ROOT/scripts/clinical-m6-monitor.php"
php -l "$OBS" >/dev/null
php -l "$CLI" >/dev/null
python3 - "$ROOT" <<'PY'
from pathlib import Path
import json,sys
r=Path(sys.argv[1]); o=(r/'api/_lib/clinical_m6_observability.php').read_text(); c=(r/'scripts/clinical-m6-monitor.php').read_text(); router=(r/'api/clinical/index.php').read_text(); agenda=(r/'modules/agenda/services/ClinicalEncounterBridge.php').read_text(); service=(r/'api/_lib/clinical_multipart_document_service.php').read_text()
for text in ['CANONICAL_V1','PATIENT_LEVEL_C04','GUARDED_LEGACY','BLOCKED','correlation_id','duration_ms','config_fingerprint','worker_generation','unexpected_fallback','parallel_writer_evidence']:
    assert text in o,text
for command in ['heartbeat','status','baseline','compare','snapshot','decision']:
    assert command in c,command
for needle in ['C04_PATIENT_DOCUMENT','C21_TOKEN_UPLOAD','clinical_m6_observability_response']:
    assert needle in router,needle
assert 'AGENDA_CLINICAL_BRIDGE' in agenda
for needle in ['STAGING_BEGIN','FINAL_OBJECT_COMMIT','MANIFEST_COMMIT','clinical_writer']:
    assert needle in service,needle
rules=json.loads((r/'modules/clinical/monitoring/m6_monitoring_rules.json').read_text())
assert {x['type'] for x in rules['rules']} >= {'zero','count','latency','freshness','error_zero'}
print('M6_MONITORING_STATIC_CHECK=PASS')
PY
