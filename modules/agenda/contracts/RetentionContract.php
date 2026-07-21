<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class RetentionContract
{
    public function __construct(private string $source, private string $projection, private string $temporaryCopy, private string $category, private bool $legalHold, private bool $dryRunDisposition, private bool $authorizedExecution, private array $dependencyBlockers)
    {
        foreach ([$source, $projection, $temporaryCopy, $category] as $value) if (trim($value) === '') throw new \InvalidArgumentException('retention source is incomplete');
    }
    public function concretePeriod(): ?string { return null; }
    public function legalHold(): bool { return $this->legalHold; }
    public function dryRunOnly(): bool { return $this->dryRunDisposition && !$this->authorizedExecution; }
    public function dependencyBlockers(): array { return $this->dependencyBlockers; }
    public function toArray(): array { return ['source' => $this->source, 'projection' => $this->projection, 'temporary_copy' => $this->temporaryCopy, 'category' => $this->category, 'legal_hold' => $this->legalHold, 'concrete_period' => null, 'dry_run_disposition' => $this->dryRunDisposition, 'authorized_execution' => $this->authorizedExecution, 'dependency_blockers' => $this->dependencyBlockers]; }
}
