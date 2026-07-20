<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionPrincipal
{
    public function __construct(
        private string $accountId,
        private int $credentialVersion,
        private string $accountStatus,
        private string $authenticatedAt
    ) {
        if ($this->accountId === '' || $this->credentialVersion < 1 || $this->accountStatus === '' || $this->authenticatedAt === '') throw new \InvalidArgumentException('invalid_session_principal');
    }

    public static function fromCandidate(AuthenticationPrincipalCandidate $candidate): self
    {
        return new self($candidate->accountId(), $candidate->credentialVersion(), $candidate->accountStatus(), $candidate->authenticatedAt());
    }

    public function accountId(): string { return $this->accountId; }
    public function credentialVersion(): int { return $this->credentialVersion; }
    public function accountStatus(): string { return $this->accountStatus; }
    public function authenticatedAt(): string { return $this->authenticatedAt; }
    public function toArray(): array { return ['account_id' => $this->accountId, 'credential_version' => $this->credentialVersion, 'account_status' => $this->accountStatus, 'authenticated_at' => $this->authenticatedAt]; }
}
