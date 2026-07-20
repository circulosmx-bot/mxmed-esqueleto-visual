<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class AccountStatus
{
    public const PENDING_VERIFICATION = 'pending_verification';
    public const ACTIVE = 'active';
    public const BLOCKED = 'blocked';
    public const DISABLED = 'disabled';

    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) {
            throw new \InvalidArgumentException('unknown_account_status');
        }
        return $value;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING_VERIFICATION, self::ACTIVE, self::BLOCKED, self::DISABLED];
    }
}
