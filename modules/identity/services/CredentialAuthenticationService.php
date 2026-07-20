<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticationDecision;
use Identity\Contracts\AuthenticationPrincipalCandidate;
use Identity\Contracts\Clock;
use Identity\Contracts\IdentityAccount;
use Identity\Contracts\PasswordHash;
use Identity\Contracts\RateLimitOperation;
use Identity\Contracts\ReasonCode;
use Identity\Repositories\AccountCredentialRepository;
use Identity\Repositories\IdentityAccountRepository;

final class CredentialAuthenticationService
{
    public function __construct(
        private IdentityAccountRepository $accounts,
        private AccountCredentialRepository $credentials,
        private RateLimitService $rateLimits,
        private Clock $clock
    ) {}

    /** @param array<string, string|null> $dimensions */
    public function authenticate(string $email, string $password, array $dimensions): AuthenticationDecision
    {
        try { $normalized = IdentityAccount::normalizeEmail($email); } catch (\Throwable) {
            $normalized = '__invalid_identifier__';
        }
        $limit = $this->rateLimits->consume(RateLimitOperation::CREDENTIAL_CHECK, $dimensions + ['identity' => $normalized]);
        if (!$limit->allowed()) return AuthenticationDecision::denied($limit->reasonCode());
        if ($normalized === '__invalid_identifier__') {
            PasswordHash::verify($password, PasswordHash::dummyHash());
            return AuthenticationDecision::denied(ReasonCode::INVALID_CREDENTIALS);
        }
        $account = $this->accounts->findByNormalizedEmail($normalized);
        $credential = is_array($account) ? $this->credentials->findByAccountId((string)$account['account_id']) : null;
        $hash = is_array($credential) ? (string)$credential['password_hash'] : PasswordHash::dummyHash();
        $passwordMatches = PasswordHash::verify($password, $hash);
        if (!is_array($account) || !is_array($credential) || !$passwordMatches) return AuthenticationDecision::denied(ReasonCode::INVALID_CREDENTIALS);
        $status = (string)$account['status'];
        if ($status === AccountStatus::PENDING_VERIFICATION) return AuthenticationDecision::denied(ReasonCode::ACCOUNT_NOT_ACTIVE);
        if ($status === AccountStatus::BLOCKED) return AuthenticationDecision::denied(ReasonCode::ACCOUNT_BLOCKED);
        if ($status === AccountStatus::DISABLED) return AuthenticationDecision::denied(ReasonCode::ACCOUNT_DISABLED);
        if ($status !== AccountStatus::ACTIVE) return AuthenticationDecision::denied(ReasonCode::INVALID_CREDENTIALS);
        $this->rateLimits->clear(RateLimitOperation::CREDENTIAL_CHECK, $dimensions + ['identity' => $normalized]);
        return AuthenticationDecision::success(new AuthenticationPrincipalCandidate(
            (string)$account['account_id'], (int)$credential['credential_version'], $status, $this->clock->now()->format('Y-m-d H:i:s')
        ));
    }
}
