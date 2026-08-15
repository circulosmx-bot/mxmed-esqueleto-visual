<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

/** Explicit minimized projections; raw persistence rows are never an API projection. */
final readonly class AuditReadProjection
{
    public const SELF_SECURITY = 'SELF_SECURITY';
    public const INTERNAL_SCOPED = 'INTERNAL_SCOPED';
    public const ADMIN_PRIVILEGED = 'ADMIN_PRIVILEGED';

    private const FIELDS = [
        self::SELF_SECURITY => ['occurred_at', 'event_type', 'severity', 'result', 'reason_code', 'source_module', 'target_type'],
        self::INTERNAL_SCOPED => ['event_id', 'occurred_at', 'event_type', 'severity', 'result', 'reason_code', 'source_module', 'target_type', 'target_id', 'request_id', 'correlation_id', 'retention_class'],
        self::ADMIN_PRIVILEGED => ['event_id', 'occurred_at', 'event_type', 'severity', 'result', 'reason_code', 'real_actor_type', 'real_actor_id', 'effective_entity_type', 'effective_entity_id', 'source_module', 'source_route', 'target_type', 'target_id', 'request_id', 'correlation_id', 'retention_class'],
    ];

    private function __construct(public string $name) {}

    public static function named(string $name): self
    {
        if (!array_key_exists($name, self::FIELDS)) {
            throw new \InvalidArgumentException('unknown_audit_read_projection');
        }
        return new self($name);
    }

    public function assertCompatible(string $capability): void
    {
        AuditReadAccess::assertCapability($capability);
        $allowed = match ($capability) {
            AuditReadAccess::SELF_SECURITY => [self::SELF_SECURITY],
            AuditReadAccess::INTERNAL_SCOPED => [self::INTERNAL_SCOPED],
            AuditReadAccess::ADMIN_PRIVILEGED => [self::INTERNAL_SCOPED, self::ADMIN_PRIVILEGED],
        };
        if (!in_array($this->name, $allowed, true)) {
            throw new \InvalidArgumentException('audit_read_projection_not_authorized');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public function project(array $row): array
    {
        $result = [];
        foreach (self::FIELDS[$this->name] as $field) {
            if (!array_key_exists($field, $row)) {
                throw new \UnexpectedValueException('audit_read_projection_field_missing:' . $field);
            }
            $result[$field] = $row[$field];
        }
        return $result;
    }

    /** @return list<string> */
    public function fields(): array { return self::FIELDS[$this->name]; }
}
