<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionRotationDecision
{
    public function __construct(private bool $allowed, private string $reasonCode, private ?SessionRecord $record = null, private ?SessionToken $token = null, private ?SessionCookieDescriptor $cookie = null) {}
    public function allowed(): bool { return $this->allowed; }
    public function isAllowed(): bool { return $this->allowed; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function record(): ?SessionRecord { return $this->record; }
    public function token(): ?SessionToken { return $this->token; }
    public function cookie(): ?SessionCookieDescriptor { return $this->cookie; }
}
