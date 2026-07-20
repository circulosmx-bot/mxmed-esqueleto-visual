<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class PasswordResetDecision
{
    public function __construct(private bool $reset, private string $reasonCode, private ?int $credentialVersion = null) {}
    public function reset(): bool { return $this->reset; }
    public function publicCode(): string { return $this->reset ? 'PASSWORD_RESET_COMPLETED' : 'PASSWORD_RESET_UNAVAILABLE'; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function credentialVersion(): ?int { return $this->credentialVersion; }
}
