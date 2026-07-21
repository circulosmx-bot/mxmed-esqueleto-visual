<?php
declare(strict_types=1);

namespace Platform\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Platform\Contracts\ActorReference;
use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditEventEnvelope;
use Platform\Contracts\AuditEventReference;
use Platform\Contracts\SubjectReference;

/** PDO-only persistence preparation. It performs no schema management or fallback logging. */
final class PdoAuditEventRepository implements AuditEventRepository
{
    /** @var null|callable():bool */
    private $availabilityProbe;

    /** @param null|callable():bool $availabilityProbe */
    public function __construct(private readonly PDO $pdo, ?callable $availabilityProbe = null)
    {
        $this->availabilityProbe = $availabilityProbe;
    }

    public function availability(): string
    {
        try {
            if ($this->availabilityProbe !== null && !(bool) ($this->availabilityProbe)()) return AuditAvailability::UNAVAILABLE;
            $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            return AuditAvailability::AVAILABLE;
        } catch (\Throwable) {
            return AuditAvailability::UNAVAILABLE;
        }
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollBack(): void { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }

    public function findByEventId(string $eventId): ?AuditEventEnvelope
    {
        $statement = $this->pdo->prepare(self::selectColumns() . ' WHERE event_id = :event_id LIMIT 1');
        $statement->execute(['event_id' => $eventId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function latest(string $streamKey): ?AuditEventEnvelope
    {
        $statement = $this->pdo->prepare(self::selectColumns() . ' WHERE stream_key = :stream_key ORDER BY sequence_number DESC LIMIT 1 LOCK IN SHARE MODE');
        $statement->execute(['stream_key' => $streamKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function insert(AuditEventEnvelope $event): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO platform_audit_events '
            . '(stream_key, sequence_number, event_id, schema_version, occurred_at_utc, action, risk_level, outcome, reason_code, '
            . 'real_actor_reference, effective_actor_reference, affected_subject_reference, correlation_id, request_id, case_reference, '
            . 'resource_type, resource_reference, metadata_json, previous_hash, event_hash, created_at_utc) '
            . 'VALUES (:stream_key, :sequence_number, :event_id, :schema_version, :occurred_at_utc, :action, :risk_level, :outcome, :reason_code, '
            . ':real_actor_reference, :effective_actor_reference, :affected_subject_reference, :correlation_id, :request_id, :case_reference, '
            . ':resource_type, :resource_reference, :metadata_json, :previous_hash, :event_hash, :created_at_utc)'
        );
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $statement->execute([
            'stream_key' => $event->streamKey(),
            'sequence_number' => $event->sequenceNumber(),
            'event_id' => $event->eventId(),
            'schema_version' => $event->schemaVersion(),
            'occurred_at_utc' => str_replace('T', ' ', rtrim($event->occurredAtUtc(), 'Z')),
            'action' => $event->action(),
            'risk_level' => $event->riskLevel(),
            'outcome' => $event->outcome(),
            'reason_code' => $event->reasonCode(),
            'real_actor_reference' => $event->realActorReference(),
            'effective_actor_reference' => $event->effectiveActorReference(),
            'affected_subject_reference' => $event->affectedSubjectReference(),
            'correlation_id' => $event->correlationId(),
            'request_id' => $event->requestId(),
            'case_reference' => $event->caseReference(),
            'resource_type' => $event->resourceType(),
            'resource_reference' => $event->resourceReference(),
            'metadata_json' => json_encode($event->metadata(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'previous_hash' => $event->previousHash(),
            'event_hash' => $event->eventHash(),
            'created_at_utc' => $now,
        ]);
    }

    private static function selectColumns(): string
    {
        return 'SELECT stream_key, sequence_number, event_id, schema_version, occurred_at_utc, action, risk_level, outcome, reason_code, real_actor_reference, effective_actor_reference, affected_subject_reference, correlation_id, request_id, case_reference, resource_type, resource_reference, metadata_json, previous_hash, event_hash FROM platform_audit_events';
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): AuditEventEnvelope
    {
        $metadata = json_decode((string) $row['metadata_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($metadata)) throw new \RuntimeException('audit_metadata_invalid');
        $event = new AuditEventReference((string) $row['action'], (string) $row['risk_level'], self::actor($row['real_actor_reference'] ?? null), self::actor($row['effective_actor_reference'] ?? null), self::subject($row['affected_subject_reference'] ?? null), (string) $row['correlation_id'], (string) $row['request_id'], (string) $row['outcome'], $metadata);
        $occurredAt = str_replace(' ', 'T', (string) $row['occurred_at_utc']);
        if (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $occurredAt) !== 1) $occurredAt .= 'Z';
        return new AuditEventEnvelope((string) $row['schema_version'], (string) $row['event_id'], (string) $row['stream_key'], (int) $row['sequence_number'], $occurredAt, $event, self::nullable($row['reason_code'] ?? null), self::nullable($row['case_reference'] ?? null), self::nullable($row['resource_type'] ?? null), self::nullable($row['resource_reference'] ?? null), (string) $row['previous_hash'], self::nullable($row['event_hash'] ?? null));
    }

    private static function actor(mixed $value): ?ActorReference
    {
        return self::reference($value, static fn (string $kind, string $id): ActorReference => new ActorReference($kind, $id));
    }
    private static function subject(mixed $value): ?SubjectReference
    {
        return self::reference($value, static fn (string $kind, string $id): SubjectReference => new SubjectReference($kind, $id));
    }
    private static function reference(mixed $value, callable $factory): mixed
    {
        if ($value === null || $value === '') return null;
        $parts = explode(':', (string) $value, 2);
        if (count($parts) !== 2) throw new \RuntimeException('audit_reference_invalid');
        return $factory($parts[0], $parts[1]);
    }
    private static function nullable(mixed $value): ?string { return $value === null || $value === '' ? null : (string) $value; }
}
