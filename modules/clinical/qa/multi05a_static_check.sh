#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../.."
python3 - <<'PY'
from pathlib import Path
import subprocess,re
base='10c09aa8fa0960fd5d1d57d72c765755026c2c06'
r=Path('api/clinical/index.php').read_text()
prior=subprocess.check_output(['git','show',base+':api/clinical/index.php'],text=True)
bridge=re.search(r'                    // MULTI05A BRIDGE BEGIN\.[\s\S]*?                    // MULTI05A BRIDGE END\.\n',r).group(0)
assert 'if ($isMultipart)' in bridge and 'return;' in bridge
rest=r.replace(bridge,'')
rest=re.sub(r'    // MULTI05A UUID BEGIN\.[\s\S]*?    // MULTI05A UUID END\.\n','',rest)
rest=rest.replace('string $actorId,?string $documentUuid=null): int','string $actorId): int',1)
anchor="                    $documentClass=clinical_v1_document_class($payload);"
guard="""                    if(!clinical_v1_multipart_document_write_allowed($isMultipart,is_array($uploadFile))){
                        throw new RuntimeException('V1_MULTIPART_STORAGE_NOT_READY');
                    }
"""
rest=rest.replace(anchor,guard+anchor,1)
assert rest==prior,'unrelated router/JSON/legacy/GET mutation'
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
assert 'clinical_v1_multipart_document_write_allowed' in r
protected=['api/_lib/clinical_private_binary_http.php','api/_lib/clinical_private_binary_retrieval.php','api/_lib/clinical_private_binary_storage.php','api/_lib/clinical_multipart_document_service.php','api/_lib/clinical_idempotency.php','api/clinical-documents.php','api/evolution-note-generate.php','modules/clinical/db/migrations','assets','index.html']
subprocess.run(['git','diff','--exit-code',base,'--']+protected,check=True)
print('MULTI05A_STATIC_QA=PASS W01,W05-W07,W10-W13; canonical order/UUID/JSON/legacy/GET/protected source')
PY
