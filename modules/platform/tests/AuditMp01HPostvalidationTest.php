<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/modules/platform/contracts/CanonicalAuditEventType.php';
require_once $root . '/modules/platform/contracts/TrustedRequestContext.php';
require_once $root . '/modules/platform/contracts/AuditEventScopePolicy.php';
require_once $root . '/modules/platform/services/CanonicalAuditPolicyRegistry.php';
require_once $root . '/modules/platform/services/CorrelatableOperationCatalog.php';
require_once $root . '/modules/platform/services/SourceModuleCatalog.php';
require_once $root . '/modules/identity/audit/Mp01eEventScopePolicy.php';
require_once $root . '/modules/platform/audit/Mp01fEventScopePolicy.php';
require_once $root . '/modules/platform/audit/readiness/AuditMp01HReadiness.php';

use Identity\Audit\Mp01eEventScopePolicy;
use Platform\Audit\Mp01fEventScopePolicy;
use Platform\Audit\Readiness\AuditMp01HReadiness;
use Platform\Contracts\AuditEventScopePolicy;
use Platform\Contracts\CanonicalAuditEventType;
use Platform\Services\CanonicalAuditPolicyRegistry;
use Platform\Services\CorrelatableOperationCatalog;
use Platform\Services\SourceModuleCatalog;

$tests = [];
function h01Ok(array &$tests, bool $condition, string $name): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL {$name}\n");
        exit(1);
    }
    $tests[] = $name;
}

$events = CanonicalAuditEventType::all();
$rows = CanonicalAuditPolicyRegistry::canonicalRows();
$policyEvents = array_column($rows, 'event_type');
$operations = new CorrelatableOperationCatalog();
$modules = new SourceModuleCatalog();
$mp01e = new Mp01eEventScopePolicy();
$mp01f = new Mp01fEventScopePolicy($operations, $modules);
$eScope = array_keys($mp01e->map());
$fScope = $mp01f->eventTypes();
$combined = array_merge($eScope, $fScope);
$eligible = array_values(array_filter($rows, static fn(array $row): bool => ($row['self_timeline'] ?? false) === true));

h01Ok($tests, count($events) === 28 && count(array_unique($events)) === 28, 'canonical_event_count');
h01Ok($tests, $policyEvents === $events, 'policy_catalog_exact_order');
h01Ok($tests, count($rows) === 28, 'policy_row_count');
h01Ok($tests, array_keys($operations->eventMap()) === $events, 'operation_mapping_complete');
h01Ok($tests, array_keys($modules->eventMap()) === $events, 'source_module_mapping_complete');
h01Ok($tests, count($eScope) === 13 && $eScope === array_slice($events, 0, 13), 'mp01e_scope_exact');
h01Ok($tests, count($fScope) === 15 && $fScope === array_slice($events, 13), 'mp01f_scope_exact');
h01Ok($tests, count(array_intersect($eScope, $fScope)) === 0, 'scope_duplicate_zero');
h01Ok($tests, $combined === $events, 'scope_unaccounted_zero');
h01Ok($tests, $mp01e instanceof AuditEventScopePolicy && $mp01f instanceof AuditEventScopePolicy, 'shared_scope_contract');
h01Ok($tests, count($eligible) === 12, 'self_timeline_canonical_eligible_12');

$matrix = AuditMp01HReadiness::readinessMatrix();
$sequence = AuditMp01HReadiness::activationSequence();
$rollback = AuditMp01HReadiness::rollbackMatrix();
$secrets = AuditMp01HReadiness::secretRequirements();
$privileges = AuditMp01HReadiness::privilegeRequirements();
$scenarios = AuditMp01HReadiness::stagingScenarios();
$signals = AuditMp01HReadiness::observabilitySignals();
$boundaries = AuditMp01HReadiness::authorizationBoundaries();
$summary = AuditMp01HReadiness::summary();

