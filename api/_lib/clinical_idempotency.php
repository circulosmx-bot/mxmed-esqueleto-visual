<?php
declare(strict_types=1);

final class ClinicalIdempotencyException extends RuntimeException
{
    public string $errorCode;
    public int $httpStatus;

    public function __construct(string $errorCode, string $message, int $httpStatus = 409)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }
}

function clinical_idempotency_canonicalization_version(): int
{
    return 1;
}

/** @return mixed */
function clinical_idempotency_normalize_value($value)
{
    if (is_array($value)) {
        if (array_is_list($value)) {
            return array_map('clinical_idempotency_normalize_value', $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = clinical_idempotency_normalize_value($item);
        }
        return $value;
    }
    if (is_string($value) && class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
        return is_string($normalized) ? $normalized : $value;
    }
    if (is_float($value)) {
        return (float)sprintf('%.14g', $value);
    }
    return $value;
}

function clinical_idempotency_request_hash(array $semanticRequest, int $version = 1): string
{
    if ($version !== clinical_idempotency_canonicalization_version()) {
        throw new InvalidArgumentException('UNKNOWN_CANONICALIZATION_VERSION');
    }
    $canonical = clinical_idempotency_normalize_value($semanticRequest);
    $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    if (!is_string($json)) {
        throw new InvalidArgumentException('IDEMPOTENCY_PAYLOAD_NOT_CANONICALIZABLE');
    }
    return hash('sha256', $json);
}

function clinical_idempotency_key_validate(string $key): string
{
    $key = trim($key);
    if ($key === '' || strlen($key) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/', $key) !== 1) {
        throw new ClinicalIdempotencyException('IDEMPOTENCY_KEY_INVALID', 'Idempotency-Key is required and must be a stable opaque token', 400);
    }
    return $key;
}

function clinical_idempotency_operation_validate(string $operationType): string
{
    $operationType = strtoupper(trim($operationType));
    $allowed = [
        'CREATE_OBSERVATION',
        'CREATE_ENCOUNTER_DOCUMENT',
        'CREATE_POST_ENCOUNTER_RESULT',
        'CREATE_ENCOUNTER_AMENDMENT',
        'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT',
    ];
    if (!in_array($operationType, $allowed, true)) {
        throw new InvalidArgumentException('IDEMPOTENCY_OPERATION_UNSUPPORTED');
    }
    return $operationType;
}

final class ClinicalIdempotencyRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Must run inside the same transaction that creates the clinical resource.
     * A duplicate-key caller must roll back and invoke replay() in a fresh transaction.
     */
    public function claim(string $operationType, string $doctorId, string $contextType, string $contextId, string $key, string $requestHash, string $actorUserId): int
    {
        $operationType = clinical_idempotency_operation_validate($operationType);
        $stmt = $this->pdo->prepare('INSERT INTO clinical_idempotency_requests
            (operation_type, doctor_id, context_type, context_id, idempotency_key, canonicalization_version, request_hash, actor_user_id, created_at)
            VALUES (:operation_type, :doctor_id, :context_type, :context_id, :idempotency_key, :canonicalization_version, :request_hash, :actor_user_id, UTC_TIMESTAMP())');
        $stmt->execute([
            ':operation_type' => $operationType,
            ':doctor_id' => $doctorId,
            ':context_type' => $contextType,
            ':context_id' => $contextId,
            ':idempotency_key' => clinical_idempotency_key_validate($key),
            ':canonicalization_version' => clinical_idempotency_canonicalization_version(),
            ':request_hash' => $requestHash,
            ':actor_user_id' => $actorUserId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function complete(int $requestId, string $resultColumn, int $resourceId): void
    {
        $allowed = ['observation_id', 'document_id', 'encounter_amendment_id', 'document_revision_id'];
        if (!in_array($resultColumn, $allowed, true) || $resourceId <= 0) {
            throw new InvalidArgumentException('INVALID_IDEMPOTENCY_RESULT_REFERENCE');
        }
        $sql = 'UPDATE clinical_idempotency_requests SET `' . $resultColumn . '` = :resource_id, committed_at = UTC_TIMESTAMP() WHERE request_id = :request_id AND committed_at IS NULL';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':resource_id' => $resourceId, ':request_id' => $requestId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('IDEMPOTENCY_COMPLETION_CONFLICT');
        }
    }

    public function replay(string $operationType, string $doctorId, string $contextType, string $contextId, string $key, string $requestHash): array
    {
        $operationType = clinical_idempotency_operation_validate($operationType);
        $stmt = $this->pdo->prepare('SELECT * FROM clinical_idempotency_requests
            WHERE operation_type = :operation_type AND doctor_id = :doctor_id AND context_type = :context_type
              AND context_id = :context_id AND idempotency_key = :idempotency_key LIMIT 1');
        $stmt->execute([
            ':operation_type' => $operationType,
            ':doctor_id' => $doctorId,
            ':context_type' => $contextType,
            ':context_id' => $contextId,
            ':idempotency_key' => clinical_idempotency_key_validate($key),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || trim((string)($row['committed_at'] ?? '')) === '') {
            throw new ClinicalIdempotencyException('IDEMPOTENCY_RESULT_NOT_READY', 'The original request has not committed', 409);
        }
        if (!hash_equals((string)$row['request_hash'], $requestHash)) {
            throw new ClinicalIdempotencyException('IDEMPOTENCY_KEY_REUSED', 'Idempotency-Key was reused with a different semantic request', 409);
        }
        return $row;
    }
}

final class ClinicalIdempotentCreateExecutor
{
    private ClinicalIdempotencyRepository $requests;

    public function __construct(private PDO $pdo)
    {
        $this->requests = new ClinicalIdempotencyRepository($pdo);
    }

    /**
     * @param callable():int $createResource Creates one resource on this PDO transaction.
     * @param callable(int):array $fetchResource Resolves the durable typed result reference.
     */
    public function execute(
        string $operationType,
        string $doctorId,
        string $contextType,
        string $contextId,
        string $idempotencyKey,
        array $semanticRequest,
        string $resultColumn,
        string $actorUserId,
        callable $createResource,
        callable $fetchResource
    ): array {
        $requestHash = clinical_idempotency_request_hash($semanticRequest);
        $this->pdo->beginTransaction();
        try {
            try {
                $requestId = $this->requests->claim(
                    $operationType,
                    $doctorId,
                    $contextType,
                    $contextId,
                    $idempotencyKey,
                    $requestHash,
                    $actorUserId
                );
            } catch (PDOException $claimError) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                if ((string)$claimError->getCode() !== '23000') {
                    throw $claimError;
                }
                $replay = $this->requests->replay(
                    $operationType,
                    $doctorId,
                    $contextType,
                    $contextId,
                    $idempotencyKey,
                    $requestHash
                );
                $resourceId = (int)($replay[$resultColumn] ?? 0);
                if ($resourceId <= 0) {
                    throw new ClinicalIdempotencyException('IDEMPOTENCY_RESULT_NOT_READY', 'Committed result reference missing', 409);
                }
                return $fetchResource($resourceId) + ['_idempotency_replay' => true];
            }
            $resourceId = (int)$createResource();
            if ($resourceId <= 0) {
                throw new RuntimeException('IDEMPOTENT_CREATE_RESULT_INVALID');
            }
            $this->requests->complete($requestId, $resultColumn, $resourceId);
            $resource = $fetchResource($resourceId);
            $this->pdo->commit();
            return $resource + ['_idempotency_replay' => false];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
