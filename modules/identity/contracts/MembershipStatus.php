<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class MembershipStatus
{
    public const PENDING = 'pending';
    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended';
    public const REVOKED = 'revoked';

    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) {
            throw new \InvalidArgumentException('unknown_membership_status');
        }
        return $value;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::ACTIVE, self::SUSPENDED, self::REVOKED];
    }
}
