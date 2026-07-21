<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\AuditEventEnvelope;
use Platform\Contracts\AuditIntegrityReason;

/** Pure verifier that reports the first tamper or ordering failure without repair. */
final class AuditIntegrityVerifier
{
    public function __construct(private readonly AuditIntegrityChain $chain = new AuditIntegrityChain()) {}

    /** @param list<AuditEventEnvelope> $events @return array{valid:bool,checked_events:int,first_invalid_sequence:?int,reason_code:string,expected_hash:?string,actual_hash:?string} */
    public function verify(array $events): array
    {
        $stream = null;
        $expectedSequence = 1;
        $previousHash = AuditEventEnvelope::GENESIS_HASH;
        $eventIds = [];
        foreach ($events as $event) {
            if (!$event instanceof AuditEventEnvelope) return $this->invalid($expectedSequence, AuditIntegrityReason::SEQUENCE_OUT_OF_ORDER, null, null, $expectedSequence - 1);
            if ($event->schemaVersion() !== AuditEventEnvelope::SCHEMA_VERSION) return $this->invalid($event->sequenceNumber(), AuditIntegrityReason::UNSUPPORTED_SCHEMA_VERSION, null, null, $expectedSequence - 1);
            if ($stream === null) $stream = $event->streamKey();
            if ($event->streamKey() !== $stream) return $this->invalid($event->sequenceNumber(), AuditIntegrityReason::STREAM_MISMATCH, null, null, $expectedSequence - 1);
            if (isset($eventIds[$event->eventId()])) return $this->invalid($event->sequenceNumber(), AuditIntegrityReason::EVENT_ID_DUPLICATE, null, null, $expectedSequence - 1);
            $eventIds[$event->eventId()] = true;
            if ($event->sequenceNumber() < $expectedSequence) return $this->invalid($event->sequenceNumber(), AuditIntegrityReason::SEQUENCE_DUPLICATE, null, null, $expectedSequence - 1);
            if ($event->sequenceNumber() > $expectedSequence) return $this->invalid($event->sequenceNumber(), AuditIntegrityReason::SEQUENCE_GAP, null, null, $expectedSequence - 1);
            if ($event->previousHash() !== $previousHash) return $this->invalid($event->sequenceNumber(), AuditIntegrityReason::PREVIOUS_HASH_MISMATCH, $previousHash, $event->previousHash(), $expectedSequence - 1);
            $expectedHash = $this->chain->calculateHash($event, $event->previousHash());
            if ($event->eventHash() === null || $event->eventHash() !== $expectedHash) return $this->invalid($event->sequenceNumber(), AuditIntegrityReason::EVENT_HASH_MISMATCH, $expectedHash, $event->eventHash(), $expectedSequence - 1);
            $previousHash = $event->eventHash();
            $expectedSequence++;
        }
        return ['valid' => true, 'checked_events' => count($events), 'first_invalid_sequence' => null, 'reason_code' => AuditIntegrityReason::VALID, 'expected_hash' => null, 'actual_hash' => null];
    }

    /** @return array{valid:bool,checked_events:int,first_invalid_sequence:?int,reason_code:string,expected_hash:?string,actual_hash:?string} */
    private function invalid(int $sequence, string $reason, ?string $expected, ?string $actual, int $checked): array
    {
        return ['valid' => false, 'checked_events' => max(0, $checked), 'first_invalid_sequence' => $sequence, 'reason_code' => $reason, 'expected_hash' => $expected, 'actual_hash' => $actual];
    }
}
