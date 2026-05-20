<?php
declare(strict_types=1);

namespace Agenda\Repositories;

use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/OperatorAuditRepository.php';

class OperatorsRepository
{
    private const MAX_ALLOWED = 3;
    private const COUNTABLE_STATUSES = ['active', 'paused', 'pending'];
    private const ALL_STATUSES = ['active', 'paused', 'pending', 'archived'];

    private PDO $pdo;
    private string $operatorsTable;
    private string $permissionsTable;
    private string $auditTable;
    private array $columnsCache = [];
    private OperatorAuditRepository $auditRepository;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();

        $operatorsTable = trim((string)($config['operators_table'] ?? 'agenda_operators'));
        $permissionsTable = trim((string)($config['operator_permissions_table'] ?? 'agenda_operator_permissions'));
        $auditTable = trim((string)($config['operator_audit_events_table'] ?? 'agenda_operator_audit_events'));

        $this->operatorsTable = $this->sanitizeIdentifier($operatorsTable);
        $this->permissionsTable = $this->sanitizeIdentifier($permissionsTable);
        $this->auditTable = $this->sanitizeIdentifier($auditTable);

        if ($this->operatorsTable === '' || $this->permissionsTable === '' || $this->auditTable === '') {
            throw new RuntimeException('operators tables not ready');
        }

