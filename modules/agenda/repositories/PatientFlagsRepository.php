<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class PatientFlagsRepository
{
    private PDO $pdo;
    private ?string $table = null;
    private bool $enabled = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['patient_flags_table'] ?? ''));
        $this->enabled = $table !== '';
        if ($this->enabled) {
            $this->table = $table;
        }
    }

    public function listByPatientId(string $patientId, bool $activeOnly, int $limit): array
    {
        $this->ensureTable();

        $sql = sprintf('SELECT * FROM %s WHERE patient_id = :patient_id', $this->table);
        if ($activeOnly) {
            $sql .= ' AND (expires_at IS NULL OR expires_at >= NOW())';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function ensureTable(): void
    {
        if (!$this->enabled || !$this->table || !$this->tableExists($this->table)) {
            throw new RuntimeException('patient flags not ready');
        }
    }

    private function loadConfig(): array
    {
        $path = __DIR__ . '/../config/agenda.php';
        if (!is_file($path)) {
            return [];
        }
        $config = require $path;
        return is_array($config) ? $config : [];
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
