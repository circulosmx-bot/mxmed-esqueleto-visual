<?php
declare(strict_types=1);

namespace Patients\Identity\Persistence;

interface PatientIdentityPersistencePort
{
    public function beginTransaction(): void;
    public function lockResolutionFingerprint(string $requestFingerprint): void;
    public function lockLegacyReferenceDigest(?string $legacyReferenceDigest): void;
    public function findResolutionByFingerprint(string $requestFingerprint): ?array;
    public function persistProcessingResolution(array $resolution): void;
    public function persistCompletedResolution(array $resolution): void;
    public function persistFailedResolution(string $requestFingerprint, string $failureCode, string $failedAt): void;
    public function appendAuditEvent(array $event): void;
    public function findLegacyLink(string $legacyPatientKeyHash): ?array;
    public function persistLegacyLink(array $link): void;
    public function loadBackfillCheckpoint(string $jobReference): ?array;
    public function persistBackfillCheckpoint(array $checkpoint): void;
    public function commit(): void;
    public function rollBack(): void;
}
