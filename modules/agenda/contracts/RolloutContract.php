<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class RolloutContract
{
    public function __construct(private array $modes, private bool $serverSideFeatureFlag, private array $metrics, private bool $hardStop, private string $rollback, private string $owner, private string $retirementCondition)
    {
        $allowed = ['shadow', 'dual_read', 'reversible_backfill'];
        if ($modes === [] || array_diff($modes, $allowed) !== []) throw new \InvalidArgumentException('rollout mode invalid');
        foreach ([$rollback, $owner, $retirementCondition] as $value) if (trim($value) === '') throw new \InvalidArgumentException('rollout contract incomplete');
    }
    public function retirementRequired(): bool { return true; }
    public function hardStop(): bool { return $this->hardStop; }
    public function serverSideFeatureFlag(): bool { return $this->serverSideFeatureFlag; }
    public function toArray(): array { return ['modes' => $this->modes, 'server_side_feature_flag' => $this->serverSideFeatureFlag, 'metrics' => $this->metrics, 'hard_stop' => $this->hardStop, 'rollback' => $this->rollback, 'owner' => $this->owner, 'retirement_condition' => $this->retirementCondition, 'retirement_required' => true]; }
}
