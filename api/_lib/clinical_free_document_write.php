<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_free_document.php';

/** Draft authority. Emission remains disabled until FREE-DOC02B binds the physician signature. */
function clinical_free_document_write(PDO $pdo, array $doctor, string $patientId, array $body, string $idempotencyKey): array
{
    $payload = clinical_free_document_normalize($body, $pdo, $doctor, $patientId);
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $expectedVersion = $body['expected_version'] ?? null;
    if ($draftRef !== '' && (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $draftRef) !== 1
        || !is_int($expectedVersion) || $expectedVersion < 1))
        throw new InvalidArgumentException('FREE_DOCUMENT_DRAFT_IDENTITY_INVALID');
    if ($draftRef === '' && $expectedVersion !== null)
        throw new InvalidArgumentException('FREE_DOCUMENT_DRAFT_IDENTITY_INVALID');
    $step = $body['ui_state']['wizard_step'] ?? 1;
    $payload['ui_state'] = ['wizard_step'=>in_array($step, [1,2], true) ? $step : 1];
    $payload['free_snapshot'] = ['version'=>1,'html'=>clinical_free_document_render_html($payload)];
    $key = clinical_idempotency_key_validate($idempotencyKey);
    clinical_encounter_integrity_assert_schema_ready($pdo);
    $canonical = $body;
    $canonical['payload'] = $payload;
    $canonical['title'] = $payload['document']['title'];
    $semantic = clinical_document_semantic_request($canonical, null) + [
        'patient_id'=>$patientId,'operation'=>'CREATE_ENCOUNTER_DOCUMENT',
        'free_intent'=>'draft','draft_ref'=>$draftRef ?: null,
        'expected_version'=>$draftRef !== '' ? $expectedVersion : null,
    ];
    $service = new ClinicalEncounterIntegrityService($pdo);
    return $service->idempotentCreate('CREATE_ENCOUNTER_DOCUMENT', (string)$doctor['doctor_id'],
        'PATIENT', $patientId, $key, $semantic, 'document_id', (string)$doctor['user_id'],
        function () use ($pdo, $doctor, $patientId, $canonical, $payload, $draftRef, $expectedVersion): int {
            if (!clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], $patientId))
                throw new InvalidArgumentException('FREE_DOCUMENT_PATIENT_SCOPE_INVALID');
            if ($draftRef === '') {
                $new = $canonical;
                $new['context'] = ['patient_id'=>$patientId,'care_setting'=>'consulta'];
                $new['actor'] = ['user_id'=>(string)$doctor['user_id']];
                $new['summary'] = $payload['document']['purpose'];
                $id = clinical_v1_document_insert($pdo, $new['context'], $new, (string)$doctor['user_id']);
                $update = $pdo->prepare("UPDATE clinical_documents SET status='draft',generated_at=NULL WHERE id=? AND status='generated'");
                $update->execute([$id]);
                if ($update->rowCount() !== 1) throw new RuntimeException('FREE_DOCUMENT_DRAFT_CREATE_FAILED');
                return $id;
            }
            $query = $pdo->prepare('SELECT id,patient_id,document_type,status,version,created_by_user_id
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
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $update = $pdo->prepare("UPDATE clinical_documents SET title=?,summary=?,payload_json=?,rendered_text=?,
                version=version+1,updated_at=UTC_TIMESTAMP(),updated_by_user_id=?,edited_flag=1
                WHERE id=? AND version=? AND status='draft'");
            $update->execute([$payload['document']['title'],$payload['document']['purpose'],$encoded,
                trim(strip_tags($payload['free_snapshot']['html'])),(string)$doctor['user_id'],
                (int)$existing['id'],$expectedVersion]);
            if ($update->rowCount() !== 1)
                throw new ClinicalIdempotencyException('FREE_DOCUMENT_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            return (int)$existing['id'];
        }, static fn(int $id): array => clinical_v1_document_fetch($pdo, $id));
}
