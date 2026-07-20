<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class EmailVerificationDecision
{
    public function __construct(private bool $verified, private string $reasonCode) {}
    public function verified(): bool { return $this->verified; }
    public function publicCode(): string { return $this->verified ? 'EMAIL_VERIFIED' : 'EMAIL_VERIFICATION_UNAVAILABLE'; }
    public function reasonCode(): string { return $this->reasonCode; }
}
