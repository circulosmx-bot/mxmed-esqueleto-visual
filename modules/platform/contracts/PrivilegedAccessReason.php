<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class PrivilegedAccessReason
{
    public const FEATURE_DISABLED = 'feature_disabled';
    public const PRODUCTIVE_APPROVAL_REQUIRED = 'productive_approval_required';
    public const RUNTIME_ACTIVATION_DISABLED = 'runtime_activation_disabled';
    public const MODE_MISMATCH = 'mode_mismatch';
    public const INVALID_STATE = 'invalid_state';
    public const ACTOR_SEPARATION_REQUIRED = 'actor_separation_required';
    public const CASE_REQUIRED = 'case_required';
    public const REASON_REQUIRED = 'reason_required';
    public const SCOPE_REQUIRED = 'scope_required';
    public const SCOPE_DENIED = 'scope_denied';
    public const WILDCARD_SCOPE_DENIED = 'wildcard_scope_denied';
    public const EXPIRATION_REQUIRED = 'expiration_required';
    public const REQUEST_EXPIRED = 'request_expired';
    public const REAUTHENTICATION_REQUIRED = 'reauthentication_required';
    public const MFA_REQUIRED = 'mfa_required';
    public const APPROVAL_REQUIRED = 'approval_required';
    public const DUAL_APPROVAL_REQUIRED = 'dual_approval_required';
    public const APPROVER_SEPARATION_REQUIRED = 'approver_separation_required';
    public const AUDIT_REQUIRED = 'audit_required';
    public const AUDIT_UNAVAILABLE = 'audit_unavailable';
    public const VISIBILITY_REQUIRED = 'visibility_required';
    public const POST_REVIEW_REQUIRED = 'post_review_required';
    public const CLINICAL_ACCESS_DENIED = 'clinical_access_denied';
    public const EMERGENCY_REQUIRED = 'emergency_required';
    public const AUTHORIZATION_DENIED = 'authorization_denied';
    public const RISK_REQUIREMENT_UNSATISFIED = 'risk_requirement_unsatisfied';
    public const POLICY_SATISFIED_NOT_ACTIVATABLE = 'policy_satisfied_not_activatable';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::FEATURE_DISABLED, self::PRODUCTIVE_APPROVAL_REQUIRED, self::RUNTIME_ACTIVATION_DISABLED, self::MODE_MISMATCH, self::INVALID_STATE, self::ACTOR_SEPARATION_REQUIRED, self::CASE_REQUIRED, self::REASON_REQUIRED, self::SCOPE_REQUIRED, self::SCOPE_DENIED, self::WILDCARD_SCOPE_DENIED, self::EXPIRATION_REQUIRED, self::REQUEST_EXPIRED, self::REAUTHENTICATION_REQUIRED, self::MFA_REQUIRED, self::APPROVAL_REQUIRED, self::DUAL_APPROVAL_REQUIRED, self::APPROVER_SEPARATION_REQUIRED, self::AUDIT_REQUIRED, self::AUDIT_UNAVAILABLE, self::VISIBILITY_REQUIRED, self::POST_REVIEW_REQUIRED, self::CLINICAL_ACCESS_DENIED, self::EMERGENCY_REQUIRED, self::AUTHORIZATION_DENIED, self::RISK_REQUIREMENT_UNSATISFIED, self::POLICY_SATISFIED_NOT_ACTIVATABLE];
    }
    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_privileged_access_reason');
        return $value;
    }
}
