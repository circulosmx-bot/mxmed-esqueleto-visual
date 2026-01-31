<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class ConsultoriosRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function ensureTable(): void
    {
        if (!$this->tableExists('consultorios')) {
            throw new RuntimeException('consultorios table not identified yet');
        }
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listByDoctor(string $doctorId): array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('SELECT * FROM consultorios WHERE doctor_id = :doctor_id');
        $stmt->execute(['doctor_id' => $doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
