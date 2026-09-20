#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../.."

python3 - <<'PY'
from pathlib import Path
import subprocess

baseline = '46c8aa7b4033a15693a3b4e9c7c1cdc578814cfe'
router = Path('api/clinical/index.php').read_text()
adapter = Path('api/_lib/clinical_encounter_multipart_adapter.php').read_text()
app = Path('assets/js/app.js').read_text()

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

route_start = router.index("($segments[2] ?? '') === 'documents'\n        && ($segments[2] ?? '') === 'amendments'") if False else router.index("($segments[2] ?? '') === 'amendments')")
route_end = router.index("\n    if (($segments[0] ?? '') === 'documents')", route_start)
route = router[route_start:route_end]

assert "$isMultipart=strpos($contentType,'multipart/form-data')!==false;" in route
assert 'clinical_document_amendment_multipart_execute(' in route
assert 'clinical_document_amendment_multipart_response(' in route
assert 'clinical_v1_multipart_document_write_allowed' not in route
assert "clinical_v1_document_amendment_insert($pdo,$original,$command['replacement'],$command['reason'],$doctorContext)" in route

insert = function_body(router, 'function clinical_v1_document_amendment_insert(')
for token in ['FOR UPDATE', 'DOCUMENT_ALREADY_SUPERSEDED', 'createDocumentRevision(',
              'clinical_v1_originating_order_valid(', "$doc['document_id']=$documentUuid"]:
    assert token in insert
assert not any(token in insert for token in ['UPDATE clinical_documents SET payload_json', 'unlink(', 'rename('])

request = function_body(router, 'function clinical_v1_document_amendment_request(')
assert "clinical_document_result_lineage_refs_match($originalPayload,$replacement['payload'])" in request

multipart = function_body(adapter, 'function clinical_document_amendment_multipart_execute(')
for token in ["'operation' => 'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT'",
              'clinical_document_amendment_semantic_request(',
              'clinical_v1_document_amendment_insert(',
              "'result_column' => 'document_revision_id'",
              'clinical_v1_document_revision_fetch(']:
    assert token in multipart

save = function_body(app, 'async function saveCanonicalStudyOrder(params)')
replacement = save[save.index('      if(isReplacementMode){'):save.index('      }else{', save.index('      if(isReplacementMode){'))]
for token in ['buildClinicalDocumentAmendmentUrl(replacementRef)',
              'c05-order-replacement:${replacementRef}:${documentType}',
              "'Idempotency-Key': key", 'window.mxmedClinicalCommandKeys.run(',
              'replacement_source_document_id', 'replacement_source_document_uuid']:
    assert token in replacement
assert 'buildScopedClinicalDocumentReplaceUrl(' not in replacement
assert '/replace' not in replacement

render = function_body(app, 'function syncCanonicalOrderCardsFromDocuments(rows, resultsIndex = new Map())')
assert 'lineageSuccessors.set(lifecycle.replacementSourceRef' in render
assert "lifecycle.status = 'replaced'" in render
assert 'lifecycle.replacedByRef = successor.uuid || successor.id' in render

before = subprocess.check_output(['git', 'show', baseline + ':assets/js/app.js'], text=True)
assert function_body(app, 'async function saveOrderResultFromModal()') == function_body(before, 'async function saveOrderResultFromModal()')

legacy = router[router.index("if ($method === 'POST' && count($segments) === 3 && ($segments[2] ?? '') === 'replace')"):]
assert 'M6_GUARD_C05_C20_REPLACE' in legacy and 'clinical_m6_assert_legacy_write_allowed($patientId)' in legacy

protected = [
    'modules/clinical/db/migrations', 'api/_lib/clinical_private_binary_storage.php',
    'api/_lib/clinical_multipart_document_service.php', 'api/_lib/clinical_idempotency.php',
    'api/_lib/clinical_private_binary_retrieval.php', 'api/_lib/clinical_private_binary_http.php'
]
subprocess.run(['git', 'diff', '--exit-code', baseline, '--'] + protected, check=True)
print('MULTI06B_C05_REPLACEMENT_STATIC_QA=PASS append-only,lineage,order-authority,multipart,idempotency,no-fallback,C04/C21')
PY

php modules/clinical/qa/multi03b_orchestration_spy_test.php
node modules/clinical/qa/multi06a_c05_caller_test.js
