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

    /** @return array<string, mixed>|null */
    public function findByNormalizedEmail(string $emailNormalized): ?array
    {
        $stmt = $this->pdo->prepare('SELECT account_id, email_address, email_normalized, status, email_verified_at FROM auth_accounts WHERE email_normalized = :email LIMIT 1');
        $stmt->execute([':email' => $emailNormalized]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(string $accountId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT account_id, email_address, email_normalized, status, email_verified_at FROM auth_accounts WHERE account_id = :account_id LIMIT 1');
        $stmt->execute([':account_id' => $accountId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function activateAfterVerification(string $accountId, string $verifiedAt): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE auth_accounts SET status = 'active', email_verified_at = :verified_at, updated_at = CURRENT_TIMESTAMP
             WHERE account_id = :account_id AND status = 'pending_verification' AND email_verified_at IS NULL"
        );
        $stmt->execute([':verified_at' => $verifiedAt, ':account_id' => $accountId]);
        return $stmt->rowCount() === 1;
    }

    public function requiredConsentsPresent(string $accountId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT document_type) FROM auth_account_consents
             WHERE account_id = :account_id AND document_type IN ('terms','privacy_notice')"
        );
        $stmt->execute([':account_id' => $accountId]);
        return (int)$stmt->fetchColumn() === 2;
    }
}