        $this->auditRepository = new OperatorAuditRepository($this->pdo, $this->auditTable);
    }

    public function readStateByDoctor(string $doctorId, int $auditLimit = 120): array
    {
        $this->ensureTables();
        $operators = $this->fetchOperatorsByDoctor($doctorId, false);
        $archivedOperators = $this->fetchOperatorsByDoctor($doctorId, true);
        $permissionsByOperator = $this->fetchPermissionsByDoctor($doctorId);

        $operators = array_map(
            fn(array $operator): array => $this->withPermissions($operator, $permissionsByOperator),
            $operators
        );
        $archivedOperators = array_map(
            fn(array $operator): array => $this->withPermissions($operator, $permissionsByOperator),
            $archivedOperators
        );

        $summary = $this->buildSummary($operators, $archivedOperators);
        $auditTrail = $this->formatAuditTrail(
            $this->auditRepository->listByDoctor($doctorId, $auditLimit)
        );

        return [
            'operators' => $operators,
            'archived_operators' => $archivedOperators,
            'audit_trail' => $auditTrail,
            'summary' => $summary,
            'limits' => [
                'max_allowed' => self::MAX_ALLOWED,
            ],
        ];
    }

    public function createOperator(string $doctorId, array $payload, array $actorContext = []): array
    {
        $this->ensureTables();

        $status = strtolower(trim((string)($payload['status'] ?? 'pending')));
        if (!in_array($status, self::ALL_STATUSES, true)) {
            $status = 'pending';
        }
        if ($status === 'archived') {
            $status = 'pending';
        }

        $operatorId = trim((string)($payload['operator_id'] ?? ''));
        if ($operatorId === '') {
            $operatorId = bin2hex(random_bytes(12));
        }

        $insertPayload = [
            'operator_id' => $operatorId,
            'doctor_id' => $doctorId,
            'operator_label' => trim((string)($payload['operator_label'] ?? '')),
            'alias' => trim((string)($payload['alias'] ?? '')),
            'alias_normalized' => trim((string)($payload['alias_normalized'] ?? '')),
            'full_name' => trim((string)($payload['full_name'] ?? '')),
            'phone' => trim((string)($payload['phone'] ?? '')),
            'email' => strtolower(trim((string)($payload['email'] ?? ''))),
            'gender' => strtolower(trim((string)($payload['gender'] ?? ''))),
            'role' => strtolower(trim((string)($payload['role'] ?? 'operator'))),
            'status' => $status,
            'login' => trim((string)($payload['login'] ?? '')),
            'login_normalized' => trim((string)($payload['login_normalized'] ?? '')),
            'temp_password_hash' => trim((string)($payload['temp_password_hash'] ?? '')),
            'force_password_change' => !empty($payload['force_password_change']) ? 1 : 0,
            'invitation_status' => strtolower(trim((string)($payload['invitation_status'] ?? 'pending'))),
            'operator_credentials_sent_at' => trim((string)($payload['operator_credentials_sent_at'] ?? '')),
            'last_access' => trim((string)($payload['last_access'] ?? '')),
            'archived_at' => '',
        ];

        $permissions = $this->sanitizePermissionKeys($payload['permissions'] ?? []);

        try {
            $this->pdo->beginTransaction();
            $this->ensureDoctorQuotaAvailableForCreate($doctorId, $status);
            $this->assertAliasIsUnique($doctorId, $insertPayload['alias_normalized']);
            $this->assertLoginIsUnique($doctorId, $insertPayload['login_normalized']);
            $this->assertOperatorIdIsUnique($insertPayload['operator_id']);

            $this->insert($this->operatorsTable, $insertPayload);
            $this->replacePermissions($doctorId, $operatorId, $permissions);

            $auditEvent = $this->auditRepository->insertEvent([
                'doctor_id' => $doctorId,
                'operator_id' => $operatorId,
                'event_type' => 'operator_created',
                'module_name' => 'Operadores',
                'action_label' => 'Operador guardado',
                'entity_label' => $insertPayload['alias'] !== '' ? $insertPayload['alias'] : $insertPayload['full_name'],
                'actor_role' => trim((string)($actorContext['mode'] ?? 'system')),
                'actor_id' => trim((string)($actorContext['user_id'] ?? 'system')),
                'notes' => '',
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $state = $this->readStateByDoctor($doctorId, 120);
        $operator = $this->findOperatorById($doctorId, $operatorId);
        if (!$operator) {
            throw new RuntimeException('operator not found after insert');
        }
        $permissionsByOperator = $this->fetchPermissionsByDoctor($doctorId);
        $operator = $this->withPermissions($operator, $permissionsByOperator);

        return [
            'operator' => $operator,
            'summary' => $state['summary'],
            'limits' => $state['limits'],
            'audit_event' => $this->formatAuditEventRow($auditEvent),
        ];
    }

    public function mutateOperatorStatus(
        string $doctorId,
        string $operatorId,
        string $mutation,
        array $actorContext = [],
        array $options = []
    ): array {
        $this->ensureTables();
        $mutationKey = strtolower(trim($mutation));
        $spec = $this->resolveMutationSpec($mutationKey);
        if (!$spec) {
            throw new RuntimeException('invalid_mutation');
        }

        $row = $this->findOperatorRowForUpdate($doctorId, $operatorId);
        if (!$row) {
            throw new RuntimeException('operator_not_found');
        }
        $currentStatus = strtolower(trim((string)($row['status'] ?? '')));
        if (!in_array($currentStatus, self::ALL_STATUSES, true)) {
            $currentStatus = 'pending';
        }
        if (!in_array($currentStatus, $spec['allowed_from'], true)) {
            throw new RuntimeException('invalid_transition');
        }

        $targetStatus = $spec['target_status'];
        if ($mutationKey === 'restore') {
            $requested = strtolower(trim((string)($options['restore_status'] ?? '')));
            if (in_array($requested, ['active', 'pending'], true)) {
                $targetStatus = $requested;
            }
        }

        try {
            $this->pdo->beginTransaction();

            if ($currentStatus === 'archived' && in_array($targetStatus, self::COUNTABLE_STATUSES, true)) {
                $this->ensureDoctorQuotaAvailableForCreate($doctorId, $targetStatus);
                $aliasNormalized = trim((string)($row['alias_normalized'] ?? ''));
                $loginNormalized = trim((string)($row['login_normalized'] ?? ''));
                $this->assertAliasIsUnique($doctorId, $aliasNormalized, $operatorId);
                $this->assertLoginIsUnique($doctorId, $loginNormalized, $operatorId);
            }

            $this->updateStatus($doctorId, $operatorId, $targetStatus);

            $auditNotes = $this->buildAuditNotes($options);
            $auditEvent = $this->auditRepository->insertEvent([
                'doctor_id' => $doctorId,
                'operator_id' => $operatorId,
                'event_type' => $spec['event_type'],
                'module_name' => 'Operadores',
                'action_label' => $spec['action_label'],
                'entity_label' => trim((string)($row['alias'] ?? '')) !== ''
                    ? trim((string)($row['alias'] ?? ''))
                    : trim((string)($row['full_name'] ?? '')),
                'actor_role' => trim((string)($actorContext['mode'] ?? 'system')),
                'actor_id' => trim((string)($actorContext['user_id'] ?? 'system')),
                'notes' => $auditNotes,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $state = $this->readStateByDoctor($doctorId, 120);
        $operator = $this->findOperatorById($doctorId, $operatorId);
        if (!$operator) {
            throw new RuntimeException('operator not found after mutate');
        }
        $permissionsByOperator = $this->fetchPermissionsByDoctor($doctorId);
        $operator = $this->withPermissions($operator, $permissionsByOperator);

        return [
            'operator' => $operator,
            'summary' => $state['summary'],
            'limits' => $state['limits'],
            'audit_event' => $this->formatAuditEventRow($auditEvent),
            'mutation' => $mutationKey,
            'from_status' => $currentStatus,
            'to_status' => $targetStatus,
        ];
    }

    private function fetchOperatorsByDoctor(string $doctorId, bool $archived): array
    {
        $where = $archived
            ? 'doctor_id = :doctor_id AND status = :status'
            : 'doctor_id = :doctor_id AND status <> :status';
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s ORDER BY created_at ASC LIMIT 500',
            $this->operatorsTable,
            $where
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'doctor_id' => $doctorId,
            'status' => 'archived',
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map([$this, 'formatOperatorRow'], $rows);
    }

    private function findOperatorById(string $doctorId, string $operatorId): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE doctor_id = :doctor_id AND operator_id = :operator_id LIMIT 1',
            $this->operatorsTable
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'doctor_id' => $doctorId,
            'operator_id' => $operatorId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->formatOperatorRow($row) : null;
    }

    private function findOperatorRowForUpdate(string $doctorId, string $operatorId): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE doctor_id = :doctor_id AND operator_id = :operator_id LIMIT 1 FOR UPDATE',
            $this->operatorsTable
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'doctor_id' => $doctorId,
            'operator_id' => $operatorId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function fetchPermissionsByDoctor(string $doctorId): array
    {
        $sql = sprintf(
            'SELECT operator_id, permission_key, is_enabled FROM %s WHERE doctor_id = :doctor_id ORDER BY permission_id ASC',
            $this->permissionsTable
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doctor_id' => $doctorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $operatorId = trim((string)($row['operator_id'] ?? ''));
            $permissionKey = strtolower(trim((string)($row['permission_key'] ?? '')));
            $isEnabled = (int)($row['is_enabled'] ?? 1) === 1;
            if ($operatorId === '' || $permissionKey === '' || !$isEnabled) {
                continue;
            }
            if (!isset($map[$operatorId])) {
                $map[$operatorId] = [];
            }
            if (!in_array($permissionKey, $map[$operatorId], true)) {
                $map[$operatorId][] = $permissionKey;
            }
        }
        return $map;
    }

    private function replacePermissions(string $doctorId, string $operatorId, array $permissionKeys): void
    {
        $deleteSql = sprintf(
            'DELETE FROM %s WHERE doctor_id = :doctor_id AND operator_id = :operator_id',
            $this->permissionsTable
        );
        $deleteStmt = $this->pdo->prepare($deleteSql);
        $deleteStmt->execute([
            'doctor_id' => $doctorId,
            'operator_id' => $operatorId,
        ]);

        if (empty($permissionKeys)) {
            return;
        }
        foreach ($permissionKeys as $permissionKey) {
            $this->insert($this->permissionsTable, [
                'doctor_id' => $doctorId,
                'operator_id' => $operatorId,
                'permission_key' => $permissionKey,
                'is_enabled' => 1,
            ]);
        }
    }

    private function sanitizePermissionKeys($permissions): array
    {
        $allowed = ['agenda', 'patients', 'billing', 'payment_proof'];
        $seen = [];
        foreach ((array)$permissions as $item) {
            $key = strtolower(trim((string)$item));
            if ($key === '' || !in_array($key, $allowed, true)) {
                continue;
            }
            $seen[$key] = true;
        }
        return array_keys($seen);
    }

    private function buildSummary(array $operators, array $archivedOperators): array
    {
        $activeCount = 0;
        $pausedCount = 0;
        $pendingCount = 0;
        foreach ($operators as $operator) {
            $status = strtolower(trim((string)($operator['status'] ?? '')));
            if ($status === 'active') {
                $activeCount += 1;
            } elseif ($status === 'paused') {
                $pausedCount += 1;
            } elseif ($status === 'pending') {
                $pendingCount += 1;
            }
        }
        $quotaUsed = $activeCount + $pausedCount + $pendingCount;

        return [
            'quota_used' => $quotaUsed,
            'quota_available' => max(0, self::MAX_ALLOWED - $quotaUsed),
            'active_count' => $activeCount,
            'pending_count' => $pendingCount,
            'paused_count' => $pausedCount,
            'archived_count' => count($archivedOperators),
            'max_allowed' => self::MAX_ALLOWED,
        ];
    }

    private function withPermissions(array $operator, array $permissionsByOperator): array
    {
        $operatorId = trim((string)($operator['operator_id'] ?? ''));
        $operator['permissions'] = $permissionsByOperator[$operatorId] ?? [];
        return $operator;
    }

    private function formatOperatorRow(array $row): array
    {
        return [
            'operator_id' => trim((string)($row['operator_id'] ?? '')),
            'doctor_id' => trim((string)($row['doctor_id'] ?? '')),
            'operator_label' => trim((string)($row['operator_label'] ?? '')),
            'alias' => trim((string)($row['alias'] ?? '')),
            'full_name' => trim((string)($row['full_name'] ?? '')),
            'phone' => trim((string)($row['phone'] ?? '')),
            'email' => strtolower(trim((string)($row['email'] ?? ''))),
            'gender' => strtolower(trim((string)($row['gender'] ?? ''))),
            'role' => strtolower(trim((string)($row['role'] ?? 'operator'))),
            'status' => strtolower(trim((string)($row['status'] ?? 'pending'))),
            'login' => trim((string)($row['login'] ?? '')),
            'force_password_change' => ((int)($row['force_password_change'] ?? 0) === 1),
            'invitation_status' => strtolower(trim((string)($row['invitation_status'] ?? 'pending'))),
            'operator_credentials_sent_at' => trim((string)($row['operator_credentials_sent_at'] ?? '')),
            'last_access' => trim((string)($row['last_access'] ?? '')),
            'archived_at' => trim((string)($row['archived_at'] ?? '')),
            'created_at' => trim((string)($row['created_at'] ?? '')),
            'updated_at' => trim((string)($row['updated_at'] ?? '')),
        ];
    }

    private function formatAuditTrail(array $rows): array
    {
        return array_map([$this, 'formatAuditEventRow'], $rows);
    }

    private function formatAuditEventRow(array $row): array
    {
        return [
            'event_id' => trim((string)($row['event_id'] ?? '')),
            'doctor_id' => trim((string)($row['doctor_id'] ?? '')),
            'operator_id' => trim((string)($row['operator_id'] ?? '')),
            'event_type' => trim((string)($row['event_type'] ?? '')),
            'module' => trim((string)($row['module_name'] ?? '')),
            'action' => trim((string)($row['action_label'] ?? '')),
            'entity' => trim((string)($row['entity_label'] ?? '')),
            'actor_role' => trim((string)($row['actor_role'] ?? '')),
            'actor_id' => trim((string)($row['actor_id'] ?? '')),
            'notes' => trim((string)($row['notes'] ?? '')),
            'at' => trim((string)($row['at'] ?? '')),
        ];
    }

    private function ensureDoctorQuotaAvailableForCreate(string $doctorId, string $nextStatus): void
    {
        if (!in_array($nextStatus, self::COUNTABLE_STATUSES, true)) {
            return;
        }
        $sql = sprintf(
            'SELECT COUNT(*) FROM %s WHERE doctor_id = :doctor_id AND status IN (\'active\',\'paused\',\'pending\') FOR UPDATE',
            $this->operatorsTable
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doctor_id' => $doctorId]);
        $usedCount = (int)$stmt->fetchColumn();
        if ($usedCount >= self::MAX_ALLOWED) {
            throw new RuntimeException('quota_limit_reached');
        }
    }

    private function assertAliasIsUnique(string $doctorId, string $aliasNormalized, ?string $excludeOperatorId = null): void
    {
        $params = [
            'doctor_id' => $doctorId,
            'archived' => 'archived',
            'alias' => $aliasNormalized,
        ];
        $excludeSql = '';
        if ($excludeOperatorId !== null && $excludeOperatorId !== '') {
            $excludeSql = ' AND operator_id <> :exclude_operator_id';
            $params['exclude_operator_id'] = $excludeOperatorId;
        }
        $sql = sprintf(
            'SELECT COUNT(*) FROM %s WHERE doctor_id = :doctor_id AND status <> :archived AND alias_normalized = :alias%s LIMIT 1',
            $this->operatorsTable,
            $excludeSql
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('alias_duplicated');
        }
    }

    private function assertLoginIsUnique(string $doctorId, string $loginNormalized, ?string $excludeOperatorId = null): void
    {
        $params = [
            'doctor_id' => $doctorId,
            'archived' => 'archived',
            'login' => $loginNormalized,
        ];
        $excludeSql = '';
        if ($excludeOperatorId !== null && $excludeOperatorId !== '') {
            $excludeSql = ' AND operator_id <> :exclude_operator_id';
            $params['exclude_operator_id'] = $excludeOperatorId;
        }
        $sql = sprintf(
            'SELECT COUNT(*) FROM %s WHERE doctor_id = :doctor_id AND status <> :archived AND login_normalized = :login%s LIMIT 1',
            $this->operatorsTable,
            $excludeSql
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('login_duplicated');
        }
    }

    private function assertOperatorIdIsUnique(string $operatorId): void
    {
        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE operator_id = :operator_id LIMIT 1', $this->operatorsTable);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['operator_id' => $operatorId]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('operator_id_duplicated');
        }
    }

    private function insert(string $table, array $data): void
    {
        $columns = $this->getColumns($table);
        $available = array_intersect_key($data, array_flip($columns));
        if (empty($available)) {
            throw new RuntimeException('no columns available for operator insert');
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

    private function ensureTables(): void
    {
        if (
            !$this->tableExists($this->operatorsTable)
            || !$this->tableExists($this->permissionsTable)
            || !$this->tableExists($this->auditTable)
        ) {
            throw new RuntimeException('operators tables not ready');
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

    private function resolveMutationSpec(string $mutation): ?array
    {
        $map = [
            'pause' => [
                'allowed_from' => ['active', 'pending'],
                'target_status' => 'paused',
                'event_type' => 'operator_paused',
                'action_label' => 'Acceso pausado',
            ],
            'reactivate' => [
                'allowed_from' => ['paused', 'pending'],
                'target_status' => 'active',
                'event_type' => 'operator_reactivated',
                'action_label' => 'Acceso reactivado',
            ],
            'archive' => [
                'allowed_from' => ['active', 'paused', 'pending'],
                'target_status' => 'archived',
                'event_type' => 'operator_archived',
                'action_label' => 'Operador archivado',
            ],
            'restore' => [
                'allowed_from' => ['archived'],
                'target_status' => 'pending',
                'event_type' => 'operator_restored',
                'action_label' => 'Operador restaurado',
            ],
        ];

        return $map[$mutation] ?? null;
    }

    private function updateStatus(string $doctorId, string $operatorId, string $targetStatus): void
    {
        $setChunks = ['status = :status', 'updated_at = CURRENT_TIMESTAMP'];
        $params = [
            'status' => $targetStatus,
            'doctor_id' => $doctorId,
            'operator_id' => $operatorId,
        ];
        if ($targetStatus === 'archived') {
            $setChunks[] = 'archived_at = CURRENT_TIMESTAMP';
        } else {
            $setChunks[] = 'archived_at = NULL';
        }
        if ($targetStatus === 'active') {
            $setChunks[] = 'invitation_status = :invitation_status';
            $params['invitation_status'] = 'active';
        } elseif ($targetStatus === 'pending') {
            $setChunks[] = 'invitation_status = :invitation_status';
            $params['invitation_status'] = 'pending';
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE doctor_id = :doctor_id AND operator_id = :operator_id LIMIT 1',
            $this->operatorsTable,
            implode(', ', $setChunks)
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function buildAuditNotes(array $options): string
    {
        $reason = trim((string)($options['reason'] ?? ''));
        $metadata = $options['metadata'] ?? null;
        $payload = [];
        if ($reason !== '') {
            $payload['reason'] = $reason;
        }
        if (is_array($metadata) && !empty($metadata)) {
            $payload['metadata'] = $metadata;
        }
        return empty($payload) ? '' : (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }
}
