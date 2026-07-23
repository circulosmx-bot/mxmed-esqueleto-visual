<?php
declare(strict_types=1);

require_once __DIR__ . '/../identity/persistence/PatientIdentityPersistencePolicy.php';
require_once __DIR__ . '/../identity/persistence/PatientIdentityPersistenceManifest.php';
require_once __DIR__ . '/../identity/persistence/PatientIdentityRetentionPolicy.php';
require_once __DIR__ . '/../identity/persistence/PatientIdentityBackfillPlan.php';
require_once __DIR__ . '/../identity/persistence/PatientIdentityRolloutPolicy.php';
require_once __DIR__ . '/../identity/persistence/PatientIdentityPersistencePort.php';

use Patients\Identity\Persistence\PatientIdentityBackfillPlan;
use Patients\Identity\Persistence\PatientIdentityPersistenceManifest;
use Patients\Identity\Persistence\PatientIdentityPersistencePolicy;
use Patients\Identity\Persistence\PatientIdentityPersistencePort;
use Patients\Identity\Persistence\PatientIdentityRetentionPolicy;
use Patients\Identity\Persistence\PatientIdentityRolloutPolicy;

function gate8gAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function gate8gRead(string $path): string
{
    $content = file_get_contents($path);
    gate8gAssert(is_string($content), 'unreadable file: ' . $path);
    return $content;
}

function gate8gNormalizedPlanBlock(string $plan, int $number): string
{
    $matches = [];
    preg_match('/### PP-' . $number . ' .*?(?=### PP-[0-9]+ —|\z)/s', $plan, $matches);
    gate8gAssert(isset($matches[0]), 'missing PP-' . $number);
    return rtrim($matches[0], "\r\n") . "\n";
}

function gate8gAssertMethods(string $class, array $expected): void
{
    $reflection = new ReflectionClass($class);
    $actual = array_map(static fn(ReflectionMethod $method): string => $method->getName(), $reflection->getMethods(ReflectionMethod::IS_PUBLIC));
    sort($actual);
    sort($expected);
    gate8gAssert($actual === $expected, $class . ' methods exact');
}

$root = dirname(__DIR__, 3);
$policy = new PatientIdentityPersistencePolicy();
$manifest = new PatientIdentityPersistenceManifest();
$retention = new PatientIdentityRetentionPolicy();
$backfill = new PatientIdentityBackfillPlan();
$rollout = new PatientIdentityRolloutPolicy();

$readonlyClasses = [PatientIdentityPersistencePolicy::class, PatientIdentityPersistenceManifest::class, PatientIdentityRetentionPolicy::class, PatientIdentityBackfillPlan::class, PatientIdentityRolloutPolicy::class];
foreach ($readonlyClasses as $class) {
    $reflection = new ReflectionClass($class);
    gate8gAssert($reflection->isFinal() && $reflection->isReadOnly() && !$reflection->isInterface(), $class . ' final readonly');
    gate8gAssert(!(new $class())->executesOperations(), $class . ' execution false');
    gate8gAssert((new $class())->toArray()['execution'] === false, $class . ' serialized execution false');
}
$portReflection = new ReflectionClass(PatientIdentityPersistencePort::class);
gate8gAssert($portReflection->isInterface(), 'persistence port interface');
gate8gAssert(!$portReflection->hasMethod('executesOperations'), 'port has no execution method');

