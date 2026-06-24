<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use InvalidArgumentException;
use PDO;

final class SubscriptionEntityWriteLockService
{
    private const LOCK_PREFIX = 'mxmed:subscriptions';
    private const LOCK_OPERATION = 'create';
    private const CHECKOUT_CREATE_OPERATION = 'checkout_create';
    private const MAX_LOCK_NAME_LENGTH = 64;
    public const ERROR_CHECKOUT_LOCK_TIMEOUT = 'subscription_checkout_lock_timeout';
    public const ERROR_LOCK_PURPOSE_INVALID = 'subscription_lock_purpose_invalid';
    public const ERROR_LOCK_ACQUIRE_FAILED = 'subscription_lock_acquire_failed';
    public const ERROR_LOCK_RELEASE_FAILED = 'subscription_lock_release_failed';
    private const ALLOWED_OPERATIONS = [
        self::LOCK_OPERATION,
        self::CHECKOUT_CREATE_OPERATION,
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function acquire(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string
    {
        return $this->acquireForPurpose($entityType, $entityId, self::LOCK_OPERATION, $timeoutSeconds);
    }

    public function acquireCheckoutCreate(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string
    {
        return $this->acquireForPurpose($entityType, $entityId, self::CHECKOUT_CREATE_OPERATION, $timeoutSeconds);
    }

    public function acquireForPurpose(
        string $entityType,
        string $entityId,
        string $purpose,
        int $timeoutSeconds = 2
    ): ?string
    {
        $lockName = $this->buildLockName($entityType, $entityId, $purpose);
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, :timeout_seconds) AS lock_result');
        $stmt->execute([
            'lock_name' => $lockName,
            'timeout_seconds' => max(0, $timeoutSeconds),
        ]);
        $result = $stmt->fetchColumn();

        return (string)$result === '1' ? $lockName : null;
    }

    public function release(?string $lockName): void
    {
        if ($lockName === null || $lockName === '') {
            return;
        }

        $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => $lockName]);
    }

    public function buildLockName(string $entityType, string $entityId, string $purpose): string
    {
        $type = strtolower(trim($entityType));
        $id = trim($entityId);
        if ($type === '' || $id === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $type . ':' . $id)) {
            throw new InvalidArgumentException('invalid subscription write lock scope');
        }

        $operation = trim($purpose);
        if (!in_array($operation, self::ALLOWED_OPERATIONS, true)) {
            throw new InvalidArgumentException(self::ERROR_LOCK_PURPOSE_INVALID);
        }

        $lockName = self::LOCK_PREFIX . ':' . $type . ':' . $id . ':' . $operation;
        if (strlen($lockName) <= self::MAX_LOCK_NAME_LENGTH) {
            return $lockName;
        }

        return self::LOCK_PREFIX . ':' . $type . ':' . substr(hash('sha256', $id), 0, 24) . ':' . $operation;
    }
}
