<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_encounter_multipart_adapter.php';

/** The consent command owns its identity files in the same canonical create. */
function clinical_consent_identity_files(array $files): array
{
    if (!isset($files['identity_files'])) return [];
    if (count($files) !== 1 || !is_array($files['identity_files'])) {
        throw new InvalidArgumentException('CONSENT_IDENTITY_FILES_INVALID');
    }
    $input = $files['identity_files'];
    if (!is_array($input['name'] ?? null)) {
        throw new InvalidArgumentException('CONSENT_IDENTITY_FILES_INVALID');
    }
    $count = count($input['name']);
    if ($count < 1 || $count > 10) throw new InvalidArgumentException('CONSENT_IDENTITY_FILES_INVALID');
    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $error = $input['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $path = $input['tmp_name'][$i] ?? null;
        if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
            throw new InvalidArgumentException('CONSENT_IDENTITY_FILE_INVALID');
        }
        $result[] = ['path' => $path, 'name' => (string)($input['name'][$i] ?? '')];
    }
    return $result;
}

function clinical_consent_validate_remote_refs(PDO $pdo, string $patientId, array $payload): void
{
    $refs = $payload['signer_identity_attachments'] ?? [];
    if (!is_array($refs) || count($refs) > 30) {
        throw new InvalidArgumentException('CONSENT_IDENTITY_REFS_INVALID');
    }
    foreach ($refs as $ref) {
        if (!is_array($ref)) throw new InvalidArgumentException('CONSENT_IDENTITY_REFS_INVALID');
        $uuid = trim((string)($ref['document_uuid'] ?? ''));
        $id = trim((string)($ref['document_id'] ?? ''));
        $token = trim((string)($ref['note_capture_token'] ?? ''));
        if ($uuid === '' && $id === '' && $token === '') {
            throw new InvalidArgumentException('CONSENT_IDENTITY_REFS_INVALID');
        }
        if ($token !== '') {
            try { $capture = clinical_note_capture_token_fetch($pdo, $token); }
            catch (Throwable) { $capture = null; }
            if (!is_array($capture) || (string)($capture['patient_id'] ?? '') !== $patientId
                || !in_array((string)($capture['status'] ?? ''), ['uploaded', 'consumed'], true)) {
                throw new InvalidArgumentException('CONSENT_IDENTITY_REF_SCOPE_INVALID');
            }
            if ($uuid === '' && $id === '') {
                $uuid = trim((string)($capture['document_uuid'] ?? ''));
                $id = trim((string)($capture['document_id'] ?? ''));
            }
        }
        if ($uuid === '' && $id === '') {
            throw new InvalidArgumentException('CONSENT_IDENTITY_REF_SCOPE_INVALID');
        }
        $document = clinical_v1_document_record_by_token($pdo, $uuid !== '' ? $uuid : $id);
        if ($document === null || (string)$document['patient_id'] !== $patientId
            || !in_array((string)$document['document_type'], ['pdf', 'image'], true)
            || ($uuid !== '' && $id !== '' && (string)$document['id'] !== $id)) {
            throw new InvalidArgumentException('CONSENT_IDENTITY_REF_SCOPE_INVALID');
        }
    }
}

