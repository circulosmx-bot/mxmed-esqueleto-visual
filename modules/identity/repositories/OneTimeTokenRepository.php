<?php
declare(strict_types=1);

namespace Identity\Repositories;

use Identity\Contracts\OneTimeTokenPurpose;
use PDO;
use PDOException;

final class OneTimeTokenRepository
{
    public function __construct(private PDO $pdo) {}

    public function invalidateForPurpose(string $accountId, string $purpose, string $invalidatedAt): void
    {
        OneTimeTokenPurpose::assertValid($purpose);
        $stmt = $this->pdo->prepare(
            'UPDATE auth_account_one_time_tokens SET invalidated_at = :invalidated_at, updated_at = CURRENT_TIMESTAMP
             WHERE account_id = :account_id AND purpose = :purpose AND consumed_at IS NULL AND invalidated_at IS NULL'
        );
        $stmt->execute([':invalidated_at' => $invalidatedAt, ':account_id' => $accountId, ':purpose' => $purpose]);
    }

    public function issue(string $tokenId, string $accountId, string $purpose, string $tokenHash, string $issuedAt, string $expiresAt): void
    {
        OneTimeTokenPurpose::assertValid($purpose);
        if (!preg_match('/^[A-Fa-f0-9]{64}$/', $tokenHash)) throw new \InvalidArgumentException('invalid_token_hash');
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO auth_account_one_time_tokens (token_id, account_id, purpose, token_hash, issued_at, expires_at)
                 VALUES (:token_id, :account_id, :purpose, :token_hash, :issued_at, :expires_at)'
            );
            $stmt->execute([
                ':token_id' => $tokenId, ':account_id' => $accountId, ':purpose' => $purpose,
                ':token_hash' => $tokenHash, ':issued_at' => $issuedAt, ':expires_at' => $expiresAt,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('identity_token_issue_failed', 0, $e);
        }
    }

    /** @return array<string, mixed>|null */
    public function findByHashAndPurpose(string $tokenHash, string $purpose): ?array
    {
        OneTimeTokenPurpose::assertValid($purpose);
        $stmt = $this->pdo->prepare(
            'SELECT token_id, account_id, purpose, token_hash, issued_at, expires_at, consumed_at, invalidated_at
             FROM auth_account_one_time_tokens WHERE token_hash = :token_hash AND purpose = :purpose LIMIT 1'
        );
        $stmt->execute([':token_hash' => $tokenHash, ':purpose' => $purpose]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function consume(string $tokenId, string $consumedAt): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE auth_account_one_time_tokens SET consumed_at = :consumed_at, updated_at = CURRENT_TIMESTAMP
             WHERE token_id = :token_id AND consumed_at IS NULL AND invalidated_at IS NULL AND expires_at > :now'
        );
        $stmt->execute([':consumed_at' => $consumedAt, ':token_id' => $tokenId, ':now' => $consumedAt]);
        return $stmt->rowCount() === 1;
    }

    public function invalidateToken(string $tokenId, string $invalidatedAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE auth_account_one_time_tokens SET invalidated_at = :invalidated_at, updated_at = CURRENT_TIMESTAMP
             WHERE token_id = :token_id AND consumed_at IS NULL AND invalidated_at IS NULL'
        );
        $stmt->execute([':invalidated_at' => $invalidatedAt, ':token_id' => $tokenId]);
    }
}
