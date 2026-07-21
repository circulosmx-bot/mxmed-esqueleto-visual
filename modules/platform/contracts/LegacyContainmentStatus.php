<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class LegacyContainmentStatus
{
    public const RETIRED_FAIL_CLOSED = 'retired_fail_closed';
    public const REMEDIATED_READ_PURITY = 'remediated_read_purity';
    public const CONTAINED_DEPLOYMENT_BLOCKER = 'contained_deployment_blocker';
    public const DEFERRED_DOMAIN_MIGRATION = 'deferred_domain_migration';
    public const UNRESOLVED_STOP_REQUIRED = 'unresolved_stop_required';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::RETIRED_FAIL_CLOSED,
            self::REMEDIATED_READ_PURITY,
            self::CONTAINED_DEPLOYMENT_BLOCKER,
            self::DEFERRED_DOMAIN_MIGRATION,
            self::UNRESOLVED_STOP_REQUIRED,
        ];
    }

    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) {
            throw new \InvalidArgumentException('unknown_legacy_containment_status');
        }
        return $value;
    }

    public static function isResolved(string $value): bool
    {
        self::assertValid($value);
        return in_array($value, [self::RETIRED_FAIL_CLOSED, self::REMEDIATED_READ_PURITY], true);
    }

    public static function isBlocker(string $value): bool
    {
        self::assertValid($value);
        return in_array($value, [self::CONTAINED_DEPLOYMENT_BLOCKER, self::DEFERRED_DOMAIN_MIGRATION, self::UNRESOLVED_STOP_REQUIRED], true);
    }
}

