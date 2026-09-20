<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_multipart_document_service.php';

/** Deployment configuration only; no implicit product TTL or storage fallback. */
function clinical_encounter_multipart_config(): array
{
    $root = getenv('MXMED_CLINICAL_PRIVATE_STORAGE_ROOT');
    if (!is_string($root) || trim($root) === '') {
        throw new RuntimeException('V1_MULTIPART_STORAGE_NOT_READY');
    }
    $ttl = getenv('MXMED_CLINICAL_STAGING_TTL_SECONDS');
    if (!is_string($ttl) || !preg_match('/^[0-9]+$/D', $ttl)
        || filter_var($ttl, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 86400]]) === false) {
        throw new RuntimeException('V1_MULTIPART_STORAGE_NOT_READY');
    }
    return [$root, (int)$ttl];
}

function clinical_encounter_multipart_file(array $files): array
{
    $file = $files['file'] ?? null;
    if (count($files) !== 1 || !is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK
        || !is_string($file['tmp_name'] ?? null) || $file['tmp_name'] === ''
        || !is_file($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])
        || !is_string($file['name'] ?? null)) {
        throw new InvalidArgumentException('MULTIPART_FILE_REQUIRED');
    }
    return $file;
}

/** Called only after the canonical route's authorization and operation policy pass. */
function clinical_encounter_multipart_execute(PDO $pdo, array $encounter, array $doctor,
    array $payload, string $createOperation, string $policyOperation, string $documentClass,
    array $policyContext, array $files, string $idempotencyKey): array
{
    $file = clinical_encounter_multipart_file($files);
    [$root, $ttl] = clinical_encounter_multipart_config();
    try {
        $storage = new ClinicalPrivateBinaryStorage($root);
    } catch (Throwable) {
        throw new RuntimeException('V1_MULTIPART_STORAGE_NOT_READY');
    }
    $context = [
        'operation' => $createOperation,
        'doctor_id' => $doctor['doctor_id'],
        'patient_id' => $encounter['patient_id'],
        'context_type' => 'ENCOUNTER',
        'context_id' => (string)$encounter['encounter_id'],
        'document_type' => strtolower(trim((string)$payload['document_type'])),
        'metadata' => clinical_document_semantic_request($payload, null),
    ];
    return (new ClinicalMultipartDocumentService($pdo, $storage))->execute(
        $context, $idempotencyKey, $doctor['user_id'], $file['tmp_name'], $file['name'],
        (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+' . $ttl . ' seconds'),
        function (PDO $transaction, string $documentUuid) use ($encounter, $doctor, $payload, $createOperation, $policyOperation, $documentClass, $policyContext): array {
            $lock = $transaction->prepare('SELECT * FROM clinical_encounters WHERE encounter_id=:id FOR UPDATE');
            $lock->execute([':id' => $encounter['encounter_id']]);
            $locked = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) throw new RuntimeException('ENCOUNTER_NOT_FOUND');
            if ((string)$locked['doctor_id'] !== (string)$doctor['doctor_id']
                || (string)$locked['patient_id'] !== (string)$encounter['patient_id']) {
                throw new RuntimeException('DOCUMENT_CONTEXT_MISMATCH');
            }
            if ($createOperation === 'CREATE_POST_ENCOUNTER_RESULT') {
                // Canonical order authority is checked again while the encounter is locked.
                $policyContext['valid_originating_order'] = clinical_v1_originating_order_valid($transaction, $payload, $locked);
            }
            $decision = clinical_document_operation_policy($policyOperation, $documentClass, (string)$locked['status'], $policyContext);
            if (($decision['allowed'] ?? false) !== true) throw new RuntimeException((string)($decision['code'] ?? 'DOCUMENT_OPERATION_UNSUPPORTED'));
            $id = clinical_v1_document_insert($transaction, $locked, $payload, $doctor['user_id'], $documentUuid);
            return ['document_id' => $id, 'document_uuid' => $documentUuid, 'result_column' => 'document_id', 'result_id' => $id];
        },
        static fn(PDO $transaction, string $column, int $id): array => clinical_v1_document_fetch($transaction, $id)
    );
}