gate8gAssertMethods(PatientIdentityPersistencePolicy::class, ['contractId', 'contractVersion', 'transactionRequired', 'lockOrder', 'idempotencyStates', 'auditInSameTransaction', 'automaticMerge', 'directSqlExecution', 'runtimeWiring', 'clinicalMutation', 'foreignKeysToLegacyPatients', 'defaultRollout', 'executesOperations', 'toArray']);
gate8gAssertMethods(PatientIdentityPersistenceManifest::class, ['tables', 'upMigrations', 'downMigrations', 'auditStrategy', 'platformAuditReuse', 'platformAuditReuseReason', 'engine', 'charset', 'collation', 'seedData', 'modifiesExistingTables', 'createMigrations', 'rollbackMigrations', 'phpContracts', 'testFile', 'documentFile', 'planMasterFile', 'versionedFiles', 'versionedFileCount', 'createdFileCount', 'modifiedFileCount', 'sqlFileCount', 'phpFileCount', 'documentFileCount', 'executesOperations', 'toArray']);
gate8gAssertMethods(PatientIdentityRetentionPolicy::class, ['auditRetention', 'legacyLinkRetention', 'resolutionRetention', 'checkpointRetention', 'automaticPurge', 'automaticArchive', 'automaticDeletion', 'numericRetentionPeriods', 'executesOperations', 'toArray']);
gate8gAssertMethods(PatientIdentityBackfillPlan::class, ['steps', 'failureMode', 'deterministic', 'resumable', 'idempotent', 'batchLimited', 'executesBackfill', 'runtimeWiring', 'automaticMerge', 'deletesData', 'clinicalMutation', 'containsPii', 'legacyRuntimePreserved', 'executesOperations', 'toArray']);
gate8gAssertMethods(PatientIdentityRolloutPolicy::class, ['stages', 'initialStage', 'metrics', 'piiInMetricsLabelsOrLogs', 'activatesRuntime', 'seedData', 'gate8gEnabledStages', 'activationAllowed', 'writesEnabled', 'backfillEnabled', 'executesOperations', 'toArray']);
gate8gAssertMethods(PatientIdentityPersistencePort::class, ['beginTransaction', 'lockResolutionFingerprint', 'lockLegacyReferenceDigest', 'findResolutionByFingerprint', 'persistProcessingResolution', 'persistCompletedResolution', 'persistFailedResolution', 'appendAuditEvent', 'findLegacyLink', 'persistLegacyLink', 'loadBackfillCheckpoint', 'persistBackfillCheckpoint', 'commit', 'rollBack']);

$tables = ['patient_identity_resolutions', 'patient_identity_audit_events', 'patient_identity_legacy_links', 'patient_identity_backfill_checkpoints'];
$createMigrations = [
    'modules/patients/db/migrations/2026_07_22_01_create_patient_identity_resolutions.sql',
    'modules/patients/db/migrations/2026_07_22_02_create_patient_identity_audit_events.sql',
    'modules/patients/db/migrations/2026_07_22_03_create_patient_identity_legacy_links.sql',
    'modules/patients/db/migrations/2026_07_22_04_create_patient_identity_backfill_checkpoints.sql',
];
$rollbackMigrations = [
    'modules/patients/db/migrations/2026_07_22_04_rollback_patient_identity_backfill_checkpoints.sql',
    'modules/patients/db/migrations/2026_07_22_03_rollback_patient_identity_legacy_links.sql',
    'modules/patients/db/migrations/2026_07_22_02_rollback_patient_identity_audit_events.sql',
    'modules/patients/db/migrations/2026_07_22_01_rollback_patient_identity_resolutions.sql',
];
$phpContracts = [
    'modules/patients/identity/persistence/PatientIdentityPersistencePolicy.php',
    'modules/patients/identity/persistence/PatientIdentityPersistenceManifest.php',
    'modules/patients/identity/persistence/PatientIdentityRetentionPolicy.php',
    'modules/patients/identity/persistence/PatientIdentityBackfillPlan.php',
    'modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php',
    'modules/patients/identity/persistence/PatientIdentityPersistencePort.php',
];
$versionedFiles = array_merge($phpContracts, [
    'modules/patients/db/migrations/2026_07_22_01_create_patient_identity_resolutions.sql',
    'modules/patients/db/migrations/2026_07_22_01_rollback_patient_identity_resolutions.sql',
    'modules/patients/db/migrations/2026_07_22_02_create_patient_identity_audit_events.sql',
    'modules/patients/db/migrations/2026_07_22_02_rollback_patient_identity_audit_events.sql',
    'modules/patients/db/migrations/2026_07_22_03_create_patient_identity_legacy_links.sql',
    'modules/patients/db/migrations/2026_07_22_03_rollback_patient_identity_legacy_links.sql',
    'modules/patients/db/migrations/2026_07_22_04_create_patient_identity_backfill_checkpoints.sql',
    'modules/patients/db/migrations/2026_07_22_04_rollback_patient_identity_backfill_checkpoints.sql',
    'modules/patients/tests/Gate8GPatientIdentityPersistenceMigrationTest.php',
    'docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_MIGRACIONES.md',
    'docs/PLAN_MAESTRO_MXMED.md',
]);

