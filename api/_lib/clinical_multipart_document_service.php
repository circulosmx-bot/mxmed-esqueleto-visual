<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_idempotency.php';
require_once __DIR__ . '/clinical_private_binary_storage.php';
require_once __DIR__ . '/clinical_multipart_storage_schema.php';

/** Internal server input only. Authorization and clinical policy belong to the caller. */
function clinical_multipart_semantic_request(array $context, array $binary): array
{
    $operation = clinical_idempotency_operation_validate((string)($context['operation'] ?? ''));
    if (!in_array($operation, ['CREATE_ENCOUNTER_DOCUMENT', 'CREATE_POST_ENCOUNTER_RESULT', 'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT'], true)) {
        throw new InvalidArgumentException('MULTIPART_OPERATION_UNSUPPORTED');
    }
    $normalized = [];
    foreach (['doctor_id' => 64, 'patient_id' => 128, 'context_id' => 128, 'document_type' => 100] as $field => $limit) {
        $value = $context[$field] ?? null;
        if ((!is_string($value) && !is_int($value)) || trim((string)$value) === ''
            || strlen(trim((string)$value)) > $limit || preg_match('/[\x00-\x1F\x7F]/', (string)$value)) {
            throw new InvalidArgumentException('MULTIPART_CONTEXT_INVALID');
        }
        $normalized[$field] = trim((string)$value);
    }
    $contextType = strtoupper(trim((string)($context['context_type'] ?? '')));
    if (!in_array($contextType, ['ENCOUNTER', 'PATIENT'], true)
        || ($contextType === 'PATIENT' && ($operation !== 'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT'
            || $normalized['context_id'] !== $normalized['patient_id']))) {
        throw new InvalidArgumentException('MULTIPART_CONTEXT_INVALID');
    }
    $metadata = $context['metadata'] ?? [];
    if (!is_array($metadata)) {
        throw new InvalidArgumentException('MULTIPART_METADATA_INVALID');
    }
    // Generated transport/storage identities are never logical document metadata.
    $validateMetadata = static function (mixed $value) use (&$validateMetadata): void {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                if (in_array($key, ['upload_id', 'staging_key', 'document_uuid', 'binary_uuid', 'final_key', 'storage_key'], true)) {
                    throw new InvalidArgumentException('MULTIPART_METADATA_GENERATED_IDENTITY');
                }
                $validateMetadata($child);
            }
        } elseif (!is_null($value) && !is_scalar($value)) {
            throw new InvalidArgumentException('MULTIPART_METADATA_INVALID');
        }
    };
    $validateMetadata($metadata);
    $sha = $binary['sha256'] ?? '';
    $bytes = $binary['byte_length'] ?? null;
    $mime = $binary['mime_type'] ?? '';
    if (!is_string($sha) || !preg_match('/^[a-f0-9]{64}$/', $sha)
        || !is_int($bytes) || $bytes <= 0 || $bytes > ClinicalPrivateBinaryStorage::MAX_BYTES
        || !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new InvalidArgumentException('MULTIPART_BINARY_INTEGRITY_INVALID');
    }
    return [
        'canonicalization_version' => clinical_idempotency_canonicalization_version(),
        'operation' => $operation,
        'doctor_id' => $normalized['doctor_id'],
        'patient_id' => $normalized['patient_id'],
        'context_type' => $contextType,
        'context_id' => $normalized['context_id'],
        'document_type' => $normalized['document_type'],
        'metadata' => $metadata,
        'binary_sha256' => $sha,
        'binary_byte_length' => $bytes,
        'binary_mime' => $mime,
    ];
}

/** Operational coordination only; does not own clinical resources or command replay. */
final class ClinicalMultipartCoordinationRepository
{
    public function __construct(private PDO $pdo) {}

