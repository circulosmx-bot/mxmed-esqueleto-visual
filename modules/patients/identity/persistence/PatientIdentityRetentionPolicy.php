<?php
declare(strict_types=1);

namespace Patients\Identity\Persistence;

final readonly class PatientIdentityRetentionPolicy
{
    public function auditRetention(): string { return 'durable_no_purge'; }
    public function legacyLinkRetention(): string { return 'durable_no_purge'; }
    public function resolutionRetention(): string { return 'UNRESOLVED_PENDING_POLICY_APPROVAL'; }
    public function checkpointRetention(): string { return 'UNRESOLVED_PENDING_POLICY_APPROVAL'; }
    public function automaticPurge(): bool { return false; }
    public function automaticArchive(): bool { return false; }
    public function automaticDeletion(): bool { return false; }
    public function numericRetentionPeriods(): ?array { return null; }
    public function executesOperations(): bool { return false; }

    public function toArray(): array
    {
        return [
            'audit_retention' => $this->auditRetention(),
            'legacy_link_retention' => $this->legacyLinkRetention(),
            'resolution_retention' => $this->resolutionRetention(),
            'checkpoint_retention' => $this->checkpointRetention(),
            'automatic_purge' => $this->automaticPurge(),
            'automatic_archive' => $this->automaticArchive(),
            'automatic_deletion' => $this->automaticDeletion(),
            'numeric_retention_periods' => $this->numericRetentionPeriods(),
            'execution' => false,
        ];
    }
}
