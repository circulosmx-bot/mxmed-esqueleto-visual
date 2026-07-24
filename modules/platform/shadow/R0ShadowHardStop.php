<?php
declare(strict_types=1);

namespace Platform\Shadow;

use InvalidArgumentException;

final class R0ShadowHardStop
{
    private const CODES = [
        'PII_OR_CLINICAL_DATA_DETECTED',
        'LEGACY_RESPONSE_CHANGED',
        'HTTP_STATUS_CHANGED',
        'HTTP_HEADERS_CHANGED',
        'PAYLOAD_CHANGED',
        'CANONICAL_WRITE_ATTEMPTED',
        'NEW_DB_CONNECTION_ATTEMPTED',
        'SQL_OR_DDL_ATTEMPTED',
        'REAL_OTP_ATTEMPTED',
        'CLINICAL_REQUEST_ATTEMPTED',
        'SCOPE_LEAKAGE_DETECTED',
        'AUTHORITY_AUDIT_UNAVAILABLE',
        'UNKNOWN_OPERATION',
        'UNEXPECTED_SIDE_EFFECT',
        'BUDGET_BREACH_AFTER_APPROVAL',
    ];

    public static function all(): array
    {
        return self::CODES;
    }

    public static function isEligible(string $code): bool
    {
        return in_array($code, self::CODES, true);
    }

    public static function assertEligible(string $code): void
    {
        if (!self::isEligible($code)) {
            throw new InvalidArgumentException('unknown_r0_shadow_hard_stop');
        }
    }
}
