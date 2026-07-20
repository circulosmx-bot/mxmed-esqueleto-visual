<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionValidationDecision
{
    public function __construct(private bool $allowed, private string $reasonCode, private ?SessionPrincipal $principal = null, private ?SessionRecord $record = null, private ?AuthenticatedAccessContext $context = null) {}
    public function allowed(): bool { return $this->allowed; }
    public function isAllowed(): bool { return $this->allowed; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function principal(): ?SessionPrincipal { return $this->principal; }
    public function record(): ?SessionRecord { return $this->record; }
    public function context(): ?AuthenticatedAccessContext { return $this->context; }
}
