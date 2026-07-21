<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class PlatformFoundationReadinessDecision
{
    public const NO_GO_LEGACY_BLOCKERS_PRESENT = 'NO_GO_LEGACY_BLOCKERS_PRESENT';

    /** @param list<string> $blockers @param array<string,mixed> $gateResults */
    public function __construct(
        private bool $ready,
        private string $deploymentDecision,
        private array $blockers,
        private array $gateResults
    ) {
        if ($deploymentDecision !== self::NO_GO_LEGACY_BLOCKERS_PRESENT) {
            throw new \InvalidArgumentException('unsupported_platform_readiness_decision');
        }
        foreach ($blockers as $blocker) {
            if (!is_string($blocker) || trim($blocker) === '') throw new \InvalidArgumentException('invalid_platform_readiness_blocker');
        }
    }

    public function ready(): bool { return $this->ready; }
    public function deploymentDecision(): string { return $this->deploymentDecision; }
    /** @return list<string> */
    public function blockers(): array { return $this->blockers; }
    /** @return array<string,mixed> */
    public function gateResults(): array { return $this->gateResults; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready,
            'deployment_decision' => $this->deploymentDecision,
            'blockers' => $this->blockers,
            'gate_results' => $this->gateResults,
        ];
    }
}

