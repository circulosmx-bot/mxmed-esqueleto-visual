<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class AuthorizationPlane
{
    public const CUSTOMER_PROFESSIONAL = 'customer_professional';
    public const INTERNAL_OPERATOR = 'internal_operator';
    public const GOVERNANCE_EMERGENCY = 'governance_emergency';
    public const PUBLIC_SYSTEM = 'public_system';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::CUSTOMER_PROFESSIONAL, self::INTERNAL_OPERATOR, self::GOVERNANCE_EMERGENCY, self::PUBLIC_SYSTEM];
    }

    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_authorization_plane');
        return $value;
    }

    public static function isCommercialPlan(string $value): bool
    {
        return false;
    }
}
