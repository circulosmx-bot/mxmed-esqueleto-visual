<?php
declare(strict_types=1);

require_once __DIR__ . '/../contracts/Pg03ObservabilityPort.php';

use Platform\Contracts\Pg03AuditReference;
use Platform\Contracts\Pg03MetricReference;
use Platform\Contracts\Pg03ObservabilityDecision;
use Platform\Contracts\Pg03ObservabilityFailurePolicy;
use Platform\Contracts\Pg03ObservabilityPort;
use Platform\Contracts\RejectingPg03ObservabilityPort;

function cut01dObservabilityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut01dObservabilityThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

function cut01dObservabilityPublicMethods(string $class): array
{
    $methods = array_map(
        static fn(ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );
    $methods = array_values(array_filter($methods, static fn(string $method): bool => $method !== '__construct'));
    sort($methods, SORT_STRING);
    return $methods;
}

function cut01dObservabilitySource(string $path): string
{
    $lines = file($path);
    cut01dObservabilityAssert(is_array($lines), 'observability source readable');
    return implode('', $lines);
}

cut01dObservabilityAssert(cut01dObservabilityPublicMethods(Pg03ObservabilityPort::class) === [
    'appendAudit',
    'availability',
    'emitMetric',
], 'observability port API exact');
cut01dObservabilityAssert(cut01dObservabilityPublicMethods(Pg03MetricReference::class) === [
    'correlationReference',
    'dimensions',
    'metricName',
    'toArray',
], 'metric reference API exact');
cut01dObservabilityAssert(cut01dObservabilityPublicMethods(Pg03AuditReference::class) === [
    'correlationReference',
    'eventClass',
    'outcomeCode',
    'toArray',
], 'audit reference API exact');
cut01dObservabilityAssert(cut01dObservabilityPublicMethods(Pg03ObservabilityDecision::class) === [
    'alertRequired',
    'proceed',
    'reason',
    'toArray',
], 'decision API exact');
cut01dObservabilityAssert(cut01dObservabilityPublicMethods(Pg03ObservabilityFailurePolicy::class) === [
    'evaluate',
], 'failure policy API exact');
cut01dObservabilityAssert((new ReflectionClass(Pg03MetricReference::class))->isReadOnly(), 'metric reference readonly');
cut01dObservabilityAssert((new ReflectionClass(Pg03AuditReference::class))->isReadOnly(), 'audit reference readonly');
cut01dObservabilityAssert((new ReflectionClass(Pg03ObservabilityDecision::class))->isReadOnly(), 'decision readonly');

$metric = new Pg03MetricReference('write_outcome', 'correlation:opaque-01', [
    'operation_class' => 'secondary_metric',
    'success' => true,
]);
cut01dObservabilityAssert($metric->toArray() === [
    'metric_name' => 'write_outcome',
    'correlation_reference' => 'correlation:opaque-01',
    'dimensions' => ['operation_class' => 'secondary_metric', 'success' => true],
], 'metric serialization exact');
foreach (['name', 'phone', 'email', 'address', 'patient', 'doctor', 'appointment', 'contact', 'payload', 'clinical'] as $key) {
    cut01dObservabilityThrows(
        static fn() => new Pg03MetricReference('write_outcome', 'correlation:opaque-01', [$key => 'opaque']),
        'sensitive dimension rejected'
    );
}
foreach (['unsafe reference', 'https://example.test/value', 'value?query=1', 'value/path'] as $unsafe) {
    cut01dObservabilityThrows(
        static fn() => new Pg03MetricReference('write_outcome', $unsafe),
        'unsafe correlation rejected'
    );
}
cut01dObservabilityThrows(
    static fn() => new Pg03MetricReference('write_outcome', 'correlation:opaque-01', ['limit' => 10]),
    'numeric dimension rejected'
);

$audit = new Pg03AuditReference('authority_audit', 'correlation:opaque-02', 'denied');
cut01dObservabilityAssert($audit->toArray() === [
    'event_class' => 'authority_audit',
    'correlation_reference' => 'correlation:opaque-02',
    'outcome_code' => 'denied',
], 'audit serialization exact');
cut01dObservabilityThrows(
    static fn() => new Pg03AuditReference('unknown', 'correlation:opaque-02', 'denied'),
    'unknown audit class rejected'
);

$port = new RejectingPg03ObservabilityPort();
cut01dObservabilityAssert($port->availability() === 'unavailable', 'rejecting port unavailable');
cut01dObservabilityAssert($port->emitMetric($metric) === 'metric_sink_not_configured', 'metric emission rejected');
cut01dObservabilityAssert($port->appendAudit($audit) === 'audit_sink_not_configured', 'audit append rejected');

$policy = new Pg03ObservabilityFailurePolicy();
$expected = [
    ['secondary_metric', 'available', true, false, 'observability_available'],
    ['authority_audit', 'available', true, false, 'observability_available'],
    ['write_audit', 'available', true, false, 'observability_available'],
    ['secondary_metric', 'unavailable', true, true, 'metric_unavailable_fail_open'],
    ['authority_audit', 'unavailable', false, true, 'audit_unavailable_fail_closed'],
    ['write_audit', 'unavailable', false, true, 'audit_unavailable_fail_closed'],
    ['unknown', 'available', false, true, 'unknown_observability_operation'],
    ['unknown', 'unavailable', false, true, 'unknown_observability_operation'],
];
foreach ($expected as [$operation, $availability, $proceed, $alert, $reason]) {
    $decision = $policy->evaluate($operation, $availability);
    cut01dObservabilityAssert($decision->toArray() === [
        'proceed' => $proceed,
        'alert_required' => $alert,
        'reason' => $reason,
    ], 'failure policy row exact');
}

$source = cut01dObservabilitySource(dirname(__DIR__, 3) . '/modules/platform/contracts/Pg03ObservabilityPort.php');
foreach ([
    'file_' . 'put_contents',
    'f' . 'open(',
    'curl' . '_',
    'error_' . 'log',
    'new ' . 'P' . 'DO',
    'mysqli',
    'header' . '(',
] as $forbidden) {
    cut01dObservabilityAssert(!str_contains($source, $forbidden), 'observability port has zero executable side effects');
}

echo "Cut01DObservabilityFailurePolicyTest PASS\n";