    public function insertStaged(array $row): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO clinical_binary_uploads
            (upload_id,operation_type,doctor_id,context_type,context_id,idempotency_key_digest,
             semantic_request_hash,binary_sha256,byte_length,mime_type,staging_key,planned_final_prefix,
             storage_state,idempotency_request_id,document_id,created_at,updated_at,expires_at)
            VALUES (:upload_id,:operation_type,:doctor_id,:context_type,:context_id,:idempotency_key_digest,
             :semantic_request_hash,:binary_sha256,:byte_length,:mime_type,:staging_key,:planned_final_prefix,
             'STAGED',NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP(),:expires_at)");
        $stmt->execute($row);
    }

    public function bindRequest(string $uploadId, int $requestId): void
    {
        $stmt = $this->pdo->prepare("UPDATE clinical_binary_uploads SET idempotency_request_id=?,updated_at=UTC_TIMESTAMP()
            WHERE upload_id=? AND storage_state='STAGED' AND document_id IS NULL AND idempotency_request_id IS NULL");
        $stmt->execute([$requestId, $uploadId]);
        $this->assertOne($stmt);
    }

    public function finalize(string $uploadId, int $requestId, int $documentId): void
    {
        $stmt = $this->pdo->prepare("UPDATE clinical_binary_uploads SET storage_state='FINALIZED',document_id=?,updated_at=UTC_TIMESTAMP()
            WHERE upload_id=? AND idempotency_request_id=? AND storage_state='STAGED' AND document_id IS NULL");
        $stmt->execute([$documentId, $uploadId, $requestId]);
        $this->assertOne($stmt);
    }

    /** Called in an independent short transaction; identifiers remain intact. */
    public function recordRecovery(string $uploadId, string $state, string $errorCode): void
    {
        if (!in_array($state, ['STAGED', 'ORPHANED', 'RECONCILIATION_REQUIRED'], true)
            || !preg_match('/^[A-Z0-9_]{1,64}$/', $errorCode)) {
            throw new InvalidArgumentException('MULTIPART_RECOVERY_STATE_INVALID');
        }
        $stmt = $this->pdo->prepare("UPDATE clinical_binary_uploads SET storage_state=?,last_error_code=?,updated_at=UTC_TIMESTAMP()
            WHERE upload_id=? AND (?='RECONCILIATION_REQUIRED' OR (storage_state='STAGED' AND document_id IS NULL AND idempotency_request_id IS NULL))");
        $stmt->execute([$state, $errorCode, $uploadId, $state]);
        $this->assertOne($stmt);
    }

    public function deleteRedundantStaged(string $uploadId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM clinical_binary_uploads WHERE upload_id=?
            AND storage_state='STAGED' AND document_id IS NULL AND idempotency_request_id IS NULL");
        $stmt->execute([$uploadId]);
        $this->assertOne($stmt);
    }

    private function assertOne(PDOStatement $stmt): void
    {
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('MULTIPART_COORDINATION_CONFLICT');
        }
    }
}

/** Immutable ORIGINAL manifest: insertion and reading only. */
final class ClinicalMultipartBinaryManifestRepository
{
    public function __construct(private PDO $pdo) {}

    public function insertOriginal(int $documentId, string $binaryUuid, string $finalKey, array $binary): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO clinical_document_binaries
            (document_id,binary_uuid,variant_role,variant_version,storage_key,sha256,byte_length,mime_type,
             source_filename,created_at,finalized_at)
            VALUES (?,?,'ORIGINAL',1,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->execute([$documentId, $binaryUuid, $finalKey, $binary['sha256'], $binary['byte_length'],
            $binary['mime_type'], $binary['source_filename'] ?? null]);
    }

    public function findOriginal(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clinical_document_binaries WHERE document_id=? AND variant_role='ORIGINAL' AND variant_version=1");
        $stmt->execute([$documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}

final class ClinicalMultipartDocumentService
{
    private ClinicalIdempotencyRepository $requests;
    private ClinicalMultipartCoordinationRepository $uploads;
    private ClinicalMultipartBinaryManifestRepository $binaries;

    /** Requires an exception-mode connection; never opens a connection itself. */
    public function __construct(private PDO $pdo, private ClinicalPrivateBinaryStorage $storage)
    {
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new InvalidArgumentException('MULTIPART_PDO_EXCEPTION_MODE_REQUIRED');
        }
        $this->requests = new ClinicalIdempotencyRepository($pdo);
        $this->uploads = new ClinicalMultipartCoordinationRepository($pdo);
        $this->binaries = new ClinicalMultipartBinaryManifestRepository($pdo);
    }

    /**
     * Internal entry point, never an HTTP authority. The caller authorizes clinical
     * policy and supplies canonical context and logical metadata before calling.
     * createResource(PDO, documentUuid, semanticRequest): typed result identifiers;
     * fetchResource(PDO, resultColumn, resultId): canonical response array.
     * Neither callback may commit/rollback or otherwise take transaction ownership.
     * expiresAt is explicit caller configuration, never an implicit product TTL.
     */
    public function execute(
        array $context,
        string $idempotencyKey,
        string $actorUserId,
        string $sourcePath,
        ?string $sourceFilename,
        DateTimeImmutable $expiresAt,
        callable $createResource,
        callable $fetchResource
    ): array {
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('MULTIPART_OUTER_TRANSACTION_NOT_ALLOWED');
        }
        $key = clinical_idempotency_key_validate($idempotencyKey);
        if (trim($actorUserId) === '' || strlen($actorUserId) > 64) {
            throw new InvalidArgumentException('MULTIPART_ACTOR_REQUIRED');
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($expiresAt <= $now) {
            throw new InvalidArgumentException('MULTIPART_EXPIRATION_INVALID');
        }
        // Validate context before performing filesystem writes; real binary identity follows staging.
        clinical_multipart_semantic_request($context, ['sha256' => str_repeat('0', 64), 'byte_length' => 1, 'mime_type' => 'application/pdf']);
        clinical_multipart_storage_assert_schema_ready($this->pdo);
        $binary = $this->storage->stageFile($sourcePath, $sourceFilename);
        $semantic = clinical_multipart_semantic_request($context, $binary);
        $requestHash = clinical_idempotency_request_hash($semantic);
        $uploadId = ClinicalPrivateBinaryStorage::uuidV4();
        $documentUuid = ClinicalPrivateBinaryStorage::uuidV4();
        $binaryUuid = ClinicalPrivateBinaryStorage::uuidV4();
        $plannedKey = $this->storage->buildFinalKey($documentUuid, $binaryUuid, 'ORIGINAL', $now);
        $row = [
            'upload_id' => $uploadId, 'operation_type' => $semantic['operation'], 'doctor_id' => $semantic['doctor_id'],
            'context_type' => $semantic['context_type'], 'context_id' => $semantic['context_id'],
            'idempotency_key_digest' => hash('sha256', $key), 'semantic_request_hash' => $requestHash,
            'binary_sha256' => $binary['sha256'], 'byte_length' => $binary['byte_length'], 'mime_type' => $binary['mime_type'],
            'staging_key' => $binary['staging_key'], 'planned_final_prefix' => substr($plannedKey, 0, strrpos($plannedKey, '/')),
            'expires_at' => $expiresAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ];
        try {
            // Separate transaction makes STAGED evidence durable before clinical work.
            $this->begin();
            $this->uploads->insertStaged($row);
            $this->commit();
        } catch (Throwable $error) {
            $this->rollbackConfirmed();
            // Preserve staged bytes even if the short commit outcome is unknown.
            throw new RuntimeException('MULTIPART_STAGED_COORDINATION_FAILED', 0, $error);
        }
        return $this->coordinate($semantic, $key, $actorUserId, $requestHash, $uploadId,
            $documentUuid, $binaryUuid, $now, $binary, $createResource, $fetchResource);
    }

    private function coordinate(array $semantic, string $key, string $actorUserId, string $requestHash,
        string $uploadId, string $documentUuid, string $binaryUuid, DateTimeImmutable $partitionDate,
        array $binary, callable $createResource, callable $fetchResource): array
    {
        $resultColumn = $semantic['operation'] === 'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT' ? 'document_revision_id' : 'document_id';
        $finalKey = null;
        $finalCreated = false;
        $finalAttempted = false;
        $commitAttempted = false;
        try {
            $this->begin();
            try {
                $requestId = $this->requests->claim($semantic['operation'], $semantic['doctor_id'],
                    $semantic['context_type'], $semantic['context_id'], $key, $requestHash, $actorUserId);
            } catch (PDOException $claimError) {
                if (!$this->rollbackConfirmed()) {
                    throw new RuntimeException('MULTIPART_ROLLBACK_UNCONFIRMED', 0, $claimError);
                }
                // Only the MySQL duplicate-key error is eligible for canonical replay.
                if ((int)($claimError->errorInfo[1] ?? 0) !== 1062) {
                    throw $claimError;
                }
                return $this->replay($semantic, $key, $requestHash, $resultColumn, $uploadId, $binary, $fetchResource);
            }
            $this->uploads->bindRequest($uploadId, $requestId);
            $result = $createResource($this->pdo, $documentUuid, $semantic);
            if (!$this->pdo->inTransaction()) {
                throw new RuntimeException('MULTIPART_CALLBACK_TRANSACTION_VIOLATION');
            }
            if (!is_array($result) || ($result['document_uuid'] ?? null) !== $documentUuid) {
                throw new RuntimeException('MULTIPART_DOCUMENT_UUID_MISMATCH');
            }
            $documentId = $result['document_id'] ?? null;
            $resultId = $result['result_id'] ?? null;
            if (!is_int($documentId) || $documentId <= 0 || !is_int($resultId) || $resultId <= 0
                || ($result['result_column'] ?? null) !== $resultColumn
                || ($resultColumn === 'document_id' && $resultId !== $documentId)) {
                throw new RuntimeException('MULTIPART_RESOURCE_RESULT_INVALID');
            }
            $finalKey = $this->storage->buildFinalKey($documentUuid, $binaryUuid, 'ORIGINAL', $partitionDate);
            $finalAttempted = true;
            $this->storage->finalizeCreateOnly($binary['staging_key'], $finalKey, $binary['sha256'], $binary['byte_length']);
            $finalCreated = true;
            $this->binaries->insertOriginal($documentId, $binaryUuid, $finalKey, $binary);
            $this->uploads->finalize($uploadId, $requestId, $documentId);
            $this->requests->complete($requestId, $resultColumn, $resultId);
            $resource = $fetchResource($this->pdo, $resultColumn, $resultId);
            if (!is_array($resource) || !$this->pdo->inTransaction()) {
                throw new RuntimeException('MULTIPART_RESOURCE_FETCH_INVALID');
            }
            $commitAttempted = true;
            $this->commit();
        } catch (Throwable $error) {
            $rollbackConfirmed = $this->rollbackConfirmed();
            $state = 'STAGED';
            $code = 'MULTIPART_PRECOMMIT_FAILED';
            if ($finalCreated) {
                $state = 'RECONCILIATION_REQUIRED';
                $code = 'MULTIPART_COMMIT_OUTCOME_UNKNOWN';
                // Never quarantine potentially committed content after an ambiguous COMMIT.
                if ($rollbackConfirmed) {
                    try {
                        $quarantine = $this->storage->quarantine($finalKey);
                        if (!$quarantine['cleanup_pending'] && !$this->storage->stat($finalKey)['exists']) {
                            $state = 'ORPHANED';
                            $code = 'MULTIPART_FINAL_QUARANTINED';
                        } else {
                            $code = 'MULTIPART_QUARANTINE_UNCONFIRMED';
                        }
                    } catch (Throwable) {
                        $code = 'MULTIPART_QUARANTINE_FAILED';
                    }
                }
            } elseif ($finalAttempted || $commitAttempted) {
                $state = 'RECONCILIATION_REQUIRED';
                $code = 'MULTIPART_FINALIZATION_FAILED';
            }
            $this->recordRecovery($uploadId, $state, $code);
            if ($error instanceof PDOException) {
                throw new RuntimeException('MULTIPART_DATABASE_COORDINATION_FAILED', 0, $error);
            }
            throw $error;
        }
        // Outside the pre-commit catch: cleanup failure cannot roll back success.
        $cleanupPending = !$this->cleanupCommittedStaging($uploadId, $binary['staging_key']);
        return array_merge($resource, ['_idempotency_replay' => false, 'cleanup_pending' => $cleanupPending]);
    }

    private function replay(array $semantic, string $key, string $hash, string $resultColumn,
        string $uploadId, array $binary, callable $fetchResource): array
    {
        try {
            $this->begin();
            $replay = $this->requests->replay($semantic['operation'], $semantic['doctor_id'],
                $semantic['context_type'], $semantic['context_id'], $key, $hash);
            $resultId = (int)($replay[$resultColumn] ?? 0);
            if ($resultId <= 0) {
                throw new ClinicalIdempotencyException('IDEMPOTENCY_RESULT_NOT_READY', 'Committed result reference missing', 409);
            }
            $resource = $fetchResource($this->pdo, $resultColumn, $resultId);
            if (!is_array($resource) || !$this->pdo->inTransaction()) {
                throw new RuntimeException('MULTIPART_RESOURCE_FETCH_INVALID');
            }
            $this->commit();
        } catch (Throwable $error) {
            $rolledBack = $this->rollbackConfirmed();
            if ($rolledBack && $error instanceof ClinicalIdempotencyException && $error->errorCode === 'IDEMPOTENCY_KEY_REUSED') {
                $this->cleanupRedundantStaging($uploadId, $binary['staging_key']);
            }
            throw $error;
        }
        $cleaned = $this->cleanupRedundantStaging($uploadId, $binary['staging_key']);
        return array_merge($resource, ['_idempotency_replay' => true, 'cleanup_pending' => !$cleaned]);
    }

    private function cleanupCommittedStaging(string $uploadId, string $stagingKey): bool
    {
        try {
            $this->storage->deleteUncommitted($stagingKey);
            return true;
        } catch (Throwable) {
            $this->recordRecovery($uploadId, 'RECONCILIATION_REQUIRED', 'MULTIPART_POSTCOMMIT_CLEANUP_FAILED');
            return false;
        }
    }

    private function cleanupRedundantStaging(string $uploadId, string $stagingKey): bool
    {
        try {
            $this->storage->deleteUncommitted($stagingKey);
            $this->begin();
            $this->uploads->deleteRedundantStaged($uploadId);
            $this->commit();
            return true;
        } catch (Throwable) {
            $this->rollbackConfirmed();
            $this->recordRecovery($uploadId, 'RECONCILIATION_REQUIRED', 'MULTIPART_REDUNDANT_CLEANUP_FAILED');
            return false;
        }
    }

    /** Best effort bounded telemetry; failures never destroy storage or imply success. */
    private function recordRecovery(string $uploadId, string $state, string $code): void
    {
        try {
            if ($this->pdo->inTransaction()) {
                return;
            }
            $this->begin();
            $this->uploads->recordRecovery($uploadId, $state, $code);
            $this->commit();
        } catch (Throwable) {
            $this->rollbackConfirmed();
        }
    }

    private function begin(): void
    {
        if (!$this->pdo->beginTransaction()) {
            throw new RuntimeException('MULTIPART_TRANSACTION_BEGIN_FAILED');
        }
    }

    private function commit(): void
    {
        if (!$this->pdo->commit()) {
            throw new RuntimeException('MULTIPART_TRANSACTION_COMMIT_FAILED');
        }
    }

    private function rollbackConfirmed(): bool
    {
        try {
            return $this->pdo->inTransaction() && $this->pdo->rollBack();
        } catch (Throwable) {
            return false;
        }
    }
}
