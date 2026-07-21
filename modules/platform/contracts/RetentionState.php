<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class RetentionState
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const FROZEN = 'frozen';
    public const ARCHIVED = 'archived';
    public const ANONYMIZED = 'anonymized';
    public const DELETED = 'deleted';
    /** @return list<string> */
    public static function all(): array { return [self::ACTIVE, self::INACTIVE, self::FROZEN, self::ARCHIVED, self::ANONYMIZED, self::DELETED]; }
    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_retention_state');
        return $value;
    }
}
