<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\AccountStatus;
use Identity\Contracts\Clock;
use Identity\Contracts\IdentityAccount;
use Identity\Contracts\IdentityNotificationPort;
use Identity\Contracts\NotificationMessage;
use Identity\Contracts\OneTimeTokenPurpose;
use Identity\Contracts\PasswordHash;
use Identity\Contracts\PasswordPolicy;
use Identity\Contracts\PasswordResetDecision;
use Identity\Contracts\RateLimitOperation;
use Identity\Contracts\ReasonCode;
use Identity\Contracts\RecoveryRequestDecision;
use Identity\Repositories\AccountCredentialRepository;
use Identity\Repositories\IdentityAccountRepository;
use Identity\Repositories\OneTimeTokenRepository;

final class RecoveryService
{
    public function __construct(
        private \PDO $pdo,
        private IdentityAccountRepository $accounts,
        private AccountCredentialRepository $credentials,
        private OneTimeTokenRepository $tokens,
        private RateLimitService $rateLimits,
        private IdentityNotificationPort $notifications,
        private Clock $clock
    ) {}

    /** @param array<string, string|null> $dimensions */
    public function request(string $email, array $dimensions): RecoveryRequestDecision
    {
        try { $normalized = IdentityAccount::normalizeEmail($email); } catch (\Throwable) { return new RecoveryRequestDecision(ReasonCode::ALLOWED); }
        $limit = $this->rateLimits->consume(RateLimitOperation::RECOVERY_REQUEST, $dimensions + ['identity' => $normalized]);
        if (!$limit->allowed()) return new RecoveryRequestDecision($limit->reasonCode());
        $account = $this->accounts->findByNormalizedEmail($normalized);
        if (!is_array($account) || (string)$account['status'] !== AccountStatus::ACTIVE) return new RecoveryRequestDecision(ReasonCode::ALLOWED);
        $token = OneTimeTokenCodec::issue();
        $now = $this->clock->now();
        $issuedAt = $now->format('Y-m-d H:i:s');
        $expiresAt = $now->modify('+30 minutes')->format('Y-m-d H:i:s');
        try {
            $this->pdo->beginTransaction();
            $this->tokens->invalidateForPurpose((string)$account['account_id'], OneTimeTokenPurpose::PASSWORD_RECOVERY, $issuedAt);
            $this->tokens->issue(IdentityIdGenerator::tokenId(), (string)$account['account_id'], OneTimeTokenPurpose::PASSWORD_RECOVERY, OneTimeTokenCodec::hash($token), $issuedAt, $expiresAt);
            $this->pdo->commit();
            $this->notifications->send(new NotificationMessage(OneTimeTokenPurpose::PASSWORD_RECOVERY, (string)$account['email_address'], $token, $expiresAt));
            return new RecoveryRequestDecision(ReasonCode::ALLOWED);
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return new RecoveryRequestDecision(ReasonCode::NOTIFICATION_UNAVAILABLE);
        }
    }

    /** @param array<string, string|null> $dimensions */
    public function reset(string $rawToken, string $newPassword, array $dimensions): PasswordResetDecision
    {
        $limit = $this->rateLimits->consume(RateLimitOperation::PASSWORD_RESET, $dimensions);
        if (!$limit->allowed()) return new PasswordResetDecision(false, $limit->reasonCode());
        $tokenLimit = $this->rateLimits->consume(RateLimitOperation::TOKEN_CONSUME, $dimensions);
        if (!$tokenLimit->allowed()) return new PasswordResetDecision(false, $tokenLimit->reasonCode());
        try { $hash = OneTimeTokenCodec::hash($rawToken); } catch (\Throwable) { return new PasswordResetDecision(false, ReasonCode::TOKEN_INVALID); }
        $row = $this->tokens->findByHashAndPurpose($hash, OneTimeTokenPurpose::PASSWORD_RECOVERY);
        $now = $this->clock->now();
        if (!is_array($row)) return new PasswordResetDecision(false, ReasonCode::TOKEN_INVALID);
        if ($row['consumed_at'] !== null) return new PasswordResetDecision(false, ReasonCode::TOKEN_CONSUMED);
        if ($row['invalidated_at'] !== null) return new PasswordResetDecision(false, ReasonCode::TOKEN_INVALIDATED);
        if ((string)$row['expires_at'] <= $now->format('Y-m-d H:i:s')) return new PasswordResetDecision(false, ReasonCode::TOKEN_EXPIRED);
        $account = $this->accounts->findById((string)$row['account_id']);
        if (!is_array($account)) return new PasswordResetDecision(false, ReasonCode::TOKEN_INVALID);
        if (in_array((string)$account['status'], [AccountStatus::BLOCKED, AccountStatus::DISABLED], true)) return new PasswordResetDecision(false, ReasonCode::ACCOUNT_NOT_ACTIVE);
        try { PasswordPolicy::assertValid($newPassword, (string)$account['email_normalized']); } catch (\Throwable) { return new PasswordResetDecision(false, ReasonCode::INVALID_INPUT); }
        try {
            $this->pdo->beginTransaction();
            $newHash = PasswordHash::hash($newPassword);
            $version = $this->credentials->replacePassword((string)$row['account_id'], $newHash, $now->format('Y-m-d H:i:s'));
            if (!$this->tokens->consume((string)$row['token_id'], $now->format('Y-m-d H:i:s'))) throw new \RuntimeException(ReasonCode::TOKEN_CONSUMED);
            $this->tokens->invalidateForPurpose((string)$row['account_id'], OneTimeTokenPurpose::PASSWORD_RECOVERY, $now->format('Y-m-d H:i:s'));
            $this->pdo->commit();
            return new PasswordResetDecision(true, ReasonCode::ALLOWED, $version);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return new PasswordResetDecision(false, ReasonCode::isKnown($e->getMessage()) ? $e->getMessage() : ReasonCode::STORAGE_UNAVAILABLE);
        }
    }
}
