<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class SourceClassification
{
    public const CANONICAL_WRITE = 'canonical_write';
    public const CANONICAL_READ = 'canonical_read';
    public const DERIVED_PROJECTION = 'derived_projection';
    public const MIGRATION_SOURCE = 'migration_source';
    public const LEGACY_READ_ONLY = 'legacy_read_only';
    public const DRAFT_NOT_AUTHORITATIVE = 'draft_not_authoritative';
    public const FIXTURE_TEST_ONLY = 'fixture_test_only';
    public const UNRESOLVED = 'unresolved';

    /** @return list<string> */
    public static function all(): array { return [self::CANONICAL_WRITE, self::CANONICAL_READ, self::DERIVED_PROJECTION, self::MIGRATION_SOURCE, self::LEGACY_READ_ONLY, self::DRAFT_NOT_AUTHORITATIVE, self::FIXTURE_TEST_ONLY, self::UNRESOLVED]; }
    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_source_classification');
        return $value;
    }
    public static function canWrite(string $value): bool { return self::assertValid($value) === self::CANONICAL_WRITE; }
}
