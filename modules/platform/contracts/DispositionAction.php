<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class DispositionAction
{
    public const ACCESS = 'access';
    public const EXPORT = 'export';
    public const EXPORT_MASS = 'export_mass';
    public const CORRECT = 'correct';
    public const RESTRICT = 'restrict';
    public const REVOKE_CONSENT = 'revoke_consent';
    public const ARCHIVE = 'archive';
    public const ANONYMIZE = 'anonymize';
    public const DELETE = 'delete';
    public const LEGAL_HOLD = 'legal_hold';
    public const DENY_WITH_REASON = 'deny_with_reason';
    /** @return list<string> */
    public static function all(): array { return [self::ACCESS, self::EXPORT, self::EXPORT_MASS, self::CORRECT, self::RESTRICT, self::REVOKE_CONSENT, self::ARCHIVE, self::ANONYMIZE, self::DELETE, self::LEGAL_HOLD, self::DENY_WITH_REASON]; }
    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_disposition_action');
        return $value;
    }
    public static function requiresR3(string $value): bool { return in_array(self::assertValid($value), [self::EXPORT_MASS, self::ANONYMIZE, self::DELETE], true); }
    public static function isIrreversible(string $value): bool { return in_array(self::assertValid($value), [self::ANONYMIZE, self::DELETE], true); }
}
