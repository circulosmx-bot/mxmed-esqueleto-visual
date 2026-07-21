<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../services/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../adapters/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../repositories/*.php') as $file) require_once $file;

use Platform\Adapters\InMemoryAuditTrailAdapter;
use Platform\Adapters\PdoAuditTrailAdapter;
use Platform\Adapters\RejectingAuditTrailAdapter;
use Platform\Adapters\UnavailableAuditTrailAdapter;
use Platform\Contracts\ActorReference;
use Platform\Contracts\ApprovalReferenceSet;
use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditEventEnvelope;
use Platform\Contracts\AuditEventReference;
use Platform\Contracts\AuditIntegrityReason;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\AuditWriteResult;
use Platform\Contracts\AuthorizationContext;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\AuthorizationRequirement;
use Platform\Contracts\CapabilitySet;
use Platform\Contracts\ReasonCode;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\ScopeSet;
use Platform\Contracts\SessionReference;
use Platform\Contracts\SubjectReference;
use Platform\Contracts\TrustedAuthorizationContext;
use Platform\Repositories\AuditEventRepository;
use Platform\Services\AuditEventCanonicalizer;
use Platform\Services\AuditEventSanitizer;
use Platform\Services\AuditIntegrityChain;
use Platform\Services\AuditIntegrityVerifier;
use Platform\Services\AuthorizationBoundary;

function gate6dAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function gate6dThrows(callable $callback, string $message): void
{
    try { $callback(); } catch (InvalidArgumentException|RuntimeException) { return; }
    throw new RuntimeException($message);
}

/** @param array<string,mixed> $metadata */
function gate6dEvent(string $suffix = 'one', array $metadata = []): AuditEventReference
{
    $defaults = [
        'resource_type' => 'profile',
        'resource_reference' => 'profile-' . $suffix,
        'decision' => 'allow',
        'reason_code' => 'all_requirements_satisfied',
        'authorization_plane' => AuthorizationPlane::CUSTOMER_PROFESSIONAL,
        'case_reference' => 'case-' . $suffix,
        'audit_category' => 'authorization',
    ];
    return new AuditEventReference(
        'profile_read',
        RiskLevel::R2,
        new ActorReference('account', 'real-' . $suffix),
        new ActorReference('operator', 'effective-' . $suffix),
        new SubjectReference('profile', 'subject-' . $suffix),
        'correlation-' . $suffix,
        'request-' . $suffix,
        AuditWriteResult::ACCEPTED,
        [...$defaults, ...$metadata]
    );
}

final class Gate6DInMemoryAuditEventRepository implements AuditEventRepository
{
    /** @var list<AuditEventEnvelope> */
    public array $events = [];
    public bool $available = true;
    public bool $failInsert = false;
    public int $rollbacks = 0;
    private bool $transaction = false;

    public function availability(): string { return $this->available ? AuditAvailability::AVAILABLE : AuditAvailability::UNAVAILABLE; }
    public function beginTransaction(): void { if (!$this->available) throw new RuntimeException('repository_unavailable'); $this->transaction = true; }
    public function commit(): void { if (!$this->transaction) throw new RuntimeException('transaction_missing'); $this->transaction = false; }
    public function rollBack(): void { $this->transaction = false; $this->rollbacks++; }
    public function inTransaction(): bool { return $this->transaction; }
    public function findByEventId(string $eventId): ?AuditEventEnvelope
    {
        foreach ($this->events as $event) if ($event->eventId() === $eventId) return $event;
        return null;
    }
    public function latest(string $streamKey): ?AuditEventEnvelope
    {
        $matches = array_values(array_filter($this->events, static fn(AuditEventEnvelope $event): bool => $event->streamKey() === $streamKey));
        usort($matches, static fn(AuditEventEnvelope $left, AuditEventEnvelope $right): int => $right->sequenceNumber() <=> $left->sequenceNumber());
        return $matches[0] ?? null;
    }
    public function insert(AuditEventEnvelope $event): void
    {
        if ($this->failInsert) throw new RuntimeException('insert_failed');
        if ($this->findByEventId($event->eventId()) !== null) throw new RuntimeException('duplicate_event');
        $this->events[] = $event;
    }
}

$sanitizer = new AuditEventSanitizer();
$canonicalizer = new AuditEventCanonicalizer($sanitizer);
$chain = new AuditIntegrityChain($canonicalizer);
$verifier = new AuditIntegrityVerifier($chain);
$event = $sanitizer->sanitize(gate6dEvent());

