<?php
declare(strict_types=1);

namespace Patients\Identity\Persistence;

final readonly class PatientIdentityPersistenceManifest
{
    public function tables(): array
    {
        return ['patient_identity_resolutions', 'patient_identity_audit_events', 'patient_identity_legacy_links', 'patient_identity_backfill_checkpoints'];
    }

    public function upMigrations(): array
    {
        return ['2026_07_22_01_create_patient_identity_resolutions.sql', '2026_07_22_02_create_patient_identity_audit_events.sql', '2026_07_22_03_create_patient_identity_legacy_links.sql', '2026_07_22_04_create_patient_identity_backfill_checkpoints.sql'];
    }

    public function downMigrations(): array
    {
        return ['2026_07_22_04_rollback_patient_identity_backfill_checkpoints.sql', '2026_07_22_03_rollback_patient_identity_legacy_links.sql', '2026_07_22_02_rollback_patient_identity_audit_events.sql', '2026_07_22_01_rollback_patient_identity_resolutions.sql'];
    }

    public function auditStrategy(): string { return 'patient_identity_specific_append_only'; }
    public function platformAuditReuse(): bool { return false; }
    public function platformAuditReuseReason(): string { return 'protected_platform_envelope_is_not_sufficient_for_gate8f_identity_metadata'; }
    public function engine(): string { return 'InnoDB'; }
    public function charset(): string { return 'utf8mb4'; }
    public function collation(): string { return 'utf8mb4_unicode_ci'; }
    public function seedData(): bool { return false; }
    public function modifiesExistingTables(): bool { return false; }

    public function createMigrations(): array
    {
        return [
            'modules/patients/db/migrations/2026_07_22_01_create_patient_identity_resolutions.sql',
            'modules/patients/db/migrations/2026_07_22_02_create_patient_identity_audit_events.sql',
            'modules/patients/db/migrations/2026_07_22_03_create_patient_identity_legacy_links.sql',
            'modules/patients/db/migrations/2026_07_22_04_create_patient_identity_backfill_checkpoints.sql',
        ];
    }

    public function rollbackMigrations(): array
    {
        return [
            'modules/patients/db/migrations/2026_07_22_04_rollback_patient_identity_backfill_checkpoints.sql',
            'modules/patients/db/migrations/2026_07_22_03_rollback_patient_identity_legacy_links.sql',
            'modules/patients/db/migrations/2026_07_22_02_rollback_patient_identity_audit_events.sql',
            'modules/patients/db/migrations/2026_07_22_01_rollback_patient_identity_resolutions.sql',
        ];
    }

    public function phpContracts(): array
    {
        return [
            'modules/patients/identity/persistence/PatientIdentityPersistencePolicy.php',
            'modules/patients/identity/persistence/PatientIdentityPersistenceManifest.php',
            'modules/patients/identity/persistence/PatientIdentityRetentionPolicy.php',
            'modules/patients/identity/persistence/PatientIdentityBackfillPlan.php',
            'modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php',
            'modules/patients/identity/persistence/PatientIdentityPersistencePort.php',
        ];
    }

    public function testFile(): string { return 'modules/patients/tests/Gate8GPatientIdentityPersistenceMigrationTest.php'; }
    public function documentFile(): string { return 'docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_MIGRACIONES.md'; }
    public function planMasterFile(): string { return 'docs/PLAN_MAESTRO_MXMED.md'; }

    public function versionedFiles(): array
    {
        return array_merge(
            $this->phpContracts(),
            [
                'modules/patients/db/migrations/2026_07_22_01_create_patient_identity_resolutions.sql',
                'modules/patients/db/migrations/2026_07_22_01_rollback_patient_identity_resolutions.sql',
                'modules/patients/db/migrations/2026_07_22_02_create_patient_identity_audit_events.sql',
                'modules/patients/db/migrations/2026_07_22_02_rollback_patient_identity_audit_events.sql',
                'modules/patients/db/migrations/2026_07_22_03_create_patient_identity_legacy_links.sql',
                'modules/patients/db/migrations/2026_07_22_03_rollback_patient_identity_legacy_links.sql',
                'modules/patients/db/migrations/2026_07_22_04_create_patient_identity_backfill_checkpoints.sql',
                'modules/patients/db/migrations/2026_07_22_04_rollback_patient_identity_backfill_checkpoints.sql',
                $this->testFile(),
                $this->documentFile(),
                $this->planMasterFile(),
            ]
        );
    }

    public function versionedFileCount(): int { return 17; }
    public function createdFileCount(): int { return 16; }
    public function modifiedFileCount(): int { return 1; }
    public function sqlFileCount(): int { return 8; }
    public function phpFileCount(): int { return 7; }
    public function documentFileCount(): int { return 2; }
    public function executesOperations(): bool { return false; }

    public function toArray(): array
    {
        return [
            'tables' => $this->tables(),
            'create_migrations' => $this->createMigrations(),
            'rollback_migrations' => $this->rollbackMigrations(),
            'php_contracts' => $this->phpContracts(),
            'test_file' => $this->testFile(),
            'document_file' => $this->documentFile(),
            'plan_master_file' => $this->planMasterFile(),
            'versioned_files' => $this->versionedFiles(),
            'versioned_file_count' => $this->versionedFileCount(),
            'created_file_count' => $this->createdFileCount(),
            'modified_file_count' => $this->modifiedFileCount(),
            'sql_file_count' => $this->sqlFileCount(),
            'php_file_count' => $this->phpFileCount(),
            'document_file_count' => $this->documentFileCount(),
            'execution' => false,
        ];
    }
}
