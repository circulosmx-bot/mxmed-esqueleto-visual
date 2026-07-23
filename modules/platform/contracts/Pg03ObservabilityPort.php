<?php
declare(strict_types=1);

namespace Platform\Contracts;

use InvalidArgumentException;

interface Pg03ObservabilityPort
{
    public function availability(): string;
    public function emitMetric(Pg03MetricReference $metric): string;
    public function appendAudit(Pg03AuditReference $event): string;
}

final readonly class Pg03MetricReference
{
    private string $metricName;
    private string $correlationReference;
    private array $dimensions;

    public function __construct(
        string $metricName,
        string $correlationReference,
        array $dimensions = []
    ) {
        $this->metricName = self::opaqueReference($metricName);
        $this->correlationReference = self::opaqueReference($correlationReference);
        $this->dimensions = self::safeDimensions($dimensions);
    }

    public function metricName(): string
    {
        return $this->metricName;
    }

    public function correlationReference(): string
    {
        return $this->correlationReference;
    }

    public function dimensions(): array
    {
        return $this->dimensions;
    }

    public function toArray(): array
    {
        return [
            'metric_name' => $this->metricName,
            'correlation_reference' => $this->correlationReference,
            'dimensions' => $this->dimensions,
        ];
    }

    private static function opaqueReference(string $value): string
    {
        if ($value === '' || preg_match('/\A[A-Za-z0-9._:-]+\z/', $value) !== 1) {
            throw new InvalidArgumentException('unsafe_observability_reference');
        }
        return $value;
    }

    private static function safeDimensions(array $dimensions): array
    {
        $forbidden = [
            'name',
            'phone',
            'email',
            'address',
            'patient',
            'doctor',
            'appointment',
            'contact',
            'payload',
            'clinical',
        ];
        foreach ($dimensions as $key => $value) {
            if (!is_string($key) || $key === '' || preg_match('/\A[A-Za-z0-9._:-]+\z/', $key) !== 1) {
                throw new InvalidArgumentException('unsafe_observability_dimension');
            }
            $normalizedKey = strtolower($key);
            foreach ($forbidden as $fragment) {
                if (str_contains($normalizedKey, $fragment)) {
                    throw new InvalidArgumentException('pii_observability_dimension');
                }
            }
            if ((!is_string($value) && !is_bool($value)) || (is_string($value) && preg_match('/\A[A-Za-z0-9._:-]+\z/', $value) !== 1)) {
                throw new InvalidArgumentException('unsafe_observability_dimension');
            }
        }
        return $dimensions;
    }
}

final readonly class Pg03AuditReference
{
    private string $eventClass;
    private string $correlationReference;
    private string $outcomeCode;

    public function __construct(
        string $eventClass,
        string $correlationReference,
        string $outcomeCode
    ) {
        if (!in_array($eventClass, ['authority_audit', 'write_audit'], true)) {
            throw new InvalidArgumentException('unknown_audit_event_class');
        }
        foreach ([$correlationReference, $outcomeCode] as $value) {
            if ($value === '' || preg_match('/\A[A-Za-z0-9._:-]+\z/', $value) !== 1) {
                throw new InvalidArgumentException('unsafe_audit_reference');
            }
        }
        $this->eventClass = $eventClass;
        $this->correlationReference = $correlationReference;
        $this->outcomeCode = $outcomeCode;
    }

    public function eventClass(): string
    {
        return $this->eventClass;
    }

    public function correlationReference(): string
    {
        return $this->correlationReference;
    }

    public function outcomeCode(): string
    {
        return $this->outcomeCode;
    }

    public function toArray(): array
    {
        return [
            'event_class' => $this->eventClass,
            'correlation_reference' => $this->correlationReference,
            'outcome_code' => $this->outcomeCode,
        ];
    }
}

final class RejectingPg03ObservabilityPort implements Pg03ObservabilityPort
{
    public function availability(): string
    {
        return 'unavailable';
    }

    public function emitMetric(Pg03MetricReference $metric): string
    {
        return 'metric_sink_not_configured';
    }

    public function appendAudit(Pg03AuditReference $event): string
    {
        return 'audit_sink_not_configured';
    }
}

final readonly class Pg03ObservabilityDecision
{
    public function __construct(
        private bool $proceed,
        private bool $alertRequired,
        private string $reason
    ) {
    }

    public function proceed(): bool
    {
        return $this->proceed;
    }

    public function alertRequired(): bool
    {
        return $this->alertRequired;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return [
            'proceed' => $this->proceed,
            'alert_required' => $this->alertRequired,
            'reason' => $this->reason,
        ];
    }
}

final class Pg03ObservabilityFailurePolicy
{
    public function evaluate(
        string $operationClass,
        string $availability
    ): Pg03ObservabilityDecision {
        if (!in_array($operationClass, ['secondary_metric', 'authority_audit', 'write_audit'], true)) {
            return new Pg03ObservabilityDecision(false, true, 'unknown_observability_operation');
        }
        if ($availability === 'available') {
            return new Pg03ObservabilityDecision(true, false, 'observability_available');
        }
        if ($operationClass === 'secondary_metric') {
            return new Pg03ObservabilityDecision(true, true, 'metric_unavailable_fail_open');
        }
        return new Pg03ObservabilityDecision(false, true, 'audit_unavailable_fail_closed');
    }
}
