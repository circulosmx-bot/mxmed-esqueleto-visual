<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class AuthorizationRequirement
{
    private string $authorizationPlane;
    private string $riskLevel;
    private string $action;
    private string $resourceType;
    private ?string $resourceId;
    private bool $auditTrailRequired;
    /** @var list<string> */
    private array $rolesAccepted;

    /** @param list<string> $rolesAccepted */
    public function __construct(
        string $authorizationPlane,
        string $riskLevel,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        private bool $resourceIdRequired = false,
        private bool $actorAuthenticatedRequired = true,
        private bool $membershipRequired = false,
        private bool $entityProfileRequired = false,
        private bool $ownershipRequired = false,
        array $rolesAccepted = [],
        private ?ScopeSet $scopesRequired = null,
        private ?CapabilitySet $capabilitiesRequired = null,
        private bool $reauthenticationRequired = false,
        private bool $mfaRequired = false,
        private bool $caseRequired = false,
        private bool $approvalRequired = false,
        private bool $dualApprovalRequired = false,
        ?bool $auditTrailRequired = null,
        private bool $publicRouteDeclared = false,
        private bool $systemRouteDeclared = false
    ) {
        $this->authorizationPlane = AuthorizationPlane::assertValid($authorizationPlane);
        $this->riskLevel = RiskLevel::assertValid($riskLevel);
        $this->action = (new SafeIdentifier($action))->value();
        $this->resourceType = (new SafeIdentifier($resourceType))->value();
        $this->resourceId = $resourceId === null ? null : (new SafeIdentifier($resourceId))->value();
        if ($this->resourceIdRequired && $this->resourceId === null) throw new \InvalidArgumentException('resource_id_required');
        $normalizedRoles = [];
        foreach ($rolesAccepted as $role) {
            $role = (new SafeIdentifier($role))->value();
            if (in_array($role, ['*', 'all', 'admin.everything', 'support.all'], true)) throw new \InvalidArgumentException('global_role_wildcard_forbidden');
            $normalizedRoles[] = $role;
        }
        $normalizedRoles = array_values(array_unique($normalizedRoles, SORT_STRING));
        sort($normalizedRoles, SORT_STRING);
        $this->rolesAccepted = $normalizedRoles;
        $auditByRisk = RiskLevel::requiresAuditTrail($this->riskLevel);
        if ($auditTrailRequired === false && $auditByRisk) throw new \InvalidArgumentException('risk_audit_requirement_cannot_be_disabled');
        $this->auditTrailRequired = $auditByRisk || $auditTrailRequired === true;
        if ($this->publicRouteDeclared && $this->systemRouteDeclared) throw new \InvalidArgumentException('public_system_route_ambiguous');
        if ($this->publicRouteDeclared && $this->authorizationPlane !== AuthorizationPlane::PUBLIC_SYSTEM) throw new \InvalidArgumentException('public_route_plane_mismatch');
        if ($this->systemRouteDeclared && $this->authorizationPlane !== AuthorizationPlane::PUBLIC_SYSTEM) throw new \InvalidArgumentException('system_route_plane_mismatch');
    }

    public function authorizationPlane(): string { return $this->authorizationPlane; }
    public function riskLevel(): string { return $this->riskLevel; }
    public function action(): string { return $this->action; }
    public function resourceType(): string { return $this->resourceType; }
    public function resourceId(): ?string { return $this->resourceId; }
    public function resourceIdRequired(): bool { return $this->resourceIdRequired; }
    public function actorAuthenticatedRequired(): bool { return $this->actorAuthenticatedRequired; }
    public function membershipRequired(): bool { return $this->membershipRequired; }
    public function entityProfileRequired(): bool { return $this->entityProfileRequired; }
    public function ownershipRequired(): bool { return $this->ownershipRequired; }
    /** @return list<string> */
    public function rolesAccepted(): array { return $this->rolesAccepted; }
    public function scopesRequired(): ScopeSet { return $this->scopesRequired ?? new ScopeSet(); }
    public function capabilitiesRequired(): CapabilitySet { return $this->capabilitiesRequired ?? new CapabilitySet(); }
    public function reauthenticationRequired(): bool { return $this->reauthenticationRequired; }
    public function mfaRequired(): bool { return $this->mfaRequired; }
    public function caseRequired(): bool { return $this->caseRequired; }
    public function approvalRequired(): bool { return $this->approvalRequired; }
    public function dualApprovalRequired(): bool { return $this->dualApprovalRequired; }
    public function auditTrailRequired(): bool { return $this->auditTrailRequired; }
    public function publicRouteDeclared(): bool { return $this->publicRouteDeclared; }
    public function systemRouteDeclared(): bool { return $this->systemRouteDeclared; }
}
