<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\AuditEventReference;
use Platform\Contracts\AuditWriteResult;
use Platform\Contracts\PrivilegedAccessMode;
use Platform\Contracts\PrivilegedAccessReason;
use Platform\Contracts\PrivilegedAccessRequest;

/** Creates only the minimized Gate 6D-compatible audit reference. */
final class PrivilegedAccessAuditEventFactory
{
    public function policyEvaluated(PrivilegedAccessRequest $request, string $decision, string $reasonCode): AuditEventReference
    {
        PrivilegedAccessReason::assertValid($reasonCode);
        $action = $request->mode() === PrivilegedAccessMode::SUPPORT_ASSISTED ? 'support_assisted_policy_evaluated' : 'break_glass_policy_evaluated';
        return $this->event($request, $action, $decision, $reasonCode);
    }

    public function transitionPlanned(PrivilegedAccessRequest $request, string $decision, string $reasonCode): AuditEventReference
    {
        PrivilegedAccessReason::assertValid($reasonCode);
        return $this->event($request, 'privileged_access_transition_planned', $decision, $reasonCode);
    }

    private function event(PrivilegedAccessRequest $request, string $action, string $decision, string $reasonCode): AuditEventReference
    {
        $metadata = [
            'resource_type' => 'privileged_access',
            'resource_reference' => $request->requestReference(),
            'decision' => $decision,
            'reason_code' => $reasonCode,
            'authorization_plane' => $request->authorizationPlane() ?? 'unresolved',
            'audit_category' => 'privileged_access',
        ];
        if ($request->caseReference() !== null) $metadata['case_reference'] = $request->caseReference();
        return new AuditEventReference($action, $request->riskLevel() ?? 'R3', $request->realActor(), $request->effectiveActor(), $request->affectedSubject(), $request->correlationId() ?? $request->requestReference(), $request->auditRequestId() ?? $request->requestReference(), AuditWriteResult::ACCEPTED, $metadata);
    }
}
