<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\AuditEventEnvelope;
use Platform\Contracts\AuditEventReference;

/** Pure deterministic JSON canonicalization; PHP serialization is intentionally absent. */
final class AuditEventCanonicalizer
{
    public function __construct(private readonly AuditEventSanitizer $sanitizer = new AuditEventSanitizer()) {}

    public function canonicalizeReference(AuditEventReference $event): string
    {
        $event = $this->sanitizer->sanitize($event);
        return $this->encode([
            'action' => $event->eventName(),
            'risk_level' => $event->riskLevel(),
            'outcome' => $event->result(),
            'real_actor_reference' => self::actorReference($event->realActor()),
            'effective_actor_reference' => self::actorReference($event->effectiveActor()),
            'affected_subject_reference' => self::subjectReference($event->affectedSubject()),
            'correlation_id' => $event->correlationId(),
            'request_id' => $event->requestId(),
            'metadata' => $event->metadata(),
        ]);
    }

    public function canonicalizeEnvelope(AuditEventEnvelope $envelope): string
    {
        return $this->encode($envelope->contentArray());
    }

    public function streamKey(AuditEventReference $event): string
    {
        $event = $this->sanitizer->sanitize($event);
        $metadata = $event->metadata();
        if (isset($metadata['resource_type'], $metadata['resource_reference'])) return (string) $metadata['resource_type'] . ':' . (string) $metadata['resource_reference'];
        if ($event->affectedSubject() !== null) return 'subject:' . $event->affectedSubject()->kind() . ':' . $event->affectedSubject()->id();
        return 'action:' . $event->eventName();
    }

    /** Stable id uses only the minimized logical event fingerprint fields. */
    public function eventId(AuditEventReference $event, string $streamKey, string $schemaVersion = AuditEventEnvelope::SCHEMA_VERSION): string
    {
        $event = $this->sanitizer->sanitize($event);
        return hash('sha256', $this->encode([
            'schema_version' => $schemaVersion,
            'stream_key' => $streamKey,
            'correlation_id' => $event->correlationId(),
            'request_id' => $event->requestId(),
            'action' => $event->eventName(),
            'risk_level' => $event->riskLevel(),
            'outcome' => $event->result(),
            'affected_subject_reference' => self::subjectReference($event->affectedSubject()),
            'resource_type' => $event->metadata()['resource_type'] ?? null,
            'resource_reference' => $event->metadata()['resource_reference'] ?? null,
        ]));
    }

    /** Stable full content fingerprint used to compare idempotent duplicates. */
    public function eventFingerprint(AuditEventReference $event, string $streamKey, string $schemaVersion = AuditEventEnvelope::SCHEMA_VERSION): string
    {
        return hash('sha256', $this->encode(['schema_version' => $schemaVersion, 'stream_key' => $streamKey, 'event' => json_decode($this->canonicalizeReference($event), true, 512, JSON_THROW_ON_ERROR)]));
    }

    /** @param array<string,mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
    private static function actorReference(?\Platform\Contracts\ActorReference $actor): ?string { return $actor === null ? null : $actor->kind() . ':' . $actor->id(); }
    private static function subjectReference(?\Platform\Contracts\SubjectReference $subject): ?string { return $subject === null ? null : $subject->kind() . ':' . $subject->id(); }
}
