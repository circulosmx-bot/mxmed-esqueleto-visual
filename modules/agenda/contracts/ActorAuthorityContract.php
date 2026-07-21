<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class ActorReference
{
    public function __construct(private string $kind, private string $id)
    {
        $this->kind = self::text($kind, 'actor kind');
        $this->id = self::text($id, 'actor id');
    }

    public function kind(): string { return $this->kind; }
    public function id(): string { return $this->id; }
    public function toArray(): array { return ['kind' => $this->kind, 'id' => $this->id]; }

    private static function text(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128) {
            throw new \InvalidArgumentException($field . ' is invalid');
        }
        return $value;
    }
}

final class ActorAuthorityContract
{
    public const SOURCE_SERVER_CONTEXT = 'server_context';
    public const ALLOWED = 'allowed';
    public const AUTHORITY_MISSING = 'authority_missing';
    public const CLIENT_INPUT_NOT_AUTHORITATIVE = 'client_input_not_authoritative';

    private function __construct(
        private bool $allowed,
        private string $reason,
        private ?ActorReference $realActor,
        private ?ActorReference $effectiveActor,
        private ?string $accountId,
        private ?string $membershipId,
        private ?string $role,
        private ?string $subjectScope,
        private ?string $ownership
    ) {}

    public static function trusted(
        ActorReference $realActor,
        ActorReference $effectiveActor,
        string $accountId,
        string $membershipId,
        string $role,
        string $subjectScope,
        string $ownership
    ): self {
        foreach ([$accountId, $membershipId, $role, $subjectScope] as $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException('trusted authority context is incomplete');
            }
        }
        $ownership = strtolower(trim($ownership));
        if (!in_array($ownership, ['owner', 'member'], true)) {
            throw new \InvalidArgumentException('ownership is invalid');
        }
        return new self(true, self::ALLOWED, $realActor, $effectiveActor, trim($accountId), trim($membershipId), trim($role), trim($subjectScope), $ownership);
    }

    public static function denied(string $reason = self::AUTHORITY_MISSING): self
    {
        if (!in_array($reason, [self::AUTHORITY_MISSING, self::CLIENT_INPUT_NOT_AUTHORITATIVE], true)) {
            throw new \InvalidArgumentException('unknown authority denial');
        }
        return new self(false, $reason, null, null, null, null, null, null, null);
    }

    public function allowed(): bool { return $this->allowed; }
    public function reason(): string { return $this->reason; }
    public function serverAuthoritative(): bool { return $this->allowed; }
    public function realActor(): ?ActorReference { return $this->realActor; }
    public function effectiveActor(): ?ActorReference { return $this->effectiveActor; }
    public function accountId(): ?string { return $this->accountId; }
    public function membershipId(): ?string { return $this->membershipId; }
    public function role(): ?string { return $this->role; }
    public function subjectScope(): ?string { return $this->subjectScope; }
    public function ownership(): ?string { return $this->ownership; }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'source' => $this->allowed ? self::SOURCE_SERVER_CONTEXT : null,
            'real_actor' => $this->realActor?->toArray(),
            'effective_actor' => $this->effectiveActor?->toArray(),
            'account_id' => $this->accountId,
            'membership_id' => $this->membershipId,
            'role' => $this->role,
            'subject_scope' => $this->subjectScope,
            'ownership' => $this->ownership,
        ];
    }
}
