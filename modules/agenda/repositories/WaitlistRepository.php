<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

require_once __DIR__ . '/../../../api/_lib/db.php';

class WaitlistRepository
{
    private PDO $pdo;
    private ?string $table = null;
    private array $columnsCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['waitlist_entries_table'] ?? ''));
        if ($table === '') {
            throw new RuntimeException('waitlist table not ready');
        }
        $this->table = $this->sanitizeIdentifier($table);
        if ($this->table === '') {
            throw new RuntimeException('waitlist table not ready');
        }
    }

    public function listEntries(array $filters): array
    {
        $this->ensureTable();

        $builder = [];
        $params = [];

        if (!empty($filters['doctor_id'])) {
            $builder[] = 'doctor_id = :doctor_id';
            $params['doctor_id'] = $filters['doctor_id'];
        }
        if (!empty($filters['consultorio_id'])) {
            $builder[] = 'consultorio_id = :consultorio_id';
            $params['consultorio_id'] = $filters['consultorio_id'];
        }
        if (!empty($filters['status'])) {
            $builder[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        $sql = "SELECT * FROM {$this->table}";
        if (!empty($builder)) {
            $sql .= ' WHERE ' . implode(' AND ', $builder);
        }
        $sql .= ' ORDER BY created_at ASC LIMIT 500';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createEntry(array $data): array
    {
        $this->ensureTable();
        $entryId = $this->generateId();
        $payload = array_merge($data, ['id' => $entryId, 'status' => $data['status'] ?? 'active']);
        $this->insert($this->table, $payload);
        $entry = $this->getById($entryId);
        if (!$entry) {
            throw new RuntimeException('waitlist entry not found after insert');
        }
        return $entry;
    }

    public function getById(string $id): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(sprintf('SELECT * FROM %s WHERE id = :id LIMIT 1', $this->table));
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatus(string $id, string $status): array
    {
        return $this->updateEntry($id, ['status' => $status]);
    }

    public function updateEntry(string $id, array $updates): array
    {
        $this->ensureTable();
        $columns = $this->getColumns($this->table);
        $updateColumns = array_intersect_key($updates, array_flip($columns));
        if (empty($updateColumns)) {
            throw new RuntimeException('no columns available for waitlist update');
        }
        $set = [];
        foreach ($updateColumns as $column => $value) {
            $set[] = "{$column} = :{$column}";
        }
        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $this->table, implode(',', $set));
        $stmt = $this->pdo->prepare($sql);
        foreach ($updateColumns as $column => $value) {
            $stmt->bindValue(":{$column}", $value);
        }
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $entry = $this->getById($id);
        if (!$entry) {
            throw new RuntimeException('waitlist entry not found after update');
        }
        return $entry;
    }

    private function insert(string $table, array $data): void
    {
        $columns = $this->getColumns($table);
        $available = array_intersect_key($data, array_flip($columns));
        if (empty($available)) {
            throw new RuntimeException('no columns available for insert');
        }
        $placeholders = array_map(fn($col) => ':' . $col, array_keys($available));
        $sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", $table, implode(',', array_keys($available)), implode(',', $placeholders));
        $stmt = $this->pdo->prepare($sql);
        foreach ($available as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->execute();
    }

    private function ensureTable(): void
    {
        if (!$this->table || !$this->tableExists($this->table)) {
            throw new RuntimeException('waitlist table not ready');
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function getColumns(string $table): array
    {
        if (!isset($this->columnsCache[$table])) {
            $stmt = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table');
            $stmt->execute(['table' => $table]);
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->columnsCache[$table] = $columns;
        }
        return $this->columnsCache[$table];
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

    private function sanitizeIdentifier(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?: '';
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(12));
    }
}