gate6dAssert(is_subclass_of(PdoAuditTrailAdapter::class, AuditTrailPort::class), 'PDO adapter preserves AuditTrailPort');
gate6dAssert((new InMemoryAuditTrailAdapter())->availability() === AuditAvailability::AVAILABLE, 'in-memory adapter preserved');
gate6dAssert((new RejectingAuditTrailAdapter())->write($event) === AuditWriteResult::REJECTED, 'rejecting adapter preserved');
gate6dAssert((new UnavailableAuditTrailAdapter())->write($event) === AuditWriteResult::UNAVAILABLE, 'unavailable adapter preserved');

$allowedKeys = ['audit_category', 'authorization_plane', 'case_reference', 'decision', 'reason_code', 'resource_reference', 'resource_type'];
gate6dAssert(array_keys($event->metadata()) === $allowedKeys, 'metadata is allow-listed and ordered');
gate6dThrows(fn() => $sanitizer->sanitize(gate6dEvent('sensitive', ['Diagnosis' => 'text'])), 'case-insensitive sensitive key rejected');
gate6dThrows(fn() => $sanitizer->sanitize(gate6dEvent('unknown', ['free_text' => 'not allowed'])), 'unknown metadata rejected');
gate6dThrows(fn() => $sanitizer->sanitize(new AuditEventReference('profile_read', RiskLevel::R2, null, null, null, 'correlation-bad', 'request-bad', AuditWriteResult::ACCEPTED, ['note' => 'not allowed'])), 'sensitive note rejected');
gate6dThrows(fn() => new AuditEventReference('profile_read', RiskLevel::R2, null, null, null, 'correlation-bad', 'request-bad', AuditWriteResult::ACCEPTED, ['nested' => ['not allowed']]), 'nested metadata rejected');

$stream = $canonicalizer->streamKey($event);
$eventId = $canonicalizer->eventId($event, $stream);
gate6dAssert($eventId === $canonicalizer->eventId($event, $stream), 'event_id stable');
gate6dAssert($canonicalizer->canonicalizeReference($event) === $canonicalizer->canonicalizeReference($sanitizer->sanitize($event)), 'canonicalization deterministic');
$first = new AuditEventEnvelope(AuditEventEnvelope::SCHEMA_VERSION, $eventId, $stream, 1, '2026-07-20T12:00:00.000000Z', $event, $event->metadata()['reason_code'], $event->metadata()['case_reference'], $event->metadata()['resource_type'], $event->metadata()['resource_reference']);
$first = $chain->seal($first, AuditEventEnvelope::GENESIS_HASH);
gate6dAssert(AuditEventEnvelope::GENESIS_VERSION === 'audit-genesis-v1' && $first->previousHash() === AuditEventEnvelope::GENESIS_HASH && $first->eventHash() !== null, 'first event uses explicit versioned genesis hash');

$secondEvent = $sanitizer->sanitize(gate6dEvent('two'));
$secondStream = $canonicalizer->streamKey($secondEvent);
$second = new AuditEventEnvelope(AuditEventEnvelope::SCHEMA_VERSION, $canonicalizer->eventId($secondEvent, $secondStream), $secondStream, 1, '2026-07-20T12:00:01.000000Z', $secondEvent, $secondEvent->metadata()['reason_code'], $secondEvent->metadata()['case_reference'], $secondEvent->metadata()['resource_type'], $secondEvent->metadata()['resource_reference']);
$second = $chain->seal($second, AuditEventEnvelope::GENESIS_HASH);
gate6dAssert($verifier->verify([$first])['valid'], 'single event verifies');
gate6dAssert($verifier->verify([$first, $second])['valid'] === false, 'different streams cannot form one chain');

