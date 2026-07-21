<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class ReasonCode
{
    public const ALLOWED = 'allowed';
    public const AUTH_CONTEXT_MISSING = 'auth_context_missing';
    public const SESSION_MISSING = 'session_missing';
    public const SESSION_INVALID = 'session_invalid';
    public const SESSION_EXPIRED = 'session_expired';
    public const SESSION_REVOKED = 'session_revoked';
    public const CREDENTIAL_VERSION_MISMATCH = 'credential_version_mismatch';
    public const ACCOUNT_INACTIVE = 'account_inactive';
    public const MEMBERSHIP_MISSING = 'membership_missing';
    public const MEMBERSHIP_INACTIVE = 'membership_inactive';
    public const ENTITY_UNRESOLVED = 'entity_unresolved';
    public const PROFILE_UNRESOLVED = 'profile_unresolved';
    public const OWNERSHIP_REQUIRED = 'ownership_required';
    public const OWNERSHIP_DENIED = 'ownership_denied';
    public const ROLE_REQUIRED = 'role_required';
    public const ROLE_DENIED = 'role_denied';
    public const SCOPE_REQUIRED = 'scope_required';
    public const SCOPE_DENIED = 'scope_denied';
    public const CAPABILITY_REQUIRED = 'capability_required';
    public const CAPABILITY_DENIED = 'capability_denied';
    public const ACTION_UNSUPPORTED = 'action_unsupported';
    public const RESOURCE_UNRESOLVED = 'resource_unresolved';
    public const AUTHORIZATION_PLANE_MISMATCH = 'authorization_plane_mismatch';
    public const RISK_REQUIREMENT_UNSATISFIED = 'risk_requirement_unsatisfied';
    public const AUDIT_REQUIRED = 'audit_required';
    public const AUDIT_UNAVAILABLE = 'audit_unavailable';
    public const CASE_REQUIRED = 'case_required';
    public const APPROVAL_REQUIRED = 'approval_required';
    public const MFA_REQUIRED = 'mfa_required';
    public const REAUTHENTICATION_REQUIRED = 'reauthentication_required';
    public const FEATURE_DISABLED = 'feature_disabled';
    public const PUBLIC_ROUTE_NOT_DECLARED = 'public_route_not_declared';
    public const SYSTEM_ROUTE_NOT_DECLARED = 'system_route_not_declared';
    public const DEPENDENCY_UNAVAILABLE = 'dependency_unavailable';
    public const RATE_LIMITED = 'rate_limited';
    public const CSRF_INVALID = 'csrf_invalid';
    public const ORIGIN_INVALID = 'origin_invalid';
    public const TRANSITIONAL_OPEN_DENIED = 'transitional_open_denied';
    public const CLIENT_IDENTITY_NOT_AUTHORITATIVE = 'client_identity_not_authoritative';
    public const DECISION_UNINITIALIZED = 'decision_uninitialized';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ALLOWED, self::AUTH_CONTEXT_MISSING, self::SESSION_MISSING, self::SESSION_INVALID,
            self::SESSION_EXPIRED, self::SESSION_REVOKED, self::CREDENTIAL_VERSION_MISMATCH,
            self::ACCOUNT_INACTIVE, self::MEMBERSHIP_MISSING, self::MEMBERSHIP_INACTIVE,
            self::ENTITY_UNRESOLVED, self::PROFILE_UNRESOLVED, self::OWNERSHIP_REQUIRED,
            self::OWNERSHIP_DENIED, self::ROLE_REQUIRED, self::ROLE_DENIED, self::SCOPE_REQUIRED,
            self::SCOPE_DENIED, self::CAPABILITY_REQUIRED, self::CAPABILITY_DENIED,
            self::ACTION_UNSUPPORTED, self::RESOURCE_UNRESOLVED, self::AUTHORIZATION_PLANE_MISMATCH,
            self::RISK_REQUIREMENT_UNSATISFIED, self::AUDIT_REQUIRED, self::AUDIT_UNAVAILABLE,
            self::CASE_REQUIRED, self::APPROVAL_REQUIRED, self::MFA_REQUIRED,
            self::REAUTHENTICATION_REQUIRED, self::FEATURE_DISABLED, self::PUBLIC_ROUTE_NOT_DECLARED,
            self::SYSTEM_ROUTE_NOT_DECLARED, self::DEPENDENCY_UNAVAILABLE, self::RATE_LIMITED,
            self::CSRF_INVALID, self::ORIGIN_INVALID, self::TRANSITIONAL_OPEN_DENIED,
            self::CLIENT_IDENTITY_NOT_AUTHORITATIVE, self::DECISION_UNINITIALIZED,
        ];
    }

    public static function assertValid(string $value): string
    {
        if (!self::isKnown($value)) throw new \InvalidArgumentException('unknown_reason_code');
        return $value;
    }
    public static function isKnown(string $value): bool { return in_array($value, self::all(), true); }
    public static function isDeny(string $value): bool { return self::assertValid($value) !== self::ALLOWED; }
}
