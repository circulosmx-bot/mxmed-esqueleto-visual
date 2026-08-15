<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

/** Finite MP01G read capabilities, scopes and controlled access reasons. */
final class AuditReadAccess
{
    public const SELF_SECURITY = 'AUDIT_READ_SELF_SECURITY';
    public const INTERNAL_SCOPED = 'AUDIT_READ_INTERNAL_SCOPED';
    public const ADMIN_PRIVILEGED = 'AUDIT_READ_ADMIN_PRIVILEGED';

    public const REASON_SELF_SECURITY = 'SELF_SECURITY';
    public const REASON_SUPPORT = 'SUPPORT_INVESTIGATION';
    public const REASON_SECURITY = 'SECURITY_INVESTIGATION';
    public const REASON_COMPLIANCE = 'COMPLIANCE_REVIEW';
    public const REASON_DIAGNOSTIC = 'OPERATIONAL_DIAGNOSTIC';

    public const SCOPE_SELF_ACCOUNT = 'SELF_ACCOUNT';
    public const SCOPE_ACCOUNT = 'ACCOUNT';
    public const SCOPE_PROFILE = 'PROFILE';
    public const SCOPE_CORRELATION = 'CORRELATION';
    public const SCOPE_REQUEST = 'REQUEST';
    public const SCOPE_EVENT_TYPE = 'EVENT_TYPE';
    public const SCOPE_TIME_RANGE = 'TIME_RANGE';

    /** @return list<string> */
    public static function capabilities(): array
    {
        return [self::SELF_SECURITY, self::INTERNAL_SCOPED, self::ADMIN_PRIVILEGED];
    }

    /** @return list<string> */
    public static function reasons(): array
    {
        return [self::REASON_SELF_SECURITY, self::REASON_SUPPORT, self::REASON_SECURITY, self::REASON_COMPLIANCE, self::REASON_DIAGNOSTIC];
    }

    /** @return list<string> */
    public static function scopes(): array
    {
        return [self::SCOPE_SELF_ACCOUNT, self::SCOPE_ACCOUNT, self::SCOPE_PROFILE, self::SCOPE_CORRELATION, self::SCOPE_REQUEST, self::SCOPE_EVENT_TYPE, self::SCOPE_TIME_RANGE];
    }

    public static function assertCapability(string $capability): void
    {
        if (!in_array($capability, self::capabilities(), true)) {
            throw new \InvalidArgumentException('unknown_audit_read_capability');
        }
    }

    public static function assertScope(string $scope): void
    {
        if (!in_array($scope, self::scopes(), true)) {
            throw new \InvalidArgumentException('unknown_audit_read_scope');
        }
    }

    public static function assertReason(string $reason): void
    {
        if (!in_array($reason, self::reasons(), true)) {
            throw new \InvalidArgumentException('unknown_audit_read_reason');
        }
    }

    public static function assertCombination(string $capability, string $scope, string $reason): void
    {
        self::assertCapability($capability);
        self::assertScope($scope);
        self::assertReason($reason);
        if ($capability === self::SELF_SECURITY) {
            if ($scope !== self::SCOPE_SELF_ACCOUNT || $reason !== self::REASON_SELF_SECURITY) {
                throw new \InvalidArgumentException('invalid_self_security_read_contract');
            }
            return;
        }
        if ($scope === self::SCOPE_SELF_ACCOUNT || $reason === self::REASON_SELF_SECURITY) {
            throw new \InvalidArgumentException('self_scope_reserved');
        }
        if ($capability === self::INTERNAL_SCOPED && $scope === self::SCOPE_EVENT_TYPE) {
            throw new \InvalidArgumentException('broad_event_scope_requires_privileged_capability');
        }
    }
}
