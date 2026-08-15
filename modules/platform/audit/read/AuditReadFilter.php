<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

use DateTimeImmutable;
use DateTimeZone;
use Platform\Contracts\CanonicalAuditEventType;
use Platform\Contracts\CanonicalAuditReasonCode;
use Platform\Contracts\CanonicalAuditResult;
use Platform\Contracts\CanonicalAuditRetentionClass;
use Platform\Contracts\CanonicalAuditSeverity;
use Platform\Services\SourceModuleCatalog;

/** Validated filter map over physically persisted audit.v1 dimensions. */
final readonly class AuditReadFilter
{
    private const SUPPORTED = [
        'event_type', 'result', 'reason_code', 'source_module',
        'target_type', 'target_id', 'real_actor_type', 'real_actor_id',
        'effective_entity_type', 'effective_entity_id', 'request_id',
        'correlation_id', 'created_at_from', 'created_at_to', 'severity',
        'retention_class',
    ];

    /** @param array<string,string> $values */
    private function __construct(public array $values) {}

    /** @param array<string,mixed> $input */
    public static function fromArray(array $input, SourceModuleCatalog $sourceModules): self
    {
        $unknown = array_diff(array_keys($input), self::SUPPORTED);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('unknown_audit_read_filter');
        }
        $values = [];
        foreach ($input as $key => $value) {
            if (!is_string($value) || $value === '' || $value !== trim($value) || strlen($value) > 160) {
                throw new \InvalidArgumentException('invalid_audit_read_filter_value');
            }
            self::assertValue($key, $value, $sourceModules);
            $values[$key] = $value;
        }
        ksort($values, SORT_STRING);
        if (isset($values['created_at_from'], $values['created_at_to']) && $values['created_at_from'] > $values['created_at_to']) {
            throw new \InvalidArgumentException('invalid_audit_read_time_range');
        }
        return new self($values);
    }

    public function assertSafeForSelfTimeline(): void
    {
        foreach (['target_type', 'target_id', 'real_actor_type', 'real_actor_id', 'effective_entity_type', 'effective_entity_id'] as $override) {
            if (array_key_exists($override, $this->values)) {
                throw new \InvalidArgumentException('self_timeline_target_override_forbidden');
            }
        }
    }

    /** @return list<string> */
    public static function supported(): array { return self::SUPPORTED; }

    private static function assertValue(string $key, string $value, SourceModuleCatalog $modules): void
    {
        match ($key) {
            'event_type' => CanonicalAuditEventType::assertKnown($value),
            'result' => CanonicalAuditResult::assertKnown($value),
            'reason_code' => CanonicalAuditReasonCode::assertKnown($value),
            'severity' => CanonicalAuditSeverity::assertKnown($value),
            'retention_class' => CanonicalAuditRetentionClass::assertKnown($value),
            'source_module' => $modules->assertKnown($value),
            'created_at_from', 'created_at_to' => self::assertCanonicalUtc($value),
            default => self::assertIdentifier($value),
        };
    }

    private static function assertCanonicalUtc(string $value): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\TH:i:s.u\Z') !== $value) {
            throw new \InvalidArgumentException('invalid_audit_read_time');
        }
    }

    private static function assertIdentifier(string $value): void
    {
        if (preg_match('/^[A-Za-z0-9._:@\/-]+$/D', $value) !== 1
            || preg_match('/(?:password|credential|secret|bearer|otp|raw[_-]?token|magic[_-]?link)/i', $value) === 1) {
            throw new \InvalidArgumentException('unsafe_audit_read_identifier');
        }
    }
}
