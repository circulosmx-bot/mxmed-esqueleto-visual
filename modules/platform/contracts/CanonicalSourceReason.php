<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class CanonicalSourceReason
{
    public const ALLOWED = 'allowed';
    public const SOURCE_UNRESOLVED = 'canonical_source_unresolved';
    public const SOURCE_CONFLICT = 'canonical_source_conflict';
    public const SOURCE_NOT_WRITABLE = 'canonical_source_not_writable';
    public const READ_SOURCE_UNAVAILABLE = 'canonical_read_source_unavailable';
    public const DUAL_WRITE_FORBIDDEN = 'dual_write_forbidden';
    public const FALLBACK_FORBIDDEN = 'fallback_forbidden';
    public const READ_SIDE_EFFECT_FORBIDDEN = 'read_side_effect_forbidden';

    /** @return list<string> */
    public static function all(): array { return [self::ALLOWED, self::SOURCE_UNRESOLVED, self::SOURCE_CONFLICT, self::SOURCE_NOT_WRITABLE, self::READ_SOURCE_UNAVAILABLE, self::DUAL_WRITE_FORBIDDEN, self::FALLBACK_FORBIDDEN, self::READ_SIDE_EFFECT_FORBIDDEN]; }
    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_canonical_source_reason');
        return $value;
    }
}
