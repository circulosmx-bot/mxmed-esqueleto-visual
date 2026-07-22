<?php
declare(strict_types=1);

namespace Patients\Identity\Persistence;

final readonly class PatientIdentityRolloutPolicy
{
    public function stages(): array { return ['R0' => 'disabled', 'R1' => 'shadow', 'R2' => 'audit_only', 'R3' => 'read_compare', 'R4' => 'enabled']; }
    public function initialStage(): string { return 'disabled'; }
    public function metrics(): array { return ['total_evaluated', 'already_canonical', 'mapped_from_legacy', 'create_minimal_required', 'review_required', 'ambiguous', 'conflicts', 'idempotency_replay', 'candidate_set_mismatch', 'audit_append_failure', 'transaction_rollback', 'latency_buckets', 'backfill_checkpoint_lag']; }
    public function piiInMetricsLabelsOrLogs(): bool { return false; }
    public function activatesRuntime(): bool { return false; }
    public function seedData(): bool { return false; }
    public function gate8gEnabledStages(): array { return ['disabled']; }
    public function activationAllowed(): bool { return false; }
    public function writesEnabled(): bool { return false; }
    public function backfillEnabled(): bool { return false; }
    public function executesOperations(): bool { return false; }

    public function toArray(): array
    {
        return [
            'stages' => $this->stages(),
            'initial_stage' => $this->initialStage(),
            'metrics' => $this->metrics(),
            'pii_in_metrics_labels_or_logs' => $this->piiInMetricsLabelsOrLogs(),
            'activates_runtime' => $this->activatesRuntime(),
            'seed_data' => $this->seedData(),
            'gate8g_enabled_stages' => $this->gate8gEnabledStages(),
            'activation_allowed' => false,
            'writes_enabled' => false,
            'backfill_enabled' => false,
            'execution' => false,
        ];
    }
}
