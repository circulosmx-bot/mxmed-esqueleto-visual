<?php
declare(strict_types=1);
namespace Platform\Contracts;
final class CanonicalAuditSeverity
{
    private const VALUES = ['INFO', 'WARN', 'HIGH', 'CRITICAL'];
    /** @return list<string> */ public static function all(): array { return self::VALUES; }
    public static function assertKnown(string $value): string { if (!in_array($value, self::VALUES, true)) throw new \InvalidArgumentException('unknown_audit_severity'); return $value; }
}
