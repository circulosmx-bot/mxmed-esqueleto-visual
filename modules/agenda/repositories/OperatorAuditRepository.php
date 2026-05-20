<?php
declare(strict_types=1);

namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class OperatorAuditRepository
{
    private PDO $pdo;
    private string $table;
    private array $columnsCache = [];

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $safeTable = $this->sanitizeIdentifier($table);
        if ($safeTable === '') {
            throw new RuntimeException('operator audit table not ready');
        }
        $this->table = $safeTable;
    }

    public function listByDoctor(string $doctorId, int $limit = 120): array
    {
        $this->ensureTable();
        $safeLimit = max(1, min(500, $limit));
        $sql = sprintf(
            'SELECT * FROM %s WHERE doctor_id = :doctor_id ORDER BY at DESC LIMIT :limit',
            $this->table
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':doctor_id', $doctorId);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertEvent(array $payload): array
    {
        $this->ensureTable();
        $eventId = trim((string)($payload['event_id'] ?? ''));
        if ($eventId === '') {
            $eventId = bin2hex(random_bytes(12));
        }
        $insertPayload = [
            'event_id' => $eventId,
            'doctor_id' => trim((string)($payload['doctor_id'] ?? '')),
            'operator_id' => trim((string)($payload['operator_id'] ?? '')),
            'event_type' => trim((string)($payload['event_type'] ?? 'operator_event')),
            'module_name' => trim((string)($payload['module_name'] ?? 'Operadores')),
            'action_label' => trim((string)($payload['action_label'] ?? 'Actividad registrada')),
            'entity_label' => trim((string)($payload['entity_label'] ?? '')),
            'actor_role' => trim((string)($payload['actor_role'] ?? '')),
            'actor_id' => trim((string)($payload['actor_id'] ?? '')),
            'notes' => trim((string)($payload['notes'] ?? '')),
        ];
        $at = trim((string)($payload['at'] ?? ''));
        if ($at !== '') {
            $insertPayload['at'] = $at;
        }
        $this->insert($this->table, $insertPayload);
        $sql = sprintf('SELECT * FROM %s WHERE event_id = :event_id LIMIT 1', $this->table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('operator audit event not found after insert');
        }
        return $row;
    }

    private function insert(string $table, array $data): void
    {
        $columns = $this->getColumns($table);
        $available = array_intersect_key($data, array_flip($columns));
        if (empty($available)) {
            throw new RuntimeException('no columns available for operator audit insert');
        }
        foreach ($available as $column => $value) {
            if ($value === '') {
                $available[$column] = null;
            }
        }
        $placeholders = array_map(static fn($col) => ':' . $col, array_keys($available));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(',', array_keys($available)),
            implode(',', $placeholders)
        );
        $stmt = $this->pdo->prepare($sql);
        foreach ($available as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->execute();
    }

    private function ensureTable(): void
    {
        if (!$this->tableExists($this->table)) {
            throw new RuntimeException('operator audit table not ready');
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function getColumns(string $table): array
    {
        if (!isset($this->columnsCache[$table])) {
            $stmt = $this->pdo->prepare(
                'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $stmt->execute(['table' => $table]);
            $this->columnsCache[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        return $this->columnsCache[$table];
    }

    private function sanitizeIdentifier(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?: '';
    }
}
