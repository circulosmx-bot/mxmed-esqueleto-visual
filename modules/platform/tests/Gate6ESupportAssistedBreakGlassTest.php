<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../services/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../adapters/*.php') as $file) require_once $file;

use Platform\Adapters\InMemoryAuditTrailAdapter;
use Platform\Adapters\RejectingAuditTrailAdapter;
use Platform\Adapters\UnavailableAuditTrailAdapter;
use Platform\Contracts\ActorReference;
use Platform\Contracts\ApprovalReferenceSet;
use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\AuditEventReference;
use Platform\Contracts\AuditWriteResult;
use Platform\Contracts\BreakGlassContract;
use Platform\Contracts\CapabilitySet;
use Platform\Contracts\FeatureFlags;
use Platform\Contracts\PrivilegedAccessApprovalEvidence;
use Platform\Contracts\PrivilegedAccessMode;
use Platform\Contracts\PrivilegedAccessReason;
use Platform\Contracts\PrivilegedAccessRequest;
use Platform\Contracts\ReasonCode;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\ScopeSet;
use Platform\Contracts\SupportAccessState;
use Platform\Contracts\SupportAssistedAccessContract;
use Platform\Contracts\SubjectReference;
use Platform\Services\BreakGlassAccessEvaluator;
use Platform\Services\PrivilegedAccessActivationGate;
use Platform\Services\PrivilegedAccessAuditEventFactory;
use Platform\Services\PrivilegedAccessPolicySupport;
use Platform\Services\SupportAccessLifecyclePlanner;
use Platform\Services\SupportAssistedAccessEvaluator;

function gate6eAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function gate6eThrows(callable $callback, string $message): void
{
    try { $callback(); } catch (InvalidArgumentException|RuntimeException) { return; }
    throw new RuntimeException($message);
}

function gate6eApproval(string $reference, string $actorId, string $mode, string $case, string $at): PrivilegedAccessApprovalEvidence
{
    return new PrivilegedAccessApprovalEvidence($reference, new ActorReference('governance', $actorId), $mode, $case, $at);
}

/** @param array<string,mixed> $overrides */
function gate6eRequest(array $overrides = []): PrivilegedAccessRequest
{
    $value = static fn(string $key, mixed $default): mixed => array_key_exists($key, $overrides) ? $overrides[$key] : $default;
    $mode = $value('mode', PrivilegedAccessMode::SUPPORT_ASSISTED);
    $case = $value('case', 'case-6e');
    $defaultApprovals = $case === null ? [] : [gate6eApproval('approval-1', 'approver-1', $mode, $case, '2026-07-20T11:00:00Z')];
    return new PrivilegedAccessRequest(
        requestReference: $value('request', 'request-6e'),
        mode: $mode,
        state: $value('state', SupportAccessState::APPROVED),
        realActor: $value('real', new ActorReference('account', 'real-6e')),
        effectiveActor: $value('effective', new ActorReference('operator', 'effective-6e')),
        affectedSubject: $value('subject', new SubjectReference('profile', 'subject-6e')),
        authorizationPlane: $value('plane', $mode === PrivilegedAccessMode::BREAK_GLASS ? AuthorizationPlane::GOVERNANCE_EMERGENCY : AuthorizationPlane::INTERNAL_OPERATOR),
        riskLevel: $value('risk', $mode === PrivilegedAccessMode::BREAK_GLASS ? RiskLevel::R3 : RiskLevel::R2),
        caseReference: $case,
        reasonReference: $value('reason', 'reason-reference-6e'),
        scopes: $value('scopes', new ScopeSet(['profile:read'])),
        capabilities: $value('capabilities', new CapabilitySet(['profile:read'])),
        requestedAtUtc: $value('requested', '2026-07-20T10:00:00Z'),
        expiresAtUtc: $value('expires', '2026-07-20T13:00:00Z'),
        correlationId: $value('correlation', 'correlation-6e'),
        auditRequestId: $value('audit', 'audit-request-6e'),
        approvalEvidence: $value('approvals', $defaultApprovals),
        reauthenticationVerified: $value('reauth', true),
        mfaVerified: $value('mfa', true),
        visibilityRequired: $value('visibility', true),
        postReviewRequired: $value('post_review', $mode === PrivilegedAccessMode::BREAK_GLASS),
        clinicalAccessRequested: $value('clinical', false),
        emergencyConfirmed: $value('emergency', $mode === PrivilegedAccessMode::BREAK_GLASS)
    );
}

