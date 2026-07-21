<?php
declare(strict_types=1);

namespace Platform\Contracts;

use DateTimeImmutable;
use DateTimeZone;

/** Immutable, minimized persistence representation of an audit reference. */
final readonly class AuditEventEnvelope
{
    public const SCHEMA_VERSION = 'audit.v1';
    public const GENESIS_VERSION = 'audit-genesis-v1';
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /** @var list<string> */
    private const ALLOWED_METADATA_KEYS = [
        'resource_type',
        'resource_reference',
        'decision',
        'reason_code',
        'authorization_plane',
        'case_reference',
        'source_reference',
        'policy_reference',
        'disposition_mode',
        'audit_category',
    ];

    private string $schemaVersion;
    private string $eventId;
    private string $streamKey;
    private int $sequenceNumber;
    private string $occurredAtUtc;
    private AuditEventReference $event;
    private ?string $reasonCode;
    private ?string $realActorReference;
    private ?string $effectiveActorReference;
    private ?string $affectedSubjectReference;
    private string $correlationId;
    private string $requestId;
    private ?string $caseReference;
    private ?string $resourceType;
    private ?string $resourceReference;
    /** @var array<string,string|int|bool|null> */
    private array $metadata;
    private string $previousHash;
    private ?string $eventHash;

    public function __construct(
        string $schemaVersion,
        string $eventId,
        string $streamKey,
        int $sequenceNumber,
        string $occurredAtUtc,
        AuditEventReference $event,
        ?string $reasonCode = null,
        ?string $caseReference = null,
        ?string $resourceType = null,
        ?string $resourceReference = null,
        string $previousHash = self::GENESIS_HASH,
        ?string $eventHash = null
    ) {
        $this->schemaVersion = (new SafeIdentifier($schemaVersion))->value();
        if (preg_match('/^[a-f0-9]{64}$/i', $eventId) !== 1) throw new \InvalidArgumentException('invalid_audit_event_id');
        $this->eventId = strtolower($eventId);
        $this->streamKey = (new SafeIdentifier($streamKey))->value();
        if ($sequenceNumber < 1) throw new \InvalidArgumentException('invalid_audit_sequence');
        $this->sequenceNumber = $sequenceNumber;
        $this->occurredAtUtc = self::normalizeUtc($occurredAtUtc);
        $this->event = $event;
        $this->reasonCode = self::optionalIdentifier($reasonCode);
        $this->realActorReference = self::actorReference($event->realActor());
        $this->effectiveActorReference = self::actorReference($event->effectiveActor());
        $this->affectedSubjectReference = self::subjectReference($event->affectedSubject());
        $this->correlationId = self::requiredIdentifier($event->correlationId(), 'correlation_id_required');
        $this->requestId = self::requiredIdentifier($event->requestId(), 'request_id_required');
        $this->caseReference = self::optionalIdentifier($caseReference ?? self::metadataString($event->metadata(), 'case_reference'));
        $this->resourceType = self::optionalIdentifier($resourceType ?? self::metadataString($event->metadata(), 'resource_type'));
        $this->resourceReference = self::optionalIdentifier($resourceReference ?? self::metadataString($event->metadata(), 'resource_reference'));
        $this->metadata = self::assertMetadata($event->metadata());
        $this->previousHash = self::assertHash($previousHash, 'invalid_previous_hash');
        $this->eventHash = $eventHash === null ? null : self::assertHash($eventHash, 'invalid_event_hash');
    }

    /** @return list<string> */
    public static function allowedMetadataKeys(): array { return self::ALLOWED_METADATA_KEYS; }
    public function schemaVersion(): string { return $this->schemaVersion; }
    public function eventId(): string { return $this->eventId; }
    public function streamKey(): string { return $this->streamKey; }
    public function sequenceNumber(): int { return $this->sequenceNumber; }
    public function occurredAtUtc(): string { return $this->occurredAtUtc; }
    public function event(): AuditEventReference { return $this->event; }
    public function action(): string { return $this->event->eventName(); }
    public function riskLevel(): string { return $this->event->riskLevel(); }
    public function outcome(): string { return $this->event->result(); }
    public function reasonCode(): ?string { return $this->reasonCode; }
    public function realActorReference(): ?string { return $this->realActorReference; }
    public function effectiveActorReference(): ?string { return $this->effectiveActorReference; }
    public function affectedSubjectReference(): ?string { return $this->affectedSubjectReference; }
    public function correlationId(): string { return $this->correlationId; }
    public function requestId(): string { return $this->requestId; }
    public function caseReference(): ?string { return $this->caseReference; }
    public function resourceType(): ?string { return $this->resourceType; }
    public function resourceReference(): ?string { return $this->resourceReference; }
    /** @return array<string,string|int|bool|null> */
    public function metadata(): array { return $this->metadata; }
    public function previousHash(): string { return $this->previousHash; }
    public function eventHash(): ?string { return $this->eventHash; }

    /** @return array<string,mixed> */
    public function contentArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'event_id' => $this->eventId,
            'stream_key' => $this->streamKey,
            'sequence_number' => $this->sequenceNumber,
            'occurred_at_utc' => $this->occurredAtUtc,
            'action' => $this->action(),
            'risk_level' => $this->riskLevel(),
            'outcome' => $this->outcome(),
            'reason_code' => $this->reasonCode,
            'real_actor_reference' => $this->realActorReference,
            'effective_actor_reference' => $this->effectiveActorReference,
            'affected_subject_reference' => $this->affectedSubjectReference,
            'correlation_id' => $this->correlationId,
            'request_id' => $this->requestId,
            'case_reference' => $this->caseReference,
            'resource_type' => $this->resourceType,
            'resource_reference' => $this->resourceReference,
            'metadata' => $this->metadata,
        ];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [...$this->contentArray(), 'previous_hash' => $this->previousHash, 'event_hash' => $this->eventHash];
    }

    public function withIntegrity(string $previousHash, string $eventHash): self
    {
        return new self($this->schemaVersion, $this->eventId, $this->streamKey, $this->sequenceNumber, $this->occurredAtUtc, $this->event, $this->reasonCode, $this->caseReference, $this->resourceType, $this->resourceReference, $previousHash, $eventHash);
    }

    /** @param array<string,mixed> $metadata @return array<string,string|int|bool|null> */
    private static function assertMetadata(array $metadata): array
    {
        $clean = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key) || !in_array($key, self::ALLOWED_METADATA_KEYS, true)) throw new \InvalidArgumentException('audit_metadata_not_sanitized');
            if (!is_string($value) && !is_int($value) && !is_bool($value) && $value !== null) throw new \InvalidArgumentException('audit_metadata_not_sanitized');
            if (is_string($value) && (strlen($value) > 256 || preg_match('/[\r\n\x00-\x1F\x7F]/', $value) === 1)) throw new \InvalidArgumentException('audit_metadata_not_sanitized');
            $clean[$key] = $value;
        }
        ksort($clean, SORT_STRING);
        return $clean;
    }
    private static function requiredIdentifier(?string $value, string $error): string
    {
        if ($value === null || trim($value) === '') throw new \InvalidArgumentException($error);
        return (new SafeIdentifier($value))->value();
    }
    private static function optionalIdentifier(?string $value): ?string { return $value === null ? null : (new SafeIdentifier($value))->value(); }
    private static function metadataString(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? null;
        return is_string($value) ? $value : null;
    }
    private static function actorReference(?ActorReference $actor): ?string { return $actor === null ? null : $actor->kind() . ':' . $actor->id(); }
    private static function subjectReference(?SubjectReference $subject): ?string { return $subject === null ? null : $subject->kind() . ':' . $subject->id(); }
    private static function assertHash(string $value, string $error): string
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $value) !== 1) throw new \InvalidArgumentException($error);
        return strtolower($value);
    }
    private static function normalizeUtc(string $value): string
    {
        if (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $value) !== 1) throw new \InvalidArgumentException('audit_timestamp_must_be_utc');
        try { $date = new DateTimeImmutable($value); } catch (\Throwable) { throw new \InvalidArgumentException('invalid_audit_timestamp'); }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
