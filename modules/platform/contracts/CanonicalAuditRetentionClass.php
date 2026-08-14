<?php
declare(strict_types=1);
namespace Platform\Contracts;
final class CanonicalAuditRetentionClass
{
    private const VALUES = ['AUTH_SECURITY', 'OWNERSHIP', 'ROLE_ADMIN', 'PAYMENT', 'CLINICAL_ACCESS', 'GENERAL_ACTIVITY', 'BREAK_GLASS_LEGAL_HOLD'];
    /** @return list<string> */ public static function all(): array { return self::VALUES; }
    public static function assertKnown(string $value): string { if (!in_array($value, self::VALUES, true)) throw new \InvalidArgumentException('unknown_audit_retention_class'); return $value; }
}