function gate6eAuthorization(string $risk = RiskLevel::R2, bool $allowed = true, ?string $correlation = 'correlation-6e'): AuthorizationDecision
{
    return $allowed ? AuthorizationDecision::allow($risk, 'central_privileged_access_policy', ['context', 'risk', 'audit'], $correlation) : AuthorizationDecision::deny($risk, ReasonCode::AUTHORIZATION_PLANE_MISMATCH, ['context'], ['central_authorization'], $correlation);
}

$supportEvaluator = new SupportAssistedAccessEvaluator(true);
$breakEvaluator = new BreakGlassAccessEvaluator(true);
$audit = new InMemoryAuditTrailAdapter();
$now = '2026-07-20T12:00:00Z';

gate6eAssert(FeatureFlags::defaults()[FeatureFlags::SUPPORT_ASSISTED_SESSION_ENABLED] === false && FeatureFlags::defaults()[FeatureFlags::BREAK_GLASS_ENABLED] === false, 'feature flags default false');
gate6eAssert(SupportAssistedAccessContract::enabledByDefault() === false && BreakGlassContract::enabledByDefault() === false, 'existing privileged contracts disabled');
gate6eAssert(SupportAssistedAccessContract::minimumRisk() === RiskLevel::R2 && BreakGlassContract::risk() === RiskLevel::R3, 'existing risk contracts preserved');
gate6eAssert(PrivilegedAccessMode::all() === [PrivilegedAccessMode::SUPPORT_ASSISTED, PrivilegedAccessMode::BREAK_GLASS], 'privileged modes exact');
gate6eThrows(fn() => PrivilegedAccessMode::assertValid('support_assisted_to_break_glass'), 'unknown mode rejected');

$validSupport = gate6eRequest();
$supportDecision = $supportEvaluator->evaluate($validSupport, gate6eAuthorization(RiskLevel::R2), $audit, $now);
gate6eAssert($supportDecision->policySatisfied() && !$supportDecision->activatable() && $supportDecision->reasonCode() === PrivilegedAccessReason::POLICY_SATISFIED_NOT_ACTIVATABLE, 'support policy satisfied but never activatable');
gate6eAssert($audit->events()[0] instanceof AuditEventReference, 'support policy audit event written to test adapter');
gate6eAssert($audit->events()[0]->realActor()?->id() === 'real-6e' && $audit->events()[0]->effectiveActor()?->id() === 'effective-6e', 'audit preserves real/effective actors');
gate6eAssert(array_diff(array_keys($audit->events()[0]->metadata()), ['resource_type', 'resource_reference', 'decision', 'reason_code', 'authorization_plane', 'case_reference', 'audit_category']) === [], 'privileged audit metadata is Gate6D allow-listed');

