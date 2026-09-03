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

    public function createOtp(string $doctorId, string $contactType, string $contactValue, string $codeHash, string $expiresAt, string $createdAt): string
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
            'created_at' => $createdAt,
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
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->query('PRAGMA table_info(' . $this->table . ')');
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $column) {
                if ((string)($column['name'] ?? '') === 'doctor_id') {
                    return (string)($column['type'] ?? '');
                }
            }
            return '';
        }
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

    public function findBookingState(string $appointmentId): ?array
    {
        $sql = 'SELECT
                    f.appointment_id,
                    f.doctor_id,
                    f.status,
                    f.expires_at,
                    f.payload_json,
                    f.otp_id,
                    o.created_at AS otp_created_at
                FROM agenda_public_appointment_flows f
                LEFT JOIN ' . $this->table . ' o ON o.id = f.otp_id
                WHERE f.appointment_id = :appointment_id
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['appointment_id' => $appointmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function bindOtpToPendingBooking(string $appointmentId, int $otpId, ?int $previousOtpId, string $notExpiredAt): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE agenda_public_appointment_flows
                SET otp_id = :otp_id, otp_channel = :otp_channel
              WHERE appointment_id = :appointment_id
                AND status = :status
                AND expires_at >= :not_expired_at
                AND ((:previous_otp_id IS NULL AND otp_id IS NULL) OR otp_id = :previous_otp_id_match)'
        );
        $stmt->execute([
            'otp_id' => $otpId,
            'otp_channel' => 'email',
            'appointment_id' => $appointmentId,
            'status' => 'pending_otp',
            'not_expired_at' => $notExpiredAt,
            'previous_otp_id' => $previousOtpId,
            'previous_otp_id_match' => $previousOtpId,
        ]);
        return $stmt->rowCount() === 1;
    }

    public function discardFailedDelivery(string $appointmentId, int $otpId, ?int $previousOtpId = null): void
    {
        $this->pdo->beginTransaction();
        try {
            $clear = $this->pdo->prepare(
                'UPDATE agenda_public_appointment_flows
                    SET otp_id = :previous_otp_id,
                        otp_channel = :previous_otp_channel
                  WHERE appointment_id = :appointment_id AND otp_id = :otp_id AND status = :status'
            );
            $clear->execute([
                'previous_otp_id' => $previousOtpId,
                'previous_otp_channel' => $previousOtpId === null ? null : 'email',
                'appointment_id' => $appointmentId,
                'otp_id' => $otpId,
                'status' => 'pending_otp',
            ]);
            $delete = $this->pdo->prepare(
                'DELETE FROM ' . $this->table . ' WHERE id = :otp_id AND verified = 0'
            );
            $delete->execute(['otp_id' => $otpId]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
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
