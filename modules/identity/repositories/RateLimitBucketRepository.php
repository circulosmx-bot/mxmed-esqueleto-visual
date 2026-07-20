<?php
declare(strict_types=1);

namespace Identity\Repositories;

use PDO;

final class RateLimitBucketRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array{attempts_count:int,blocked_until:?string}|null */
    public function findForUpdate(string $operation, string $dimension, string $hash, string $windowStartedAt): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT attempts_count, blocked_until FROM auth_rate_limit_buckets
             WHERE operation_code = :operation AND dimension_code = :dimension
               AND dimension_key_hash = :key_hash AND window_started_at = :window_start FOR UPDATE'
        );
        $stmt->execute([':operation' => $operation, ':dimension' => $dimension, ':key_hash' => $hash, ':window_start' => $windowStartedAt]);
        $row = $stmt->fetch();
        return is_array($row) ? ['attempts_count' => (int)$row['attempts_count'], 'blocked_until' => $row['blocked_until'] !== null ? (string)$row['blocked_until'] : null] : null;
    }

    public function create(string $bucketId, string $operation, string $dimension, string $hash, string $windowStartedAt, string $createdAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_rate_limit_buckets (bucket_id, operation_code, dimension_code, dimension_key_hash, window_started_at, attempts_count, created_at, updated_at)
             VALUES (:bucket_id, :operation, :dimension, :key_hash, :window_start, 0, :created_at, :created_at)'
        );
        $stmt->execute([':bucket_id' => $bucketId, ':operation' => $operation, ':dimension' => $dimension, ':key_hash' => $hash, ':window_start' => $windowStartedAt, ':created_at' => $createdAt]);
    }

    public function update(string $operation, string $dimension, string $hash, string $windowStartedAt, int $attempts, ?string $blockedUntil): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE auth_rate_limit_buckets SET attempts_count = :attempts, blocked_until = :blocked_until, updated_at = CURRENT_TIMESTAMP
             WHERE operation_code = :operation AND dimension_code = :dimension AND dimension_key_hash = :key_hash AND window_started_at = :window_start'
        );
        $stmt->execute([':attempts' => $attempts, ':blocked_until' => $blockedUntil, ':operation' => $operation, ':dimension' => $dimension, ':key_hash' => $hash, ':window_start' => $windowStartedAt]);
    }

    public function reset(string $operation, string $dimension, string $hash, string $windowStartedAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE auth_rate_limit_buckets SET attempts_count = 0, blocked_until = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE operation_code = :operation AND dimension_code = :dimension AND dimension_key_hash = :key_hash AND window_started_at = :window_start'
        );
        $stmt->execute([':operation' => $operation, ':dimension' => $dimension, ':key_hash' => $hash, ':window_start' => $windowStartedAt]);
    }
}