/** Canonical append-only amendment with an immutable successor binary. */
function clinical_document_amendment_multipart_execute(PDO $pdo, array $original, array $command,
    array $doctor, array $files, string $idempotencyKey): array
{
    $file = clinical_encounter_multipart_file($files);
    [$root, $ttl] = clinical_encounter_multipart_config();
    try {
        $storage = new ClinicalPrivateBinaryStorage($root);
    } catch (Throwable) {
        throw new RuntimeException('V1_MULTIPART_STORAGE_NOT_READY');
    }
    $replacement = $command['replacement'];
    $encounterId = isset($original['encounter_ref_id']) ? (int)$original['encounter_ref_id'] : 0;
    $context = [
        'operation' => 'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT',
        'doctor_id' => $doctor['doctor_id'],
        'patient_id' => $original['patient_id'],
        'context_type' => $encounterId > 0 ? 'ENCOUNTER' : 'PATIENT',
        'context_id' => $encounterId > 0 ? (string)$encounterId : (string)$original['patient_id'],
        'document_type' => strtolower(trim((string)$replacement['document_type'])),
        'metadata' => clinical_document_amendment_semantic_request(
            (string)$doctor['doctor_id'], $original, $replacement, (string)$command['reason']
        ),
    ];
    return (new ClinicalMultipartDocumentService($pdo, $storage))->execute(
        $context, $idempotencyKey, (string)$doctor['user_id'], $file['tmp_name'], $file['name'],
        (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+' . $ttl . ' seconds'),
        function (PDO $transaction, string $documentUuid) use ($original, $replacement, $command, $doctor): array {
            $revisionId = clinical_v1_document_amendment_insert(
                $transaction, $original, $replacement, (string)$command['reason'], $doctor, $documentUuid
            );
            $revision = clinical_v1_document_revision_fetch($transaction, $revisionId);
            return [
                'document_id' => (int)$revision['new_document_id'],
                'document_uuid' => $documentUuid,
                'result_column' => 'document_revision_id',
                'result_id' => $revisionId,
            ];
        },
        static fn(PDO $transaction, string $column, int $id): array => clinical_v1_document_revision_fetch($transaction, $id)
    );
}

/** Public error codes only; never forward storage paths or exception text. */
function clinical_encounter_multipart_error(Throwable $error): array
{
    $code = $error->getMessage();
    if ($error instanceof ClinicalIdempotencyException) return [$error->httpStatus, $error->errorCode];
    if ($code === 'V1_MULTIPART_STORAGE_NOT_READY' || str_starts_with($code, 'MULTIPART_STORAGE_SCHEMA_NOT_READY')) return [503, 'V1_MULTIPART_STORAGE_NOT_READY'];
    if ($code === 'MULTIPART_FILE_REQUIRED') return [400, $code];
    if (in_array($code, ['STAGING_MAX_BYTES_EXCEEDED', 'STAGING_MIME_NOT_ALLOWED', 'STAGING_SOURCE_NOT_REGULAR_FILE'], true)) return [400, 'MULTIPART_FILE_INVALID'];
    if (in_array($code, ['ENCOUNTER_VOIDED', 'ENCOUNTER_TERMINAL', 'ENCOUNTER_CLOSED', 'DOCUMENT_CONTEXT_MISMATCH',
        'DOCUMENT_TYPE_MISMATCH', 'DOCUMENT_ALREADY_SUPERSEDED', 'DOCUMENT_LINEAGE_INVALID',
        'DOCUMENT_OPERATION_UNSUPPORTED', 'DOCUMENT_NOT_FOUND', 'ENCOUNTER_NOT_FOUND'], true)) {
        return [clinical_v1_error_status($error), clinical_v1_error_code($error)];
    }
    return [500, 'server_error'];
}

function clinical_encounter_multipart_response(array $result): array
{
    $replay = ($result['_idempotency_replay'] ?? false) === true;
    $cleanup = ($result['cleanup_pending'] ?? false) === true;
    unset($result['_idempotency_replay'], $result['cleanup_pending']);
    return [$replay ? 200 : 201, [
        'ok' => true, 'error' => null, 'message' => 'document created', 'data' => $result,
        'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents',
            'idempotency_replay' => $replay, 'binary_cleanup_pending' => $cleanup],
    ]];
}

function clinical_document_amendment_multipart_response(array $result): array
{
    $replay = ($result['_idempotency_replay'] ?? false) === true;
    $cleanup = ($result['cleanup_pending'] ?? false) === true;
    unset($result['_idempotency_replay'], $result['cleanup_pending']);
    return [$replay ? 200 : 201, [
        'ok' => true, 'error' => null, 'message' => 'document amendment created', 'data' => $result,
        'meta' => ['method' => 'POST', 'route' => 'documents/{document_id_or_uuid}/amendments',
            'idempotency_replay' => $replay, 'binary_cleanup_pending' => $cleanup],
    ]];
}
