<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class RateLimitDecision
{
    public function __construct(private bool $allowed, private string $reasonCode, private int $retryAfterSeconds = 0) {}
    public function allowed(): bool { return $this->allowed; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function retryAfterSeconds(): int { return $this->retryAfterSeconds; }
}