gate6eAssert((new SupportAssistedAccessEvaluator())->evaluate($validSupport, gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::FEATURE_DISABLED, 'support feature hard disabled');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::MODE_MISMATCH, 'support mode mismatch denied');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['plane' => AuthorizationPlane::GOVERNANCE_EMERGENCY]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::MODE_MISMATCH, 'support governance plane denied');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['risk' => RiskLevel::R1]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::RISK_REQUIREMENT_UNSATISFIED, 'support R0/R1 denied');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['state' => SupportAccessState::PENDING_APPROVAL]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::INVALID_STATE, 'support state must be approved');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['real' => null]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::ACTOR_SEPARATION_REQUIRED, 'support real actor required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['effective' => null]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::ACTOR_SEPARATION_REQUIRED, 'support effective actor required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['effective' => new ActorReference('account', 'real-6e')]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::ACTOR_SEPARATION_REQUIRED, 'support actor impersonation denied');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['case' => null]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::CASE_REQUIRED, 'support case required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['reason' => null]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::REASON_REQUIRED, 'support reason reference required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['scopes' => new ScopeSet()]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::SCOPE_REQUIRED, 'support scope required');
gate6eThrows(fn() => new ScopeSet(['*']), 'global wildcard scope rejected');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['scopes' => new ScopeSet(['all'])]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::WILDCARD_SCOPE_DENIED, 'all wildcard denied');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['scopes' => new ScopeSet(['admin.everything'])]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::WILDCARD_SCOPE_DENIED, 'admin wildcard denied');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['scopes' => new ScopeSet(['support.all'])]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::WILDCARD_SCOPE_DENIED, 'support wildcard denied');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['expires' => null]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::EXPIRATION_REQUIRED, 'expiration required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['requested' => '2026-07-20T13:00:00Z', 'expires' => '2026-07-20T13:00:00Z']), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::EXPIRATION_REQUIRED, 'expiration after request required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['expires' => '2026-07-20T11:00:00Z']), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::REQUEST_EXPIRED, 'expired request denied');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['expires' => '2026-07-20T13:00:00']), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::EXPIRATION_REQUIRED, 'timezone-less expiration denied');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['reauth' => false]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::REAUTHENTICATION_REQUIRED, 'support reauthentication required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['mfa' => false]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::MFA_REQUIRED, 'support MFA required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['approvals' => []]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::APPROVAL_REQUIRED, 'support approval required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['approvals' => [new PrivilegedAccessApprovalEvidence('approval-1', new ActorReference('account', 'real-6e'), PrivilegedAccessMode::SUPPORT_ASSISTED, 'case-6e', '2026-07-20T11:00:00Z')]]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::APPROVER_SEPARATION_REQUIRED, 'support approver separation required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['visibility' => false]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::VISIBILITY_REQUIRED, 'support visibility required');
gate6eAssert($supportEvaluator->evaluate(gate6eRequest(['clinical' => true]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::CLINICAL_ACCESS_DENIED, 'support clinical access denied');
gate6eAssert($supportEvaluator->evaluate($validSupport, AuthorizationDecision::deny(RiskLevel::R2, ReasonCode::AUTHORIZATION_PLANE_MISMATCH), $audit, $now)->reasonCode() === PrivilegedAccessReason::AUTHORIZATION_DENIED, 'central authorization deny propagates');
gate6eAssert($supportEvaluator->evaluate($validSupport, gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::RISK_REQUIREMENT_UNSATISFIED, 'central risk mismatch denied');
gate6eAssert($supportEvaluator->evaluate($validSupport, gate6eAuthorization(), null, $now)->reasonCode() === PrivilegedAccessReason::AUDIT_UNAVAILABLE, 'support missing audit fail closed');
gate6eAssert($supportEvaluator->evaluate($validSupport, gate6eAuthorization(), new UnavailableAuditTrailAdapter(), $now)->reasonCode() === PrivilegedAccessReason::AUDIT_UNAVAILABLE, 'support unavailable audit fail closed');
gate6eAssert($supportEvaluator->evaluate($validSupport, gate6eAuthorization(), new RejectingAuditTrailAdapter(), $now)->reasonCode() === PrivilegedAccessReason::AUDIT_REQUIRED, 'support rejected audit fail closed');

$breakRequest = gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'approvals' => [gate6eApproval('approval-1', 'approver-1', PrivilegedAccessMode::BREAK_GLASS, 'case-6e', '2026-07-20T11:00:00Z'), gate6eApproval('approval-2', 'approver-2', PrivilegedAccessMode::BREAK_GLASS, 'case-6e', '2026-07-20T11:01:00Z')]]);
$breakDecision = $breakEvaluator->evaluate($breakRequest, gate6eAuthorization(RiskLevel::R3), $audit, $now);
gate6eAssert($breakDecision->policySatisfied() && !$breakDecision->activatable() && $breakDecision->reasonCode() === PrivilegedAccessReason::POLICY_SATISFIED_NOT_ACTIVATABLE, 'break-glass policy satisfied but never activatable');
gate6eAssert($breakEvaluator->evaluate($breakRequest, gate6eAuthorization(RiskLevel::R3), null, $now)->reasonCode() === PrivilegedAccessReason::AUDIT_UNAVAILABLE, 'break-glass audit unavailable fail closed');
gate6eAssert((new BreakGlassAccessEvaluator())->evaluate($breakRequest, gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::FEATURE_DISABLED, 'break-glass feature hard disabled');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::SUPPORT_ASSISTED]), gate6eAuthorization(), $audit, $now)->reasonCode() === PrivilegedAccessReason::MODE_MISMATCH, 'break-glass support mode denied');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'plane' => AuthorizationPlane::INTERNAL_OPERATOR, 'approvals' => []]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::MODE_MISMATCH, 'break-glass internal plane denied');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'risk' => RiskLevel::R2, 'approvals' => []]), gate6eAuthorization(RiskLevel::R2), $audit, $now)->reasonCode() === PrivilegedAccessReason::RISK_REQUIREMENT_UNSATISFIED, 'break-glass risk must be R3');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'emergency' => false, 'approvals' => []]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::EMERGENCY_REQUIRED, 'break-glass emergency required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'case' => null, 'approvals' => []]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::CASE_REQUIRED, 'break-glass case required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'reason' => null, 'approvals' => []]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::REASON_REQUIRED, 'break-glass reason required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'scopes' => new ScopeSet(), 'approvals' => []]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::SCOPE_REQUIRED, 'break-glass scope required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'expires' => null, 'approvals' => []]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::EXPIRATION_REQUIRED, 'break-glass expiration required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'reauth' => false, 'approvals' => []]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::REAUTHENTICATION_REQUIRED, 'break-glass reauthentication required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'mfa' => false, 'approvals' => []]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::MFA_REQUIRED, 'break-glass MFA required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'approvals' => []]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::DUAL_APPROVAL_REQUIRED, 'break-glass dual approval required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'approvals' => [gate6eApproval('approval-1', 'approver-1', PrivilegedAccessMode::BREAK_GLASS, 'case-6e', '2026-07-20T11:00:00Z')]]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::DUAL_APPROVAL_REQUIRED, 'break-glass second approval required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'post_review' => false, 'approvals' => [gate6eApproval('approval-1', 'approver-1', PrivilegedAccessMode::BREAK_GLASS, 'case-6e', '2026-07-20T11:00:00Z'), gate6eApproval('approval-2', 'approver-2', PrivilegedAccessMode::BREAK_GLASS, 'case-6e', '2026-07-20T11:01:00Z')]]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->reasonCode() === PrivilegedAccessReason::POST_REVIEW_REQUIRED, 'break-glass post review required');
gate6eAssert($breakEvaluator->evaluate(gate6eRequest(['mode' => PrivilegedAccessMode::BREAK_GLASS, 'clinical' => true, 'approvals' => [gate6eApproval('approval-1', 'approver-1', PrivilegedAccessMode::BREAK_GLASS, 'case-6e', '2026-07-20T11:00:00Z'), gate6eApproval('approval-2', 'approver-2', PrivilegedAccessMode::BREAK_GLASS, 'case-6e', '2026-07-20T11:01:00Z')]]), gate6eAuthorization(RiskLevel::R3), $audit, $now)->activatable() === false, 'break-glass clinical request never activates access');
gate6eAssert($breakEvaluator->evaluate($breakRequest, AuthorizationDecision::deny(RiskLevel::R3, ReasonCode::AUTHORIZATION_PLANE_MISMATCH), $audit, $now)->reasonCode() === PrivilegedAccessReason::AUTHORIZATION_DENIED, 'break-glass central authorization deny propagates');
gate6eAssert($breakEvaluator->evaluate($breakRequest, gate6eAuthorization(RiskLevel::R3), new RejectingAuditTrailAdapter(), $now)->reasonCode() === PrivilegedAccessReason::AUDIT_REQUIRED, 'break-glass rejected audit fail closed');

