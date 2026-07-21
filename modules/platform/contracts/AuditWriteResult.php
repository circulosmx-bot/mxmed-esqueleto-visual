<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class AuditWriteResult
{
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const UNAVAILABLE = 'unavailable';
    public static function assertValid(string $value): string
    {
        if (!in_array($value, [self::ACCEPTED, self::REJECTED, self::UNAVAILABLE], true)) throw new \InvalidArgumentException('unknown_audit_write_result');
        return $value;
    }
}
