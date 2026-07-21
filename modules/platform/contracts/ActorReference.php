<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class ActorReference
{
    private string $kind;
    private SafeIdentifier $id;

    public function __construct(string $kind, string $id)
    {
        if (!in_array($kind, ['account', 'operator', 'service', 'system', 'support', 'governance'], true)) {
            throw new \InvalidArgumentException('unknown_actor_kind');
        }
        $this->kind = $kind;
        $this->id = new SafeIdentifier($id);
    }
    public function kind(): string { return $this->kind; }
    public function id(): string { return $this->id->value(); }
    public function toArray(): array { return ['kind' => $this->kind, 'id' => $this->id->value()]; }
}
