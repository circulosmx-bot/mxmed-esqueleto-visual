<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../adapters/*.php') as $file) require_once $file;
require_once __DIR__ . '/../services/AuthorizationBoundary.php';

use Platform\Adapters\InMemoryAuditTrailAdapter;
use Platform\Adapters\RejectingAuditTrailAdapter;
use Platform\Adapters\UnavailableAuditTrailAdapter;
use Platform\Contracts\ActorReference;
use Platform\Contracts\ApprovalReferenceSet;
use Platform\Contracts\AuthorizationContext;
use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\AuthorizationRequirement;
use Platform\Contracts\CapabilitySet;
use Platform\Contracts\ReasonCode;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\ScopeSet;
use Platform\Contracts\SessionReference;
use Platform\Contracts\SubjectReference;
use Platform\Contracts\TrustedAuthorizationContext;
use Platform\Services\AuthorizationBoundary;

function gate6bAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function gate6bThrows(callable $callback, string $message): void
{
    try { $callback(); } catch (InvalidArgumentException|RuntimeException) { return; }
    throw new RuntimeException($message);
}

function gate6bContext(array $overrides = [], array $trustedOverrides = []): TrustedAuthorizationContext
{
    $value = static fn(string $key, mixed $default): mixed => array_key_exists($key, $overrides) ? $overrides[$key] : $default;
    $context = new AuthorizationContext(
        realActor: $value('real_actor', new ActorReference('account', 'account-ref-6b')),
        effectiveActor: $value('effective_actor', new ActorReference('account', 'account-ref-6b')),
        affectedSubject: $value('affected_subject', new SubjectReference('profile', 'profile-ref-6b')),
        sessionReference: $value('session_reference', new SessionReference('session-ref-6b')),
        accountId: $value('account_id', 'account-ref-6b'),
        credentialVersion: $value('credential_version', 1),
        membershipId: $value('membership_id', 'membership-ref-6b'),
        entityId: $value('entity_id', 'entity-ref-6b'),
        profileId: $value('profile_id', 'profile-ref-6b'),
        ownership: $value('ownership', 'owner'),
        role: $value('role', 'professional'),
        scopes: $value('scopes', new ScopeSet(['profile:read', 'profile:write'])),
        capabilities: $value('capabilities', new CapabilitySet(['profile:read', 'profile:write'])),
        action: $value('action', 'read'),
        resource: $value('resource', 'profile'),
        authorizationPlane: $value('authorization_plane', AuthorizationPlane::CUSTOMER_PROFESSIONAL),
        riskLevel: $value('risk_level', RiskLevel::R1),
        correlationId: $value('correlation_id', 'correlation-ref-6b'),
        requestId: $value('request_id', 'request-ref-6b'),
        caseId: $value('case_id', 'case-ref-6b'),
        approvalReferences: $value('approval_references', new ApprovalReferenceSet(['approval-ref-6b', 'approval-ref-6b-2']))
    );
    return TrustedAuthorizationContext::fromBackend(
        $context,
        'backend_resolver',
        array_key_exists('session_status', $trustedOverrides) ? $trustedOverrides['session_status'] : 'active',
        $trustedOverrides['account_active'] ?? true,
        $trustedOverrides['membership_active'] ?? true,
        $trustedOverrides['reauthenticated'] ?? true,
        $trustedOverrides['mfa_verified'] ?? true,
        $trustedOverrides['transitional_open'] ?? false
    );
}

function gate6bRequirement(array $overrides = []): AuthorizationRequirement
{
    $value = static fn(string $key, mixed $default): mixed => array_key_exists($key, $overrides) ? $overrides[$key] : $default;
    return new AuthorizationRequirement(
        $value('plane', AuthorizationPlane::CUSTOMER_PROFESSIONAL),
        $value('risk', RiskLevel::R1),
        $value('action', 'read'),
        $value('resource', 'profile'),
        $value('resource_id', 'profile-ref-6b'),
        $value('resource_id_required', true),
        $value('actor_required', true),
        $value('membership_required', true),
        $value('entity_profile_required', true),
        $value('ownership_required', true),
        $value('roles', ['professional']),
        $value('scopes', new ScopeSet(['profile:read'])),
        $value('capabilities', new CapabilitySet(['profile:read'])),
        $value('reauth', false),
        $value('mfa', false),
        $value('case', false),
        $value('approval', false),
        $value('dual_approval', false),
        $value('audit', null),
        $value('public_declared', false),
        $value('system_declared', false)
    );
}

$boundary = new AuthorizationBoundary();
$audit = new InMemoryAuditTrailAdapter();
$requirement = gate6bRequirement();
$allowed = $boundary->authorize(gate6bContext(), $requirement, $audit);
gate6bAssert($allowed->allowed() && $allowed->satisfiedRule() === 'all_requirements_satisfied', 'complete context allows');
gate6bAssert(count($audit->events()) === 1, 'R1 audit contract receives sanitized event');
gate6bAssert($allowed->toArray()['correlation_id'] === 'correlation-ref-6b', 'allow decision is sanitized');

$deny = $boundary->authorize(null, $requirement, $audit);
gate6bAssert(!$deny->allowed() && $deny->reasonCode() === ReasonCode::AUTH_CONTEXT_MISSING, 'null context default deny');
gate6bAssert($boundary->authorize(new AuthorizationContext(), $requirement, $audit)->reasonCode() === ReasonCode::CLIENT_IDENTITY_NOT_AUTHORITATIVE, 'plain context is not trusted');
gate6bAssert($boundary->authorize(TrustedAuthorizationContext::fromClient(new AuthorizationContext()), $requirement, $audit)->reasonCode() === ReasonCode::CLIENT_IDENTITY_NOT_AUTHORITATIVE, 'client context denied');
gate6bAssert($boundary->authorize(gate6bContext([], ['transitional_open' => true]), $requirement, $audit)->reasonCode() === ReasonCode::TRANSITIONAL_OPEN_DENIED, 'transitional open denied');

gate6bAssert($boundary->authorize(gate6bContext([], ['session_status' => null]), $requirement, $audit)->reasonCode() === ReasonCode::SESSION_MISSING, 'missing session precedes later requirements');
gate6bAssert($boundary->authorize(gate6bContext([], ['session_status' => 'invalid']), $requirement, $audit)->reasonCode() === ReasonCode::SESSION_INVALID, 'invalid session denied');
gate6bAssert($boundary->authorize(gate6bContext([], ['session_status' => 'expired']), $requirement, $audit)->reasonCode() === ReasonCode::SESSION_EXPIRED, 'expired session denied');
gate6bAssert($boundary->authorize(gate6bContext([], ['session_status' => 'revoked']), $requirement, $audit)->reasonCode() === ReasonCode::SESSION_REVOKED, 'revoked session denied');
gate6bAssert($boundary->authorize(gate6bContext(['credential_version' => null]), $requirement, $audit)->reasonCode() === ReasonCode::CREDENTIAL_VERSION_MISMATCH, 'credential version denied');
gate6bAssert($boundary->authorize(gate6bContext([], ['account_active' => false]), $requirement, $audit)->reasonCode() === ReasonCode::ACCOUNT_INACTIVE, 'inactive account denied');
gate6bAssert($boundary->authorize(gate6bContext(['membership_id' => null]), $requirement, $audit)->reasonCode() === ReasonCode::MEMBERSHIP_MISSING, 'missing membership denied');
gate6bAssert($boundary->authorize(gate6bContext([], ['membership_active' => false]), $requirement, $audit)->reasonCode() === ReasonCode::MEMBERSHIP_INACTIVE, 'inactive membership denied');
gate6bAssert($boundary->authorize(gate6bContext(['entity_id' => null]), $requirement, $audit)->reasonCode() === ReasonCode::ENTITY_UNRESOLVED, 'entity unresolved denied');
gate6bAssert($boundary->authorize(gate6bContext(['profile_id' => null]), $requirement, $audit)->reasonCode() === ReasonCode::PROFILE_UNRESOLVED, 'profile unresolved denied');
gate6bAssert($boundary->authorize(gate6bContext(['authorization_plane' => AuthorizationPlane::INTERNAL_OPERATOR]), $requirement, $audit)->reasonCode() === ReasonCode::AUTHORIZATION_PLANE_MISMATCH, 'plane mismatch denied');
gate6bAssert($boundary->authorize(gate6bContext(['ownership' => 'denied']), $requirement, $audit)->reasonCode() === ReasonCode::OWNERSHIP_DENIED, 'ownership denied');
gate6bAssert($boundary->authorize(gate6bContext(['role' => 'operator']), $requirement, $audit)->reasonCode() === ReasonCode::ROLE_DENIED, 'role denied independently');
gate6bAssert($boundary->authorize(gate6bContext(['scopes' => new ScopeSet(['profile:write'])]), $requirement, $audit)->reasonCode() === ReasonCode::SCOPE_DENIED, 'scope denied independently');
gate6bAssert($boundary->authorize(gate6bContext(['capabilities' => new CapabilitySet(['profile:write'])]), $requirement, $audit)->reasonCode() === ReasonCode::CAPABILITY_DENIED, 'capability denied independently');
gate6bAssert($boundary->authorize(gate6bContext(['action' => 'write']), $requirement, $audit)->reasonCode() === ReasonCode::ACTION_UNSUPPORTED, 'action denied');
gate6bAssert($boundary->authorize(gate6bContext(['resource' => 'patient']), $requirement, $audit)->reasonCode() === ReasonCode::RESOURCE_UNRESOLVED, 'resource denied');

$r3Requirement = gate6bRequirement(['risk' => RiskLevel::R3, 'reauth' => true, 'mfa' => true, 'case' => true, 'approval' => true, 'dual_approval' => true]);
gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R1]), $r3Requirement, $audit)->reasonCode() === ReasonCode::RISK_REQUIREMENT_UNSATISFIED, 'risk mismatch precedes R3 controls');
gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R3], ['reauthenticated' => false]), $r3Requirement, $audit)->reasonCode() === ReasonCode::REAUTHENTICATION_REQUIRED, 'reauth required');
gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R3], ['mfa_verified' => false]), $r3Requirement, $audit)->reasonCode() === ReasonCode::MFA_REQUIRED, 'MFA required');
gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R3, 'case_id' => null]), $r3Requirement, $audit)->reasonCode() === ReasonCode::CASE_REQUIRED, 'case required');
gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R3, 'approval_references' => new ApprovalReferenceSet()]), $r3Requirement, $audit)->reasonCode() === ReasonCode::APPROVAL_REQUIRED, 'approval required');
gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R3, 'approval_references' => new ApprovalReferenceSet(['approval-only'])]), $r3Requirement, $audit)->reasonCode() === ReasonCode::APPROVAL_REQUIRED, 'dual approval required');
gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R3]), $r3Requirement, $audit)->allowed(), 'accepted audit permits R3 allow');
gate6bAssert(count($audit->events()) === 2, 'accepted audit receives sanitized R3 event');

gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R3]), $r3Requirement, new RejectingAuditTrailAdapter())->reasonCode() === ReasonCode::AUDIT_REQUIRED, 'rejecting audit fail closed');
gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R3]), $r3Requirement, new UnavailableAuditTrailAdapter())->reasonCode() === ReasonCode::AUDIT_UNAVAILABLE, 'unavailable audit fail closed');
gate6bAssert($boundary->authorize(gate6bContext(['risk_level' => RiskLevel::R3]), $r3Requirement, null)->reasonCode() === ReasonCode::AUDIT_UNAVAILABLE, 'missing audit fail closed');

$r0 = gate6bRequirement(['plane' => AuthorizationPlane::PUBLIC_SYSTEM, 'risk' => RiskLevel::R0, 'action' => 'read', 'resource' => 'catalog', 'actor_required' => false, 'membership_required' => false, 'entity_profile_required' => false, 'ownership_required' => false, 'roles' => [], 'scopes' => new ScopeSet(), 'capabilities' => new CapabilitySet(), 'public_declared' => true]);
$publicContext = gate6bContext(['authorization_plane' => AuthorizationPlane::PUBLIC_SYSTEM, 'risk_level' => RiskLevel::R0, 'action' => 'read', 'resource' => 'catalog', 'session_reference' => null, 'account_id' => null, 'credential_version' => null, 'membership_id' => null, 'entity_id' => null, 'profile_id' => null, 'ownership' => null, 'role' => null]);
$publicResult = $boundary->authorize($publicContext, $r0, null);
gate6bAssert($publicResult->allowed(), 'explicit public R0 allows without actor: ' . $publicResult->reasonCode());
$publicUndeclared = gate6bRequirement(['plane' => AuthorizationPlane::PUBLIC_SYSTEM, 'risk' => RiskLevel::R0, 'actor_required' => false, 'membership_required' => false, 'entity_profile_required' => false, 'ownership_required' => false, 'roles' => [], 'scopes' => new ScopeSet(), 'capabilities' => new CapabilitySet()]);
gate6bAssert($boundary->authorize($publicContext, $publicUndeclared, null)->reasonCode() === ReasonCode::PUBLIC_ROUTE_NOT_DECLARED, 'undeclared public route denied');
$systemUndeclared = gate6bRequirement(['plane' => AuthorizationPlane::PUBLIC_SYSTEM, 'risk' => RiskLevel::R1, 'system_declared' => false]);
gate6bAssert($boundary->authorize(gate6bContext(['authorization_plane' => AuthorizationPlane::PUBLIC_SYSTEM]), $systemUndeclared, $audit)->reasonCode() === ReasonCode::SYSTEM_ROUTE_NOT_DECLARED, 'undeclared system route denied');

gate6bThrows(fn() => gate6bRequirement(['roles' => ['*']]), 'global wildcard role rejected');
gate6bThrows(fn() => gate6bRequirement(['roles' => ['all']]), 'all wildcard role rejected');
gate6bThrows(fn() => gate6bRequirement(['roles' => ['admin.everything']]), 'admin wildcard role rejected');
gate6bThrows(fn() => gate6bRequirement(['roles' => ['support.all']]), 'support wildcard role rejected');
gate6bThrows(fn() => new AuthorizationRequirement(AuthorizationPlane::PUBLIC_SYSTEM, RiskLevel::R0, 'read', 'catalog', null, false, false, false, false, false, [], new ScopeSet(), new CapabilitySet(), false, false, false, false, false, null, true, true), 'public and system declaration cannot be ambiguous');

echo "Gate6BAuthorizationBoundaryTest PASS\n";
