<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_responsiva_binding.php';
require_once __DIR__ . '/clinical_responsiva_render.php';
require_once __DIR__ . '/clinical_responsiva_qr.php';

/** Preserve an existing QR artifact exactly, regardless of JSON object key order. */
function clinical_responsiva_qr_entry_unchanged($previous, $incoming): bool
{
    if (is_array($previous) || is_array($incoming)) {
        if (!is_array($previous) || !is_array($incoming) || count($previous) !== count($incoming)) return false;
        foreach ($previous as $key => $value) {
            if (!array_key_exists($key, $incoming)
                || !clinical_responsiva_qr_entry_unchanged($value, $incoming[$key])) return false;
        }
        return true;
    }
    return $previous === $incoming;
}

/** Canonical create/update/finalize command for Responsiva. Caller supplies authenticated doctor scope. */
function clinical_responsiva_write(PDO $pdo, array $doctor, string $patientId, array $body, string $idempotencyKey): array
{
    if (($body['document_type'] ?? '') !== 'responsiva_medica'
        || ($body['type'] ?? '') !== 'responsiva_medica'
        || !is_array($body['payload'] ?? null))
        throw new InvalidArgumentException('RESPONSIVA_DOCUMENT_INVALID');
    $payload = $body['payload'];
    if (!clinical_legal_document_presentation_valid($payload))
        throw new InvalidArgumentException('RESPONSIVA_PRESENTATION_INVALID');
    $intent = (string)($payload['status'] ?? '');
    if (!in_array($intent, ['draft', 'issued'], true))
        throw new InvalidArgumentException('RESPONSIVA_INTENT_INVALID');
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $expectedVersion = (int)($body['expected_version'] ?? 0);
    if ($draftRef !== '' && (preg_match('/^[0-9a-f-]{36}$/i', $draftRef) !== 1 || $expectedVersion < 1))
        throw new InvalidArgumentException('RESPONSIVA_DRAFT_IDENTITY_INVALID');
    if (trim((string)($body['actor']['user_id'] ?? '')) !== (string)$doctor['user_id']
        || trim((string)($payload['actor_snapshot']['user_id'] ?? '')) !== (string)$doctor['user_id'])
        throw new InvalidArgumentException('RESPONSIVA_ACTOR_MISMATCH');
    if (trim((string)($body['context']['patient_id'] ?? '')) !== $patientId)
        throw new InvalidArgumentException('RESPONSIVA_PATIENT_MISMATCH');
    $key = clinical_idempotency_key_validate($idempotencyKey);
    clinical_encounter_integrity_assert_schema_ready($pdo);
    $semantic = clinical_document_semantic_request($body, null) + [
        'patient_id' => $patientId, 'operation' => 'CREATE_ENCOUNTER_DOCUMENT',
        'responsiva_intent' => $intent, 'draft_ref' => $draftRef ?: null,
        'expected_version' => $draftRef !== '' ? $expectedVersion : null,
    ];
    $service = new ClinicalEncounterIntegrityService($pdo);
    return $service->idempotentCreate('CREATE_ENCOUNTER_DOCUMENT', (string)$doctor['doctor_id'],
        'PATIENT', $patientId, $key, $semantic, 'document_id', (string)$doctor['user_id'],
        function () use ($pdo, $doctor, $patientId, $body, $payload, $intent, $draftRef, $expectedVersion): int {
            if (!clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], $patientId))
                throw new InvalidArgumentException('RESPONSIVA_PATIENT_SCOPE_INVALID');
            $existing = null;
            if ($draftRef !== '') {
                $stmt = $pdo->prepare('SELECT id,document_uuid,document_type,patient_id,status,version,
                    created_by_user_id,payload_json,generated_at,signed_at FROM clinical_documents
                    WHERE document_uuid=? LIMIT 1 FOR UPDATE');
                $stmt->execute([$draftRef]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!is_array($existing) || (string)$existing['patient_id'] !== $patientId
                    || (string)$existing['document_type'] !== 'responsiva_medica'
                    || (string)$existing['created_by_user_id'] !== (string)$doctor['user_id'])
                    throw new InvalidArgumentException('RESPONSIVA_DRAFT_SCOPE_INVALID');
                if ((string)$existing['status'] !== 'draft')
                    throw new ClinicalIdempotencyException('RESPONSIVA_DRAFT_FINAL', 'La responsiva ya no es borrador.', 409);
                if ((int)$existing['version'] !== $expectedVersion)
                    throw new ClinicalIdempotencyException('RESPONSIVA_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            }
            $writeContext = ['patient_id' => $patientId, 'care_setting' => 'consulta'];
            $canonical = $body;
            $canonical['context'] = $writeContext;
            $canonical['actor'] = ['user_id' => (string)$doctor['user_id']];
            $canonical['payload'] = $payload;
            $uploadedQrRoles = [];
            $previousPayload = $existing !== null ? json_decode((string)$existing['payload_json'], true) : null;
            foreach (['signer', 'doctor'] as $role) {
                $canonical['payload']['signature_binding_status'][$role] =
                    clinical_responsiva_binding_classify($canonical, $role, (string)$doctor['doctor_id'], $pdo);
                $entry = (array)($payload['signatures'][$role] ?? []);
                if (($entry['source'] ?? '') !== 'remote_qr') continue;
                $qr = clinical_responsiva_qr_row($pdo, (string)($entry['token'] ?? ''));
                $previousEntry = is_array($previousPayload)
                    ? (array)($previousPayload['signatures'][$role] ?? []) : [];
                if ($canonical['payload']['signature_binding_status'][$role] !== 'valid_bound_signature'
                    && ($existing === null || !clinical_responsiva_qr_entry_unchanged($previousEntry, $entry)))
                    throw new InvalidArgumentException('RESPONSIVA_QR_UNVERIFIED');
                if ($qr !== null && (string)$qr['status'] === 'uploaded') {
                    if ($existing === null || $canonical['payload']['signature_binding_status'][$role] !== 'valid_bound_signature')
                        throw new InvalidArgumentException('RESPONSIVA_QR_STALE');
                    $uploadedQrRoles[] = $role;
                }
            }
            $canonical['payload']['responsiva_snapshot'] = [
                'version' => 1,
                'html' => clinical_responsiva_render_html($canonical['payload']),
            ];
            $nextStatus = $intent === 'draft' ? 'draft' : 'generated';
            if ($existing === null) {
                $id = clinical_v1_document_insert($pdo, $writeContext, $canonical, (string)$doctor['user_id']);
                if ($nextStatus === 'draft') {
                    $mark = $pdo->prepare("UPDATE clinical_documents SET status='draft',generated_at=NULL WHERE id=? AND status='generated'");
                    $mark->execute([$id]);
                    if ($mark->rowCount() !== 1) throw new RuntimeException('RESPONSIVA_DRAFT_CREATE_FAILED');
                }
                return $id;
            }
            $id = (int)$existing['id'];
            $encoded = json_encode($canonical['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $update = $pdo->prepare('UPDATE clinical_documents SET title=?,summary=?,payload_json=?,rendered_text=?,
                event_datetime=?,status=?,version=version+1,updated_at=UTC_TIMESTAMP(),updated_by_user_id=?,
                edited_flag=1,generated_at=CASE WHEN ?=\'generated\' THEN UTC_TIMESTAMP() ELSE NULL END
                WHERE id=? AND version=? AND status=\'draft\'');
            $update->execute([(string)($body['title'] ?? 'Responsiva médica'), (string)($body['summary'] ?? ''),
                $encoded, (string)($payload['rendered_text'] ?? ''),
                (string)($body['event_datetime'] ?? gmdate('Y-m-d H:i:s')),
                $nextStatus, (string)$doctor['user_id'], $nextStatus, $id, $expectedVersion]);
            if ($update->rowCount() !== 1)
                throw new ClinicalIdempotencyException('RESPONSIVA_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            foreach ($uploadedQrRoles as $role) {
                clinical_responsiva_qr_claim($pdo, $canonical, (array)$payload['signatures'][$role], $role,
                    (string)$doctor['doctor_id'], $draftRef, $expectedVersion);
            }
            return $id;
        }, static fn(int $id): array => clinical_v1_document_fetch($pdo, $id));
}