gate8gAssert($manifest->tables() === $tables, 'tables exact');
gate8gAssert($manifest->createMigrations() === $createMigrations, 'create order exact');
gate8gAssert($manifest->rollbackMigrations() === $rollbackMigrations, 'rollback order exact');
gate8gAssert($manifest->phpContracts() === $phpContracts && $manifest->versionedFiles() === $versionedFiles, 'versioned scope exact');
gate8gAssert($manifest->versionedFileCount() === 17 && $manifest->createdFileCount() === 16 && $manifest->modifiedFileCount() === 1, 'versioned counts exact');
gate8gAssert($manifest->sqlFileCount() === 8 && $manifest->phpFileCount() === 7 && $manifest->documentFileCount() === 2, 'type counts exact');
gate8gAssert($manifest->documentFile() === 'docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_MIGRACIONES.md', 'canonical document exact');
gate8gAssert(!str_contains(json_encode($manifest->toArray(), JSON_THROW_ON_ERROR), 'PERSISTENCIA_IDENTIDAD.md'), 'deprecated document absent from manifest');
gate8gAssert(array_keys($manifest->toArray()) === ['tables', 'create_migrations', 'rollback_migrations', 'php_contracts', 'test_file', 'document_file', 'plan_master_file', 'versioned_files', 'versioned_file_count', 'created_file_count', 'modified_file_count', 'sql_file_count', 'php_file_count', 'document_file_count', 'execution'], 'manifest serialization order exact');
foreach ($versionedFiles as $path) gate8gAssert(is_file($root . '/' . $path), 'versioned path exists: ' . $path);
gate8gAssert(!is_file($root . '/docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_IDENTIDAD.md'), 'deprecated document not created');

gate8gAssert($policy->contractId() === 'pg03-patient-identity-persistence' && $policy->contractVersion() === 1, 'policy identity exact');
gate8gAssert($policy->transactionRequired() && $policy->auditInSameTransaction(), 'transaction contract');
gate8gAssert($policy->lockOrder() === ['resolution_fingerprint', 'legacy_reference', 'candidate_set', 'audit_stream'], 'lock order exact');
gate8gAssert($policy->idempotencyStates() === ['processing', 'completed', 'failed'], 'idempotency states exact');
gate8gAssert(!$policy->automaticMerge() && !$policy->directSqlExecution() && !$policy->runtimeWiring() && !$policy->clinicalMutation() && !$policy->foreignKeysToLegacyPatients(), 'policy boundaries');

gate8gAssert($retention->auditRetention() === 'durable_no_purge' && $retention->legacyLinkRetention() === 'durable_no_purge', 'durable retention');
gate8gAssert($retention->resolutionRetention() === 'UNRESOLVED_PENDING_POLICY_APPROVAL' && $retention->checkpointRetention() === 'UNRESOLVED_PENDING_POLICY_APPROVAL', 'unresolved retention');
gate8gAssert(!$retention->automaticPurge() && !$retention->automaticArchive() && !$retention->automaticDeletion() && $retention->numericRetentionPeriods() === null, 'no automatic disposition or TTL');

$backfillSteps = ['preflight', 'external_snapshot_backup', 'shadow_scan', 'batched_read', 'trusted_adapter_digest', 'candidate_resolution', 'no_match_partition', 'review_queue_partition', 'idempotency_check', 'append_audit', 'persist_checkpoint', 'reconciliation', 'emit_metrics', 'abort_or_rollback'];
gate8gAssert($backfill->steps() === $backfillSteps && $backfill->failureMode() === 'abort_and_rollback', 'backfill steps exact');
gate8gAssert($backfill->deterministic() && $backfill->resumable() && $backfill->idempotent() && $backfill->batchLimited() && $backfill->legacyRuntimePreserved(), 'backfill safety properties');
gate8gAssert(!$backfill->executesBackfill() && !$backfill->runtimeWiring() && !$backfill->automaticMerge() && !$backfill->deletesData() && !$backfill->clinicalMutation() && !$backfill->containsPii(), 'backfill closed');

