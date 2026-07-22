<?php
declare(strict_types=1);

namespace Patients\Identity\Persistence;

final readonly class PatientIdentityBackfillPlan
{
    public function steps(): array
    {
        return ['preflight', 'external_snapshot_backup', 'shadow_scan', 'batched_read', 'trusted_adapter_digest', 'candidate_resolution', 'no_match_partition', 'review_queue_partition', 'idempotency_check', 'append_audit', 'persist_checkpoint', 'reconciliation', 'emit_metrics', 'abort_or_rollback'];
    }

    public function failureMode(): string { return 'abort_and_rollback'; }
    public function deterministic(): bool { return true; }
    public function resumable(): bool { return true; }
    public function idempotent(): bool { return true; }
    public function batchLimited(): bool { return true; }
    public function executesBackfill(): bool { return false; }
    public function runtimeWiring(): bool { return false; }
    public function automaticMerge(): bool { return false; }
    public function deletesData(): bool { return false; }
    public function clinicalMutation(): bool { return false; }
    public function containsPii(): bool { return false; }
    public function legacyRuntimePreserved(): bool { return true; }
    public function executesOperations(): bool { return false; }

    public function toArray(): array
    {
        return [
            'steps' => $this->steps(),
            'failure_mode' => $this->failureMode(),
            'deterministic' => $this->deterministic(),
            'resumable' => $this->resumable(),
            'idempotent' => $this->idempotent(),
            'batch_limited' => $this->batchLimited(),
            'executes_backfill' => $this->executesBackfill(),
            'runtime_wiring' => $this->runtimeWiring(),
            'automatic_merge' => $this->automaticMerge(),
            'deletes_data' => $this->deletesData(),
            'clinical_mutation' => $this->clinicalMutation(),
            'contains_pii' => $this->containsPii(),
            'legacy_runtime_preserved' => true,
            'execution' => false,
        ];
    }
}