h01Ok($tests, count($matrix) === 18, 'readiness_matrix_18');
h01Ok($tests, $matrix['PERSISTENCE_SCHEMA']['status'] === 'IMPLEMENTED_DORMANT', 'persistence_dormant');
h01Ok($tests, $matrix['D11_MIGRATION']['status'] === 'REQUIRES_DB_EXECUTION', 'd11_requires_execution');
h01Ok($tests, $matrix['DB_PRIVILEGES']['status'] === 'REQUIRES_SECRET_OR_PRIVILEGE', 'db_privileges_deferred');
h01Ok($tests, $matrix['WRITER_RUNTIME_BINDING']['status'] === 'REQUIRES_RUNTIME_IMPLEMENTATION', 'writer_binding_deferred');
h01Ok($tests, $matrix['STAGING_E2E']['status'] === 'REQUIRES_STAGING_E2E', 'staging_e2e_deferred');
h01Ok($tests, $matrix['PRODUCTION_CUTOVER']['status'] === 'REQUIRES_DIRECTOR_AUTHORIZATION', 'cutover_authorization_required');
h01Ok($tests, $matrix['SELF_SUBJECT_RESOLVER_ADAPTER']['missing_prerequisite'] === 'REQUIRED_BEFORE_PRODUCTIVE_READ_WIRING', 'subject_pagination_prerequisite');
h01Ok($tests, count($sequence) === 16 && $sequence[0]['code'] === 'A0' && $sequence[15]['code'] === 'A15', 'activation_sequence_exact');
h01Ok($tests, count($rollback) === 7, 'rollback_layers_exact');
h01Ok($tests, array_reduce($rollback, static fn(bool $carry, array $row): bool => $carry && $row['data_deletion'] === false, true), 'rollback_never_deletes_history');
h01Ok($tests, count($secrets) === 5 && !str_contains(json_encode($secrets, JSON_THROW_ON_ERROR), 'secret_value'), 'secret_classes_no_values');
h01Ok($tests, count($privileges) === 3, 'least_privilege_principals');
$writerPrivilege = array_values(array_filter($privileges, static fn(array $row): bool => $row['principal'] === 'WRITER'))[0] ?? [];
h01Ok($tests, str_contains($writerPrivilege['least_privilege'] ?? '', 'audit_mp01c_lock_stream_head_v1'), 'writer_lock_execute_required');
h01Ok($tests, str_contains($writerPrivilege['least_privilege'] ?? '', 'audit_mp01c_advance_stream_head_cas_v1'), 'writer_cas_execute_required');
h01Ok($tests, str_contains($writerPrivilege['least_privilege'] ?? '', 'no direct head UPDATE/FOR UPDATE, DELETE, or LOCK TABLES'), 'writer_direct_lock_mutations_absent');
h01Ok($tests, count($scenarios) === 19, 'staging_scenarios_complete');
h01Ok($tests, count($signals) === 11, 'observability_signals_complete');
h01Ok($tests, array_reduce($signals, static fn(bool $carry, array $row): bool => $carry && $row['threshold'] === 'TO_BE_SET_BEFORE_CUTOVER', true), 'thresholds_not_invented');
h01Ok($tests, count($boundaries) === 9 && array_reduce($boundaries, static fn(bool $carry, array $row): bool => $carry && $row['director_authorization_required'] === true, true), 'future_authorizations_explicit');
h01Ok($tests, $summary['STATIC_SUBSYSTEM_READY'] === true, 'static_subsystem_ready');
h01Ok($tests, $summary['ACTIVATION_PLAN_READY'] === true, 'activation_plan_ready');
h01Ok($tests, $summary['PRODUCTIVE_ACTIVATION_READY'] === false, 'productive_activation_not_ready');
h01Ok($tests, $summary['PRODUCTION_CUTOVER_READY'] === false, 'production_cutover_not_ready');
h01Ok($tests, $summary['SELF_SUBJECT_SCOPE_PAGINATION_COMPATIBLE'] === 'REQUIRED_BEFORE_PRODUCTIVE_READ_WIRING', 'subject_pagination_future_invariant');

echo "CROSS_PHASE_CONTRACT_COMPATIBILITY=PASS\n";
echo "TOTAL_CANONICAL_AUDIT_EVENT_TYPES=28\n";
echo "EVENT_CATALOG_MATCH=28/28\n";
echo "POLICY_REGISTRY_MATCH=28/28\n";
echo "OPERATION_MAPPING=28/28\n";
echo "SOURCE_MODULE_MAPPING=28/28\n";
echo "MP01E_SCOPE=13/13\n";
echo "MP01F_SCOPE=15/15\n";
echo "UNSCOPED_CANONICAL_EVENTS=0\n";
echo "DUPLICATE_SCOPED_EVENTS=0\n";
echo "SELF_TIMELINE_CANONICAL_ELIGIBLE_EVENTS=12/12\n";
echo "READINESS_MATRIX=18/18_VALID\n";
echo "ACTIVATION_SEQUENCE=A0-A15_VALID\n";
echo "ROLLBACK_MATRIX=7/7_VALID\n";
echo "SELF_SUBJECT_SCOPE_PAGINATION_COMPATIBLE=REQUIRED_BEFORE_PRODUCTIVE_READ_WIRING\n";
echo 'POSTVALIDATION_TESTS=' . count($tests) . '/' . count($tests) . "_PASS\n";
