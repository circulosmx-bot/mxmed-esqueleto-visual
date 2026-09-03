<?php
declare(strict_types=1);

namespace Media\Repositories;

use PDO;
use RuntimeException;

final class LogoReferenceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function physician(string $doctorId): array
    {
        $stmt = $this->pdo->prepare('SELECT doctor_id, display_name, logo_url FROM profiles_doctors WHERE doctor_id = :doctor_id LIMIT 1');
        $stmt->execute(['doctor_id' => $doctorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('physician_profile_not_found');
        }
        return $row;
    }

    public function consultorio(string $doctorId, string $consultorioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT doctor_id, consultorio_id, titulo, grupo_nombre, logo_url
               FROM consultorios
              WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id
              LIMIT 1'
        );
        $stmt->execute(['doctor_id' => $doctorId, 'consultorio_id' => $consultorioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('consultorio_not_found');
        }
        return $row;
    }

    public function updatePhysician(string $doctorId, ?string $publicUrl): void
    {
        $stmt = $this->pdo->prepare('UPDATE profiles_doctors SET logo_url = :logo_url, updated_at = CURRENT_TIMESTAMP WHERE doctor_id = :doctor_id');
        $stmt->execute(['logo_url' => $publicUrl, 'doctor_id' => $doctorId]);
    }

    public function updateConsultorio(string $doctorId, string $consultorioId, ?string $publicUrl): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE consultorios SET logo_url = :logo_url, updated_at = CURRENT_TIMESTAMP
              WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id'
        );
        $stmt->execute([
            'logo_url' => $publicUrl,
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
        ]);
    }

    public function countReferences(string $publicUrl): int
    {
        $profileStmt = $this->pdo->prepare('SELECT COUNT(*) FROM profiles_doctors WHERE logo_url = :public_url');
        $profileStmt->execute(['public_url' => $publicUrl]);
        $consultorioStmt = $this->pdo->prepare('SELECT COUNT(*) FROM consultorios WHERE logo_url = :public_url');
        $consultorioStmt->execute(['public_url' => $publicUrl]);
        return (int)$profileStmt->fetchColumn() + (int)$consultorioStmt->fetchColumn();
    }
}
