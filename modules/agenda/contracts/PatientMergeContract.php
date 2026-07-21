<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class PatientMergeContract
{
    public const DRY_RUN = 'dry_run';
    public const APPLY = 'apply';
    public const UNDO = 'undo';
    public function __construct(private string $mode, private string $actorId, private string $reason, private array $aliases, private array $snapshot, private array $affectedReferences, private bool $reauthenticated = false)
    {
        if (!in_array($mode, [self::DRY_RUN, self::APPLY, self::UNDO], true)) throw new \InvalidArgumentException('merge mode invalid');
        foreach ([$actorId, $reason] as $value) if (trim($value) === '') throw new \InvalidArgumentException('merge authorization context incomplete');
    }
    public function mode(): string { return $this->mode; }
    public function risk(): string { return 'R3'; }
    public function disabled(): bool { return true; }
    public function endpointEnabled(): bool { return false; }
    public function requiresReauthentication(): bool { return true; }
    public function reauthenticated(): bool { return $this->reauthenticated; }
    public function canExecute(): bool { return false; }
    public function aliases(): array { return $this->aliases; }
    public function snapshot(): array { return $this->snapshot; }
    public function affectedReferences(): array { return $this->affectedReferences; }
    public function toArray(): array { return ['mode' => $this->mode, 'risk' => 'R3', 'disabled' => true, 'endpoint_enabled' => false, 'requires_reauthentication' => true, 'can_execute' => false, 'aliases' => $this->aliases, 'snapshot' => $this->snapshot, 'affected_references' => $this->affectedReferences]; }
}