$chainedSecondEvent = $sanitizer->sanitize(gate6dEvent('one-b', ['resource_reference' => 'profile-one']));
$chainedSecondStream = $canonicalizer->streamKey($chainedSecondEvent);
$chainedSecond = new AuditEventEnvelope(AuditEventEnvelope::SCHEMA_VERSION, $canonicalizer->eventId($chainedSecondEvent, $chainedSecondStream), $chainedSecondStream, 2, '2026-07-20T12:00:01.000000Z', $chainedSecondEvent, $chainedSecondEvent->metadata()['reason_code'], $chainedSecondEvent->metadata()['case_reference'], $chainedSecondEvent->metadata()['resource_type'], $chainedSecondEvent->metadata()['resource_reference'], $first->eventHash());
$chainedSecond = $chain->seal($chainedSecond, $first->eventHash());
gate6dAssert($verifier->verify([$first, $chainedSecond])['valid'], 'second event links to first');
$duplicateSequence = new AuditEventEnvelope(AuditEventEnvelope::SCHEMA_VERSION, $second->eventId(), $stream, 1, $second->occurredAtUtc(), $second->event(), null, null, null, null, AuditEventEnvelope::GENESIS_HASH, $second->eventHash());
gate6dAssert($verifier->verify([$first, $duplicateSequence])['reason_code'] === AuditIntegrityReason::SEQUENCE_DUPLICATE, 'duplicate sequence detected');

$mutated = new AuditEventEnvelope(AuditEventEnvelope::SCHEMA_VERSION, $chainedSecond->eventId(), $chainedSecond->streamKey(), 2, $chainedSecond->occurredAtUtc(), $sanitizer->sanitize(gate6dEvent('one-b', ['decision' => 'deny'])), $chainedSecond->reasonCode(), $chainedSecond->caseReference(), $chainedSecond->resourceType(), $chainedSecond->resourceReference(), $chainedSecond->previousHash(), $chainedSecond->eventHash());
gate6dAssert($verifier->verify([$first, $mutated])['reason_code'] === AuditIntegrityReason::EVENT_HASH_MISMATCH, 'event modification detected');
gate6dAssert($verifier->verify([$first, $chainedSecond, $chainedSecond])['reason_code'] === AuditIntegrityReason::EVENT_ID_DUPLICATE, 'sequence/event duplicate detected');
gate6dAssert($verifier->verify([$first, $chainedSecond, new AuditEventEnvelope(AuditEventEnvelope::SCHEMA_VERSION, $canonicalizer->eventId($secondEvent, $secondStream), $secondStream, 3, '2026-07-20T12:00:02.000000Z', $secondEvent, null, null, null, null, $chainedSecond->eventHash(), $chainedSecond->eventHash())])['reason_code'] === AuditIntegrityReason::STREAM_MISMATCH, 'stream mismatch detected');
gate6dAssert($verifier->verify([$first, new AuditEventEnvelope(AuditEventEnvelope::SCHEMA_VERSION, $second->eventId(), $stream, 3, $second->occurredAtUtc(), $second->event(), null, null, null, null, $first->eventHash(), $second->eventHash())])['reason_code'] === AuditIntegrityReason::SEQUENCE_GAP, 'intermediate deletion detected');
gate6dAssert($verifier->verify([$chainedSecond, $first])['reason_code'] === AuditIntegrityReason::SEQUENCE_GAP, 'reordering detected');
gate6dAssert($verifier->verify([new AuditEventEnvelope('audit.v999', $first->eventId(), $first->streamKey(), 1, $first->occurredAtUtc(), $first->event(), null, null, null, null, AuditEventEnvelope::GENESIS_HASH, $first->eventHash())])['reason_code'] === AuditIntegrityReason::UNSUPPORTED_SCHEMA_VERSION, 'unknown schema version detected');

$repository = new Gate6DInMemoryAuditEventRepository();
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-20T12:00:00+00:00');
$adapter = new PdoAuditTrailAdapter($repository, $sanitizer, $canonicalizer, $chain, $clock);
gate6dAssert($adapter->availability() === AuditAvailability::AVAILABLE, 'PDO adapter availability is explicit');
gate6dAssert($adapter->write(gate6dEvent('adapter')) === AuditWriteResult::ACCEPTED, 'persisted event accepted');
gate6dAssert($adapter->write(gate6dEvent('adapter')) === AuditWriteResult::ACCEPTED && count($repository->events) === 1, 'identical duplicate accepted idempotently');
gate6dAssert($adapter->write(gate6dEvent('adapter', ['decision' => 'deny'])) === AuditWriteResult::REJECTED && count($repository->events) === 1, 'incompatible duplicate rejected');
gate6dAssert($adapter->write(gate6dEvent('adapter-two')) === AuditWriteResult::ACCEPTED, 'second stream event accepted');
$repository->available = false;
gate6dAssert($adapter->write(gate6dEvent('unavailable')) === AuditWriteResult::UNAVAILABLE, 'unavailable repository fail closed');
$repository->available = true;
$repository->failInsert = true;
gate6dAssert($adapter->write(gate6dEvent('insert-failure')) === AuditWriteResult::UNAVAILABLE && $repository->rollbacks > 0, 'transaction failure returns unavailable and rolls back');
gate6dAssert($adapter->write(gate6dEvent('bad-sanitize', ['free_text' => 'not allowed'])) === AuditWriteResult::REJECTED, 'sanitization failure returns rejected');

