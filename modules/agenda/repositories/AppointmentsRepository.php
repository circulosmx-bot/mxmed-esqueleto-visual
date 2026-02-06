<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class AppointmentsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function ensureTable(): void
    {
        if (!$this->tableExists('appointments')) {
            throw new RuntimeException('appointments table not ready');
        }
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listByRange(string $from, string $to, ?string $doctorId = null, ?string $consultorioId = null, int $limit = 200): array
    {
        $this->ensureTable();
        $sql = 'SELECT appointment_id, doctor_id, consultorio_id, patient_id, start_at, end_at, modality, status, price_amount FROM appointments WHERE start_at >= :from AND end_at <= :to';
        $params = ['from' => $from, 'to' => $to];

        if ($doctorId) {
            $sql .= ' AND doctor_id = :doctor_id';
            $params['doctor_id'] = $doctorId;
        }
        if ($consultorioId) {
            $sql .= ' AND consultorio_id = :consultorio_id';
            $params['consultorio_id'] = $consultorioId;
        }

        $sql .= ' ORDER BY start_at ASC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(string $id): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('SELECT * FROM appointments WHERE appointment_id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
