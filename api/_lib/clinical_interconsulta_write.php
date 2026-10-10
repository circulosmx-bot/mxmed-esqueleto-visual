<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_interconsulta_binding.php';
require_once __DIR__ . '/clinical_interconsulta_render.php';
require_once __DIR__ . '/clinical_interconsulta_qr.php';

/** Preserve an existing QR artifact exactly, regardless of JSON object key order. */
function clinical_interconsulta_qr_entry_unchanged($previous, $incoming): bool
{
    if (is_array($previous) || is_array($incoming)) {
        if (!is_array($previous) || !is_array($incoming) || count($previous) !== count($incoming)) return false;
        foreach ($previous as $key => $value) {
            if (!array_key_exists($key, $incoming)
                || !clinical_interconsulta_qr_entry_unchanged($value, $incoming[$key])) return false;
        }
        return true;
    }
    return $previous === $incoming;
}

/** Canonical create/update/finalize command for Interconsulta. Caller supplies authenticated doctor scope. */
function clinical_interconsulta_write(PDO $pdo, array $doctor, string $patientId, array $body, string $idempotencyKey): array
{
    if (($body['document_type'] ?? '') !== 'interconsulta'
        || ($body['type'] ?? '') !== 'interconsulta'
        || !is_array($body['payload'] ?? null)
        || (int)($body['payload']['contract_version'] ?? 0) !== 2)
        throw new InvalidArgumentException('INTERCONSULTA_DOCUMENT_INVALID');
    $payload = $body['payload'];
    if (!clinical_legal_document_presentation_valid($payload))
        throw new InvalidArgumentException('INTERCONSULTA_PRESENTATION_INVALID');
    $intent = (string)($payload['status'] ?? '');
    if (!in_array($intent, ['draft', 'issued'], true))
        throw new InvalidArgumentException('INTERCONSULTA_INTENT_INVALID');
    $recipient = (array)($payload['recipient'] ?? []);
    if (!in_array((string)($recipient['mode'] ?? ''), ['doctor', 'service'], true)
        || (string)($recipient['source'] ?? '') !== 'manual')
        throw new InvalidArgumentException('INTERCONSULTA_RECIPIENT_INVALID');
    if ($intent === 'issued') {
        $content = (array)($payload['content'] ?? []);
        if (($recipient['mode'] ?? '') === 'doctor' && trim((string)($recipient['doctor_name'] ?? '')) === '')
            throw new InvalidArgumentException('INTERCONSULTA_DOCTOR_DESTINATION_REQUIRED');
        if (($recipient['mode'] ?? '') === 'service'
            && trim((string)($recipient['service'] ?? '')) === ''
            && trim((string)($recipient['specialty'] ?? '')) === '')
            throw new InvalidArgumentException('INTERCONSULTA_SERVICE_DESTINATION_REQUIRED');
        foreach (['reason', 'summary', 'request'] as $field)
            if (trim((string)($content[$field] ?? '')) === '')
                throw new InvalidArgumentException('INTERCONSULTA_'.strtoupper($field).'_REQUIRED');
    }
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $expectedVersion = (int)($body['expected_version'] ?? 0);
    if ($draftRef !== '' && (preg_match('/^[0-9a-f-]{36}$/i', $draftRef) !== 1 || $expectedVersion < 1))
        throw new InvalidArgumentException('INTERCONSULTA_DRAFT_IDENTITY_INVALID');
    if (trim((string)($body['actor']['user_id'] ?? '')) !== (string)$doctor['user_id']
        || trim((string)($payload['actor_snapshot']['user_id'] ?? '')) !== (string)$doctor['user_id'])
        throw new InvalidArgumentException('INTERCONSULTA_ACTOR_MISMATCH');
    if (trim((string)($body['context']['patient_id'] ?? '')) !== $patientId)
        throw new InvalidArgumentException('INTERCONSULTA_PATIENT_MISMATCH');
    $key = clinical_idempotency_key_validate($idempotencyKey);
    clinical_encounter_integrity_assert_schema_ready($pdo);
    clinical_interconsulta_qr_ensure_schema($pdo);
    $semantic = clinical_document_semantic_request($body, null) + [
        'patient_id' => $patientId, 'operation' => 'CREATE_ENCOUNTER_DOCUMENT',
        'interconsulta_intent' => $intent, 'draft_ref' => $draftRef ?: null,
        'expected_version' => $draftRef !== '' ? $expectedVersion : null,
    ];
    $service = new ClinicalEncounterIntegrityService($pdo);
    return $service->idempotentCreate('CREATE_ENCOUNTER_DOCUMENT', (string)$doctor['doctor_id'],
        'PATIENT', $patientId, $key, $semantic, 'document_id', (string)$doctor['user_id'],
        function () use ($pdo, $doctor, $patientId, $body, $payload, $intent, $draftRef, $expectedVersion): int {
            if (!clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], $patientId))
                throw new InvalidArgumentException('INTERCONSULTA_PATIENT_SCOPE_INVALID');
            $existing = null;
            if ($draftRef !== '') {
                $stmt = $pdo->prepare('SELECT id,document_uuid,document_type,patient_id,status,version,
                    created_by_user_id,payload_json,generated_at,signed_at FROM clinical_documents
                    WHERE document_uuid=? LIMIT 1 FOR UPDATE');
                $stmt->execute([$draftRef]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!is_array($existing) || (string)$existing['patient_id'] !== $patientId
                    || (string)$existing['document_type'] !== 'interconsulta'
                    || (string)$existing['created_by_user_id'] !== (string)$doctor['user_id'])
                    throw new InvalidArgumentException('INTERCONSULTA_DRAFT_SCOPE_INVALID');
                if ((string)$existing['status'] !== 'draft')
                    throw new ClinicalIdempotencyException('INTERCONSULTA_DRAFT_FINAL', 'La interconsulta ya no es borrador.', 409);
                if ((int)$existing['version'] !== $expectedVersion)
                    throw new ClinicalIdempotencyException('INTERCONSULTA_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            }
            $writeContext = ['patient_id' => $patientId, 'care_setting' => 'consulta'];
            $canonical = $body;
            $canonical['context'] = $writeContext;
            $canonical['actor'] = ['user_id' => (string)$doctor['user_id']];
            $canonical['payload'] = $payload;
            $canonical['payload']['rendered_text'] = clinical_interconsulta_canonical_text($canonical['payload']);
            $uploadedQrRoles = [];
            $previousPayload = $existing !== null ? json_decode((string)$existing['payload_json'], true) : null;
            foreach (['doctor'] as $role) {
                $canonical['payload']['signature_binding_status'][$role] =
                    clinical_interconsulta_binding_classify($canonical, (string)$doctor['doctor_id'], $pdo);
                $entry = (array)($payload['signatures'][$role] ?? []);
                if (($entry['source'] ?? '') !== 'remote_qr') continue;
                $qr = clinical_interconsulta_qr_row($pdo, (string)($entry['token'] ?? ''));
                $previousEntry = is_array($previousPayload)
                    ? (array)($previousPayload['signatures'][$role] ?? []) : [];
                if ($canonical['payload']['signature_binding_status'][$role] !== 'valid_bound_signature'
                    && ($existing === null || !clinical_interconsulta_qr_entry_unchanged($previousEntry, $entry)))
                    throw new InvalidArgumentException('INTERCONSULTA_QR_UNVERIFIED');
                if ($qr !== null && (string)$qr['status'] === 'uploaded') {
                    if ($existing === null || $canonical['payload']['signature_binding_status'][$role] !== 'valid_bound_signature')
                        throw new InvalidArgumentException('INTERCONSULTA_QR_STALE');
                    $uploadedQrRoles[] = $role;
                }
            }
            if ($intent === 'issued') {
                foreach (['doctor' => 'INTERCONSULTA_PHYSICIAN_SIGNATURE'] as $role => $errorPrefix) {
                    $classification = $canonical['payload']['signature_binding_status'][$role];
                    if ($classification === 'absent')
                        throw new InvalidArgumentException($errorPrefix . '_REQUIRED');
                    if ($classification !== 'valid_bound_signature')
                        throw new InvalidArgumentException($errorPrefix . '_INVALID_CURRENT_VERSION');
                }
            }
            $canonical['payload']['interconsulta_snapshot'] = [
                'version' => 1,
                'html' => clinical_interconsulta_render_html($canonical['payload']),
            ];
            $nextStatus = $intent === 'draft' ? 'draft' : 'generated';
            if ($existing === null) {
                $id = clinical_v1_document_insert($pdo, $writeContext, $canonical, (string)$doctor['user_id']);
                if ($nextStatus === 'draft') {
                    $mark = $pdo->prepare("UPDATE clinical_documents SET status='draft',generated_at=NULL WHERE id=? AND status='generated'");
                    $mark->execute([$id]);
                    if ($mark->rowCount() !== 1) throw new RuntimeException('INTERCONSULTA_DRAFT_CREATE_FAILED');
                }
                return $id;
            }
            $id = (int)$existing['id'];
            $encoded = json_encode($canonical['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $update = $pdo->prepare('UPDATE clinical_documents SET title=?,summary=?,payload_json=?,rendered_text=?,
                event_datetime=?,status=?,version=version+1,updated_at=UTC_TIMESTAMP(),updated_by_user_id=?,
                edited_flag=1,generated_at=CASE WHEN ?=\'generated\' THEN UTC_TIMESTAMP() ELSE NULL END
                WHERE id=? AND version=? AND status=\'draft\'');
            $update->execute([(string)($body['title'] ?? 'Interconsulta médico'), (string)($body['summary'] ?? ''),
                $encoded, (string)$canonical['payload']['rendered_text'],
                (string)($body['event_datetime'] ?? gmdate('Y-m-d H:i:s')),
                $nextStatus, (string)$doctor['user_id'], $nextStatus, $id, $expectedVersion]);
            if ($update->rowCount() !== 1)
                throw new ClinicalIdempotencyException('INTERCONSULTA_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            foreach ($uploadedQrRoles as $role) {
                clinical_interconsulta_qr_claim($pdo, $canonical, (array)$payload['signatures'][$role], $role,
                    (string)$doctor['doctor_id'], $draftRef, $expectedVersion);
            }
            return $id;
        }, static fn(int $id): array => clinical_v1_document_fetch($pdo, $id));
}