gate8gAssert($rollout->stages() === ['R0' => 'disabled', 'R1' => 'shadow', 'R2' => 'audit_only', 'R3' => 'read_compare', 'R4' => 'enabled'], 'rollout stages exact');
gate8gAssert($rollout->initialStage() === 'disabled' && $rollout->gate8gEnabledStages() === ['disabled'], 'rollout R0 disabled');
gate8gAssert(!$rollout->activationAllowed() && !$rollout->writesEnabled() && !$rollout->backfillEnabled() && !$rollout->activatesRuntime(), 'rollout inactive');
gate8gAssert(!$rollout->piiInMetricsLabelsOrLogs() && !$rollout->seedData(), 'rollout privacy and seed');

$definitions = [
    $createMigrations[0] => [
        'table' => 'patient_identity_resolutions',
        'columns' => [
            'request_fingerprint CHAR(64) NOT NULL', 'operation_reference VARCHAR(128) NOT NULL', 'correlation_reference VARCHAR(128) NOT NULL', 'resolution_source VARCHAR(32) NOT NULL', 'input_type VARCHAR(32) NOT NULL', 'identity_reference_digest CHAR(64) NOT NULL',
            "legacy_lock_digest CHAR(64) GENERATED ALWAYS AS (CASE WHEN input_type = 'legacy_patient_key_hash' THEN identity_reference_digest ELSE NULL END) STORED", 'candidate_set_digest CHAR(64) NULL', 'status VARCHAR(32) NULL', 'reason_code VARCHAR(64) NULL', 'resolved_patient_id VARCHAR(64) NULL', 'duplicate_review_id CHAR(64) NULL', 'decision_digest CHAR(64) NULL', 'audit_event_id CHAR(64) NULL', 'policy_version INT UNSIGNED NOT NULL', "transaction_state VARCHAR(16) NOT NULL DEFAULT 'processing'", 'failure_code VARCHAR(64) NULL', 'occurred_at DATETIME(6) NOT NULL', 'started_at DATETIME(6) NOT NULL', 'completed_at DATETIME(6) NULL', 'failed_at DATETIME(6) NULL', 'created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)', 'updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)',
        ],
        'indexes' => ['PRIMARY KEY (request_fingerprint)', 'UNIQUE KEY ux_patient_identity_resolutions_operation (operation_reference)', 'UNIQUE KEY ux_patient_identity_resolutions_legacy_lock (legacy_lock_digest)', 'UNIQUE KEY ux_patient_identity_resolutions_decision (decision_digest)', 'UNIQUE KEY ux_patient_identity_resolutions_audit_event (audit_event_id)', 'KEY idx_patient_identity_resolutions_correlation (correlation_reference)', 'KEY idx_patient_identity_resolutions_status_created (status, created_at)', 'KEY idx_patient_identity_resolutions_patient (resolved_patient_id)', 'KEY idx_patient_identity_resolutions_state_updated (transaction_state, updated_at)'],
    ],
    $createMigrations[1] => [
        'table' => 'patient_identity_audit_events',
        'columns' => ['stream_key CHAR(64) NOT NULL', 'sequence_number BIGINT UNSIGNED NOT NULL', 'event_id CHAR(64) NOT NULL', 'event_type VARCHAR(64) NOT NULL', 'operation_reference VARCHAR(128) NOT NULL', 'correlation_reference VARCHAR(128) NOT NULL', 'source VARCHAR(32) NOT NULL', 'input_type VARCHAR(32) NOT NULL', 'request_fingerprint CHAR(64) NOT NULL', 'candidate_set_digest CHAR(64) NOT NULL', 'resolved_patient_id_digest CHAR(64) NULL', 'candidate_patient_id_digests_json JSON NOT NULL', 'outcome_code VARCHAR(32) NOT NULL', 'match_tier VARCHAR(32) NOT NULL', 'actor_real_reference VARCHAR(128) NOT NULL', 'actor_effective_reference VARCHAR(128) NOT NULL', 'policy_version INT UNSIGNED NOT NULL', 'occurred_at DATETIME(6) NOT NULL', 'human_review_required TINYINT(1) NOT NULL', 'create_minimal_required TINYINT(1) NOT NULL', 'merge_allowed TINYINT(1) NOT NULL DEFAULT 0', 'previous_hash CHAR(64) NOT NULL', 'event_hash CHAR(64) NOT NULL', 'created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)'],
        'indexes' => ['PRIMARY KEY (stream_key, sequence_number)', 'UNIQUE KEY ux_patient_identity_audit_events_event_id (event_id)', 'UNIQUE KEY ux_patient_identity_audit_events_event_hash (event_hash)', 'KEY idx_patient_identity_audit_events_request (request_fingerprint)', 'KEY idx_patient_identity_audit_events_correlation (correlation_reference)', 'KEY idx_patient_identity_audit_events_occurred (occurred_at)', 'KEY idx_patient_identity_audit_events_outcome (outcome_code)'],
    ],
    $createMigrations[2] => [
        'table' => 'patient_identity_legacy_links',
        'columns' => ['legacy_patient_key_hash CHAR(64) NOT NULL', 'canonical_patient_id VARCHAR(64) NOT NULL', 'resolution_decision_digest CHAR(64) NOT NULL', 'audit_event_id CHAR(64) NOT NULL', 'policy_version INT UNSIGNED NOT NULL', "link_state VARCHAR(16) NOT NULL DEFAULT 'active'", 'created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)', 'ended_at DATETIME(6) NULL'],
        'indexes' => ['PRIMARY KEY (legacy_patient_key_hash)', 'UNIQUE KEY ux_patient_identity_legacy_links_decision (resolution_decision_digest)', 'UNIQUE KEY ux_patient_identity_legacy_links_audit_event (audit_event_id)', 'KEY idx_patient_identity_legacy_links_patient_state (canonical_patient_id, link_state)', 'KEY idx_patient_identity_legacy_links_state_created (link_state, created_at)'],
    ],
    $createMigrations[3] => [
        'table' => 'patient_identity_backfill_checkpoints',
        'columns' => ['checkpoint_id CHAR(64) NOT NULL', 'job_reference VARCHAR(128) NOT NULL', 'cursor_digest CHAR(64) NULL', 'batch_number BIGINT UNSIGNED NOT NULL', 'last_processed_reference_digest CHAR(64) NULL', "state VARCHAR(32) NOT NULL DEFAULT 'pending'", 'total_evaluated BIGINT UNSIGNED NOT NULL DEFAULT 0', 'already_canonical BIGINT UNSIGNED NOT NULL DEFAULT 0', 'mapped_from_legacy BIGINT UNSIGNED NOT NULL DEFAULT 0', 'create_minimal_required BIGINT UNSIGNED NOT NULL DEFAULT 0', 'review_required BIGINT UNSIGNED NOT NULL DEFAULT 0', 'ambiguous BIGINT UNSIGNED NOT NULL DEFAULT 0', 'conflicts BIGINT UNSIGNED NOT NULL DEFAULT 0', 'checkpoint_digest CHAR(64) NOT NULL', 'error_code VARCHAR(64) NULL', 'retry_count INT UNSIGNED NOT NULL DEFAULT 0', 'started_at DATETIME(6) NULL', 'completed_at DATETIME(6) NULL', 'created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)', 'updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)'],
        'indexes' => ['PRIMARY KEY (checkpoint_id)', 'UNIQUE KEY ux_patient_identity_backfill_job_batch (job_reference, batch_number)', 'UNIQUE KEY ux_patient_identity_backfill_checkpoint_digest (checkpoint_digest)', 'KEY idx_patient_identity_backfill_state_updated (state, updated_at)', 'KEY idx_patient_identity_backfill_job (job_reference)'],
    ],
];

