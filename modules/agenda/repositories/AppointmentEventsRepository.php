<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class AppointmentEventsRepository
{
    private PDO $pdo;
    private ?string $table = null;
    private bool $enabled = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['appointment_events_table'] ?? ''));
        $this->enabled = $table !== '';
        if ($this->enabled) {
            $this->table = $table;
        }
    }

    public function listByAppointmentId(string $appointmentId, int $limit): array
    {
        $this->ensureTable();

        $sql = sprintf('SELECT * FROM %s WHERE appointment_id = :appointment_id ORDER BY timestamp ASC LIMIT :limit', $this->table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':appointment_id', $appointmentId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function ensureTable(): void
    {
        if (!$this->enabled || !$this->table || !$this->tableExists($this->table)) {
            throw new RuntimeException('appointment events not ready');
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
