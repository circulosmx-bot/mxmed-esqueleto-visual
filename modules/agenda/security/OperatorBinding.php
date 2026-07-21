<?php
declare(strict_types=1);

namespace Agenda\Security;

use DateTimeImmutable;
use Platform\Contracts\SafeIdentifier;
use Platform\Contracts\ScopeSet;

/** Backend-resolved operator-to-profile binding; never populated from a client claim. */
final class OperatorBinding
{
    public const SOURCE_BACKEND = 'backend';
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const REVOKED = 'revoked';

    public function __construct(
        private string $operatorId,
        private string $accountId,
        private string $profileId,
        private string $status,
        private bool $active,
        private ScopeSet $scope,
        private string $source = self::SOURCE_BACKEND,
        private ?DateTimeImmutable $validFrom = null,
        private ?DateTimeImmutable $validUntil = null
    ) {
        $this->operatorId = (new SafeIdentifier($this->operatorId))->value();
        $this->accountId = (new SafeIdentifier($this->accountId))->value();
        $this->profileId = (new SafeIdentifier($this->profileId))->value();
        if (!in_array($this->status, [self::ACTIVE, self::INACTIVE, self::REVOKED], true)) {
            throw new \InvalidArgumentException('unknown_operator_binding_status');
        }
        if ($this->source !== self::SOURCE_BACKEND) {
            throw new \InvalidArgumentException('operator_binding_source_must_be_backend');
        }
        if ($this->validFrom !== null && $this->validUntil !== null && $this->validUntil < $this->validFrom) {
            throw new \InvalidArgumentException('operator_binding_validity_invalid');
        }
    }

    public function operatorId(): string { return $this->operatorId; }
    public function accountId(): string { return $this->accountId; }
    public function profileId(): string { return $this->profileId; }
    public function status(): string { return $this->status; }
    public function active(): bool { return $this->active; }
    public function scope(): ScopeSet { return $this->scope; }
    public function source(): string { return $this->source; }
    public function validFrom(): ?DateTimeImmutable { return $this->validFrom; }
    public function validUntil(): ?DateTimeImmutable { return $this->validUntil; }

    public function isUsableFor(string $accountId, string $profileId, string $requestedScope, ?DateTimeImmutable $at = null): bool
    {
        if (!$this->active || $this->status !== self::ACTIVE) return false;
        if ($this->accountId !== $accountId || $this->profileId !== $profileId) return false;
        if (!$this->scope->contains($requestedScope)) return false;
        $at ??= new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($this->validFrom !== null && $at < $this->validFrom) return false;
        if ($this->validUntil !== null && $at >= $this->validUntil) return false;
        return true;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'operator_id' => $this->operatorId,
            'account_id' => $this->accountId,
            'profile_id' => $this->profileId,
            'status' => $this->status,
            'active' => $this->active,
            'scope' => $this->scope->values(),
            'source' => $this->source,
            'valid_from' => $this->validFrom?->format(DATE_ATOM),
            'valid_until' => $this->validUntil?->format(DATE_ATOM),
        ];
    }
}
