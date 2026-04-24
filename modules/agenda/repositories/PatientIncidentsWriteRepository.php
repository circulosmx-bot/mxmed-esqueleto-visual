<?php
namespace Agenda\Repositories;

use DateTime;
use DateTimeZone;
use PDO;
use RuntimeException;

class PatientIncidentsWriteRepository
{
    private PDO $pdo;
    private ?string $table = null;
    private array $columnsCache = [];

    private const TIMEZONE = 'America/Mexico_City';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['patient_incidents_table'] ?? ''));
        if ($table !== '') {
            $this->table = $table;
        }
    }

    public function appendIncident(array $data): array
    {
        $this->ensureTable();
        $columns = $this->getColumns($this->table);
        $prepared = [];
        $now = (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');

        foreach ([
            'patient_id' => fn() => $data['patient_id'],
            'doctor_id' => fn() => $data['doctor_id'],
            'appointment_id' => fn() => $data['appointment_id'],
            'incident_type' => fn() => $data['incident_type'],
            'origin' => fn() => $data['origin'],
            'created_at' => fn() => $now,
        ] as $column => $valueThunk) {
            if (in_array($column, $columns, true)) {
                $prepared[$column] = $valueThunk();
            }
        }

        if (empty($prepared)) {
            throw new RuntimeException('patient incidents not ready');
        }

        $this->insert($prepared);

        return [
            'incident_type' => $data['incident_type'],
            'origin' => $data['origin'],
            'created_at' => $now,
        ];
    }

    private function insert(array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($column) => ':' . $column, $columns);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->table, implode(',', $columns), implode(',', $placeholders));
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->execute();
    }

    private function ensureTable(): void
    {
        if (!$this->table || !$this->tableExists($this->table)) {
            throw new RuntimeException('patient incidents not ready');
        }
    }

    private function getColumns(string $table): array
    {
        if (!isset($this->columnsCache[$table])) {
            $stmt = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table');
            $stmt->execute(['table' => $table]);
            $this->columnsCache[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        return $this->columnsCache[$table];
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
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
}
