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

    public function previewMigrationFromLocalState(string $doctorId, array $source): array
    {
        $this->ensureTables();
        $stateBefore = $this->readStateByDoctor($doctorId, 120);
        $analysis = $this->analyzeMigrationPayload($doctorId, $source, $stateBefore);

        return [
            'source_counts' => $analysis['source_counts'],
            'migratable' => $analysis['migratable'],
            'skipped' => $analysis['skipped'],
            'conflicts' => $analysis['conflicts'],
            'warnings' => $analysis['warnings'],
            'has_blocking_conflicts' => $analysis['has_blocking_conflicts'],
            'summary_before' => $stateBefore['summary'],
            'summary_after_if_applied' => $analysis['summary_after_if_applied'],
            'limits' => [
                'max_allowed' => self::MAX_ALLOWED,
            ],
            'counts' => $analysis['counts'],
        ];
    }

    public function applyMigrationFromLocalState(
        string $doctorId,
        array $source,
        array $actorContext = [],
        array $preview = []
    ): array {
        $this->ensureTables();
        if (empty($preview)) {
            $preview = $this->previewMigrationFromLocalState($doctorId, $source);
        }
        if (!empty($preview['has_blocking_conflicts'])) {
            throw new RuntimeException('migration_conflicts_blocking');
        }

        $migratable = (isset($preview['migratable']) && is_array($preview['migratable']))
            ? $preview['migratable']
            : [];
        $auditTrail = (isset($source['audit_trail']) && is_array($source['audit_trail']))
            ? $source['audit_trail']
            : [];
        $localAuditCountByOperator = $this->indexLocalAuditCountsByOperator($auditTrail);

        $migratedOperators = 0;
        $migratedArchived = 0;
        $migratedAuditEvents = 0;
        $migratedItems = [];

        try {
            $this->pdo->beginTransaction();
            $existingOperatorIds = $this->fetchOperatorIdSetByDoctorForUpdate($doctorId);

            foreach ($migratable as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $status = strtolower(trim((string)($item['status'] ?? 'pending')));
                if (!in_array($status, self::ALL_STATUSES, true)) {
                    $status = 'pending';
                }
                $isCountable = in_array($status, self::COUNTABLE_STATUSES, true);
                if ($isCountable) {
                    $this->ensureDoctorQuotaAvailableForCreate($doctorId, $status);
                    $this->assertAliasIsUnique($doctorId, trim((string)($item['alias_normalized'] ?? '')));
                    $this->assertLoginIsUnique($doctorId, trim((string)($item['login_normalized'] ?? '')));
                }

                $operatorId = trim((string)($item['operator_id'] ?? ''));
                if ($operatorId === '' || isset($existingOperatorIds[$operatorId])) {
                    $operatorId = $this->generateUniqueOperatorId($existingOperatorIds);
                }
                $this->assertOperatorIdIsUnique($operatorId);
                $existingOperatorIds[$operatorId] = true;

                $insertPayload = [
                    'operator_id' => $operatorId,
                    'doctor_id' => $doctorId,
                    'operator_label' => trim((string)($item['operator_label'] ?? '')),
                    'alias' => trim((string)($item['alias'] ?? '')),
                    'alias_normalized' => trim((string)($item['alias_normalized'] ?? '')),
                    'full_name' => trim((string)($item['full_name'] ?? '')),
                    'phone' => trim((string)($item['phone'] ?? '')),
                    'email' => strtolower(trim((string)($item['email'] ?? ''))),
                    'gender' => strtolower(trim((string)($item['gender'] ?? ''))),
                    'role' => strtolower(trim((string)($item['role'] ?? 'operator'))),
                    'status' => $status,
                    'login' => trim((string)($item['login'] ?? '')),
                    'login_normalized' => trim((string)($item['login_normalized'] ?? '')),
                    'temp_password_hash' => trim((string)($item['temp_password_hash'] ?? '')),
                    'force_password_change' => !empty($item['force_password_change']) ? 1 : 0,
                    'invitation_status' => strtolower(trim((string)($item['invitation_status'] ?? 'pending'))),
                    'operator_credentials_sent_at' => trim((string)($item['operator_credentials_sent_at'] ?? '')),
                    'last_access' => trim((string)($item['last_access'] ?? '')),
                    'archived_at' => $status === 'archived'
                        ? trim((string)($item['archived_at'] ?? ''))
                        : '',
                ];
                if ($insertPayload['archived_at'] === '' && $status === 'archived') {
                    $insertPayload['archived_at'] = date('Y-m-d H:i:s');
                }

                $this->insert($this->operatorsTable, $insertPayload);
                $this->replacePermissions($doctorId, $operatorId, $this->sanitizePermissionKeys($item['permissions'] ?? []));

                $sourceOperatorId = trim((string)($item['source_operator_id'] ?? ''));
                $legacyEventCount = $sourceOperatorId !== ''
                    ? (int)($localAuditCountByOperator[$sourceOperatorId] ?? 0)
                    : 0;
                $auditNotes = json_encode([
                    'source' => 'localStorage',
                    'source_bucket' => trim((string)($item['source_bucket'] ?? 'operators')),
                    'source_index' => (int)($item['source_index'] ?? 0),
                    'legacy_event_count' => $legacyEventCount,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

                $this->auditRepository->insertEvent([
                    'doctor_id' => $doctorId,
                    'operator_id' => $operatorId,
                    'event_type' => 'operator_migrated_from_local',
                    'module_name' => 'Operadores',
                    'action_label' => 'Operador migrado desde local',
                    'entity_label' => $insertPayload['alias'] !== '' ? $insertPayload['alias'] : $insertPayload['full_name'],
                    'actor_role' => trim((string)($actorContext['mode'] ?? 'system')),
                    'actor_id' => trim((string)($actorContext['user_id'] ?? 'system')),
                    'notes' => $auditNotes,
                ]);
                $migratedAuditEvents += 1;

                if ($status === 'archived') {
                    $migratedArchived += 1;
                } else {
                    $migratedOperators += 1;
                }
                $migratedItems[] = [
                    'operator_id' => $operatorId,
                    'source_operator_id' => $sourceOperatorId,
                    'status' => $status,
                ];
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $state = $this->readStateByDoctor($doctorId, 120);

        return [
            'migrated' => [
                'operators' => $migratedOperators,
                'archived_operators' => $migratedArchived,
                'audit_events' => $migratedAuditEvents,
            ],
            'migrated_items' => $migratedItems,
            'skipped' => (isset($preview['skipped']) && is_array($preview['skipped'])) ? $preview['skipped'] : [],
            'warnings' => (isset($preview['warnings']) && is_array($preview['warnings'])) ? $preview['warnings'] : [],
            'conflicts' => (isset($preview['conflicts']) && is_array($preview['conflicts'])) ? $preview['conflicts'] : [],
            'operators' => $state['operators'],
            'archived_operators' => $state['archived_operators'],
            'audit_trail' => $state['audit_trail'],
            'summary' => $state['summary'],
            'limits' => $state['limits'],
        ];
    }

    private function analyzeMigrationPayload(string $doctorId, array $source, array $stateBefore): array
    {
        $localOperators = (isset($source['operators']) && is_array($source['operators'])) ? $source['operators'] : [];
        $localArchived = (isset($source['archived_operators']) && is_array($source['archived_operators'])) ? $source['archived_operators'] : [];
        $localAudit = (isset($source['audit_trail']) && is_array($source['audit_trail'])) ? $source['audit_trail'] : [];

        $sourceCounts = [
            'operators' => count($localOperators),
            'archived_operators' => count($localArchived),
            'audit_trail' => count($localAudit),
        ];

        $backendRows = $this->fetchOperatorsRawByDoctor($doctorId);
        $backendCountableAlias = [];
        $backendCountableLogin = [];
        $backendOperatorIds = [];
        foreach ($backendRows as $row) {
            $status = strtolower(trim((string)($row['status'] ?? 'pending')));
            $operatorId = trim((string)($row['operator_id'] ?? ''));
            if ($operatorId !== '') {
                $backendOperatorIds[$operatorId] = true;
            }
            if (!in_array($status, self::COUNTABLE_STATUSES, true)) {
                continue;
            }
            $alias = trim((string)($row['alias_normalized'] ?? ''));
            if ($alias !== '') {
                $backendCountableAlias[$alias] = true;
            }
            $login = trim((string)($row['login_normalized'] ?? ''));
            if ($login !== '') {
                $backendCountableLogin[$login] = true;
            }
        }

        $incomingAlias = [];
        $incomingLogin = [];
        $incomingOperatorIds = [];
        $warnings = [];
        $conflicts = [];
        $skipped = [];
        $migratableCountableCandidates = [];
        $migratableArchivedCandidates = [];

        $candidateRows = [];
        foreach ($localOperators as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidateRows[] = $this->normalizeIncomingMigrationRecord($row, 'operators', (int)$index);
        }
        foreach ($localArchived as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidateRows[] = $this->normalizeIncomingMigrationRecord($row, 'archived_operators', (int)$index);
        }

        foreach ($candidateRows as $candidate) {
            $candidateWarnings = (isset($candidate['_warnings']) && is_array($candidate['_warnings']))
                ? $candidate['_warnings']
                : [];
            foreach ($candidateWarnings as $warningType => $warningDetail) {
                $warnings[] = $this->buildMigrationWarning($warningType, $candidate, $warningDetail);
            }

            $candidateErrors = (isset($candidate['_errors']) && is_array($candidate['_errors']))
                ? $candidate['_errors']
                : [];
            if (!empty($candidateErrors)) {
                $isBlocking = strtolower((string)($candidate['status'] ?? '')) !== 'archived';
                $conflicts[] = $this->buildMigrationConflict(
                    'operator_incomplete',
                    $candidate,
                    $candidateErrors,
                    $isBlocking
                );
                $skipped[] = $this->buildMigrationSkipped('operator_incomplete', $candidate, $candidateErrors);
                continue;
            }

            $isCountable = in_array(strtolower((string)($candidate['status'] ?? '')), self::COUNTABLE_STATUSES, true);
            if ($isCountable) {
                $aliasNormalized = trim((string)($candidate['alias_normalized'] ?? ''));
                $loginNormalized = trim((string)($candidate['login_normalized'] ?? ''));

                if (isset($backendCountableAlias[$aliasNormalized]) || isset($incomingAlias[$aliasNormalized])) {
                    $conflicts[] = $this->buildMigrationConflict(
                        'alias_duplicated',
                        $candidate,
                        ['alias' => $aliasNormalized],
                        true
                    );
                    $skipped[] = $this->buildMigrationSkipped('alias_duplicated', $candidate, ['alias' => $aliasNormalized]);
                    continue;
                }
                if (isset($backendCountableLogin[$loginNormalized]) || isset($incomingLogin[$loginNormalized])) {
                    $conflicts[] = $this->buildMigrationConflict(
                        'login_duplicated',
                        $candidate,
                        ['login' => $loginNormalized],
                        true
                    );
                    $skipped[] = $this->buildMigrationSkipped('login_duplicated', $candidate, ['login' => $loginNormalized]);
                    continue;
                }

                $incomingAlias[$aliasNormalized] = true;
                $incomingLogin[$loginNormalized] = true;
            }

            $sourceOperatorId = trim((string)($candidate['source_operator_id'] ?? ''));
            $operatorId = trim((string)($candidate['operator_id'] ?? ''));
            if ($operatorId === '' || isset($backendOperatorIds[$operatorId]) || isset($incomingOperatorIds[$operatorId])) {
                $newOperatorId = $this->generateUniqueOperatorId($backendOperatorIds, $incomingOperatorIds);
                $warnings[] = $this->buildMigrationWarning(
                    'operator_id_reassigned',
                    $candidate,
                    [
                        'from' => $operatorId !== '' ? $operatorId : null,
                        'to' => $newOperatorId,
                    ]
                );
                $candidate['operator_id'] = $newOperatorId;
            }
            $incomingOperatorIds[(string)$candidate['operator_id']] = true;

            if ($sourceOperatorId === '') {
                $candidate['source_operator_id'] = (string)$candidate['operator_id'];
            }

            if ($isCountable) {
                $migratableCountableCandidates[] = $candidate;
            } else {
                $migratableArchivedCandidates[] = $candidate;
            }
        }

        $quotaUsedBefore = (int)($stateBefore['summary']['quota_used'] ?? 0);
        $quotaAvailableBefore = max(0, self::MAX_ALLOWED - $quotaUsedBefore);
        $migratable = [];
        $migratedCountable = 0;
        foreach ($migratableCountableCandidates as $candidate) {
            if ($migratedCountable >= $quotaAvailableBefore) {
                $conflicts[] = $this->buildMigrationConflict('quota_exceeded', $candidate, [
                    'max_allowed' => self::MAX_ALLOWED,
                    'quota_used' => $quotaUsedBefore,
                    'quota_available' => $quotaAvailableBefore,
                ], true);
                $skipped[] = $this->buildMigrationSkipped('quota_exceeded', $candidate, []);
                continue;
            }
            $migratable[] = $candidate;
            $migratedCountable += 1;
        }
        foreach ($migratableArchivedCandidates as $candidate) {
            $migratable[] = $candidate;
        }

        $summaryAfter = [
            'quota_used' => $quotaUsedBefore + $migratedCountable,
            'quota_available' => max(0, self::MAX_ALLOWED - ($quotaUsedBefore + $migratedCountable)),
            'active_count' => (int)($stateBefore['summary']['active_count'] ?? 0),
            'pending_count' => (int)($stateBefore['summary']['pending_count'] ?? 0),
            'paused_count' => (int)($stateBefore['summary']['paused_count'] ?? 0),
            'archived_count' => (int)($stateBefore['summary']['archived_count'] ?? 0),
            'max_allowed' => self::MAX_ALLOWED,
        ];
        foreach ($migratable as $candidate) {
            $status = strtolower(trim((string)($candidate['status'] ?? '')));
            if ($status === 'active') {
                $summaryAfter['active_count'] += 1;
            } elseif ($status === 'pending') {
                $summaryAfter['pending_count'] += 1;
            } elseif ($status === 'paused') {
                $summaryAfter['paused_count'] += 1;
            } elseif ($status === 'archived') {
                $summaryAfter['archived_count'] += 1;
            }
        }

        $hasBlockingConflicts = false;
        foreach ($conflicts as $conflict) {
            if (!empty($conflict['blocking'])) {
                $hasBlockingConflicts = true;
                break;
            }
        }

        return [
            'source_counts' => $sourceCounts,
            'migratable' => $migratable,
            'skipped' => $skipped,
            'conflicts' => $conflicts,
            'warnings' => $warnings,
            'has_blocking_conflicts' => $hasBlockingConflicts,
            'summary_after_if_applied' => $summaryAfter,
            'counts' => [
                'operators_local' => count($localOperators),
                'archived_local' => count($localArchived),
                'audit_local' => count($localAudit),
                'migratable' => count($migratable),
                'skipped' => count($skipped),
                'conflicts' => count($conflicts),
                'warnings' => count($warnings),
            ],
        ];
    }

    private function normalizeIncomingMigrationRecord(array $row, string $sourceBucket, int $sourceIndex): array
    {
        $errors = [];
        $warnings = [];

        $statusRaw = strtolower(trim((string)($row['status'] ?? ($sourceBucket === 'archived_operators' ? 'archived' : 'pending'))));
        if ($sourceBucket === 'archived_operators' && $statusRaw !== 'archived') {
            $warnings['status_forced_archived'] = ['from' => $statusRaw];
            $statusRaw = 'archived';
        }
        if (!in_array($statusRaw, self::ALL_STATUSES, true)) {
            $errors['status'] = 'invalid';
        }

        $alias = strtoupper(trim((string)($row['alias'] ?? '')));
        $alias = $this->normalizeAlias($alias);
        if ($alias === '') {
            $errors['alias'] = 'required';
        } elseif (strlen($alias) < 3) {
            $errors['alias'] = 'min_length_3';
        } elseif (strlen($alias) > 15) {
            $errors['alias'] = 'max_length_15';
        }

        $login = $this->normalizeLogin((string)($row['login'] ?? ''));
        if ($statusRaw === 'archived' && $login === '') {
            $seedRaw = trim((string)($row['operator_id'] ?? ''));
            if ($seedRaw === '') {
                $seedRaw = $sourceBucket . '-' . (string)$sourceIndex;
            }
            $seed = preg_replace('/[^a-z0-9]+/i', '-', strtolower($seedRaw)) ?: '';
            $seed = trim((string)$seed, '-');
            if ($seed === '') {
                $seed = 'legacy-' . substr(md5($sourceBucket . '|' . (string)$sourceIndex . '|' . $fullName . '|' . $alias), 0, 8);
            }
            $login = $this->normalizeLogin('archived.' . $seed);
            if ($login === '') {
                $login = 'archived.' . substr(md5($sourceBucket . '|' . (string)$sourceIndex), 0, 10);
            }
            $warnings['archived_login_generated'] = [
                'login' => $login,
            ];
        }
        if ($statusRaw !== 'archived' && $login === '') {
            $errors['login'] = 'required';
        }

        $fullName = trim((string)($row['full_name'] ?? ''));
        if ($fullName === '') {
            $errors['full_name'] = 'required';
        }

        $role = strtolower(trim((string)($row['role'] ?? 'operator')));
        if (!in_array($role, ['operator', 'assistant'], true)) {
            $role = 'operator';
            $warnings['role_defaulted'] = true;
        }

        $permissions = $this->sanitizePermissionKeys($row['permissions'] ?? []);
        $forcePasswordChange = !empty($row['force_password_change']);

        $tempPasswordHash = trim((string)($row['temp_password_hash'] ?? ''));
        $tempPasswordRaw = trim((string)($row['temp_password'] ?? ''));
        if ($tempPasswordHash !== '' && strpos($tempPasswordHash, '$') !== 0) {
            $warnings['temp_password_hash_discarded'] = true;
            $tempPasswordHash = '';
            $forcePasswordChange = true;
        }
        if ($tempPasswordRaw !== '') {
            $warnings['temp_password_plain_discarded'] = true;
            $tempPasswordHash = '';
            $forcePasswordChange = true;
        }

        $invitationStatus = strtolower(trim((string)($row['invitation_status'] ?? 'pending')));
        if (!in_array($invitationStatus, ['pending', 'active', 'sent', 'paused'], true)) {
            $invitationStatus = ($statusRaw === 'active') ? 'active' : 'pending';
        }

        return [
            'source_bucket' => $sourceBucket,
            'source_index' => $sourceIndex,
            'source_operator_id' => trim((string)($row['operator_id'] ?? '')),
            'operator_id' => trim((string)($row['operator_id'] ?? '')),
            'operator_label' => trim((string)($row['operator_label'] ?? '')),
            'alias' => $alias,
            'alias_normalized' => $alias,
            'full_name' => $fullName,
            'phone' => trim((string)($row['phone'] ?? '')),
            'email' => strtolower(trim((string)($row['email'] ?? ''))),
            'gender' => strtolower(trim((string)($row['gender'] ?? ''))),
            'role' => $role,
            'status' => $statusRaw,
            'login' => $login,
            'login_normalized' => $login,
            'temp_password_hash' => $tempPasswordHash,
            'force_password_change' => $forcePasswordChange,
            'invitation_status' => $invitationStatus,
            'operator_credentials_sent_at' => trim((string)($row['operator_credentials_sent_at'] ?? '')),
            'last_access' => trim((string)($row['last_access'] ?? '')),
            'archived_at' => trim((string)($row['archived_at'] ?? '')),
            'permissions' => $permissions,
            '_errors' => $errors,
            '_warnings' => $warnings,
        ];
    }

    private function buildMigrationConflict(string $type, array $candidate, array $detail, bool $blocking): array
    {
        return [
            'type' => $type,
            'blocking' => $blocking,
            'source_bucket' => trim((string)($candidate['source_bucket'] ?? 'operators')),
            'source_index' => (int)($candidate['source_index'] ?? 0),
            'operator_id' => trim((string)($candidate['source_operator_id'] ?? '')),
            'alias' => trim((string)($candidate['alias'] ?? '')),
            'login' => trim((string)($candidate['login'] ?? '')),
            'status' => trim((string)($candidate['status'] ?? '')),
            'detail' => $detail,
        ];
    }

    private function buildMigrationSkipped(string $reason, array $candidate, array $detail): array
    {
        return [
            'reason' => $reason,
            'source_bucket' => trim((string)($candidate['source_bucket'] ?? 'operators')),
            'source_index' => (int)($candidate['source_index'] ?? 0),
            'operator_id' => trim((string)($candidate['source_operator_id'] ?? '')),
            'alias' => trim((string)($candidate['alias'] ?? '')),
            'login' => trim((string)($candidate['login'] ?? '')),
            'detail' => $detail,
        ];
    }

    private function buildMigrationWarning(string $type, array $candidate, $detail): array
    {
        return [
            'type' => $type,
            'source_bucket' => trim((string)($candidate['source_bucket'] ?? 'operators')),
            'source_index' => (int)($candidate['source_index'] ?? 0),
            'operator_id' => trim((string)($candidate['source_operator_id'] ?? '')),
            'alias' => trim((string)($candidate['alias'] ?? '')),
            'login' => trim((string)($candidate['login'] ?? '')),
            'detail' => $detail,
        ];
    }

    private function fetchOperatorsRawByDoctor(string $doctorId): array
    {
        $sql = sprintf(
            'SELECT operator_id, status, alias_normalized, login_normalized FROM %s WHERE doctor_id = :doctor_id',
            $this->operatorsTable
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doctor_id' => $doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchOperatorIdSetByDoctorForUpdate(string $doctorId): array
    {
        $sql = sprintf(
            'SELECT operator_id FROM %s WHERE doctor_id = :doctor_id FOR UPDATE',
            $this->operatorsTable
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doctor_id' => $doctorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $set = [];
        foreach ($rows as $row) {
            $operatorId = trim((string)($row['operator_id'] ?? ''));
            if ($operatorId !== '') {
                $set[$operatorId] = true;
            }
        }
        return $set;
    }

    private function generateUniqueOperatorId(array $existingSet, array $incomingSet = []): string
    {
        for ($i = 0; $i < 25; $i += 1) {
            $candidate = bin2hex(random_bytes(12));
            if (!isset($existingSet[$candidate]) && !isset($incomingSet[$candidate])) {
                return $candidate;
            }
        }
        return bin2hex(random_bytes(16));
    }

    private function indexLocalAuditCountsByOperator(array $auditTrail): array
    {
        $map = [];
        foreach ($auditTrail as $event) {
            if (!is_array($event)) {
                continue;
            }
            $operatorId = trim((string)($event['operator_id'] ?? ''));
            if ($operatorId === '') {
                continue;
            }
            if (!isset($map[$operatorId])) {
                $map[$operatorId] = 0;
            }
            $map[$operatorId] += 1;
        }
        return $map;
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

    private function normalizeAlias(string $value): string
    {
        $withoutAccents = $this->removeAccents($value);
        $normalized = strtoupper(trim($withoutAccents));
        $normalized = preg_replace('/\s+/', '', $normalized) ?: '';
        $normalized = preg_replace('/[^A-Z0-9-]/', '', $normalized) ?: '';
        return $normalized;
    }

    private function normalizeLogin(string $value): string
    {
        $withoutAccents = $this->removeAccents($value);
        $normalized = strtolower(trim($withoutAccents));
        $normalized = preg_replace('/\s+/', '', $normalized) ?: '';
        $normalized = preg_replace('/[^a-z0-9.-]/', '', $normalized) ?: '';
        $normalized = preg_replace('/[.]{2,}/', '.', $normalized) ?: '';
        $normalized = preg_replace('/[-]{2,}/', '-', $normalized) ?: '';
        $normalized = preg_replace('/([.-]){2,}/', '$1', $normalized) ?: '';
        $normalized = trim($normalized, '.-');
        return $normalized;
    }

    private function removeAccents(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/[\x{0300}-\x{036f}]/u', '', $normalized) ?: $value;
            }
        } else {
            $value = strtr($value, [
                'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
                'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
                'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
                'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
                'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
                'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
                'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
                'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
                'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
                'Ñ' => 'N', 'ñ' => 'n',
            ]);
        }
        return $value;
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
