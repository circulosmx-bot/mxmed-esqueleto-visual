<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class AuthorizationDecision
{
    public function __construct(private bool $allowed, private string $reasonCode, private ?string $membershipId = null, private ?string $role = null, private ?string $scope = null, private ?array $capability = null) {}
    public function allowed(): bool { return $this->allowed; }
    public function isAllowed(): bool { return $this->allowed; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function membershipId(): ?string { return $this->membershipId; }
    public function role(): ?string { return $this->role; }
    public function scope(): ?string { return $this->scope; }
    public function capability(): ?array { return $this->capability; }
}
