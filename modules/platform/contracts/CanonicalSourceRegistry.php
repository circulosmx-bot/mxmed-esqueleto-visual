<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class CanonicalSourceRegistry
{
    /** @param list<CanonicalSourceRecord> $records */
    public static function assertInvariants(array $records): void
    {
        $writers = [];
        foreach ($records as $record) {
            if (!$record instanceof CanonicalSourceRecord) throw new \InvalidArgumentException('invalid_source_record');
            if ($record->canAuthorizeWrites()) {
                if (isset($writers[$record->key()])) throw new \InvalidArgumentException('multiple_canonical_writers');
                $writers[$record->key()] = true;
            }
            if (in_array($record->classification(), [SourceClassification::DERIVED_PROJECTION, SourceClassification::LEGACY_READ_ONLY, SourceClassification::DRAFT_NOT_AUTHORITATIVE, SourceClassification::FIXTURE_TEST_ONLY], true) && $record->canAuthorizeWrites()) {
                throw new \InvalidArgumentException('noncanonical_source_write');
            }
        }
    }

    /** @param list<CanonicalSourceRecord> $records */
    public static function selectWriteAuthority(array $records, string $domain, string $entity): CanonicalSourceRecord
    {
        self::assertInvariants($records);
        $key = (new SafeIdentifier($domain))->value() . ':' . (new SafeIdentifier($entity))->value();
        $matches = array_values(array_filter($records, static fn (CanonicalSourceRecord $record): bool => $record->key() === $key && $record->canAuthorizeWrites()));
        if (count($matches) !== 1) throw new \RuntimeException('canonical_source_unresolved');
        return $matches[0];
    }
}
