<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
require_once __DIR__ . '/../services/CanonicalSourceAuthority.php';
require_once __DIR__ . '/../services/RetentionPolicyRegistry.php';
require_once __DIR__ . '/../services/DispositionPlanner.php';

use Platform\Contracts\ApprovalReferenceSet;
use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\CanonicalSourceReason;
use Platform\Contracts\CanonicalSourceRecord;
use Platform\Contracts\CapabilitySet;
use Platform\Contracts\DispositionAction;
use Platform\Contracts\DispositionResolution;
use Platform\Contracts\DispositionRequest;
use Platform\Contracts\ReadOperationContract;
use Platform\Contracts\ReasonCode;
use Platform\Contracts\RetentionPolicy;
use Platform\Contracts\RetentionPolicyRegistration;
use Platform\Contracts\RetentionState;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\ScopeSet;
use Platform\Contracts\SourceClassification;
use Platform\Contracts\SubjectReference;
use Platform\Services\CanonicalSourceAuthority;
use Platform\Services\DispositionPlanner;
use Platform\Services\RetentionPolicyRegistry;

function gate6cAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function gate6cThrows(callable $callback, string $message): void
{
    try { $callback(); } catch (InvalidArgumentException|RuntimeException) { return; }
    throw new RuntimeException($message);
}

function gate6cWrite(string $entity = 'record'): CanonicalSourceRecord
{
    return new CanonicalSourceRecord('clinical', $entity, SourceClassification::CANONICAL_WRITE, 'clinical_writer', 'clinical_reader', 'source-' . $entity, 'pending', true, true);
}
function gate6cRead(string $entity = 'record'): CanonicalSourceRecord
{
    return new CanonicalSourceRecord('clinical', $entity, SourceClassification::CANONICAL_READ, null, 'clinical_reader', 'source-' . $entity, 'none', false, false);
}
function gate6cPolicy(string $state = RetentionState::ARCHIVED, bool $clinical = true, bool $unresolved = false, bool $legalHold = false, string $anonymization = 'resolved'): RetentionPolicyRegistration
{
    $policy = $unresolved
        ? new RetentionPolicy('clinical', 'record', 'care delivery', 'case closure', null, true, $state, RetentionState::ARCHIVED, 'reviewed_disposition', $legalHold, 'director', 'privacy_governance', 'unresolved')
        : new RetentionPolicy('clinical', 'record', 'care delivery', 'case closure', 86400, false, $state, RetentionState::ARCHIVED, 'reviewed_disposition', $legalHold, 'director', 'privacy_governance', 'approved');
    return new RetentionPolicyRegistration($policy, $clinical, false, $anonymization, true, 'policy-clinical-record');
}
function gate6cRequest(string $action, string $risk = RiskLevel::R3, bool $simulation = true, ?string $target = RetentionState::DELETED, ?string $case = 'case-ref-6c', ?ApprovalReferenceSet $approvals = null, bool $legalHold = false, bool $audit = true, bool $reconcile = true, bool $rollback = true, ?int $expiration = null): DispositionRequest
{
    return new DispositionRequest('request-ref-6c', 'idempotency-ref-6c', 'clinical', 'record', new SubjectReference('clinical_record', 'record-ref-6c'), $action, 'approved care purpose', 'actor-ref-6c', $risk, 'policy-clinical-record', 'source-record', RetentionState::ARCHIVED, $target, $simulation, $case, $approvals ?? new ApprovalReferenceSet(['approval-ref-6c']), $legalHold, $audit, $reconcile, $rollback, $expiration);
}

