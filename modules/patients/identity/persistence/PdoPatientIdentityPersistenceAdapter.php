<?php
declare(strict_types=1);

namespace Patients\Identity\Persistence;

use RuntimeException;

require_once __DIR__ . '/PatientIdentityPersistencePort.php';

final class PdoPatientIdentityPersistenceAdapter implements PatientIdentityPersistencePort
{
    public function configured(): bool
    {
        return false;
    }

    public function readiness(): array
    {
        return [
            'mode' => 'rejecting_placeholder',
            'configured' => false,
            'activation_authorized' => false,
            'writes_enabled' => false,
            'backfill_enabled' => false,
            'database_connections_opened' => 0,
            'sql_executed' => 0,
            'ready' => false,
        ];
    }

    public function beginTransaction(): void
    {
        $this->reject();
    }

    public function lockResolutionFingerprint(string $requestFingerprint): void
    {
        $this->reject();
    }

    public function lockLegacyReferenceDigest(?string $legacyReferenceDigest): void
    {
        $this->reject();
    }

    public function findResolutionByFingerprint(string $requestFingerprint): ?array
    {
        $this->reject();
    }

    public function persistProcessingResolution(array $resolution): void
    {
        $this->reject();
    }

    public function persistCompletedResolution(array $resolution): void
    {
        $this->reject();
    }

    public function persistFailedResolution(string $requestFingerprint, string $failureCode, string $failedAt): void
    {
        $this->reject();
    }

    public function appendAuditEvent(array $event): void
    {
        $this->reject();
    }

    public function findLegacyLink(string $legacyPatientKeyHash): ?array
    {
        $this->reject();
    }

    public function persistLegacyLink(array $link): void
    {
        $this->reject();
    }

    public function loadBackfillCheckpoint(string $jobReference): ?array
    {
        $this->reject();
    }

    public function persistBackfillCheckpoint(array $checkpoint): void
    {
        $this->reject();
    }

    public function commit(): void
    {
        $this->reject();
    }

    public function rollBack(): void
    {
        $this->reject();
    }

    private function reject(): never
    {
        throw new RuntimeException('patient_identity_persistence_not_configured');
    }
}
