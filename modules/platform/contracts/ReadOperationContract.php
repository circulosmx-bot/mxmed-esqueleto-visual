<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class ReadOperationContract
{
    private string $operation;
    public function __construct(string $operation, bool $createsSchema = false, bool $migrates = false, bool $seeds = false, bool $updates = false, bool $deletes = false, bool $reconciles = false, bool $dualWrites = false)
    {
        $this->operation = (new SafeIdentifier($operation))->value();
        if ($createsSchema || $migrates || $seeds || $updates || $deletes || $reconciles || $dualWrites) throw new \InvalidArgumentException(CanonicalSourceReason::READ_SIDE_EFFECT_FORBIDDEN);
    }
    public function operation(): string { return $this->operation; }
    public function isPure(): bool { return true; }
    public function hasSideEffects(): bool { return false; }
}
