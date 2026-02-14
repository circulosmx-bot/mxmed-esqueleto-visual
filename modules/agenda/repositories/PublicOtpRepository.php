<?php
namespace Agenda\Repositories;

use PDO;

class PublicOtpRepository
{
    private PDO $pdo;
    private string $table = 'agenda_public_otps';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createOtp(int $doctorId, string $contactType, string $contactValue, string $codeHash, string $expiresAt): string
    {
        $sql = 'INSERT INTO ' . $this->table . '
            (doctor_id, contact_type, contact_value, code_hash, expires_at, attempts, verified, created_at)
            VALUES
            (:doctor_id, :contact_type, :contact_value, :code_hash, :expires_at, 0, 0, :created_at)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'doctor_id' => $doctorId,
            'contact_type' => $contactType,
            'contact_value' => $contactValue,
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (string)$this->pdo->lastInsertId();
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
