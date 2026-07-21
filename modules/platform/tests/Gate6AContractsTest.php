<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;

use Platform\Contracts\ActorReference;
use Platform\Contracts\ApprovalReferenceSet;
use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditEventReference;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\AuditWriteResult;
use Platform\Contracts\AuthorizationContext;
use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\BreakGlassContract;
use Platform\Contracts\CanonicalSourceRecord;
use Platform\Contracts\CanonicalSourceRegistry;
use Platform\Contracts\CapabilitySet;
use Platform\Contracts\DispositionAction;
use Platform\Contracts\DispositionResolution;
use Platform\Contracts\FeatureFlags;
use Platform\Contracts\ReasonCode;
use Platform\Contracts\RetentionPolicy;
use Platform\Contracts\RetentionState;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\ScopeSet;
use Platform\Contracts\SessionReference;
use Platform\Contracts\SourceClassification;
use Platform\Contracts\SubjectReference;
use Platform\Contracts\SupportAccessState;
use Platform\Contracts\SupportAccessState as State;
use Platform\Contracts\SupportAssistedAccessContract;

function gate6aAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function gate6aThrows(callable $callback, string $message): void
{
    try { $callback(); } catch (InvalidArgumentException|RuntimeException) { return; }
    throw new RuntimeException($message);
}

gate6aAssert(AuthorizationPlane::all() === ['customer_professional', 'internal_operator', 'governance_emergency', 'public_system'], 'authorization planes stable');
gate6aThrows(fn() => AuthorizationPlane::assertValid('professional'), 'commercial plan cannot be an authorization plane');
gate6aAssert(!AuthorizationPlane::isCommercialPlan('professional'), 'Professional is not internal operator');
gate6aAssert(RiskLevel::requiresAuthenticatedActor(RiskLevel::R1), 'R1 actor required');
gate6aAssert(!RiskLevel::requiresAuthenticatedActor(RiskLevel::R0), 'R0 has no persistent privilege');
gate6aAssert(RiskLevel::blocksWithoutAudit(RiskLevel::R2) && RiskLevel::mayRequireDualApproval(RiskLevel::R3), 'risk requirements stable');

$reasonCodes = ReasonCode::all();
gate6aAssert(count($reasonCodes) === count(array_unique($reasonCodes)), 'reason codes unique');
foreach (['AUTH_CONTEXT_MISSING', 'SESSION_MISSING', 'MEMBERSHIP_MISSING', 'OWNERSHIP_DENIED', 'AUDIT_UNAVAILABLE', 'CASE_REQUIRED', 'MFA_REQUIRED', 'TRANSITIONAL_OPEN_DENIED', 'CLIENT_IDENTITY_NOT_AUTHORITATIVE'] as $constant) {
    gate6aAssert(ReasonCode::isKnown(constant(ReasonCode::class . '::' . $constant)), 'minimum reason coverage');
}
gate6aThrows(fn() => ReasonCode::assertValid('arbitrary_message'), 'arbitrary reason code rejected');
gate6aAssert(ReasonCode::isDeny(ReasonCode::TRANSITIONAL_OPEN_DENIED), 'transitional open is deny');

$context = new AuthorizationContext(
    realActor: new ActorReference('account', 'account-ref-1'),
    effectiveActor: new ActorReference('support', 'support-ref-1'),
    affectedSubject: new SubjectReference('profile', 'profile-ref-1'),
    sessionReference: new SessionReference('session-ref-1'),
    accountId: 'account-ref-1', credentialVersion: 2, membershipId: 'membership-ref-1', entityId: 'entity-ref-1', profileId: 'profile-ref-1',
    ownership: 'owner', role: 'professional', scopes: new ScopeSet(['profile:read', 'profile:read']), capabilities: new CapabilitySet(['profile:read']), action: 'read', resource: 'profile',
    authorizationPlane: AuthorizationPlane::CUSTOMER_PROFESSIONAL, riskLevel: RiskLevel::R1, correlationId: 'correlation-ref-1', requestId: 'request-ref-1', caseId: 'case-ref-1', approvalReferences: new ApprovalReferenceSet(['approval-ref-1'])
);
gate6aAssert($context->realActor()?->id() !== $context->effectiveActor()?->id(), 'real and effective actors remain separate');
gate6aAssert($context->scopes()->values() === ['profile:read'], 'scope set is stable and unique');
gate6aAssert($context->toArray()['session_reference'] === 'session-ref-1', 'context serialization is sanitized reference only');
gate6aThrows(fn() => new SessionReference('password-value'), 'session sensitive value rejected');
gate6aThrows(fn() => new AuthorizationContext(credentialVersion: 0), 'invalid credential version rejected');