/** Uses V1 document, idempotency, and private binary authorities; no legacy writer. */
function clinical_consent_create(PDO $pdo, array $doctor, string $patientId, array $body,
    array $files, string $idempotencyKey): array
{
    if (($body['document_type'] ?? null) !== 'consentimiento_informado'
        || ($body['type'] ?? null) !== 'consentimiento_informado'
        || !is_array($body['payload'] ?? null)) {
        throw new InvalidArgumentException('CONSENT_DOCUMENT_INVALID');
    }
    $payload = $body['payload'];
    clinical_consent_validate_remote_refs($pdo, $patientId, $payload);
    $intent = (string)($payload['consent']['status'] ?? '');
    if (!in_array($intent, ['draft', 'granted'], true)
        || ($payload['status'] ?? null) !== $intent) {
        throw new InvalidArgumentException('CONSENT_INTENT_INVALID');
    }
    $key = clinical_idempotency_key_validate($idempotencyKey);
    clinical_encounter_integrity_assert_schema_ready($pdo);
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $expectedVersion = (int)($body['expected_version'] ?? 0);
    if ($draftRef !== '' && (preg_match('/^[0-9a-f-]{36}$/i', $draftRef) !== 1 || $expectedVersion < 1)) {
        throw new InvalidArgumentException('CONSENT_DRAFT_IDENTITY_INVALID');
    }
    $context = is_array($body['context'] ?? null) ? $body['context'] : [];
    $encounterKey = trim((string)($context['encounter_key'] ?? ''));
    $appointmentId = trim((string)($context['appointment_id'] ?? ''));
    $encounter = null;
    if ($encounterKey !== '') {
        $resolved = clinical_resolve_encounter_key($pdo, $encounterKey);
        $encounter = is_array($resolved['row'] ?? null) ? $resolved['row'] : null;
        if (($resolved['ok'] ?? false) !== true || $encounter === null
            || (string)($encounter['patient_id'] ?? '') !== $patientId
            || (string)($encounter['doctor_id'] ?? '') !== (string)$doctor['doctor_id']) {
            throw new InvalidArgumentException('CONSENT_ENCOUNTER_SCOPE_INVALID');
        }
        $encounterAppointment = trim((string)($encounter['appointment_id'] ?? ''));
        if ($appointmentId !== '' && $appointmentId !== $encounterAppointment) {
            throw new InvalidArgumentException('CONSENT_APPOINTMENT_SCOPE_INVALID');
        }
        $appointmentId = $encounterAppointment;
    } elseif ($appointmentId !== '' && !clinical_appointment_matches_encounter_owner(
        $pdo, $appointmentId, (string)$doctor['doctor_id'], $patientId)) {
        throw new InvalidArgumentException('CONSENT_APPOINTMENT_SCOPE_INVALID');
    }
    $documentFiles = clinical_consent_identity_files($files);
    if ($documentFiles !== []) clinical_multipart_storage_assert_schema_ready($pdo);
    $storage = null;
    $staged = [];
    $finalized = [];
    $result = null;
    $cleanupSafe = false;
    $parentUuid = $draftRef !== '' ? $draftRef : ClinicalPrivateBinaryStorage::uuidV4();
    try {
        if ($documentFiles !== []) {
            [$root, $ttl] = clinical_encounter_multipart_config();
            $storage = new ClinicalPrivateBinaryStorage($root);
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            foreach ($documentFiles as $file) {
                try {
                    $binary = $storage->stageFile($file['path'], $file['name']);
                } catch (ClinicalPrivateBinaryStorageException $error) {
                    if (in_array($error->getMessage(), ['STAGING_MIME_NOT_ALLOWED', 'STAGING_MAX_BYTES_EXCEEDED'], true)) {
                        throw new InvalidArgumentException('CONSENT_IDENTITY_FILE_INVALID', 0, $error);
                    }
                    throw $error;
                }
                $documentUuid = ClinicalPrivateBinaryStorage::uuidV4();
                $binaryUuid = ClinicalPrivateBinaryStorage::uuidV4();
                $finalKey = $storage->buildFinalKey($documentUuid, $binaryUuid, 'ORIGINAL', $now);
                $staged[] = ['binary' => $binary, 'document_uuid' => $documentUuid,
                    'binary_uuid' => $binaryUuid, 'final_key' => $finalKey,
                    'upload_id' => ClinicalPrivateBinaryStorage::uuidV4()];
                if (!in_array($binary['mime_type'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
                    throw new InvalidArgumentException('CONSENT_IDENTITY_FILE_INVALID');
                }
            }
        }
        $semantic = clinical_document_semantic_request($body, null) + [
            'patient_id' => $patientId, 'operation' => 'CREATE_ENCOUNTER_DOCUMENT',
            'consent_intent' => $intent,
            'encounter_id' => $encounter === null ? null : (string)$encounter['encounter_id'],
            'appointment_id' => $appointmentId !== '' ? $appointmentId : null,
            'draft_ref' => $draftRef !== '' ? $draftRef : null,
            'expected_version' => $draftRef !== '' ? $expectedVersion : null,
            'identity_binaries' => array_map(static fn(array $row): array => [
                'sha256' => $row['binary']['sha256'], 'byte_length' => $row['binary']['byte_length'],
                'mime_type' => $row['binary']['mime_type']], $staged),
        ];
        $requestHash = clinical_idempotency_request_hash($semantic);
        if ($staged !== []) {
            $uploads = new ClinicalMultipartCoordinationRepository($pdo);
            $pdo->beginTransaction();
            try {
                foreach ($staged as $row) {
                    $uploads->insertStaged([
                        'upload_id' => $row['upload_id'], 'operation_type' => 'CREATE_ENCOUNTER_DOCUMENT',
                        'doctor_id' => (string)$doctor['doctor_id'], 'context_type' => 'PATIENT',
                        'context_id' => $patientId, 'idempotency_key_digest' => hash('sha256', $key),
                        'semantic_request_hash' => $requestHash,
                        'binary_sha256' => $row['binary']['sha256'],
                        'byte_length' => $row['binary']['byte_length'],
                        'mime_type' => $row['binary']['mime_type'],
                        'staging_key' => $row['binary']['staging_key'],
                        'planned_final_prefix' => substr($row['final_key'], 0, strrpos($row['final_key'], '/')),
                        'expires_at' => $now->modify('+' . $ttl . ' seconds')->format('Y-m-d H:i:s'),
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        }
        $service = new ClinicalEncounterIntegrityService($pdo);
        $result = $service->idempotentCreate('CREATE_ENCOUNTER_DOCUMENT', (string)$doctor['doctor_id'],
            'PATIENT', $patientId, $key, $semantic, 'document_id', (string)$doctor['user_id'],
            function () use ($pdo, $doctor, $patientId, $body, $payload, $context, $encounter,
                $appointmentId, $parentUuid, $draftRef, $expectedVersion, $intent,
                $staged, $storage, $key, &$finalized): int {
                if (!clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], $patientId)) {
                    throw new RuntimeException('DOCUMENT_CONTEXT_MISMATCH');
                }
                $requestStmt = $pdo->prepare("SELECT request_id FROM clinical_idempotency_requests
                    WHERE operation_type='CREATE_ENCOUNTER_DOCUMENT' AND doctor_id=?
                      AND context_type='PATIENT' AND context_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE");
                $requestStmt->execute([(string)$doctor['doctor_id'], $patientId, $key]);
                $requestId = (int)$requestStmt->fetchColumn();
                if ($requestId <= 0) throw new RuntimeException('CONSENT_IDEMPOTENCY_MISSING');
                $existing = null;
                if ($draftRef !== '') {
                    $find = $pdo->prepare('SELECT id,document_uuid,document_type,patient_id,status,version,
                        created_by_user_id,encounter_ref_id,appointment_id,payload_json,signed_at
                        FROM clinical_documents WHERE document_uuid=? LIMIT 1 FOR UPDATE');
                    $find->execute([$draftRef]);
                    $existing = $find->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($existing === null || (string)$existing['patient_id'] !== $patientId
                        || (string)$existing['document_type'] !== 'consentimiento_informado'
                        || (string)$existing['created_by_user_id'] !== (string)$doctor['user_id']) {
                        throw new InvalidArgumentException('CONSENT_DRAFT_SCOPE_INVALID');
                    }
                    $storedPayload = json_decode((string)$existing['payload_json'], true);
                    $legacyDraft = (string)$existing['status'] === 'generated'
                        && is_array($storedPayload)
                        && (string)($storedPayload['consent']['status'] ?? '') === 'draft'
                        && $existing['signed_at'] === null;
                    if ($legacyDraft) {
                        $revision = $pdo->prepare('SELECT revision_id FROM clinical_document_revisions
                            WHERE original_document_id=? OR supersedes_document_id=? OR new_document_id=? LIMIT 1');
                        $revision->execute([(int)$existing['id'], (int)$existing['id'], (int)$existing['id']]);
                        $legacyDraft = $revision->fetchColumn() === false;
                    }
                    if ((string)$existing['status'] !== 'draft' && !$legacyDraft) {
                        throw new ClinicalIdempotencyException('CONSENT_DRAFT_FINAL', 'El consentimiento ya fue emitido.', 409);
                    }
                    if ((int)$existing['version'] !== $expectedVersion) {
                        throw new ClinicalIdempotencyException('CONSENT_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
                    }
                    $storedEncounter = (int)($existing['encounter_ref_id'] ?? 0);
                    $requestedEncounter = (int)($encounter['encounter_id'] ?? 0);
                    if ($storedEncounter !== $requestedEncounter
                        || trim((string)($existing['appointment_id'] ?? '')) !== $appointmentId) {
                        throw new InvalidArgumentException('CONSENT_DRAFT_CONTEXT_MISMATCH');
                    }
                }
                $refs = is_array($payload['signer_identity_attachments'] ?? null)
                    ? $payload['signer_identity_attachments'] : [];
                if ($existing !== null) {
                    $previous = json_decode((string)$existing['payload_json'], true);
                    foreach ((array)($previous['signer_identity_attachments'] ?? []) as $oldRef) {
                        if (!is_array($oldRef)) continue;
                        $oldUuid = (string)($oldRef['document_uuid'] ?? '');
                        if ($oldUuid !== '' && !array_filter($refs, static fn($ref): bool =>
                            is_array($ref) && (string)($ref['document_uuid'] ?? '') === $oldUuid)) {
                            $refs[] = $oldRef;
                        }
                    }
                }
                foreach ($staged as $row) {
                    $refs[] = ['document_uuid' => $row['document_uuid'],
                        'title' => 'Anexo identidad firmante — ' . (string)($row['binary']['source_filename'] ?? 'archivo'),
                        'file_name' => (string)($row['binary']['source_filename'] ?? ''),
                        'source' => 'consentimiento_identidad_local'];
                }
                $consentPayload = $payload;
                $consentPayload['signer_identity_attachments'] = $refs;
                $consentPayload['attachments'] = is_array($consentPayload['attachments'] ?? null)
                    ? $consentPayload['attachments'] : [];
                $consentPayload['attachments']['signer_identity'] = $refs;
                if ($staged !== [] && is_array($consentPayload['frozen_snapshot'] ?? null)) {
                    $html = (string)($consentPayload['frozen_snapshot']['html'] ?? '');
                    $attachmentHtml = '<section><strong>Documentos de identidad del firmante</strong><ul>';
                    foreach ($staged as $row) {
                        $uuid = rawurlencode($row['document_uuid']);
                        $name = htmlspecialchars((string)($row['binary']['source_filename'] ?? 'Anexo'), ENT_QUOTES, 'UTF-8');
                        $attachmentHtml .= '<li><a href="/modules/clinical/ui/viewer.php?uuid=' . $uuid
                            . '&amp;doctor_id=' . rawurlencode((string)$doctor['doctor_id']) . '">' . $name . '</a></li>';
                    }
                    $attachmentHtml .= '</ul></section>';
                    $close = strripos($html, '</article>');
                    $consentPayload['frozen_snapshot']['html'] = $close === false
                        ? $html . $attachmentHtml
                        : substr($html, 0, $close) . $attachmentHtml . substr($html, $close);
                }
                $writeContext = ['patient_id' => $patientId,
                    'appointment_id' => $appointmentId !== '' ? $appointmentId : null,
                    'encounter_id' => $encounter === null ? null : (string)$encounter['encounter_id'],
                    'care_setting' => 'consulta'];
                $consent = $body;
                $consent['payload'] = $consentPayload;
                $consent['context'] = $writeContext;
                $nextStatus = $intent === 'draft' ? 'draft' : 'generated';
                if ($existing === null) {
                    $parentId = clinical_v1_document_insert($pdo, $writeContext, $consent,
                        (string)$doctor['user_id'], $parentUuid);
                    if ($nextStatus === 'draft') {
                        $markDraft = $pdo->prepare("UPDATE clinical_documents
                            SET status='draft',generated_at=NULL WHERE id=? AND status='generated'");
                        $markDraft->execute([$parentId]);
                        if ($markDraft->rowCount() !== 1) throw new RuntimeException('CONSENT_DRAFT_CREATE_FAILED');
                    }
                } else {
                    $parentId = (int)$existing['id'];
                    $encoded = json_encode($consentPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    $update = $pdo->prepare("UPDATE clinical_documents SET title=?,summary=?,payload_json=?,
                        event_datetime=?,status=?,version=version+1,updated_at=UTC_TIMESTAMP(),
                        updated_by_user_id=?,edited_flag=1,generated_at=CASE WHEN ?='generated' THEN UTC_TIMESTAMP() ELSE NULL END
                        WHERE id=? AND version=?");
                    $update->execute([(string)($body['title'] ?? 'Consentimiento informado'),
                        (string)($body['summary'] ?? ''), $encoded,
                        (string)($body['event_datetime'] ?? gmdate('Y-m-d H:i:s')),
                        $nextStatus, (string)$doctor['user_id'], $nextStatus, $parentId, $expectedVersion]);
                    if ($update->rowCount() !== 1) {
                        throw new ClinicalIdempotencyException('CONSENT_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
                    }
                }
                if ($staged !== []) {
                    $uploads = new ClinicalMultipartCoordinationRepository($pdo);
                    $binaries = new ClinicalMultipartBinaryManifestRepository($pdo);
                    foreach ($staged as $row) {
                        $type = $row['binary']['mime_type'] === 'application/pdf' ? 'pdf' : 'image';
                        $attachment = ['document_type' => $type,
                            'title' => 'Anexo identidad firmante — ' . (string)($row['binary']['source_filename'] ?? 'archivo'),
                            'summary' => 'Anexo de identidad del firmante',
                            'event_datetime' => $body['event_datetime'] ?? gmdate('Y-m-d H:i:s'),
                            'payload' => ['source' => 'consentimiento_identidad_anexo',
                                'owner_document_uuid' => $parentUuid,
                                'file_name' => (string)($row['binary']['source_filename'] ?? '')]];
                        $attachmentId = clinical_v1_document_insert($pdo, $writeContext,
                            $attachment, (string)$doctor['user_id'], $row['document_uuid']);
                        $uploads->bindRequest($row['upload_id'], $requestId);
                        $storage->finalizeCreateOnly($row['binary']['staging_key'], $row['final_key'],
                            $row['binary']['sha256'], $row['binary']['byte_length']);
                        $finalized[] = $row['final_key'];
                        $binaries->insertOriginal($attachmentId, $row['binary_uuid'], $row['final_key'], $row['binary']);
                        $uploads->finalize($row['upload_id'], $requestId, $attachmentId);
                    }
                }
                return $parentId;
            }, static fn(int $id): array => clinical_v1_document_fetch($pdo, $id));
        $cleanupSafe = true;
        return $result;
    } catch (Throwable $error) {
        // A failed transaction may have finalized a file before the SQL rollback.
        // Verify the durable command result before moving any final object.
        $committed = null;
        if (!$pdo->inTransaction()) {
            try {
                $check = $pdo->prepare("SELECT document_id FROM clinical_idempotency_requests
                    WHERE operation_type='CREATE_ENCOUNTER_DOCUMENT' AND doctor_id=?
                      AND context_type='PATIENT' AND context_id=? AND idempotency_key=? LIMIT 1");
                $check->execute([(string)$doctor['doctor_id'], $patientId, $key]);
                $committed = (int)$check->fetchColumn() > 0;
            } catch (Throwable) { }
        }
        if ($committed === false && $storage !== null) {
            $uploads = new ClinicalMultipartCoordinationRepository($pdo);
            foreach ($finalized as $finalKey) {
                $uploadId = null;
                foreach ($staged as $row) {
                    if ($row['final_key'] === $finalKey) { $uploadId = $row['upload_id']; break; }
                }
                $state = 'RECONCILIATION_REQUIRED';
                $code = 'CONSENT_FINAL_QUARANTINE_FAILED';
                try {
                    $quarantine = $storage->quarantine($finalKey);
                    if (!$quarantine['cleanup_pending'] && !$storage->stat($finalKey)['exists']) {
                        $state = 'ORPHANED';
                        $code = 'CONSENT_FINAL_QUARANTINED';
                    }
                } catch (Throwable) { }
                if ($uploadId !== null) {
                    try { $uploads->recordRecovery($uploadId, $state, $code); } catch (Throwable) { }
                }
            }
        }
        $cleanupSafe = $committed === false && $finalized === [];
        throw $error;
    } finally {
        if ($storage !== null && $cleanupSafe) {
            foreach ($staged as $row) {
                try { $storage->deleteUncommitted($row['binary']['staging_key']); } catch (Throwable) { }
            }
            foreach ($staged as $row) {
                try {
                    $delete = $pdo->prepare("DELETE FROM clinical_binary_uploads
                        WHERE upload_id=? AND storage_state='STAGED' AND document_id IS NULL");
                    $delete->execute([$row['upload_id']]);
                } catch (Throwable) { }
            }
        }
    }
}
