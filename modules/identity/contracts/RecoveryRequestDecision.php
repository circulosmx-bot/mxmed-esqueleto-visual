<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class RecoveryRequestDecision
{
    public function __construct(private string $reasonCode) {}
    public function publicCode(): string { return 'RECOVERY_REQUEST_RECEIVED'; }
    public function reasonCode(): string { return $this->reasonCode; }
}
