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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'enrichRescheduleConsultorioTrace'], $rows);
    }

    private function enrichRescheduleConsultorioTrace(array $row): array
    {
        if (($row['event_type'] ?? '') !== 'appointment_rescheduled') {
            return $row;
        }

        $trace = $this->extractConsultorioTraceFromNotes($row['notes'] ?? null);
        $row['from_consultorio_id'] = $row['from_consultorio_id'] ?? ($trace['from_consultorio_id'] ?? null);
        $row['to_consultorio_id'] = $row['to_consultorio_id'] ?? ($trace['to_consultorio_id'] ?? null);
        return $row;
    }

    private function extractConsultorioTraceFromNotes($notes): array
    {
        if (!is_string($notes)) {
            return [];
        }
        $trimmed = trim($notes);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return [];
        }

        $from = isset($decoded['from_consultorio_id']) ? trim((string)$decoded['from_consultorio_id']) : '';
        $to = isset($decoded['to_consultorio_id']) ? trim((string)$decoded['to_consultorio_id']) : '';

        return [
            'from_consultorio_id' => $from !== '' ? $from : null,
            'to_consultorio_id' => $to !== '' ? $to : null,
        ];
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
