<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class AuthenticationDecision
{
    private function __construct(
        private bool $allowed,
        private string $publicCode,
        private string $reasonCode,
        private ?AuthenticationPrincipalCandidate $candidate
    ) {}

    public static function success(AuthenticationPrincipalCandidate $candidate): self
    {
        return new self(true, 'AUTHENTICATION_PRINCIPAL_CANDIDATE', ReasonCode::ALLOWED, $candidate);
    }

    public static function denied(string $reasonCode = ReasonCode::INVALID_CREDENTIALS): self
    {
        if (!ReasonCode::isKnown($reasonCode)) throw new \InvalidArgumentException('unknown_authentication_reason');
        return new self(false, 'INVALID_CREDENTIALS', $reasonCode, null);
    }

    public function isAllowed(): bool { return $this->allowed; }
    public function publicCode(): string { return $this->publicCode; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function candidate(): ?AuthenticationPrincipalCandidate { return $this->candidate; }
}
