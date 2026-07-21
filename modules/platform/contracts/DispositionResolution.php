<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class DispositionResolution
{
    public const RETENTION_UNRESOLVED = 'retention_unresolved';
    public const ANONYMIZATION_UNRESOLVED = 'anonymization_unresolved';
    /** @return list<string> */
    public static function all(): array { return [self::RETENTION_UNRESOLVED, self::ANONYMIZATION_UNRESOLVED]; }
    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_disposition_resolution');
        return $value;
    }
}
