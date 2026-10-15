<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_free_document.php';
require_once __DIR__ . '/clinical_free_document_binding.php';
require_once __DIR__ . '/clinical_free_document_qr.php';
require_once __DIR__ . '/clinical_professional_authority.php';

/** Save content, apply a version-bound physician signature, or emit the same draft UUID. */
function clinical_free_document_write(PDO $pdo, array $doctor, string $patientId, array $body, string $idempotencyKey): array
{
    $action = (string)($body['free_action'] ?? 'save');
    if (!in_array($action, ['save','apply_signature','emit'], true))
        throw new InvalidArgumentException('FREE_DOCUMENT_ACTION_INVALID');
    if ($action !== 'save') {
        clinical_professional_authority_assert($doctor, 'PROFESSIONAL_DOCUMENT_' . strtoupper($action));
    }
    $payload = clinical_free_document_normalize($body, $pdo, $doctor, $patientId);
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $expectedVersion = $body['expected_version'] ?? null;
    if ($draftRef !== '' && (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $draftRef) !== 1
        || !is_int($expectedVersion) || $expectedVersion < 1))
        throw new InvalidArgumentException('FREE_DOCUMENT_DRAFT_IDENTITY_INVALID');
    if ($draftRef === '' && ($expectedVersion !== null || $action !== 'save'))
        throw new InvalidArgumentException('FREE_DOCUMENT_DRAFT_IDENTITY_INVALID');
    $step = $body['ui_state']['wizard_step'] ?? 1;
    $payload['ui_state'] = ['wizard_step'=>in_array($step, [1,2,3], true) ? $step : 1];
    if ($action === 'save') $payload['signatures'] = ['doctor'=>null];
    if ($action === 'save' && !empty($body['payload']['signatures']['doctor']))
        throw new InvalidArgumentException('FREE_DOCUMENT_SIGNATURE_ACTION_REQUIRED');
    $key = clinical_idempotency_key_validate($idempotencyKey);
    clinical_encounter_integrity_assert_schema_ready($pdo);
    clinical_free_document_qr_ensure_schema($pdo);
    $canonical = $body;
    $canonical['payload'] = $payload;
    $canonical['title'] = $payload['document']['title'];
    $semantic = clinical_document_semantic_request($canonical, null) + [
        'patient_id'=>$patientId,'operation'=>'CREATE_ENCOUNTER_DOCUMENT',
        'free_intent'=>$action,'draft_ref'=>$draftRef ?: null,
        'expected_version'=>$draftRef !== '' ? $expectedVersion : null,
    ];
    $service = new ClinicalEncounterIntegrityService($pdo);
    return $service->idempotentCreate('CREATE_ENCOUNTER_DOCUMENT', (string)$doctor['doctor_id'],
        'PATIENT', $patientId, $key, $semantic, 'document_id', (string)$doctor['user_id'],
        function () use ($pdo, $doctor, $patientId, $canonical, $payload, $draftRef, $expectedVersion, $action): int {
            if (!clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], $patientId))
                throw new InvalidArgumentException('FREE_DOCUMENT_PATIENT_SCOPE_INVALID');
            if ($draftRef === '') {
                $payload['signature_binding_status'] = ['doctor'=>'absent'];
                $payload['free_snapshot'] = ['version'=>1,'html'=>clinical_free_document_render_html($payload)];
                $new = $canonical;
                $new['payload'] = $payload;
                $new['context'] = ['patient_id'=>$patientId,'care_setting'=>'consulta'];
                $new['actor'] = ['user_id'=>(string)$doctor['user_id']];
                $new['summary'] = $payload['document']['purpose'];
                $id = clinical_v1_document_insert($pdo, $new['context'], $new, (string)$doctor['user_id']);
                $update = $pdo->prepare("UPDATE clinical_documents SET status='draft',generated_at=NULL WHERE id=? AND status='generated'");
                $update->execute([$id]);
                if ($update->rowCount() !== 1) throw new RuntimeException('FREE_DOCUMENT_DRAFT_CREATE_FAILED');
                return $id;
            }
            $query = $pdo->prepare('SELECT id,patient_id,document_type,status,version,created_by_user_id,payload_json
                FROM clinical_documents WHERE document_uuid=? LIMIT 1 FOR UPDATE');
            $query->execute([$draftRef]); $existing = $query->fetch(PDO::FETCH_ASSOC);
            if (!is_array($existing) || (string)$existing['patient_id'] !== $patientId
                || (string)$existing['document_type'] !== 'documento_libre'
                || (string)$existing['created_by_user_id'] !== (string)$doctor['user_id'])
                throw new InvalidArgumentException('FREE_DOCUMENT_DRAFT_SCOPE_INVALID');
            if ($existing['status'] !== 'draft')
                throw new ClinicalIdempotencyException('FREE_DOCUMENT_DRAFT_FINAL', 'El documento ya no es borrador.', 409);
            if ((int)$existing['version'] !== $expectedVersion)
                throw new ClinicalIdempotencyException('FREE_DOCUMENT_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            $previous = json_decode((string)$existing['payload_json'], true);
            if (!is_array($previous)) throw new RuntimeException('FREE_DOCUMENT_DRAFT_PAYLOAD_INVALID');
            $currentBody = ['draft_ref'=>$draftRef,'expected_version'=>$expectedVersion,
                'context'=>['patient_id'=>$patientId], 'payload'=>$previous];
            $incomingBody = ['draft_ref'=>$draftRef,'expected_version'=>$expectedVersion,
                'context'=>['patient_id'=>$patientId], 'payload'=>$payload];
            if ($action !== 'save' && !hash_equals(clinical_free_document_binding_fingerprint($currentBody),
                    clinical_free_document_binding_fingerprint($incomingBody)))
                throw new ClinicalIdempotencyException('FREE_DOCUMENT_CONTENT_CHANGED', 'El borrador cambió. Vuelve a revisarlo.', 409);
            if ($action === 'save') {
                $payload['signature_binding_status'] = ['doctor'=>'absent'];
                $nextVersion = $expectedVersion + 1;
                $invalidate = $pdo->prepare("UPDATE clinical_free_document_qr_sessions SET status='stale',invalidated_at=UTC_TIMESTAMP()
                    WHERE document_uuid=? AND status IN ('pending','uploaded')");
                $invalidate->execute([$draftRef]);
            } else {
                if ($action === 'emit') {
                    $payload['signatures']['doctor'] = $previous['signatures']['doctor'] ?? null;
                }
                $bindingBody = ['draft_ref'=>$draftRef,'expected_version'=>$expectedVersion,
                    'context'=>['patient_id'=>$patientId], 'payload'=>$payload];
                $status = clinical_free_document_binding_classify($bindingBody, (string)$doctor['doctor_id'], $pdo);
                if ($status !== 'valid_bound_signature')
                    throw new InvalidArgumentException($status === 'absent'
                        ? 'FREE_DOCUMENT_PHYSICIAN_SIGNATURE_REQUIRED' : 'FREE_DOCUMENT_PHYSICIAN_SIGNATURE_INVALID');
                $payload['signature_binding_status'] = ['doctor'=>$status];
                $nextVersion = $expectedVersion;
                if ($action === 'apply_signature' && ($payload['signatures']['doctor']['source'] ?? '') === 'remote_qr') {
                    clinical_free_document_qr_claim($pdo, $bindingBody,
                        (array)$payload['signatures']['doctor'], 'doctor',
                        (string)$doctor['doctor_id'], $draftRef, $expectedVersion);
                }
                if ($action === 'emit') $payload['status'] = 'generated';
            }
            $payload['free_snapshot'] = ['version'=>1,'html'=>clinical_free_document_render_html($payload)];
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $nextStatus = $action === 'emit' ? 'generated' : 'draft';
            $update = $pdo->prepare("UPDATE clinical_documents SET title=?,summary=?,payload_json=?,rendered_text=?,
                version=?,status=?,generated_at=CASE WHEN ?='generated' THEN UTC_TIMESTAMP() ELSE NULL END,
                updated_at=UTC_TIMESTAMP(),updated_by_user_id=?,edited_flag=1
                WHERE id=? AND version=? AND status='draft'");
            $update->execute([$payload['document']['title'],$payload['document']['purpose'],$encoded,
                trim(strip_tags($payload['free_snapshot']['html'])),$nextVersion,$nextStatus,$nextStatus,
                (string)$doctor['user_id'],(int)$existing['id'],$expectedVersion]);
            if ($update->rowCount() !== 1)
                throw new ClinicalIdempotencyException('FREE_DOCUMENT_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            return (int)$existing['id'];
        }, static fn(int $id): array => clinical_v1_document_fetch($pdo, $id));
}