$authority = new CanonicalSourceAuthority([gate6cWrite(), gate6cRead()]);
gate6cAssert($authority->resolveWrite('clinical', 'record')->allowed(), 'one canonical write resolves');
gate6cAssert($authority->resolveRead('clinical', 'record')->allowed(), 'one canonical read resolves');
gate6cAssert($authority->resolveWrite('clinical', 'record')->source()?->classification() === SourceClassification::CANONICAL_WRITE, 'write classification stable');
gate6cAssert($authority->snapshot()[0]['domain'] === 'clinical', 'snapshot is sanitized and stable');
gate6cAssert(!array_key_exists('sql', $authority->snapshot()[0]), 'snapshot excludes SQL and rows');
$duplicate = $authority->register(gate6cWrite());
gate6cAssert(!$duplicate->allowed() && $duplicate->reasonCode() === CanonicalSourceReason::SOURCE_CONFLICT, 'second canonical write denied');
$emptyAuthority = new CanonicalSourceAuthority();
gate6cAssert($emptyAuthority->resolveWrite('clinical', 'missing')->reasonCode() === CanonicalSourceReason::SOURCE_UNRESOLVED, 'unresolved source fail closed');
gate6cThrows(fn() => new CanonicalSourceRecord('clinical', 'broken', SourceClassification::UNRESOLVED, null, null, 'broken-source', 'unknown', false, false), 'unresolved source cannot be active');
gate6cThrows(fn() => new CanonicalSourceAuthority([gate6cWrite('conflict'), gate6cWrite('conflict')]), 'duplicate canonical writes rejected');

$read = new ReadOperationContract('catalog_read');
gate6cAssert($authority->validateRead($read)->allowed() && $read->isPure() && !$read->hasSideEffects(), 'pure read contract allowed');
gate6cThrows(fn() => new ReadOperationContract('catalog_read', true), 'schema creation on read rejected');
gate6cThrows(fn() => new ReadOperationContract('catalog_read', false, false, true), 'seed on read rejected');
gate6cThrows(fn() => new ReadOperationContract('catalog_read', false, false, false, false, false, false, true), 'dual write on read rejected');

$registry = new RetentionPolicyRegistry([gate6cPolicy()]);
gate6cAssert($registry->resolve('clinical', 'record') !== null, 'retention policy resolves');
gate6cAssert($registry->resolve('clinical', 'missing') === null, 'missing retention policy remains unresolved');
gate6cAssert($registry->snapshot()[0]['clinical_data'] === true && $registry->snapshot()[0]['commercial_state_dependency'] === false, 'clinical policy independent of commercial state');
gate6cThrows(fn() => new RetentionPolicyRegistration(new RetentionPolicy('clinical', 'bad', 'care', 'closure', 10, false, RetentionState::ACTIVE, RetentionState::ARCHIVED, 'reviewed', false, 'director', 'owner', 'approved'), true, true), 'clinical policy cannot depend on commercial state');
$unresolvedRegistry = new RetentionPolicyRegistry([gate6cPolicy(RetentionState::ARCHIVED, true, true)]);
gate6cAssert($unresolvedRegistry->resolve('clinical', 'record')?->policy()->retentionUnresolved() === true, 'retention_unresolved explicit');

$planner = new DispositionPlanner();
$authorization = AuthorizationDecision::allow(RiskLevel::R3, 'all_disposition_requirements_satisfied', ['membership', 'scope', 'capability']);
$source = $authority->resolveWrite('clinical', 'record');
$deletePlan = $planner->plan(gate6cRequest(DispositionAction::DELETE), $registry->resolve('clinical', 'record'), $source, $authorization, 'available');
gate6cAssert($deletePlan->allowedForSimulation() && !$deletePlan->executable(), 'delete is simulation only');
gate6cAssert($deletePlan->reasonCode() === 'allowed_for_simulation', 'simulation reason stable');
$exportPlan = $planner->plan(gate6cRequest(DispositionAction::EXPORT_MASS, RiskLevel::R3, true, null, 'case-ref-6c', null, false, true, true, true, 3600), $registry->resolve('clinical', 'record'), $source, $authorization, 'available');
gate6cAssert($exportPlan->allowedForSimulation() && !$exportPlan->executable(), 'mass export is simulation only and R3');
gate6cAssert($exportPlan->dispositionMode() === DispositionAction::EXPORT_MASS, 'export mode preserved');

gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE), $unresolvedRegistry->resolve('clinical', 'record'), $source, $authorization, 'available')->reasonCode() === 'retention_unresolved', 'retention unresolved blocks delete');
$anonymizationUnresolved = new RetentionPolicyRegistry([gate6cPolicy(RetentionState::ARCHIVED, true, false, false, DispositionResolution::ANONYMIZATION_UNRESOLVED)]);
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::ANONYMIZE, RiskLevel::R3, true, RetentionState::ANONYMIZED), $anonymizationUnresolved->resolve('clinical', 'record'), $source, $authorization, 'available')->reasonCode() === 'anonymization_unresolved', 'anonymization unresolved blocks anonymize');
$holdRegistry = new RetentionPolicyRegistry([gate6cPolicy(RetentionState::ARCHIVED, true, false, true)]);
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE), $holdRegistry->resolve('clinical', 'record'), $source, $authorization, 'available')->reasonCode() === 'legal_hold', 'legal hold blocks delete');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE), null, $source, $authorization, 'available')->reasonCode() === 'policy_unresolved', 'missing policy denies');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE), $registry->resolve('clinical', 'record'), $emptyAuthority->resolveWrite('clinical', 'missing'), $authorization, 'available')->reasonCode() === 'source_unresolved', 'source unresolved denies');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE), $registry->resolve('clinical', 'record'), $source, AuthorizationDecision::deny(RiskLevel::R3, ReasonCode::CAPABILITY_DENIED), 'available')->reasonCode() === 'authorization_denied', 'authorization deny propagates');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE), $registry->resolve('clinical', 'record'), $source, $authorization, 'unavailable')->reasonCode() === 'audit_unavailable', 'audit unavailable denies');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE, RiskLevel::R3, false), $registry->resolve('clinical', 'record'), $source, $authorization, 'available')->reasonCode() === 'simulation_only', 'real execution request denied');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE, RiskLevel::R2), $registry->resolve('clinical', 'record'), $source, $authorization, 'available')->reasonCode() === 'risk_requirement_unsatisfied', 'delete requires R3');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE, RiskLevel::R3, true, RetentionState::DELETED, null, new ApprovalReferenceSet()), $registry->resolve('clinical', 'record'), $source, $authorization, 'available')->reasonCode() === 'approval_required', 'R3 approval required');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE, RiskLevel::R3, true, RetentionState::DELETED, 'case-ref-6c', new ApprovalReferenceSet(), false, true, true, true), $registry->resolve('clinical', 'record'), $source, $authorization, 'available')->reasonCode() === 'approval_required', 'missing approval denied');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::EXPORT_MASS, RiskLevel::R3, true, null, 'case-ref-6c', new ApprovalReferenceSet(), false, true, true, true, null), $registry->resolve('clinical', 'record'), $source, $authorization, 'available')->reasonCode() === 'approval_required', 'mass export approval required');

$activeRegistry = new RetentionPolicyRegistry([gate6cPolicy(RetentionState::ACTIVE)]);
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE), $activeRegistry->resolve('clinical', 'record'), $source, $authorization, 'available')->reasonCode() === 'current_state_mismatch', 'active clinical record cannot go directly to deleted');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE), $registry->resolve('clinical', 'record'), $source, $authorization, 'available')->toArray()['executable'] === false, 'every Gate 6C plan executable false');
gate6cAssert($planner->plan(gate6cRequest(DispositionAction::DELETE), $registry->resolve('clinical', 'record'), $source, $authorization, 'available')->idempotencyReference() === $planner->plan(gate6cRequest(DispositionAction::DELETE), $registry->resolve('clinical', 'record'), $source, $authorization, 'available')->idempotencyReference(), 'idempotency reference stable');

echo "Gate6CCanonicalRetentionDispositionTest PASS\n";
