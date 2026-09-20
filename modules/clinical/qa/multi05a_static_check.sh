#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../.."
python3 - <<'PY'
from pathlib import Path
import subprocess,re
base='44e8275d4794d04a7f6741b5c479608b265377bc'
r=Path('api/clinical/index.php').read_text()
prior=subprocess.check_output(['git','show',base+':api/clinical/index.php'],text=True)
bridge=re.search(r'                    // MULTI05A BRIDGE BEGIN\.[\s\S]*?                    // MULTI05A BRIDGE END\.\n',r).group(0)
assert 'if ($isMultipart)' in bridge and 'return;' in bridge
c04_integrity="""                    if (clinical_documents_request_has_patient_mismatch($payload, (string)($encounterRow['patient_id'] ?? ''))) {
                        throw new RuntimeException('DOCUMENT_CONTEXT_MISMATCH');
                    }
"""
assert c04_integrity in r
assert "clinical_m6_write_window_route_is_clinical_writer($method, $segments)" in r
assert "'error'=>'M6_WRITE_WINDOW_BLOCKED'" in r
h=Path('api/_lib/clinical_encounter_multipart_adapter.php').read_text()
route=r[r.index("if (count($segments) === 3 && ($segments[2] ?? '') === 'documents' && $method === 'POST')"):]
last=0
for x in ['clinical_require_doctor_context(', 'clinical_m6_encounter_route_uses_v1(', 'clinical_v1_authorized_encounter(', 'clinical_v1_document_class(', 'clinical_document_create_operation(', 'clinical_document_policy_operation(', 'clinical_document_operation_policy(', 'clinical_encounter_multipart_execute(']:
 last=route.index(x,last)+len(x)
assert route.index('MEDIA_TAG_REQUIRED')<route.index('clinical_encounter_multipart_execute(')
assert route.index('event_datetime inválido')<route.index('clinical_encounter_multipart_execute(')
assert h.index('clinical_encounter_multipart_config();')<h.index('new ClinicalPrivateBinaryStorage($root)')<h.index('->execute(')
callback=h[h.index('function (PDO $transaction'):h.index('static fn(PDO $transaction')]
last=0
for x in ['FOR UPDATE',"$locked['doctor_id']","$locked['patient_id']",'clinical_document_operation_policy(', 'clinical_v1_document_insert(']:last=callback.index(x,last)+len(x)
assert not any(x in callback for x in ['beginTransaction(', 'commit(', 'rollBack('])
assert "'operation' => $createOperation" in h and "'doctor_id' => $doctor['doctor_id']" in h
assert "'patient_id' => $encounter['patient_id']" in h and "'context_id' => (string)$encounter['encounter_id']" in h
assert 'clinical_document_semantic_request($payload, null)' in h
assert 'is_uploaded_file(' in h and "count($files) !== 1" in h
assert "'HTTP_IDEMPOTENCY_KEY'" in bridge
assert not any(x in h for x in ['$_POST', '$_REQUEST', '$_GET', 'clinical_store_uploaded_file', '/storage/', 'INSERT INTO', "$_FILES['type']"])
protected=['api/_lib/clinical_private_binary_http.php','api/_lib/clinical_private_binary_retrieval.php','api/_lib/clinical_private_binary_storage.php','api/_lib/clinical_multipart_document_service.php','api/_lib/clinical_idempotency.php','modules/clinical/db/migrations','index.html']
subprocess.run(['git','diff','--exit-code',base,'--']+protected,check=True)
for standalone in ['api/clinical-documents.php','api/evolution-note-generate.php']:
 source=Path(standalone).read_text()
 assert 'clinical_m6_write_window_admit()' in source
 assert "'error'=>'M6_WRITE_WINDOW_BLOCKED'" in source
print('MULTI05A_STATIC_QA=PASS W01,W05-W07,W10-W13; canonical order/UUID/JSON/legacy/GET/protected source;C04 integrity scoped')
PY
