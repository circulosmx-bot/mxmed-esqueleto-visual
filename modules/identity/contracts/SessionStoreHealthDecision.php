<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionStoreHealthDecision
{
    public function __construct(private bool $healthy, private string $reasonCode = ReasonCode::ALLOWED) {}
    public function healthy(): bool { return $this->healthy; }
    public function available(): bool { return $this->healthy; }
    public function reasonCode(): string { return $this->reasonCode; }
    public static function healthyDecision(): self { return new self(true, ReasonCode::ALLOWED); }
    public static function unavailable(): self { return new self(false, ReasonCode::SESSION_STORE_UNAVAILABLE); }
}
