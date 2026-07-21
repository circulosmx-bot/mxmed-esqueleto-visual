<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class AuditAvailability
{
    public const AVAILABLE = 'available';
    public const UNAVAILABLE = 'unavailable';
    public static function assertValid(string $value): string
    {
        if (!in_array($value, [self::AVAILABLE, self::UNAVAILABLE], true)) throw new \InvalidArgumentException('unknown_audit_availability');
        return $value;
    }
}
