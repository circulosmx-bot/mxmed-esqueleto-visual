<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class LegacyContainmentDecision
{
    /** @param list<string> $blockers */
    public function __construct(
        private string $surface,
        private bool $contained,
        private bool $resolved,
        private string $status,
        private string $reason,
        private array $blockers = []
    ) {
        if (trim($surface) === '') throw new \InvalidArgumentException('legacy_surface_required');
        LegacyContainmentStatus::assertValid($status);
        if (trim($reason) === '') throw new \InvalidArgumentException('legacy_decision_reason_required');
        foreach ($blockers as $blocker) {
            if (!is_string($blocker) || trim($blocker) === '') throw new \InvalidArgumentException('legacy_decision_blocker_invalid');
        }
    }

    public function surface(): string { return $this->surface; }
    public function contained(): bool { return $this->contained; }
    public function resolved(): bool { return $this->resolved; }
    public function status(): string { return $this->status; }
    public function reason(): string { return $this->reason; }
    /** @return list<string> */
    public function blockers(): array { return $this->blockers; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'surface' => $this->surface,
            'contained' => $this->contained,
            'resolved' => $this->resolved,
            'status' => $this->status,
            'reason' => $this->reason,
            'blockers' => $this->blockers,
        ];
    }
}

