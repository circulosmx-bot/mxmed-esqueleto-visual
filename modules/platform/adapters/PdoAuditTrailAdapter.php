<?php
declare(strict_types=1);

namespace Platform\Adapters;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditEventEnvelope;
use Platform\Contracts\AuditEventReference;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\AuditWriteResult;
use Platform\Repositories\AuditEventRepository;
use Platform\Services\AuditEventCanonicalizer;
use Platform\Services\AuditEventSanitizer;
use Platform\Services\AuditIntegrityChain;

/** Persistence adapter prepared for PDO; it is not wired to runtime in Gate 6D. */
final class PdoAuditTrailAdapter implements AuditTrailPort
{
    /** @var callable():DateTimeInterface */
    private $clock;

    /** @param null|callable():DateTimeInterface $clock */
    public function __construct(
        private readonly AuditEventRepository $repository,
        private readonly AuditEventSanitizer $sanitizer = new AuditEventSanitizer(),
        private readonly AuditEventCanonicalizer $canonicalizer = new AuditEventCanonicalizer(),
        private readonly AuditIntegrityChain $chain = new AuditIntegrityChain(),
        ?callable $clock = null
    ) {
        $this->clock = $clock ?? static fn(): DateTimeInterface => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function availability(): string
    {
        try { return $this->repository->availability(); } catch (\Throwable) { return AuditAvailability::UNAVAILABLE; }
    }

    public function write(AuditEventReference $event): string
    {
        try {
            $event = $this->sanitizer->sanitize($event);
        } catch (\Throwable) {
            return AuditWriteResult::REJECTED;
        }
        if ($this->availability() !== AuditAvailability::AVAILABLE) return AuditWriteResult::UNAVAILABLE;
        $streamKey = $this->canonicalizer->streamKey($event);
        $eventId = $this->canonicalizer->eventId($event, $streamKey);
        $started = false;
        try {
            $this->repository->beginTransaction();
            $started = true;
            $existing = $this->repository->findByEventId($eventId);
            if ($existing !== null) {
                $same = $existing->schemaVersion() === AuditEventEnvelope::SCHEMA_VERSION
                    && $existing->streamKey() === $streamKey
                    && $this->canonicalizer->eventFingerprint($existing->event(), $streamKey) === $this->canonicalizer->eventFingerprint($event, $streamKey);
                $this->repository->rollBack();
                return $same ? AuditWriteResult::ACCEPTED : AuditWriteResult::REJECTED;
            }
            $latest = $this->repository->latest($streamKey);
            $sequence = $latest === null ? 1 : $latest->sequenceNumber() + 1;
            $previousHash = $latest?->eventHash() ?? AuditEventEnvelope::GENESIS_HASH;
            if ($latest !== null && $latest->eventHash() === null) {
                $this->repository->rollBack();
                return AuditWriteResult::REJECTED;
            }
            $occurred = ($this->clock)();
            $envelope = new AuditEventEnvelope(AuditEventEnvelope::SCHEMA_VERSION, $eventId, $streamKey, $sequence, $occurred->format(DateTimeInterface::ATOM), $event, $event->metadata()['reason_code'] ?? null, $event->metadata()['case_reference'] ?? null, $event->metadata()['resource_type'] ?? null, $event->metadata()['resource_reference'] ?? null, $previousHash);
            $this->repository->insert($this->chain->seal($envelope, $previousHash));
            $this->repository->commit();
            return AuditWriteResult::ACCEPTED;
        } catch (\Throwable) {
            if ($started) {
                try { $this->repository->rollBack(); } catch (\Throwable) { /* preserve fail-closed result */ }
            }
            return AuditWriteResult::UNAVAILABLE;
        }
    }
}
