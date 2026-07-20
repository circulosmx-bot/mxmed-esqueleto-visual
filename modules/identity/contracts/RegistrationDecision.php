<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class RegistrationDecision
{
    public function __construct(private bool $accepted, private string $reasonCode, private ?string $accountId = null) {}
    public function accepted(): bool { return $this->accepted; }
    public function publicCode(): string { return 'REGISTRATION_RECEIVED'; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function accountId(): ?string { return $this->accountId; }
}
