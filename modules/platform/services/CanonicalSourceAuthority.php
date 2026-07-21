<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\CanonicalSourceReason;
use Platform\Contracts\CanonicalSourceRecord;
use Platform\Contracts\CanonicalSourceRegistry;
use Platform\Contracts\CanonicalSourceResolution;
use Platform\Contracts\ReadOperationContract;
use Platform\Contracts\ReadOperationDecision;

/** In-memory source authority only; no schema, table or runtime connection. */
final class CanonicalSourceAuthority
{
    /** @var list<CanonicalSourceRecord> */
    private array $records = [];

    /** @param list<CanonicalSourceRecord> $records */
    public function __construct(array $records = [])
    {
        foreach ($records as $record) {
            if (!$record instanceof CanonicalSourceRecord) throw new \InvalidArgumentException('invalid_canonical_source_record');
            $this->records[] = $record;
        }
        $this->validateRegistry();
    }

    public function register(CanonicalSourceRecord $record): CanonicalSourceResolution
    {
        $candidate = [...$this->records, $record];
        try {
            self::validateRecords($candidate);
        } catch (\InvalidArgumentException $exception) {
            $reason = $exception->getMessage() === 'multiple_canonical_writers' ? CanonicalSourceReason::SOURCE_CONFLICT : CanonicalSourceReason::SOURCE_UNRESOLVED;
            return CanonicalSourceResolution::denied('register', $reason);
        }
        $this->records[] = $record;
        return CanonicalSourceResolution::allowResolution('register', $record);
    }

    public function resolveWrite(string $domain, string $entity): CanonicalSourceResolution
    {
        $matches = $this->matches($domain, $entity, static fn (CanonicalSourceRecord $record): bool => $record->canAuthorizeWrites());
        if (count($matches) === 0) return CanonicalSourceResolution::denied('write', CanonicalSourceReason::SOURCE_UNRESOLVED);
        if (count($matches) > 1) return CanonicalSourceResolution::denied('write', CanonicalSourceReason::SOURCE_CONFLICT);
        return CanonicalSourceResolution::allowResolution('write', $matches[0]);
    }

    public function resolveRead(string $domain, string $entity): CanonicalSourceResolution
    {
        $matches = $this->matches($domain, $entity, static fn (CanonicalSourceRecord $record): bool => $record->status() === 'active' && $record->classification() === 'canonical_read');
        if (count($matches) === 0) return CanonicalSourceResolution::denied('read', CanonicalSourceReason::READ_SOURCE_UNAVAILABLE);
        if (count($matches) > 1) return CanonicalSourceResolution::denied('read', CanonicalSourceReason::SOURCE_CONFLICT);
        return CanonicalSourceResolution::allowResolution('read', $matches[0]);
    }

    public function validateRead(ReadOperationContract $operation): ReadOperationDecision
    {
        return $operation->isPure() && !$operation->hasSideEffects() ? ReadOperationDecision::allowRead($operation) : ReadOperationDecision::denied($operation->operation(), CanonicalSourceReason::READ_SIDE_EFFECT_FORBIDDEN);
    }

    /** @return list<array<string,mixed>> */
    public function snapshot(): array
    {
        $snapshot = [];
        foreach ($this->records as $record) {
            $snapshot[] = ['domain' => $record->domain(), 'entity' => $record->entity(), 'classification' => $record->classification(), 'reader_authority' => $record->readerAuthority(), 'writer_authority' => $record->writerAuthority(), 'source_reference' => $record->sourceReference(), 'migration_status' => $record->migrationStatus(), 'reconciliation_required' => $record->reconciliationRequired(), 'rollback_required' => $record->rollbackRequired(), 'status' => $record->status()];
        }
        usort($snapshot, static fn (array $left, array $right): int => strcmp($left['domain'] . ':' . $left['entity'] . ':' . $left['classification'], $right['domain'] . ':' . $right['entity'] . ':' . $right['classification']));
        return $snapshot;
    }

    private function validateRegistry(): void { self::validateRecords($this->records); }
    /** @param list<CanonicalSourceRecord> $records */
    private static function validateRecords(array $records): void
    {
        CanonicalSourceRegistry::assertInvariants($records);
        $activeReads = [];
        foreach ($records as $record) {
            if ($record->status() === 'active' && $record->classification() === 'canonical_read') {
                if (isset($activeReads[$record->key()])) throw new \InvalidArgumentException('multiple_canonical_reads');
                $activeReads[$record->key()] = true;
            }
        }
    }
    /** @return list<CanonicalSourceRecord> */
    private function matches(string $domain, string $entity, callable $predicate): array
    {
        $key = trim($domain) . ':' . trim($entity);
        return array_values(array_filter($this->records, static fn (CanonicalSourceRecord $record): bool => $record->key() === $key && $predicate($record)));
    }
}
