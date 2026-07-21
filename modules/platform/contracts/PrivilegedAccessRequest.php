<?php
declare(strict_types=1);

namespace Platform\Contracts;

/** Immutable, minimized privileged-access policy request; it never represents a session. */
final readonly class PrivilegedAccessRequest
{
    /** @var list<PrivilegedAccessApprovalEvidence> */
    private array $approvalEvidence;

    /** @param list<PrivilegedAccessApprovalEvidence> $approvalEvidence */
    public function __construct(
        string $requestReference,
        string $mode,
        string $state,
        private ?ActorReference $realActor,
        private ?ActorReference $effectiveActor,
        private ?SubjectReference $affectedSubject,
        ?string $authorizationPlane,
        ?string $riskLevel,
        ?string $caseReference,
        ?string $reasonReference,
        private ScopeSet $scopes,
        private CapabilitySet $capabilities,
        ?string $requestedAtUtc,
        ?string $expiresAtUtc,
        ?string $correlationId,
        ?string $auditRequestId,
        array $approvalEvidence,
        private bool $reauthenticationVerified,
        private bool $mfaVerified,
        private bool $visibilityRequired,
        private bool $postReviewRequired,
        private bool $clinicalAccessRequested,
        private bool $emergencyConfirmed
    ) {
        $this->requestReference = (new SafeIdentifier($requestReference))->value();
        $this->mode = PrivilegedAccessMode::assertValid($mode);
        $this->state = SupportAccessState::assertValid($state);
        $this->authorizationPlane = $authorizationPlane === null ? null : AuthorizationPlane::assertValid($authorizationPlane);
        $this->riskLevel = $riskLevel === null ? null : RiskLevel::assertValid($riskLevel);
        $this->caseReference = $caseReference === null ? null : (new SafeIdentifier($caseReference))->value();
        $this->reasonReference = $reasonReference === null ? null : (new SafeIdentifier($reasonReference))->value();
        $this->requestedAtUtc = $requestedAtUtc === null ? null : trim($requestedAtUtc);
        $this->expiresAtUtc = $expiresAtUtc === null ? null : trim($expiresAtUtc);
        $this->correlationId = $correlationId === null ? null : (new SafeIdentifier($correlationId))->value();
        $this->auditRequestId = $auditRequestId === null ? null : (new SafeIdentifier($auditRequestId))->value();
        $seen = [];
        foreach ($approvalEvidence as $evidence) {
            if (!$evidence instanceof PrivilegedAccessApprovalEvidence) throw new \InvalidArgumentException('invalid_privileged_access_approval');
            if (isset($seen[$evidence->approvalReference()])) throw new \InvalidArgumentException('duplicate_privileged_access_approval');
            $seen[$evidence->approvalReference()] = true;
        }
        $this->approvalEvidence = array_values($approvalEvidence);
    }

    private string $requestReference;
    private string $mode;
    private string $state;
    private ?string $authorizationPlane;
    private ?string $riskLevel;
    private ?string $caseReference;
    private ?string $reasonReference;
    private ?string $requestedAtUtc;
    private ?string $expiresAtUtc;
    private ?string $correlationId;
    private ?string $auditRequestId;

    public function requestReference(): string { return $this->requestReference; }
    public function mode(): string { return $this->mode; }
    public function state(): string { return $this->state; }
    public function realActor(): ?ActorReference { return $this->realActor; }
    public function effectiveActor(): ?ActorReference { return $this->effectiveActor; }
    public function affectedSubject(): ?SubjectReference { return $this->affectedSubject; }
    public function authorizationPlane(): ?string { return $this->authorizationPlane; }
    public function riskLevel(): ?string { return $this->riskLevel; }
    public function caseReference(): ?string { return $this->caseReference; }
    public function reasonReference(): ?string { return $this->reasonReference; }
    public function scopes(): ScopeSet { return $this->scopes; }
    public function capabilities(): CapabilitySet { return $this->capabilities; }
    public function requestedAtUtc(): ?string { return $this->requestedAtUtc; }
    public function expiresAtUtc(): ?string { return $this->expiresAtUtc; }
    public function correlationId(): ?string { return $this->correlationId; }
    public function auditRequestId(): ?string { return $this->auditRequestId; }
    /** @return list<PrivilegedAccessApprovalEvidence> */
    public function approvalEvidence(): array { return $this->approvalEvidence; }
    public function reauthenticationVerified(): bool { return $this->reauthenticationVerified; }
    public function mfaVerified(): bool { return $this->mfaVerified; }
    public function visibilityRequired(): bool { return $this->visibilityRequired; }
    public function postReviewRequired(): bool { return $this->postReviewRequired; }
    public function clinicalAccessRequested(): bool { return $this->clinicalAccessRequested; }
    public function emergencyConfirmed(): bool { return $this->emergencyConfirmed; }
}