$allSql = '';
foreach ($definitions as $path => $definition) {
    $sql = gate8gRead($root . '/' . $path);
    $allSql .= $sql;
    gate8gAssert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS ' . $definition['table'] . ' ('), $path . ' create exact');
    gate8gAssert(str_contains($sql, ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'), $path . ' storage exact');
    foreach (array_merge($definition['columns'], $definition['indexes']) as $fragment) gate8gAssert(str_contains($sql, $fragment), $path . ' fragment exact: ' . $fragment);
}
foreach ($rollbackMigrations as $path) $allSql .= gate8gRead($root . '/' . $path);
foreach ($tables as $table) gate8gAssert(substr_count($allSql, 'CREATE TABLE IF NOT EXISTS ' . $table) === 1 && substr_count($allSql, 'DROP TABLE IF EXISTS ' . $table) === 1, $table . ' create/rollback pair');

$auditCreate = gate8gRead($root . '/' . $createMigrations[1]);
$auditRollback = gate8gRead($root . '/modules/patients/db/migrations/2026_07_22_02_rollback_patient_identity_audit_events.sql');
foreach (['reject_patient_identity_audit_events_update', 'reject_patient_identity_audit_events_delete'] as $trigger) gate8gAssert(str_contains($auditCreate, 'CREATE TRIGGER ' . $trigger) && str_contains($auditRollback, 'DROP TRIGGER IF EXISTS ' . $trigger), 'audit trigger pair: ' . $trigger);
$sqlWithoutAllowedUpdate = str_replace(['ON UPDATE CURRENT_TIMESTAMP(6)', 'BEFORE UPDATE ON patient_identity_audit_events'], '', $allSql);
$sqlWithoutAllowedDelete = str_replace('BEFORE DELETE ON patient_identity_audit_events', '', $allSql);
gate8gAssert(preg_match('/\bUPDATE\b/', $sqlWithoutAllowedUpdate) !== 1 && preg_match('/\bDELETE\b/', $sqlWithoutAllowedDelete) !== 1, 'no row-changing DML outside rejection triggers');
foreach (['ALTER TABLE', 'INSERT INTO', 'REPLACE INTO', 'ON DUPLICATE KEY UPDATE', 'FOREIGN KEY', 'ON DELETE CASCADE', 'ON UPDATE CASCADE', 'CREATE PROCEDURE', 'CREATE EVENT'] as $forbidden) gate8gAssert(!str_contains($allSql, $forbidden), 'forbidden SQL construct absent: ' . $forbidden);
foreach (['display_name', 'first_name', 'paternal_last_name', 'maternal_last_name', 'birthdate', 'phone', 'email', 'address', 'street', 'postal_code', 'raw_legacy_key', 'clinical_document', 'encounter_payload', 'patient_payload'] as $column) gate8gAssert(preg_match('/^\s*' . preg_quote($column, '/') . '\s+/mi', $allSql) !== 1, 'raw PII column absent: ' . $column);
gate8gAssert(!str_contains($allSql, 'patient_identity_rollout') && !str_contains($allSql, 'patient_identity_merge'), 'no rollout or merge table');

$contractSource = '';
foreach ($phpContracts as $path) $contractSource .= gate8gRead($root . '/' . $path);
foreach (['PDO', 'mysqli', 'file_get_contents', 'file_put_contents', '$_GET', '$_POST', '$_SESSION', 'getenv(', 'header(', 'curl_', 'fopen(', 'error_log', 'PatientsRepository', 'Controller', 'Clinical', 'random_bytes', 'uniqid(', 'date('] as $forbidden) gate8gAssert(!str_contains($contractSource, $forbidden), 'contract purity: ' . $forbidden);

$protectedPaths = [
    'modules/patients/db/ready_schema.sql', 'modules/patients/repositories/PatientsRepository.php',
    'modules/patients/identity/CanonicalPatientId.php', 'modules/patients/identity/LegacyPatientReference.php', 'modules/patients/identity/PatientDuplicateReview.php', 'modules/patients/identity/PatientIdentityAuditEvent.php', 'modules/patients/identity/PatientIdentityCandidate.php', 'modules/patients/identity/PatientIdentityCandidateSet.php', 'modules/patients/identity/PatientIdentityDomainException.php', 'modules/patients/identity/PatientIdentityEvidence.php', 'modules/patients/identity/PatientIdentityMutationPlan.php', 'modules/patients/identity/PatientIdentityPolicy.php', 'modules/patients/identity/PatientIdentityResolutionDecision.php', 'modules/patients/identity/PatientIdentityResolutionRequest.php', 'modules/patients/identity/PatientIdentityResolver.php', 'modules/patients/identity/PatientMergePolicy.php',
    'modules/agenda/tests/Gate8ACanonicalContractsTest.php', 'modules/agenda/tests/Gate8EPublicAgendaOtpPrivacyTest.php', 'modules/patients/tests/Gate8FPatientIdentityDuplicatesTest.php',
    'docs/clinical', 'modules/clinical',
];
$command = 'git -C ' . escapeshellarg($root) . ' diff --name-only b807f58585966936ed62c29c59025734d7295b0f -- ' . implode(' ', array_map('escapeshellarg', $protectedPaths));
$protectedDiff = [];
$protectedExit = 1;
exec($command, $protectedDiff, $protectedExit);
gate8gAssert($protectedExit === 0 && $protectedDiff === [], 'protected parent surfaces byte equivalent');

$authorizedCorrectedGateHashes = [
    'modules/agenda/tests/Gate8BServerAuthoritativeActorsTest.php' => '2b8b301cbb64b60d77d2795bb2857fc0b676fb05d936188d49dce4f592a4bda8',
    'modules/agenda/tests/Gate8CCanonicalScheduleAvailabilityTest.php' => '6e511ab01f9cd657f086fcb904b88940cde3ee81342333ab8c29dd8047a5044a',
    'modules/agenda/tests/Gate8DAppointmentLifecycleIdempotencyTest.php' => 'ae024a823c7654f55c7aa43ebdb736918244662890746163e8102224f8fc0279',
];
foreach ($authorizedCorrectedGateHashes as $path => $expectedHash) {
    $actualHash = hash_file('sha256', $root . '/' . $path);
    gate8gAssert(is_string($actualHash) && $actualHash === $expectedHash, 'authorized corrected gate hash exact: ' . $path);
}

$plan = gate8gRead($root . '/docs/PLAN_MAESTRO_MXMED.md');
foreach (['PP-304', 'PP-305', 'PP-306', 'PP-307', 'PP-308', 'PP-309', 'PP-310'] as $number) gate8gAssert(substr_count($plan, '### ' . $number . ' —') === 1, $number . ' exact once');
$pp309 = gate8gNormalizedPlanBlock($plan, 309);
$pp310 = gate8gNormalizedPlanBlock($plan, 310);
gate8gAssert(strlen($pp309) === 4869 && hash('sha256', $pp309) === '2939e9301d8117a2e4d1cd470758b07407d07c794861be0735f68a45ac94fa70', 'PP-309 stable');
gate8gAssert(substr_count($pp310, 'docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_MIGRACIONES.md') === 1, 'PP-310 canonical document path exact once');
gate8gAssert(substr_count($pp310, 'docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_IDENTIDAD.md') === 0, 'PP-310 deprecated document path absent');
$pp310Hash = hash('sha256', $pp310);
gate8gAssert($pp310Hash === 'c3c0339ad05b127b08288f3a026f2122f9af130061369db2c1a4c0c8d4a17459', 'PP-310 stable');
gate8gAssert(str_contains(gate8gRead($root . '/docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_MIGRACIONES.md'), $pp310Hash), 'PP-310 hash documented');

echo "GATE8G_TABLES=4/4\n";
echo "GATE8G_CREATE_MIGRATIONS=4/4\n";
echo "GATE8G_ROLLBACK_MIGRATIONS=4/4\n";
echo "GATE8G_PHP_CONTRACTS=6/6\n";
echo "GATE8G_VERSIONED_SCOPE=17/17\n";
echo "GATE8G_DATABASE_CONTRACT=PASS\n";
echo "GATE8G_APPEND_ONLY_AUDIT=PASS\n";
echo "GATE8G_IDEMPOTENCY_AND_LOCKS=PASS\n";
echo "GATE8G_RETENTION_POLICY=PASS\n";
echo "GATE8G_BACKFILL_PLAN=PASS\n";
echo "GATE8G_ROLLOUT_DISABLED=PASS\n";
echo "GATE8G_PRIVACY_BOUNDARY=PASS\n";
echo "GATE8G_MIGRATIONS_NOT_EXECUTED=PASS\n";
echo "GATE8G_RUNTIME_NOT_WIRED=PASS\n";
echo "GATE8G_PATIENT_MERGE_DISABLED=PASS\n";
echo "GATE8G_CLINICAL_BOUNDARY=PASS\n";
echo "PP309_HASH_STABLE=true\n";
echo 'PP310_HASH=' . $pp310Hash . "\n";
echo "Gate8GPatientIdentityPersistenceMigrationTest PASS\n";
