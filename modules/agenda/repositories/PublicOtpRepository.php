<?php
namespace Agenda\Repositories;

use Agenda\Helpers\DoctorIdentity as DoctorIdentity;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../helpers/doctor_identity.php';

class PublicOtpRepository
{
    private PDO $pdo;
    private string $table = 'agenda_public_otps';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createOtp(string $doctorId, string $contactType, string $contactValue, string $codeHash, string $expiresAt): string
    {
        $doctorIdStorage = $this->resolveDoctorIdForStorage($doctorId);
        $sql = 'INSERT INTO ' . $this->table . '
            (doctor_id, contact_type, contact_value, code_hash, expires_at, attempts, verified, created_at)
            VALUES
            (:doctor_id, :contact_type, :contact_value, :code_hash, :expires_at, 0, 0, :created_at)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'doctor_id' => $doctorIdStorage,
            'contact_type' => $contactType,
            'contact_value' => $contactValue,
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (string)$this->pdo->lastInsertId();
    }

    private function resolveDoctorIdForStorage(string $doctorId): string
    {
        $canonicalDoctorId = DoctorIdentity\resolveCanonicalDoctorId($this->pdo, $doctorId);
        if ($canonicalDoctorId === '') {
            throw new RuntimeException('doctor_id required');
        }
        $doctorIdColumnType = strtolower($this->getDoctorIdColumnType());
        $isNumericStorage = str_contains($doctorIdColumnType, 'int');
        if (!$isNumericStorage) {
            return $canonicalDoctorId;
        }

        $legacyNumeric = DoctorIdentity\resolveLegacyDoctorIdForCanonical(
            $this->pdo,
            $canonicalDoctorId,
            static fn(string $value): bool => ctype_digit($value)
        );
        if ($legacyNumeric === '') {
            throw new RuntimeException('doctor_id_legacy_alias_required');
        }
        return $legacyNumeric;
    }

    private function getDoctorIdColumnType(): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1'
        );
        $stmt->execute([
            'table' => $this->table,
            'column' => 'doctor_id',
        ]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    public function findOtpById(int $otpId): ?array
    {
        $sql = 'SELECT id, doctor_id, contact_type, contact_value, code_hash, expires_at, attempts, verified, created_at
                FROM ' . $this->table . '
                WHERE id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $otpId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function incrementAttempts(int $otpId): void
    {
        $sql = 'UPDATE ' . $this->table . ' SET attempts = attempts + 1 WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $otpId]);
    }

    public function markVerified(int $otpId): void
    {
        $sql = 'UPDATE ' . $this->table . ' SET verified = 1 WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $otpId]);
    }
}
