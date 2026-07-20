<?php
declare(strict_types=1);

namespace Identity\Repositories;

use Identity\Contracts\IdentityAccount;
use PDO;
use PDOException;

final class IdentityAccountRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(IdentityAccount $account): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO auth_accounts (account_id, email_address, email_normalized, status, email_verified_at)
                 VALUES (:account_id, :email_address, :email_normalized, :status, :email_verified_at)'
            );
            $stmt->execute([
                ':account_id' => $account->accountId(),
                ':email_address' => $account->emailAddress(),
                ':email_normalized' => $account->emailNormalized(),
                ':status' => $account->status(),
                ':email_verified_at' => $account->emailVerifiedAt(),
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('identity_account_create_failed', 0, $e);
        }
    }

    public function existsByNormalizedEmail(string $emailNormalized): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM auth_accounts WHERE email_normalized = :email LIMIT 1');
        $stmt->execute([':email' => $emailNormalized]);
        return $stmt->fetchColumn() !== false;
    }
}
