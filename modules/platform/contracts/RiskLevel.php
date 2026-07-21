<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class RiskLevel
{
    public const R0 = 'R0';
    public const R1 = 'R1';
    public const R2 = 'R2';
    public const R3 = 'R3';

    /** @return list<string> */
    public static function all(): array { return [self::R0, self::R1, self::R2, self::R3]; }
    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_risk_level');
        return $value;
    }
    public static function requiresAuthenticatedActor(string $value): bool { return self::assertValid($value) !== self::R0; }
    public static function requiresAuditTrail(string $value): bool { return self::assertValid($value) !== self::R0; }
    public static function blocksWithoutAudit(string $value): bool { return in_array(self::assertValid($value), [self::R2, self::R3], true); }
    public static function mayRequireReauthentication(string $value): bool { return in_array(self::assertValid($value), [self::R2, self::R3], true); }
    public static function mayRequireMfa(string $value): bool { return self::assertValid($value) === self::R3; }
    public static function mayRequireCase(string $value): bool { return self::assertValid($value) === self::R3; }
    public static function mayRequireApproval(string $value): bool { return self::assertValid($value) === self::R3; }
    public static function mayRequireDualApproval(string $value): bool { return self::assertValid($value) === self::R3; }
}
