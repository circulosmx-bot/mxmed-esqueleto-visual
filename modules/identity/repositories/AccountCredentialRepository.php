<?php
declare(strict_types=1);

namespace Identity\Repositories;

use PDO;
use PDOException;

final class AccountCredentialRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(string $accountId, string $passwordHash, string $changedAt): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO auth_account_credentials (account_id, password_hash, password_changed_at, credential_version)
                 VALUES (:account_id, :password_hash, :password_changed_at, 1)'
            );
            $stmt->execute([':account_id' => $accountId, ':password_hash' => $passwordHash, ':password_changed_at' => $changedAt]);
        } catch (PDOException $e) {
            throw new \RuntimeException('identity_credential_create_failed', 0, $e);
        }
    }

    /** @return array<string, mixed>|null */
    public function findByAccountId(string $accountId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT account_id, password_hash, password_changed_at, credential_version FROM auth_account_credentials WHERE account_id = :account_id LIMIT 1');
        $stmt->execute([':account_id' => $accountId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function replacePassword(string $accountId, string $passwordHash, string $changedAt): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE auth_account_credentials
             SET password_hash = :password_hash, password_changed_at = :changed_at,
                 credential_version = credential_version + 1, updated_at = CURRENT_TIMESTAMP
             WHERE account_id = :account_id'
        );
        $stmt->execute([':password_hash' => $passwordHash, ':changed_at' => $changedAt, ':account_id' => $accountId]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('identity_credential_update_failed');
        $row = $this->findByAccountId($accountId);
        if (!is_array($row)) throw new \RuntimeException('identity_credential_update_failed');
        return (int)$row['credential_version'];
    }
}