$boundary = new AuthorizationBoundary();
$context = TrustedAuthorizationContext::fromBackend(new AuthorizationContext(new ActorReference('account', 'real-r3'), new ActorReference('account', 'effective-r3'), new SubjectReference('profile', 'subject-r3'), new SessionReference('session-r3'), 'account-r3', 1, 'membership-r3', 'entity-r3', 'profile-r3', 'owner', 'professional', new ScopeSet(['profile:read']), new CapabilitySet(['profile:read']), 'read', 'profile', AuthorizationPlane::CUSTOMER_PROFESSIONAL, RiskLevel::R3, 'correlation-r3', 'request-r3', 'case-r3', new ApprovalReferenceSet(['approval-r3', 'approval-r3-2'])), 'backend_resolver', 'active', true, true, true, true, false);
$requirement = new AuthorizationRequirement(AuthorizationPlane::CUSTOMER_PROFESSIONAL, RiskLevel::R3, 'read', 'profile', 'profile-r3', true, true, true, true, true, ['professional'], new ScopeSet(['profile:read']), new CapabilitySet(['profile:read']), true, true, true, true, true, null);
gate6dAssert($boundary->authorize($context, $requirement, null)->reasonCode() === ReasonCode::AUDIT_UNAVAILABLE, 'Gate 6B R3 missing audit remains fail closed');

$migrationPath = __DIR__ . '/../db/migrations/2026_07_20_01_create_platform_audit_events.sql';
$migration = file_get_contents($migrationPath);
gate6dAssert(is_string($migration) && str_contains($migration, 'CREATE TABLE platform_audit_events'), 'versioned migration creates expected table');
foreach (['metadata_json JSON', 'event_hash', 'previous_hash', 'idx_platform_audit_correlation', 'idx_platform_audit_request', 'idx_platform_audit_occurred'] as $required) gate6dAssert(str_contains($migration, $required), 'migration requirement present: ' . $required);
gate6dAssert(!preg_match('/INSERT\s+INTO/i', $migration), 'migration has no seed insert');
gate6dAssert(!preg_match('/DROP\s+TABLE/i', $migration), 'migration has no destructive drop');
foreach (['payload', 'body', 'clinical_text', 'password', 'token'] as $forbidden) gate6dAssert(!preg_match('/\b' . preg_quote($forbidden, '/') . '\b/i', $migration), 'migration excludes sensitive column: ' . $forbidden);
$repositorySource = file_get_contents(__DIR__ . '/../repositories/PdoAuditEventRepository.php');
gate6dAssert(is_string($repositorySource) && !preg_match('/\b(UPDATE|DELETE|REPLACE)\s+platform_audit_events/i', $repositorySource), 'repository exposes no event mutation');
gate6dAssert(!preg_match('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', $repositorySource), 'repository has no destructive upsert');
$adapterSource = file_get_contents(__DIR__ . '/../adapters/PdoAuditTrailAdapter.php');
gate6dAssert(is_string($adapterSource) && !str_contains($adapterSource, 'error_log') && !str_contains($adapterSource, 'file_put_contents'), 'adapter has no logging/file fallback');
$envelopeKeys = array_keys($first->toArray());
foreach (['names', 'emails', 'phone', 'payload', 'sql', 'stack_trace'] as $forbidden) gate6dAssert(!in_array($forbidden, $envelopeKeys, true), 'envelope excludes sensitive field: ' . $forbidden);
$docs = file_get_contents(__DIR__ . '/../../../docs/MXMED_IMPLEMENTACION_V2_PG08_GATE_6D_AUDIT_TRAIL_TRANSVERSAL.md');
gate6dAssert(is_string($docs) && str_contains($docs, 'retention_unresolved') && str_contains($docs, 'no firma criptográfica externa'), 'retention unresolved and external signing boundary documented');

echo "Gate6DTransversalAuditTrailTest PASS\n";