$activationGate = new PrivilegedAccessActivationGate();
gate6eAssert(!$activationGate->mayActivate(PrivilegedAccessMode::SUPPORT_ASSISTED, [FeatureFlags::SUPPORT_ASSISTED_SESSION_ENABLED => true]), 'support activation hard stop');
gate6eAssert(!$activationGate->mayActivate(PrivilegedAccessMode::BREAK_GLASS, [FeatureFlags::BREAK_GLASS_ENABLED => true]), 'break-glass activation hard stop');
gate6eAssert($activationGate->evaluate([FeatureFlags::SUPPORT_ASSISTED_SESSION_ENABLED => true])['reason_code'] === PrivilegedAccessReason::RUNTIME_ACTIVATION_DISABLED, 'runtime activation disabled regardless of requested flag');
gate6eThrows(fn() => $activationGate->mayActivate('unknown', []), 'activation mode unknown rejected');

$lifecycle = new SupportAccessLifecyclePlanner();
gate6eAssert($lifecycle->plan(SupportAccessState::REQUESTED, SupportAccessState::PENDING_APPROVAL)['allowed'], 'requested to pending allowed theoretically');
gate6eAssert($lifecycle->plan(SupportAccessState::REQUESTED, SupportAccessState::ACTIVE)['allowed'] === false, 'requested to active denied');
gate6eAssert($lifecycle->plan(SupportAccessState::APPROVED, SupportAccessState::ACTIVE)['allowed'] && $lifecycle->plan(SupportAccessState::APPROVED, SupportAccessState::ACTIVE)['executable'] === false, 'approved active theoretical only');
gate6eAssert($lifecycle->plan(SupportAccessState::EXPIRED, SupportAccessState::ACTIVE)['allowed'] === false, 'expired cannot reactivate');
gate6eAssert($lifecycle->plan(SupportAccessState::REVOKED, SupportAccessState::ACTIVE)['allowed'] === false, 'revoked cannot reactivate');
gate6eAssert($lifecycle->plan(SupportAccessState::CLOSED, SupportAccessState::REQUESTED)['allowed'] === false, 'closed terminal');
gate6eAssert($lifecycle->plan(SupportAccessState::DENIED, SupportAccessState::CLOSED)['allowed'] && !$lifecycle->plan(SupportAccessState::DENIED, SupportAccessState::CLOSED)['transition_real'], 'denied close theoretical');

