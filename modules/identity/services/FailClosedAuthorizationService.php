<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\AuthorizationDecision;
use Identity\Contracts\MembershipRole;
use Identity\Contracts\ReasonCode;

final class FailClosedAuthorizationService
{
    public function __construct(private object $memberships, private object $capabilityAuthority) {}

    public function authorize(AuthenticatedAccessContext $context, string $targetType, string $targetId, string $capabilityId, array $capabilityContext = [], string $authMode = 'strict'): AuthorizationDecision
    {
        if ($authMode === 'transitional_open' || $targetId === '' || $capabilityId === '') return new AuthorizationDecision(false, ReasonCode::CAPABILITY_DENIED);
        if (!in_array($targetType, ['profile_doctor', 'medical_group'], true)) return new AuthorizationDecision(false, ReasonCode::PROFILE_MISMATCH);
        try { $rows = $this->memberships->activeForAccount($context->accountId()); } catch (\Throwable) { return new AuthorizationDecision(false, ReasonCode::MEMBERSHIP_MISSING); }
        if (!is_array($rows)) return new AuthorizationDecision(false, ReasonCode::MEMBERSHIP_MISSING);
        $match = null;
        foreach ($rows as $row) {
            if (!is_array($row) || (string)($row['status'] ?? 'active') !== 'active') continue;
            $role = (string)($row['role_code'] ?? '');
            if (!in_array($role, MembershipRole::all(), true)) return new AuthorizationDecision(false, ReasonCode::MEMBERSHIP_INACTIVE);
            $rowTarget = $targetType === 'profile_doctor' ? (string)($row['profile_doctor_id'] ?? '') : (string)($row['entity_group_id'] ?? '');
            if ($rowTarget !== $targetId) continue;
            $scope = (string)($row['scope_code'] ?? '');
            $allowedScopes = $targetType === 'profile_doctor' ? ['profile', 'profile_doctor'] : ['organization', 'medical_group'];
            if (!in_array($scope, $allowedScopes, true)) return new AuthorizationDecision(false, ReasonCode::PROFILE_MISMATCH);
            $match = [$row, $role, $scope];
            break;
        }
        if ($match === null) return new AuthorizationDecision(false, ReasonCode::MEMBERSHIP_MISSING);
        try {
            $capability = $this->capabilityAuthority->resolve($capabilityId, $capabilityContext + ['account_id' => $context->accountId(), 'entity_type' => $targetType, 'entity_id' => $targetId]);
            if (!is_object($capability) || !method_exists($capability, 'available') || !$capability->available()) return new AuthorizationDecision(false, ReasonCode::CAPABILITY_DENIED, (string)$match[0]['membership_id'], $match[1], $match[2], is_object($capability) && method_exists($capability, 'toArray') ? $capability->toArray() : null);
            return new AuthorizationDecision(true, ReasonCode::ALLOWED, (string)$match[0]['membership_id'], $match[1], $match[2], method_exists($capability, 'toArray') ? $capability->toArray() : null);
        } catch (\Throwable) { return new AuthorizationDecision(false, ReasonCode::CAPABILITY_DENIED, (string)$match[0]['membership_id'], $match[1], $match[2]); }
    }
}
