#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$repo_root"

python3 - <<'PY'
from pathlib import Path
import re, subprocess

baseline = '6cb473226e4239dd11375e8cdff47ee35c3ea954'
path = 'assets/js/app.js'
before = subprocess.check_output(['git', 'show', baseline + ':' + path], text=True)
after = Path(path).read_text()

def function_body(source, name):
    start = source.index('  ' + name)
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
    raise AssertionError('unterminated ' + name)

order = function_body(after, 'async function saveCanonicalStudyOrder(params)')
result = function_body(after, 'async function saveOrderResultFromModal()')

# Q01-Q07 order create and legacy compatibility.
assert 'buildEncounterClinicalDocumentCreateUrl(encounterKey)' in order
assert "'Content-Type': 'application/json'" in order
assert "'Idempotency-Key': key" in order
assert 'window.mxmedClinicalCommandKeys.run(' in order
assert 'c05-order-create:${encounterKey}:${documentType}' in order
assert "replacementSourceRef: ''" in order
assert 'else{\n          const formData = new FormData();' in order
assert 'buildScopedClinicalDocumentCreateUrl(patientId)' in order
assert order.index('if(encounterKey){') < order.index('window.mxmedClinicalCommandKeys.run(')

# The replacement branch remains exact, including its guarded legacy URL.
def replacement_block(source):
    body = function_body(source, 'async function saveCanonicalStudyOrder(params)')
    start = body.index('      if(isReplacementMode){')
    end = body.index('      }else{', start)
    return body[start:end]
assert replacement_block(after) == replacement_block(before)
assert 'buildScopedClinicalDocumentReplaceUrl(replacementRef)' in replacement_block(after)

# Q08-Q17 result authority, provenance, references, and executor-local FormData.
assert "detail?.context?.encounter_id" in result
assert 'originatingEncounterKey = hasCanonicalOrderEncounter ? `enc:${orderEncounterId}`' in result
assert 'resolveOrderEncounterKey(' not in result
assert 'buildEncounterClinicalDocumentCreateUrl(originatingEncounterKey)' in result
assert "provenance: 'estudios_host_resultado'" in result
assert "formData.append('provenance', 'estudios_host_resultado')" in result
assert 'related_order_document_id' in result and 'related_order_document_uuid' in result
assert "'Idempotency-Key': key" in result and 'window.mxmedClinicalCommandKeys.run(' in result
executor = result[result.index('async ({ key, command })=> {'):result.index(');\n        resp = canonicalResult.response')]
assert 'const formData = new FormData();' in executor
assert 'if(!response.ok || !responseJson || responseJson.ok !== true)' in executor
fingerprint_start = result.index('const resultFingerprint')
fingerprint = result[fingerprint_start:result.index('orderResultModalState.saving', fingerprint_start)]
assert 'FormData' not in fingerprint and 'eventDatetime' not in fingerprint
assert 'file.name' in fingerprint and 'file.size' in fingerprint and 'file.lastModified' in fingerprint
assert 'buildScopedClinicalDocumentCreateUrl(patientId)' in result

# HTTP failures are raised inside the registry executor, retaining the accepted
# stable key for the next same-semantic retry instead of marking it successful.
assert order.index('if(!response.ok || !responseJson || responseJson.ok !== true)') < order.index('return { response, json: responseJson }')

# Removing only the three authorized functions makes all other frontend source exact.
def without_allowed(source):
    for name in ['async function saveOrderResultFromModal()', 'async function saveCanonicalStudyOrder(params)']:
        source = source.replace(function_body(source, name), '')
    if 'function buildEncounterClinicalDocumentCreateUrl(encounterKey)' in source:
        source = source.replace(function_body(source, 'function buildEncounterClinicalDocumentCreateUrl(encounterKey)'), '')
        source = source.replace('\n\n  function buildScopedClinicalDocumentReplaceUrl', '\n  function buildScopedClinicalDocumentReplaceUrl', 1)
    return source
assert without_allowed(after) == without_allowed(before), 'C04/C21 or unrelated frontend changed'

protected = [
 'api/clinical/index.php', 'api/_lib/clinical_encounter_multipart_adapter.php',
 'api/_lib/clinical_multipart_document_service.php', 'api/_lib/clinical_private_binary_storage.php',
 'api/_lib/clinical_idempotency.php', 'modules/clinical/db/migrations'
]
subprocess.run(['git', 'diff', '--exit-code', baseline, '--'] + protected, check=True)
print('MULTI06A_C05_STATIC_QA=PASS Q01-Q03,Q05-Q17; replacement,C04,C21,backend protected')
PY

node modules/clinical/qa/multi06a_c05_caller_test.js
