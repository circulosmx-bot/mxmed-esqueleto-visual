<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use InvalidArgumentException;
use PDO;

final class SubscriptionEntityWriteLockService
{
    private const LOCK_PREFIX = 'mxmed:subscriptions';
    private const LOCK_OPERATION = 'create';
    private const MAX_LOCK_NAME_LENGTH = 64;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function acquire(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string
    {
        $lockName = $this->lockName($entityType, $entityId);
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

    private function lockName(string $entityType, string $entityId): string
    {
        $type = strtolower(trim($entityType));
        $id = trim($entityId);
        if ($type === '' || $id === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $type . ':' . $id)) {
            throw new InvalidArgumentException('invalid subscription write lock scope');
        }

        $lockName = self::LOCK_PREFIX . ':' . $type . ':' . $id . ':' . self::LOCK_OPERATION;
        if (strlen($lockName) <= self::MAX_LOCK_NAME_LENGTH) {
            return $lockName;
        }

        return self::LOCK_PREFIX . ':' . $type . ':' . substr(hash('sha256', $id), 0, 24) . ':' . self::LOCK_OPERATION;
    }
}
