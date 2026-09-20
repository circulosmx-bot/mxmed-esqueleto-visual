#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../.."

python3 - <<'PY'
from pathlib import Path
import subprocess

baseline = 'dd6c9e10506c82a04bd4d585483c801d6944e201'
router = Path('api/clinical/index.php').read_text()
adapter = Path('api/_lib/clinical_note_capture_multipart_adapter.php').read_text()

start = router.index("if (($segments[0] ?? '') === 'note-capture-tokens')")
end = router.index("\n    if (($segments[0] ?? '') === 'doctors')", start)
tokens = router[start:end]
upload_start = tokens.index("if ($method === 'POST' && count($segments) === 3 && ($segments[2] ?? '') === 'upload')")
upload = tokens[upload_start:]
auxiliary = tokens[:upload_start]

for required in [
    "'route' => 'note-capture-tokens/{token}/upload'",
    'clinical_note_capture_token_fetch($pdo, $token)',
    "$patientId = trim((string)($row['patient_id'] ?? ''))",
    "$encounterKey = trim((string)($row['encounter_key'] ?? ''))",
    'clinical_note_capture_encounter_authority($pdo, $row)',
    'clinical_note_capture_encounter_uses_v1($encounterAuthority)',
    'clinical_note_capture_multipart_execute(',
    'clinical_documents_gateway_save_upload($pdo, $payload, $uploadFile)',
    "'DOCUMENT_CONTEXT_MISMATCH'",
    "WHERE token = :token",
]:
    assert required in upload, required

# Encounter selection is terminal: the legacy gateway occurs only in the explicit
# non-V1 or absent-encounter branches, never in the canonical catch path.
canonical = upload[upload.index('if ($encounterKey !== \'\') {'):upload.index('} catch (ClinicalM6LegacyWriteBlockedException')]
assert canonical.index('clinical_note_capture_multipart_execute(') < canonical.index('} else {\n                        $document = clinical_documents_gateway_save_upload')
catch = upload[upload.index('} catch (ClinicalM6LegacyWriteBlockedException'):upload.index('$uploadedAt = gmdate')]
assert 'clinical_documents_gateway_save_upload' not in catch
assert 'clinical_note_capture_multipart_execute' not in auxiliary
assert 'clinical_documents_gateway_save_upload' not in auxiliary

for required in [
    "'note-capture.' . hash('sha256', $token)",
    "unset($data['note_capture_token'])",
    "$data['note_capture_token_ref']",
    'clinical_encounter_multipart_execute(',
]:
    assert required in adapter, required

# The public/mobile boundary and closed C04/C05 callers remain byte-identical.
for path in ['public/note-capture.html', 'assets/js/app.js']:
    before = subprocess.check_output(['git', 'show', baseline + ':' + path], text=True)
    assert Path(path).read_text() == before, path + ' changed'

for marker in [
    'M6_GUARD_C21_NOTE_CAPTURE_UPLOAD',
    'M6_GUARD_C04_C05_C20_MULTIPART',
    "'route' => 'note-capture-tokens/{token}/consume'",
]:
    assert marker in router, marker

print('MULTI06B_C21_STATIC_QA=PASS token-authority,encounter-canonical,no-encounter-preserved,no-fallback,public-route,C04,C05')
PY

php modules/clinical/qa/multi06b_c21_adapter_test.php
