<?php
declare(strict_types=1);

namespace Platform\Services;

use DateTimeImmutable;
use DateTimeZone;
use Platform\Contracts\ApprovalReferenceSet;
use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\AuditWriteResult;
use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\PrivilegedAccessApprovalEvidence;
use Platform\Contracts\PrivilegedAccessDecision;
use Platform\Contracts\PrivilegedAccessReason;
use Platform\Contracts\PrivilegedAccessRequest;
use Platform\Contracts\ScopeSet;

/** Shared pure validation helpers; it never activates access or persists state. */
final class PrivilegedAccessPolicySupport
{
    public function __construct(private readonly PrivilegedAccessAuditEventFactory $auditFactory = new PrivilegedAccessAuditEventFactory()) {}

    public function authorizationReason(PrivilegedAccessRequest $request, AuthorizationDecision $authorization): ?string
    {
        if (!$authorization->allowed() || $authorization->satisfiedRule() === null || $authorization->missingRequirements() !== []) return PrivilegedAccessReason::AUTHORIZATION_DENIED;
        if ($request->riskLevel() === null || $authorization->riskLevel() !== $request->riskLevel()) return PrivilegedAccessReason::RISK_REQUIREMENT_UNSATISFIED;
        return null;
    }

    public function temporalReason(PrivilegedAccessRequest $request, string $nowUtc): ?string
    {
        if ($request->requestedAtUtc() === null || $request->expiresAtUtc() === null) return PrivilegedAccessReason::EXPIRATION_REQUIRED;
        $requested = self::parseUtc($request->requestedAtUtc());
        $expires = self::parseUtc($request->expiresAtUtc());
        $now = self::parseUtc($nowUtc);
        if ($requested === null || $expires === null || $now === null) return PrivilegedAccessReason::EXPIRATION_REQUIRED;
        if ($expires <= $requested) return PrivilegedAccessReason::EXPIRATION_REQUIRED;
        if ($expires <= $now) return PrivilegedAccessReason::REQUEST_EXPIRED;
        return null;
    }

    public function scopeReason(ScopeSet $scopes): ?string
    {
        if ($scopes->isEmpty()) return PrivilegedAccessReason::SCOPE_REQUIRED;
        foreach ($scopes->values() as $scope) if (in_array($scope, ['*', 'all', 'admin.everything', 'support.all'], true)) return PrivilegedAccessReason::WILDCARD_SCOPE_DENIED;
        return null;
    }

    public function approvalReason(PrivilegedAccessRequest $request, int $minimum, string $nowUtc): ?string
    {
        $evidence = $request->approvalEvidence();
        if (count($evidence) < $minimum) return $minimum === 2 ? PrivilegedAccessReason::DUAL_APPROVAL_REQUIRED : PrivilegedAccessReason::APPROVAL_REQUIRED;
        $seen = [];
        foreach ($evidence as $approval) {
            if (!$approval instanceof PrivilegedAccessApprovalEvidence || isset($seen[$approval->approvalReference()])) return PrivilegedAccessReason::APPROVER_SEPARATION_REQUIRED;
            $seen[$approval->approvalReference()] = true;
            if ($approval->approvedMode() !== $request->mode() || $request->caseReference() === null || $approval->approvedCaseReference() !== $request->caseReference()) return PrivilegedAccessReason::APPROVER_SEPARATION_REQUIRED;
            if (($request->realActor() !== null && self::actorKey($approval->approverActor()) === self::actorKey($request->realActor())) || ($request->effectiveActor() !== null && self::actorKey($approval->approverActor()) === self::actorKey($request->effectiveActor()))) return PrivilegedAccessReason::APPROVER_SEPARATION_REQUIRED;
            $approvedAt = self::parseUtc($approval->approvedAtUtc());
            $now = self::parseUtc($nowUtc);
            $expires = $request->expiresAtUtc() === null ? null : self::parseUtc($request->expiresAtUtc());
            if ($approvedAt === null || $now === null || ($expires !== null && $approvedAt > $expires)) return PrivilegedAccessReason::APPROVAL_REQUIRED;
        }
        if ($minimum === 2 && count($seen) < 2) return PrivilegedAccessReason::DUAL_APPROVAL_REQUIRED;
        return null;
    }

    public function auditStatus(PrivilegedAccessRequest $request, string $decision, string $reasonCode, string $nowUtc, ?AuditTrailPort $audit): string
    {
        if ($audit === null) return AuditWriteResult::UNAVAILABLE;
        try {
            if ($audit->availability() !== AuditAvailability::AVAILABLE) return AuditWriteResult::UNAVAILABLE;
            return AuditWriteResult::assertValid($audit->write($this->auditFactory->policyEvaluated($request, $decision, $reasonCode)));
        } catch (\Throwable) {
            return AuditWriteResult::UNAVAILABLE;
        }
    }

    /** @param list<string> $controls @param list<string> $blockers */
    public function decision(PrivilegedAccessRequest $request, string $reasonCode, array $controls = [], array $blockers = [], bool $policySatisfied = false, string $auditStatus = AuditWriteResult::REJECTED, ?string $satisfiedRule = null): PrivilegedAccessDecision
    {
        return new PrivilegedAccessDecision($request->mode(), $request->state(), $policySatisfied, false, $reasonCode, $request->riskLevel() ?? 'R3', $controls, $blockers, $request->scopes(), $request->expiresAtUtc(), $request->visibilityRequired(), $request->postReviewRequired(), $auditStatus, $request->correlationId() ?? $request->requestReference(), $satisfiedRule);
    }

    public function auditFactory(): PrivilegedAccessAuditEventFactory { return $this->auditFactory; }

    private static function parseUtc(string $value): ?DateTimeImmutable
    {
        if (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $value) !== 1) return null;
        try { return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC')); } catch (\Throwable) { return null; }
    }
    private static function actorKey(\Platform\Contracts\ActorReference $actor): string { return $actor->kind() . ':' . $actor->id(); }
}
