<?php
namespace Agenda\Repositories;

use DateTime;
use DateTimeZone;
use PDO;
use RuntimeException;

class PatientFlagsWriteRepository
{
    private PDO $pdo;
    private ?string $table = null;
    private array $columnsCache = [];

    private const TIMEZONE = 'America/Mexico_City';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['patient_flags_table'] ?? ''));
        if ($table !== '') {
            $this->table = $table;
        }
    }

    public function listByPatientId(string $patientId, bool $activeOnly, int $limit): array
    {
        $this->ensureTable();
        $columns = $this->getColumns($this->table);
        $hasExpires = in_array('expires_at', $columns, true);
        $hasCreated = in_array('created_at', $columns, true);

        $sql = sprintf('SELECT * FROM %s WHERE patient_id = :patient_id', $this->table);
        if ($activeOnly && $hasExpires) {
            $sql .= ' AND (expires_at IS NULL OR expires_at >= NOW())';
        }
        if ($hasCreated) {
            $sql .= ' ORDER BY created_at DESC';
        } else {
            $sql .= ' ORDER BY 1 DESC';
        }
        $sql .= ' LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function appendFlag(array $data): array
    {
        $this->ensureTable();
        $columns = $this->getColumns($this->table);
        $prepared = [];
        $now = (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
        foreach ([
            'flag_id' => fn() => bin2hex(random_bytes(12)),
            'patient_id' => fn() => $data['patient_id'],
            'flag_type' => fn() => $data['flag_type'],
            'reason_code' => fn() => $data['reason_code'] ?? null,
            'source_appointment_id' => fn() => $data['source_appointment_id'] ?? null,
            'created_at' => fn() => $now,
            'actor_role' => fn() => $data['actor_role'] ?? null,
            'actor_id' => fn() => $data['actor_id'] ?? null,
            'channel_origin' => fn() => $data['channel_origin'] ?? null,
            'notes' => fn() => $data['notes'] ?? null,
            'expires_at' => fn() => $data['expires_at'] ?? null,
        ] as $column => $valueThunk) {
            if (in_array($column, $columns, true)) {
                $prepared[$column] = $valueThunk();
            }
        }
        if (empty($prepared)) {
            throw new RuntimeException('patient flags not ready');
        }
        $this->insert($prepared);
        return [
            'flag_type' => $data['flag_type'],
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
            throw new RuntimeException('patient flags not ready');
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
