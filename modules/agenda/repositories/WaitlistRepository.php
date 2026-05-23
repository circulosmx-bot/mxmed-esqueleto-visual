<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

require_once __DIR__ . '/../../../api/_lib/db.php';

class WaitlistRepository
{
    private const NOTES_AUDIT_MARKER = '_mxm_waitlist_audit_v1';
    private const ANY_CONSULTORIO_ID = '__all__';
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
            $consultorioId = trim((string)$filters['consultorio_id']);
            if ($consultorioId !== '') {
                if ($consultorioId === self::ANY_CONSULTORIO_ID) {
                    $builder[] = 'consultorio_id = :consultorio_id';
                    $params['consultorio_id'] = $consultorioId;
                } else {
                    $builder[] = '(consultorio_id = :consultorio_id OR consultorio_id = :consultorio_any)';
                    $params['consultorio_id'] = $consultorioId;
                    $params['consultorio_any'] = self::ANY_CONSULTORIO_ID;
                }
            }
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): array => $this->hydrateEntryRow($row), $rows);
    }

    public function createEntry(array $data, array $audit = []): array
    {
        $this->ensureTable();
        $entryId = $this->generateId();
        $payload = array_merge($data, ['id' => $entryId, 'status' => $data['status'] ?? 'active']);
        $payload = $this->applyAuditForCreate($payload, $entryId, $audit);
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
        if (!$row) {
            return null;
        }
        return $this->hydrateEntryRow($row);
    }

    public function updateStatus(string $id, string $status, array $audit = []): array
    {
        return $this->updateEntry($id, ['status' => $status], $audit);
    }

    public function updateEntry(string $id, array $updates, array $audit = []): array
    {
        $this->ensureTable();
        $currentRaw = $this->getByIdRaw($id);
        if (!$currentRaw) {
            throw new RuntimeException('waitlist entry not found after update');
        }
        $updates = $this->applyAuditForUpdate($currentRaw, $updates, $id, $audit);
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

    private function getByIdRaw(string $id): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(sprintf('SELECT * FROM %s WHERE id = :id LIMIT 1', $this->table));
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function hydrateEntryRow(array $row): array
    {
        $notesInfo = $this->extractNotesInfo($row['notes'] ?? null);
        $row['notes'] = $notesInfo['notes_text'];

        $actorRole = $this->normalizeText($row['actor_role'] ?? '') ?: $this->normalizeText($notesInfo['audit']['actor_role'] ?? '');
        $actorId = $this->normalizeText($row['actor_id'] ?? '') ?: $this->normalizeText($notesInfo['audit']['actor_id'] ?? '');
        $channelOrigin = $this->normalizeText($row['channel_origin'] ?? '') ?: $this->normalizeText($notesInfo['audit']['channel_origin'] ?? '');
        $createdByRole = $this->normalizeText($row['created_by_role'] ?? '') ?: $this->normalizeText($notesInfo['audit']['created_by_role'] ?? $actorRole);
        $createdById = $this->normalizeText($row['created_by_id'] ?? '') ?: $this->normalizeText($notesInfo['audit']['created_by_id'] ?? $actorId);
        $actorDisplayName = $this->normalizeText($notesInfo['audit']['actor_display_name'] ?? '');
        $action = $this->normalizeText($notesInfo['audit']['action'] ?? '');
        $entityType = $this->normalizeText($notesInfo['audit']['entity_type'] ?? '');
        $entityId = $this->normalizeText($notesInfo['audit']['entity_id'] ?? '') ?: $this->normalizeText($row['id'] ?? '');
        $occurredAt = $this->normalizeText($notesInfo['audit']['occurred_at'] ?? '');
        $metadata = is_array($notesInfo['metadata']) ? $notesInfo['metadata'] : [];

        if ($actorRole !== '') {
            $row['actor_role'] = $actorRole;
        }
        if ($actorId !== '') {
            $row['actor_id'] = $actorId;
        }
        if ($channelOrigin !== '') {
            $row['channel_origin'] = $channelOrigin;
        }
        if ($createdByRole !== '') {
            $row['created_by_role'] = $createdByRole;
        }
        if ($createdById !== '') {
            $row['created_by_id'] = $createdById;
        }
        if ($actorDisplayName !== '') {
            $row['actor_display_name'] = $actorDisplayName;
        }
        if ($action !== '') {
            $row['action'] = $action;
        }
        if ($entityType !== '') {
            $row['entity_type'] = $entityType;
        }
        if ($entityId !== '') {
            $row['entity_id'] = $entityId;
        }
        if ($occurredAt !== '') {
            $row['occurred_at'] = $occurredAt;
        }
        $row['metadata'] = $metadata;

        return $row;
    }

    private function applyAuditForCreate(array $payload, string $entryId, array $audit): array
    {
        $columns = $this->getColumns($this->table);
        $normalizedAudit = $this->normalizeAudit($audit, $entryId, 'waitlist_created');
        return $this->applyAuditPersistence($payload, null, $columns, $normalizedAudit);
    }

    private function applyAuditForUpdate(array $currentRaw, array $updates, string $entryId, array $audit): array
    {
        if (empty($audit)) {
            return $updates;
        }
        $columns = $this->getColumns($this->table);
        $normalizedAudit = $this->normalizeAudit($audit, $entryId, 'waitlist_updated');
        return $this->applyAuditPersistence($updates, $currentRaw, $columns, $normalizedAudit);
    }

    private function applyAuditPersistence(array $target, ?array $currentRaw, array $columns, array $audit): array
    {
        $hasActorColumn = $this->hasAnyColumn($columns, ['actor_role', 'actor_id', 'channel_origin', 'created_by_role', 'created_by_id']);
        if ($this->hasColumn($columns, 'actor_role')) {
            $target['actor_role'] = $audit['actor_role'];
        }
        if ($this->hasColumn($columns, 'actor_id')) {
            $target['actor_id'] = $audit['actor_id'];
        }
        if ($this->hasColumn($columns, 'channel_origin')) {
            $target['channel_origin'] = $audit['channel_origin'];
        }
        if ($this->hasColumn($columns, 'created_by_role')) {
            $target['created_by_role'] = $audit['created_by_role'];
        }
        if ($this->hasColumn($columns, 'created_by_id')) {
            $target['created_by_id'] = $audit['created_by_id'];
        }
        if ($this->hasColumn($columns, 'metadata')) {
            $encodedMetadata = $this->encodeJson($audit['metadata']);
            if ($encodedMetadata !== null) {
                $target['metadata'] = $encodedMetadata;
            }
        }

        // Fallback sin migración: persistimos auditoría dentro de notes como envolvente JSON.
        if (!$hasActorColumn && $this->hasColumn($columns, 'notes')) {
            $currentNotesRaw = $currentRaw['notes'] ?? null;
            $currentNotesInfo = $this->extractNotesInfo($currentNotesRaw);
            $noteTextFromTarget = array_key_exists('notes', $target)
                ? $this->normalizeNullableText($target['notes'])
                : $currentNotesInfo['notes_text'];

            $mergedMetadata = $currentNotesInfo['metadata'];
            if (is_array($audit['metadata']) && !empty($audit['metadata'])) {
                $mergedMetadata = array_merge($mergedMetadata, $audit['metadata']);
            }
            $target['notes'] = $this->buildNotesEnvelope($noteTextFromTarget, $audit, $mergedMetadata);
        }

        return $target;
    }

    private function normalizeAudit(array $audit, string $entityId, string $defaultAction): array
    {
        $actorRole = $this->normalizeText($audit['actor_role'] ?? '') ?: $this->normalizeText($audit['created_by_role'] ?? '') ?: 'doctor';
        $actorId = $this->normalizeText($audit['actor_id'] ?? '') ?: $this->normalizeText($audit['created_by_id'] ?? '') ?: '';
        $createdByRole = $this->normalizeText($audit['created_by_role'] ?? '') ?: $actorRole;
        $createdById = $this->normalizeText($audit['created_by_id'] ?? '') ?: $actorId;
        $channelOrigin = $this->normalizeText($audit['channel_origin'] ?? '') ?: ($actorRole !== '' ? $actorRole : 'agenda_internal');
        $action = $this->normalizeText($audit['action'] ?? '') ?: $defaultAction;
        $entityType = $this->normalizeText($audit['entity_type'] ?? '') ?: 'waitlist_entry';
        $entityIdNormalized = $this->normalizeText($audit['entity_id'] ?? '') ?: $entityId;
        $occurredAt = $this->normalizeText($audit['occurred_at'] ?? '') ?: gmdate('c');
        $actorDisplayName = $this->normalizeText($audit['actor_display_name'] ?? '');
        $metadata = is_array($audit['metadata'] ?? null) ? $audit['metadata'] : [];
        $metadata = array_merge($metadata, [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityIdNormalized,
        ]);

        return [
            'actor_role' => $actorRole,
            'actor_id' => $actorId,
            'actor_display_name' => $actorDisplayName,
            'channel_origin' => $channelOrigin,
            'created_by_role' => $createdByRole,
            'created_by_id' => $createdById,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityIdNormalized,
            'occurred_at' => $occurredAt,
            'metadata' => $metadata,
        ];
    }

    private function buildNotesEnvelope(?string $notesText, array $audit, array $metadata = []): string
    {
        $payload = [
            self::NOTES_AUDIT_MARKER => [
                'actor_role' => $audit['actor_role'] ?? null,
                'actor_id' => $audit['actor_id'] ?? null,
                'actor_display_name' => $audit['actor_display_name'] ?? null,
                'channel_origin' => $audit['channel_origin'] ?? null,
                'created_by_role' => $audit['created_by_role'] ?? null,
                'created_by_id' => $audit['created_by_id'] ?? null,
                'action' => $audit['action'] ?? null,
                'entity_type' => $audit['entity_type'] ?? null,
                'entity_id' => $audit['entity_id'] ?? null,
                'occurred_at' => $audit['occurred_at'] ?? null,
            ],
            'metadata' => $metadata,
            'notes_text' => $notesText,
        ];
        $encoded = $this->encodeJson($payload);
        if ($encoded !== null) {
            return $encoded;
        }
        return (string)($notesText ?? '');
    }

    private function extractNotesInfo($raw): array
    {
        $notesText = $this->normalizeNullableText($raw);
        $audit = [];
        $metadata = [];
        if (!is_string($raw) || trim($raw) === '') {
            return ['notes_text' => $notesText, 'audit' => $audit, 'metadata' => $metadata];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded[self::NOTES_AUDIT_MARKER]) || !is_array($decoded[self::NOTES_AUDIT_MARKER])) {
            return ['notes_text' => $notesText, 'audit' => $audit, 'metadata' => $metadata];
        }

        $audit = $decoded[self::NOTES_AUDIT_MARKER];
        if (isset($decoded['metadata']) && is_array($decoded['metadata'])) {
            $metadata = $decoded['metadata'];
        }
        $notesText = $this->normalizeNullableText($decoded['notes_text'] ?? null);

        return ['notes_text' => $notesText, 'audit' => $audit, 'metadata' => $metadata];
    }

    private function hasColumn(array $columns, string $column): bool
    {
        return in_array($column, $columns, true);
    }

    private function hasAnyColumn(array $columns, array $candidates): bool
    {
        foreach ($candidates as $column) {
            if ($this->hasColumn($columns, (string)$column)) {
                return true;
            }
        }
        return false;
    }

    private function encodeJson(array $payload): ?string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return null;
        }
        return $encoded;
    }

    private function normalizeText($value): string
    {
        return trim((string)($value ?? ''));
    }

    private function normalizeNullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
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