$factory = new PrivilegedAccessAuditEventFactory();
$factoryEvent = $factory->policyEvaluated($validSupport, 'allow', PrivilegedAccessReason::POLICY_SATISFIED_NOT_ACTIVATABLE);
gate6eAssert($factoryEvent->eventName() === 'support_assisted_policy_evaluated', 'support audit event name stable');
gate6eAssert($factoryEvent->realActor()?->id() === 'real-6e' && $factoryEvent->effectiveActor()?->id() === 'effective-6e', 'factory actor separation preserved');
gate6eAssert(!in_array('scopes', array_keys($factoryEvent->metadata()), true), 'factory does not serialize scopes payload');
gate6eAssert(array_diff(array_keys($factoryEvent->metadata()), ['resource_type', 'resource_reference', 'decision', 'reason_code', 'authorization_plane', 'case_reference', 'audit_category']) === [], 'factory metadata stays Gate6D allow-list');

$sourceFiles = glob(__DIR__ . '/../services/PrivilegedAccess*.php') ?: [];
$sourceFiles = [...$sourceFiles, __DIR__ . '/../services/SupportAssistedAccessEvaluator.php', __DIR__ . '/../services/BreakGlassAccessEvaluator.php', __DIR__ . '/../services/SupportAccessLifecyclePlanner.php'];
foreach ($sourceFiles as $file) {
    $source = file_get_contents($file);
    gate6eAssert(is_string($source) && !preg_match('/\b(session_start|setcookie|PDO|curl_exec|file_put_contents|error_log)\s*\(/i', $source), 'no runtime/session/persistence API in ' . basename($file));
}
gate6eAssert(!str_contains((string) file_get_contents(__DIR__ . '/../db/migrations/2026_07_20_01_create_platform_audit_events.sql'), 'Gate6E'), 'Gate 6D migration not modified by Gate 6E');

echo "Gate6ESupportAssistedBreakGlassTest PASS\n";
