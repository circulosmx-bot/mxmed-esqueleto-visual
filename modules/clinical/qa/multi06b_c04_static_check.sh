#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../.."

python3 - <<'PY'
from pathlib import Path
import subprocess

baseline = '44e8275d4794d04a7f6741b5c479608b265377bc'
app = Path('assets/js/app.js').read_text()
router = Path('api/clinical/index.php').read_text()

def function_body(source, signature):
    start = source.index(signature)
    brace = source.index('{', start)
    depth = 0
    quote = None
    escaped = False
    for index in range(brace, len(source)):
        char = source[index]
        if quote:
            if escaped: escaped = False
            elif char == '\\': escaped = True
            elif char == quote: quote = None
            continue
        if char in "'\"`": quote = char; continue
        if char == '{': depth += 1
        elif char == '}':
            depth -= 1
            if depth == 0: return source[start:index + 1]
    raise AssertionError('unterminated ' + signature)

c04_start = app.index('(function initActividadClinicaCanonicalUpload(){')
c04_end = app.index('\n})();', c04_start) + len('\n})();')
c04 = app[c04_start:c04_end]

# Encounter-owned branch uses only the accepted canonical endpoint and stable command authority.
for token in [
    'buildEncounterClinicalDocumentCreateUrl(encounterKey)',
    'buildScopedClinicalDocumentCreateUrl(patientId)',
    "const encounterOwned = encounterKey !== '';",
    'window.mxmedClinicalCommandKeys.run(',
    'c04-encounter-upload:${encounterKey}:${patientId}:${documentType}',
    "'Idempotency-Key': key",
    'body: buildFormData(command.payload)',
    'event_datetime: payload.event_datetime || nowSqlDatetime()',
]:
    assert token in c04, token
assert c04.index('if(encounterOwned){') < c04.index('window.mxmedClinicalCommandKeys.run(')
assert '}else{\n        resp = await fetch(createUrl' in c04

# FormData is executor-local for a fresh retry body; fingerprint contains logical and file identity only.
executor_start = c04.index('async ({ key, command })=> {')
executor_end = c04.index('\n          }\n        );', executor_start)
executor = c04[executor_start:executor_end]
assert 'buildFormData(command.payload)' in executor
fingerprint_start = c04.index('const commandFingerprint')
fingerprint_end = c04.index('const canonicalResult', fingerprint_start)
fingerprint = c04[fingerprint_start:fingerprint_end]
assert 'FormData' not in fingerprint
for token in ['file.name', 'file.size', 'file.lastModified', 'file.type']:
    assert token in fingerprint

# Canonical failure is terminal; the patient path is only the no-encounter semantic branch.
catch_start = c04.index('    }catch(err){', c04.index('if(encounterOwned){'))
catch_block = c04[catch_start:c04.index('    }finally{', catch_start)]
assert 'buildScopedClinicalDocumentCreateUrl' not in catch_block
assert 'fetch(' not in catch_block

# The server derives and authorizes encounter ownership, then rejects client patient mismatch.
route_start = router.index("if (count($segments) === 3 && ($segments[2] ?? '') === 'documents' && $method === 'POST')")
route_end = router.index("\n        if (count($segments) === 2 && $method === 'GET')", route_start)
route = router[route_start:route_end]
authorized = "clinical_v1_authorized_encounter($pdo,$encounterKey,$doctorContext,'encounters/{encounter_key}/documents')"
mismatch = "clinical_documents_request_has_patient_mismatch($payload, (string)($encounterRow['patient_id'] ?? ''))"
multipart = 'clinical_encounter_multipart_execute('
assert authorized in route and mismatch in route and multipart in route
assert route.index(authorized) < route.index(mismatch) < route.index(multipart)

# Closed C05 behavior and C21 remain byte-identical to the authorized start source.
before = subprocess.check_output(['git', 'show', baseline + ':assets/js/app.js'], text=True)
for signature in ['async function saveCanonicalStudyOrder(params)', 'async function saveOrderResultFromModal()']:
    assert function_body(app, signature) == function_body(before, signature), signature

before_c04_start = before.index('(function initActividadClinicaCanonicalUpload(){')
before_c04_end = before.index('\n})();', before_c04_start) + len('\n})();')
before_without_c04 = before[:before_c04_start] + before[before_c04_end:]
after_without_c04 = app[:c04_start] + app[c04_end:]
assert after_without_c04 == before_without_c04, 'source outside C04 caller changed'

protected = [
    'modules/clinical/db/migrations',
    'api/_lib/clinical_multipart_document_service.php',
    'api/_lib/clinical_private_binary_storage.php',
    'api/_lib/clinical_idempotency.php',
]
subprocess.run(['git', 'diff', '--exit-code', baseline, '--'] + protected, check=True)

assert 'M6_GUARD_C04_C05_C20_MULTIPART' in router
print('MULTI06B_C04_STATIC_QA=PASS encounter-branch,patient-branch,integrity,idempotency,no-fallback,C05,C21')
PY

node modules/clinical/qa/multi06b_c04_caller_test.js
