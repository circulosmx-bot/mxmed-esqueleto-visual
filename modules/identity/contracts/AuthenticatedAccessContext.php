<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class AuthenticatedAccessContext
{
    public function __construct(private SessionPrincipal $principal, private SessionRecord $session) {}
    public function principal(): SessionPrincipal { return $this->principal; }
    public function session(): SessionRecord { return $this->session; }
    public function accountId(): string { return $this->principal->accountId(); }
}
