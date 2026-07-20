<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\AccountStatus;
use Identity\Contracts\Clock;
use Identity\Contracts\ConsentDocumentType;
use Identity\Contracts\IdentityAccount;
use Identity\Contracts\IdentityNotificationPort;
use Identity\Contracts\NotificationMessage;
use Identity\Contracts\OneTimeTokenPurpose;
use Identity\Contracts\PasswordHash;
use Identity\Contracts\PasswordPolicy;
use Identity\Contracts\RateLimitOperation;
use Identity\Contracts\ReasonCode;
use Identity\Contracts\RegistrationDecision;
use Identity\Repositories\AccountConsentRepository;
use Identity\Repositories\AccountCredentialRepository;
use Identity\Repositories\IdentityAccountRepository;
use Identity\Repositories\OneTimeTokenRepository;
use PDO;

final class RegistrationService
{
    public function __construct(
        private PDO $pdo,
        private IdentityAccountRepository $accounts,
        private AccountCredentialRepository $credentials,
        private AccountConsentRepository $consents,
        private OneTimeTokenRepository $tokens,
        private RateLimitService $rateLimits,
        private IdentityNotificationPort $notifications,
        private Clock $clock
    ) {}

    /** @param array<string, mixed> $input @param array<string, string|null> $dimensions */
    public function register(array $input, array $dimensions): RegistrationDecision
    {
        $email = trim((string)($input['email'] ?? ''));
        $password = $input['password'] ?? null;
        $termsVersion = trim((string)($input['terms_version'] ?? ''));
        $privacyVersion = trim((string)($input['privacy_notice_version'] ?? ''));
        if (!is_string($password) || $email === '' || $termsVersion === '' || $privacyVersion === ('')) {
            return new RegistrationDecision(false, ReasonCode::INVALID_INPUT);
        }
        try {
            $normalizedEmail = IdentityAccount::normalizeEmail($email);
            PasswordPolicy::assertValid($password, $normalizedEmail);
            if (($input['terms_accepted'] ?? false) !== true || ($input['privacy_notice_accepted'] ?? false) !== true) {
                return new RegistrationDecision(false, ReasonCode::CONSENT_MISSING);
            }
        } catch (\Throwable) {
            return new RegistrationDecision(false, ReasonCode::INVALID_INPUT);
        }
        $limit = $this->rateLimits->consume(RateLimitOperation::REGISTRATION, $dimensions + ['identity' => $normalizedEmail]);
        if (!$limit->allowed()) return new RegistrationDecision(false, $limit->reasonCode());
        $existing = $this->accounts->findByNormalizedEmail($normalizedEmail);
        if (is_array($existing)) return new RegistrationDecision(true, ReasonCode::DUPLICATE_ACCOUNT);

        $accountId = IdentityIdGenerator::accountId();
        $token = OneTimeTokenCodec::issue();
        $now = $this->clock->now();
        $issuedAt = $now->format('Y-m-d H:i:s');
        $expiresAt = $now->modify('+24 hours')->format('Y-m-d H:i:s');
        try {
            $this->pdo->beginTransaction();
            $account = new IdentityAccount($accountId, $email, AccountStatus::PENDING_VERIFICATION);
            $this->accounts->create($account);
            $this->credentials->create($accountId, PasswordHash::hash($password), $issuedAt);
            $this->consents->record(IdentityIdGenerator::consentId(), $accountId, ConsentDocumentType::TERMS, $termsVersion, $issuedAt, ['source' => 'internal_registration']);
            $this->consents->record(IdentityIdGenerator::consentId(), $accountId, ConsentDocumentType::PRIVACY_NOTICE, $privacyVersion, $issuedAt, ['source' => 'internal_registration']);
            $this->tokens->issue(IdentityIdGenerator::tokenId(), $accountId, OneTimeTokenPurpose::EMAIL_VERIFICATION, OneTimeTokenCodec::hash($token), $issuedAt, $expiresAt);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return new RegistrationDecision(false, ReasonCode::STORAGE_UNAVAILABLE);
        }
        try {
            $this->notifications->send(new NotificationMessage(OneTimeTokenPurpose::EMAIL_VERIFICATION, $email, $token, $expiresAt));
        } catch (\Throwable) {
            try {
                $this->pdo->beginTransaction();
                $row = $this->tokens->findByHashAndPurpose(OneTimeTokenCodec::hash($token), OneTimeTokenPurpose::EMAIL_VERIFICATION);
                if (is_array($row)) $this->tokens->invalidateToken((string)$row['token_id'], $issuedAt);
                $this->pdo->commit();
            } catch (\Throwable) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            }
            return new RegistrationDecision(false, ReasonCode::NOTIFICATION_UNAVAILABLE, $accountId);
        }
        return new RegistrationDecision(true, ReasonCode::ALLOWED, $accountId);
    }
}
