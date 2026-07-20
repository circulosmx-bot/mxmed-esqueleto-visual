<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class AuthenticationPrincipalCandidate
{
    public function __construct(
        private string $accountId,
        private int $credentialVersion,
        private string $accountStatus,
        private string $authenticatedAt
    ) {
        CredentialVersion::assertValid($credentialVersion);
        if ($accountId === '' || $accountStatus === '' || $authenticatedAt === '') {
            throw new \InvalidArgumentException('invalid_authentication_principal_candidate');
        }
    }

    public function accountId(): string { return $this->accountId; }
    public function credentialVersion(): int { return $this->credentialVersion; }
    public function accountStatus(): string { return $this->accountStatus; }
    public function authenticatedAt(): string { return $this->authenticatedAt; }

    /** Deliberately excludes hash, email, memberships, plans and capabilities. */
    public function internalArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'credential_version' => $this->credentialVersion,
            'account_status' => $this->accountStatus,
            'authenticated_at' => $this->authenticatedAt,
            'reason_code' => ReasonCode::ALLOWED,
        ];
    }
}
