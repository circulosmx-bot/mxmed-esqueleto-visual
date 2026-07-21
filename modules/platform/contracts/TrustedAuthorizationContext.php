<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class TrustedAuthorizationContext
{
    private function __construct(
        private AuthorizationContext $context,
        private bool $trusted,
        private string $trustSource,
        private ?string $sessionStatus,
        private ?bool $accountActive,
        private ?bool $membershipActive,
        private bool $reauthenticated,
        private bool $mfaVerified,
        private bool $transitionalOpen,
        private bool $clientIdentityAuthoritative
    ) {}

    public static function fromBackend(AuthorizationContext $context, string $trustSource = 'backend_resolver', ?string $sessionStatus = 'active', ?bool $accountActive = true, ?bool $membershipActive = true, bool $reauthenticated = false, bool $mfaVerified = false, bool $transitionalOpen = false): self
    {
        $trustSource = (new SafeIdentifier($trustSource))->value();
        if ($trustSource === 'client') throw new \InvalidArgumentException('client_cannot_mark_context_trusted');
        return new self($context, true, $trustSource, self::normalizeSessionStatus($sessionStatus), $accountActive, $membershipActive, $reauthenticated, $mfaVerified, $transitionalOpen, true);
    }

    public static function fromClient(AuthorizationContext $context): self
    {
        return new self($context, false, 'client', null, null, null, false, false, false, false);
    }

    public function context(): AuthorizationContext { return $this->context; }
    public function isTrusted(): bool { return $this->trusted; }
    public function trustSource(): string { return $this->trustSource; }
    public function sessionStatus(): ?string { return $this->sessionStatus; }
    public function accountActive(): ?bool { return $this->accountActive; }
    public function membershipActive(): ?bool { return $this->membershipActive; }
    public function reauthenticated(): bool { return $this->reauthenticated; }
    public function mfaVerified(): bool { return $this->mfaVerified; }
    public function transitionalOpen(): bool { return $this->transitionalOpen; }
    public function clientIdentityAuthoritative(): bool { return $this->clientIdentityAuthoritative; }

    private static function normalizeSessionStatus(?string $value): ?string
    {
        if ($value === null) return null;
        if (!in_array($value, ['active', 'invalid', 'expired', 'revoked', 'superseded'], true)) throw new \InvalidArgumentException('unknown_session_status');
        return $value;
    }
}
