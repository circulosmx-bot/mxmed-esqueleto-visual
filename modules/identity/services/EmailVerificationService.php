<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\AccountStatus;
use Identity\Contracts\Clock;
use Identity\Contracts\IdentityNotificationPort;
use Identity\Contracts\NotificationMessage;
use Identity\Contracts\OneTimeTokenPurpose;
use Identity\Contracts\RateLimitOperation;
use Identity\Contracts\ReasonCode;
use Identity\Contracts\EmailVerificationDecision;
use Identity\Repositories\AccountConsentRepository;
use Identity\Repositories\IdentityAccountRepository;
use Identity\Repositories\OneTimeTokenRepository;

final class EmailVerificationService
{
    public function __construct(
        private \PDO $pdo,
        private IdentityAccountRepository $accounts,
        private AccountConsentRepository $consents,
        private OneTimeTokenRepository $tokens,
        private RateLimitService $rateLimits,
        private IdentityNotificationPort $notifications,
        private Clock $clock
    ) {}

    /** @param array<string, string|null> $dimensions */
    public function verify(string $rawToken, array $dimensions): EmailVerificationDecision
    {
        $limit = $this->rateLimits->consume(RateLimitOperation::TOKEN_CONSUME, $dimensions);
        if (!$limit->allowed()) return new EmailVerificationDecision(false, $limit->reasonCode());
        try { $hash = OneTimeTokenCodec::hash($rawToken); } catch (\Throwable) { return new EmailVerificationDecision(false, ReasonCode::TOKEN_INVALID); }
        $row = $this->tokens->findByHashAndPurpose($hash, OneTimeTokenPurpose::EMAIL_VERIFICATION);
        $now = $this->clock->now();
        if (!is_array($row)) return new EmailVerificationDecision(false, ReasonCode::TOKEN_INVALID);
        if ($row['consumed_at'] !== null) return new EmailVerificationDecision(false, ReasonCode::TOKEN_CONSUMED);
        if ($row['invalidated_at'] !== null) return new EmailVerificationDecision(false, ReasonCode::TOKEN_INVALIDATED);
        if ((string)$row['expires_at'] <= $now->format('Y-m-d H:i:s')) return new EmailVerificationDecision(false, ReasonCode::TOKEN_EXPIRED);
        try {
            $this->pdo->beginTransaction();
            $account = $this->accounts->findById((string)$row['account_id']);
            if (!is_array($account)) throw new \RuntimeException('account_not_found');
            if (!$this->consents->hasRequiredForAccount((string)$row['account_id'])) throw new \RuntimeException(ReasonCode::CONSENT_MISSING);
            if ((string)$account['status'] === AccountStatus::BLOCKED) throw new \RuntimeException(ReasonCode::ACCOUNT_BLOCKED);
            if ((string)$account['status'] === AccountStatus::DISABLED) throw new \RuntimeException(ReasonCode::ACCOUNT_DISABLED);
            if ((string)$account['status'] !== AccountStatus::PENDING_VERIFICATION) throw new \RuntimeException(ReasonCode::VERIFICATION_REQUIRED);
            if (!$this->accounts->activateAfterVerification((string)$row['account_id'], $now->format('Y-m-d H:i:s'))) throw new \RuntimeException('activation_conflict');
            if (!$this->tokens->consume((string)$row['token_id'], $now->format('Y-m-d H:i:s'))) throw new \RuntimeException(ReasonCode::TOKEN_CONSUMED);
            $this->tokens->invalidateForPurpose((string)$row['account_id'], OneTimeTokenPurpose::EMAIL_VERIFICATION, $now->format('Y-m-d H:i:s'));
            $this->pdo->commit();
            return new EmailVerificationDecision(true, ReasonCode::ALLOWED);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return new EmailVerificationDecision(false, ReasonCode::isKnown($e->getMessage()) ? $e->getMessage() : ReasonCode::STORAGE_UNAVAILABLE);
        }
    }

    /** @param array<string, string|null> $dimensions */
    public function resend(string $email, array $dimensions): EmailVerificationDecision
    {
        try { $normalized = \Identity\Contracts\IdentityAccount::normalizeEmail($email); } catch (\Throwable) { return new EmailVerificationDecision(true, ReasonCode::ALLOWED); }
        $limit = $this->rateLimits->consume(RateLimitOperation::EMAIL_VERIFICATION_RESEND, $dimensions + ['identity' => $normalized]);
        if (!$limit->allowed()) return new EmailVerificationDecision(true, $limit->reasonCode());
        $account = $this->accounts->findByNormalizedEmail($normalized);
        if (!is_array($account) || (string)$account['status'] !== AccountStatus::PENDING_VERIFICATION) return new EmailVerificationDecision(true, ReasonCode::ALLOWED);
        $token = OneTimeTokenCodec::issue();
        $now = $this->clock->now();
        $issuedAt = $now->format('Y-m-d H:i:s');
        $expiresAt = $now->modify('+24 hours')->format('Y-m-d H:i:s');
        try {
            $this->pdo->beginTransaction();
            $this->tokens->invalidateForPurpose((string)$account['account_id'], OneTimeTokenPurpose::EMAIL_VERIFICATION, $issuedAt);
            $this->tokens->issue(IdentityIdGenerator::tokenId(), (string)$account['account_id'], OneTimeTokenPurpose::EMAIL_VERIFICATION, OneTimeTokenCodec::hash($token), $issuedAt, $expiresAt);
            $this->pdo->commit();
            $this->notifications->send(new NotificationMessage(OneTimeTokenPurpose::EMAIL_VERIFICATION, (string)$account['email_address'], $token, $expiresAt));
            return new EmailVerificationDecision(true, ReasonCode::ALLOWED);
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return new EmailVerificationDecision(true, ReasonCode::NOTIFICATION_UNAVAILABLE);
        }
    }
}
