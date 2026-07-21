<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class AuthorizationContext
{
    public function __construct(
        private ?ActorReference $realActor = null,
        private ?ActorReference $effectiveActor = null,
        private ?SubjectReference $affectedSubject = null,
        private ?SessionReference $sessionReference = null,
        private ?string $accountId = null,
        private ?int $credentialVersion = null,
        private ?string $membershipId = null,
        private ?string $entityId = null,
        private ?string $profileId = null,
        private ?string $ownership = null,
        private ?string $role = null,
        private ?ScopeSet $scopes = null,
        private ?CapabilitySet $capabilities = null,
        private ?string $action = null,
        private ?string $resource = null,
        private ?string $authorizationPlane = null,
        private ?string $riskLevel = null,
        private ?string $correlationId = null,
        private ?string $requestId = null,
        private ?string $caseId = null,
        private ?ApprovalReferenceSet $approvalReferences = null
    ) {
        foreach (['accountId', 'membershipId', 'entityId', 'profileId', 'ownership', 'role', 'action', 'resource', 'correlationId', 'requestId', 'caseId'] as $name) {
            $value = $this->{$name};
            if ($value !== null) new SafeIdentifier($value);
        }
        if ($this->credentialVersion !== null && $this->credentialVersion < 1) throw new \InvalidArgumentException('invalid_credential_version');
        if ($this->authorizationPlane !== null) AuthorizationPlane::assertValid($this->authorizationPlane);
        if ($this->riskLevel !== null) RiskLevel::assertValid($this->riskLevel);
    }

    public function realActor(): ?ActorReference { return $this->realActor; }
    public function effectiveActor(): ?ActorReference { return $this->effectiveActor; }
    public function affectedSubject(): ?SubjectReference { return $this->affectedSubject; }
    public function sessionReference(): ?SessionReference { return $this->sessionReference; }
    public function accountId(): ?string { return $this->accountId; }
    public function credentialVersion(): ?int { return $this->credentialVersion; }
    public function membershipId(): ?string { return $this->membershipId; }
    public function entityId(): ?string { return $this->entityId; }
    public function profileId(): ?string { return $this->profileId; }
    public function ownership(): ?string { return $this->ownership; }
    public function role(): ?string { return $this->role; }
    public function scopes(): ScopeSet { return $this->scopes ?? new ScopeSet(); }
    public function capabilities(): CapabilitySet { return $this->capabilities ?? new CapabilitySet(); }
    public function action(): ?string { return $this->action; }
    public function resource(): ?string { return $this->resource; }
    public function authorizationPlane(): ?string { return $this->authorizationPlane; }
    public function riskLevel(): ?string { return $this->riskLevel; }
    public function correlationId(): ?string { return $this->correlationId; }
    public function requestId(): ?string { return $this->requestId; }
    public function caseId(): ?string { return $this->caseId; }
    public function approvalReferences(): ApprovalReferenceSet { return $this->approvalReferences ?? new ApprovalReferenceSet(); }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'real_actor' => $this->realActor?->toArray(),
            'effective_actor' => $this->effectiveActor?->toArray(),
            'affected_subject' => $this->affectedSubject?->toArray(),
            'session_reference' => $this->sessionReference?->value(),
            'account_id' => $this->accountId,
            'credential_version' => $this->credentialVersion,
            'membership_id' => $this->membershipId,
            'entity_id' => $this->entityId,
            'profile_id' => $this->profileId,
            'ownership' => $this->ownership,
            'role' => $this->role,
            'scopes' => $this->scopes()->values(),
            'capabilities' => $this->capabilities()->values(),
            'action' => $this->action,
            'resource' => $this->resource,
            'authorization_plane' => $this->authorizationPlane,
            'risk_level' => $this->riskLevel,
            'correlation_id' => $this->correlationId,
            'request_id' => $this->requestId,
            'case_id' => $this->caseId,
            'approval_references' => $this->approvalReferences()->values(),
        ];
    }
}
