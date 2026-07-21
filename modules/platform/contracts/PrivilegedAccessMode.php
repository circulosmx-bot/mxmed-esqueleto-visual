<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class PrivilegedAccessMode
{
    public const SUPPORT_ASSISTED = 'support_assisted';
    public const BREAK_GLASS = 'break_glass';

    /** @return list<string> */
    public static function all(): array { return [self::SUPPORT_ASSISTED, self::BREAK_GLASS]; }
    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_privileged_access_mode');
        return $value;
    }
}
