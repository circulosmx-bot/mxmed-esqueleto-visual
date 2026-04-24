<?php
namespace Agenda\Helpers\DoctorIdentity;

use PDO;
use Throwable;

function normalizeDoctorIdInput($value): string
{
    return trim((string)($value ?? ''));
}

function ensureIdentityMapTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS doctor_identity_map (
            legacy_doctor_id VARCHAR(64) NOT NULL,
            canonical_doctor_id VARCHAR(64) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (legacy_doctor_id),
            KEY idx_doctor_identity_map_canonical (canonical_doctor_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function resolveCanonicalDoctorId(PDO $pdo, $doctorId): string
{
    $raw = normalizeDoctorIdInput($doctorId);
    if ($raw === '') {
        return '';
    }

    try {
        ensureIdentityMapTable($pdo);
        $stmt = $pdo->prepare(
            'SELECT canonical_doctor_id
             FROM doctor_identity_map
             WHERE legacy_doctor_id = :doctor_id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['doctor_id' => $raw]);
        $canonical = normalizeDoctorIdInput($stmt->fetchColumn());
        if ($canonical !== '') {
            return $canonical;
        }
    } catch (Throwable $e) {
        // Si la tabla no existe o falla, degradar a passthrough para no romper operación.
    }

    return $raw;
}

function resolveLegacyDoctorIdForCanonical(PDO $pdo, $canonicalDoctorId, ?callable $predicate = null): string
{
    $canonical = normalizeDoctorIdInput($canonicalDoctorId);
    if ($canonical === '') {
        return '';
    }

    $candidates = [];
    try {
        ensureIdentityMapTable($pdo);
        $stmt = $pdo->prepare(
            'SELECT legacy_doctor_id
             FROM doctor_identity_map
             WHERE canonical_doctor_id = :canonical_doctor_id AND is_active = 1
             ORDER BY CASE WHEN legacy_doctor_id = :canonical_match THEN 0 ELSE 1 END, legacy_doctor_id ASC'
        );
        $stmt->execute([
            'canonical_doctor_id' => $canonical,
            'canonical_match' => $canonical,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($rows as $row) {
            $candidate = normalizeDoctorIdInput($row);
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }
    } catch (Throwable $e) {
        // passthrough abajo
    }

    $candidates[] = $canonical;
    $candidates = array_values(array_unique($candidates));

    foreach ($candidates as $candidate) {
        if ($predicate && !$predicate($candidate)) {
            continue;
        }
        return $candidate;
    }

    return '';
}

function upsertDoctorIdentityAlias(PDO $pdo, $legacyDoctorId, $canonicalDoctorId): bool
{
    $legacy = normalizeDoctorIdInput($legacyDoctorId);
    $canonical = normalizeDoctorIdInput($canonicalDoctorId);
    if ($legacy === '' || $canonical === '') {
        return false;
    }

    ensureIdentityMapTable($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO doctor_identity_map (legacy_doctor_id, canonical_doctor_id, is_active)
         VALUES (:legacy_doctor_id, :canonical_doctor_id, 1)
         ON DUPLICATE KEY UPDATE
           canonical_doctor_id = VALUES(canonical_doctor_id),
           is_active = 1'
    );
    $stmt->execute([
        'legacy_doctor_id' => $legacy,
        'canonical_doctor_id' => $canonical,
    ]);
    return true;
}