$deny = AuthorizationDecision::uninitialized(RiskLevel::R2);
gate6aAssert(!$deny->allowed() && $deny->reasonCode() === ReasonCode::DECISION_UNINITIALIZED, 'decision defaults deny');
gate6aThrows(fn() => AuthorizationDecision::deny(RiskLevel::R2, ReasonCode::ALLOWED), 'deny requires non-allowed reason');
gate6aThrows(fn() => AuthorizationDecision::allow(RiskLevel::R1, ''), 'allow requires satisfied rule');
$allow = AuthorizationDecision::allow(RiskLevel::R1, 'membership_scope_capability_satisfied', ['membership', 'scope'], 'correlation-ref-1');
gate6aAssert($allow->allowed() && $allow->satisfiedRule() !== null, 'allow carries satisfied rule');

$canonical = new CanonicalSourceRecord('profiles', 'doctor_profile', SourceClassification::CANONICAL_WRITE, 'profiles_service', 'profiles_read', 'profile-domain', 'pending', true, true);
$projection = new CanonicalSourceRecord('profiles', 'doctor_profile_projection', SourceClassification::DERIVED_PROJECTION, null, 'profile_projection_read', 'profile-domain', 'none', false, false);
CanonicalSourceRegistry::assertInvariants([$canonical, $projection]);
gate6aAssert($canonical->canAuthorizeWrites() && !$projection->canAuthorizeWrites(), 'only canonical write can write');
gate6aAssert($canonical->reconciliationRequired() && $canonical->rollbackRequired(), 'canonical record carries reconciliation and rollback requirements');
gate6aThrows(fn() => CanonicalSourceRegistry::assertInvariants([$canonical, new CanonicalSourceRecord('profiles', 'doctor_profile', SourceClassification::CANONICAL_WRITE, 'other_service', 'profiles_read', 'profile-domain-2', 'pending', true, true)]), 'two canonical writers rejected');
gate6aThrows(fn() => new CanonicalSourceRecord('profiles', 'unknown', SourceClassification::UNRESOLVED, null, null, 'unknown-source', 'unknown', false, false), 'unresolved source cannot be active');

$unresolved = new RetentionPolicy('clinical', 'record', 'care delivery', 'case closure', null, true, RetentionState::ACTIVE, RetentionState::ARCHIVED, 'reviewed_disposition', false, 'director', 'clinical_governance', 'unresolved');
gate6aAssert($unresolved->retentionUnresolved() && !$unresolved->automaticDeletionAllowed(), 'retention unresolved blocks automatic deletion');
gate6aAssert(DispositionResolution::assertValid(DispositionResolution::ANONYMIZATION_UNRESOLVED) === 'anonymization_unresolved', 'anonymization unresolved vocabulary stable');
gate6aAssert(DispositionAction::requiresR3(DispositionAction::DELETE) && DispositionAction::requiresR3(DispositionAction::EXPORT_MASS), 'R3 disposition actions');
gate6aAssert(!$unresolved->allowsTransition(RetentionState::DELETED), 'active sensitive data cannot go directly to deleted');
$hold = new RetentionPolicy('profile', 'contact', 'user contact', 'account closure', 86400, false, RetentionState::FROZEN, RetentionState::ARCHIVED, 'reviewed_disposition', true, 'director', 'privacy_governance', 'approved');
gate6aAssert(!$hold->allowsTransition(RetentionState::DELETED) && !$hold->allowsTransition(RetentionState::ANONYMIZED), 'legal hold blocks irreversible disposition');

$event = new AuditEventReference('profile_read', RiskLevel::R2, $context->realActor(), $context->effectiveActor(), $context->affectedSubject(), 'correlation-ref-1', 'request-ref-1', AuditWriteResult::ACCEPTED, ['result' => 'allowed']);
gate6aAssert($event->realActor()?->id() !== $event->effectiveActor()?->id(), 'audit event keeps actor separation');
gate6aThrows(fn() => new AuditEventReference('profile_read', RiskLevel::R2, null, null, null, null, null, AuditWriteResult::ACCEPTED, ['clinical_payload' => 'not allowed']), 'sensitive audit metadata rejected');
gate6aAssert(AuditAvailability::assertValid(AuditAvailability::UNAVAILABLE) === 'unavailable', 'audit unavailable representable');
gate6aAssert(interface_exists(AuditTrailPort::class), 'audit persistence remains a port');

gate6aAssert(FeatureFlags::defaults() === [FeatureFlags::SUPPORT_ASSISTED_SESSION_ENABLED => false, FeatureFlags::BREAK_GLASS_ENABLED => false], 'feature flags default false');
gate6aAssert(!SupportAssistedAccessContract::enabledByDefault() && !SupportAssistedAccessContract::clinicalAccessAllowedByDefault(), 'support clinical access denied by default');
gate6aAssert(BreakGlassContract::risk() === RiskLevel::R3 && !BreakGlassContract::enabledByDefault(), 'break glass disabled and R3');
gate6aAssert(SupportAccessState::assertValid(State::PENDING_APPROVAL) === 'pending_approval', 'future access states stable');
gate6aThrows(fn() => SupportAccessState::assertValid('impersonating'), 'unknown support state rejected');

echo "Gate6AContractsTest PASS\n";
