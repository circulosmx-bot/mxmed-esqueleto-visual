<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionRevocationDecision
{
    public function __construct(private bool $allowed, private string $reasonCode, private ?SessionCookieDescriptor $cookie = null, private int $revokedCount = 0) {}
    public function allowed(): bool { return $this->allowed; }
    public function isAllowed(): bool { return $this->allowed; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function cookie(): ?SessionCookieDescriptor { return $this->cookie; }
    public function revokedCount(): int { return $this->revokedCount; }
}
