<?php
declare(strict_types=1);

require_once __DIR__ . '/../adapters/CanonicalAppointmentLifecycleAdapter.php';
require_once __DIR__ . '/../../platform/contracts/Pg03CutoverFeatureFlagPort.php';
require_once __DIR__ . '/../../platform/contracts/Pg03ObservabilityPort.php';

use Agenda\Adapters\CanonicalAppointmentLifecycleAdapter;
use Agenda\Appointments\AppointmentMutationPlan;
use Platform\Contracts\ClosedPg03CutoverFeatureFlagRegistry;
use Platform\Contracts\RejectingPg03ObservabilityPort;

function cut01dClinicalAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut01dClinicalThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

function cut01dClinicalPublicMethods(string $class): array
{
    $methods = array_map(
        static fn(ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );
    $methods = array_values(array_filter($methods, static fn(string $method): bool => $method !== '__construct'));
    sort($methods, SORT_STRING);
    return $methods;
}

function cut01dClinicalSource(string $path): string
{
    $lines = file($path);
    cut01dClinicalAssert(is_array($lines), 'source readable');
    return implode('', $lines);
}

function cut01dClinicalDirectoryDigest(string $root, array $directories): string
{
    $manifest = [];
    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $digest = hash_file('sha256', $item->getPathname());
            cut01dClinicalAssert(is_string($digest), 'protected directory file readable');
            $manifest[$relative] = $digest;
        }
    }
    ksort($manifest, SORT_STRING);
    $serialized = '';
    foreach ($manifest as $path => $digest) {
        $serialized .= $digest . '  ' . $path . "\n";
    }
    return hash('sha256', $serialized);
}

$root = dirname(__DIR__, 3);
$config = require $root . '/modules/agenda/config/agenda.php';
$adapter = new CanonicalAppointmentLifecycleAdapter();
cut01dClinicalAssert(cut01dClinicalPublicMethods(CanonicalAppointmentLifecycleAdapter::class) === [
    'canonicalAppointmentLifecycleEnabled',
    'clinicalBoundary',
    'mutationPlan',
    'readiness',
], 'lifecycle adapter API exact');
foreach ([
    [],
    ['feature_flags' => []],
    ['feature_flags' => ['canonical_appointment_lifecycle' => null]],
    ['feature_flags' => ['canonical_appointment_lifecycle' => 'true']],
    ['feature_flags' => ['canonical_appointment_lifecycle' => 1]],
] as $fixture) {
    cut01dClinicalAssert(!CanonicalAppointmentLifecycleAdapter::canonicalAppointmentLifecycleEnabled($fixture), 'lifecycle flag fails closed');
}
cut01dClinicalAssert(
    CanonicalAppointmentLifecycleAdapter::canonicalAppointmentLifecycleEnabled(
        ['feature_flags' => ['canonical_appointment_lifecycle' => true]]
    ),
    'literal true detected'
);
cut01dClinicalAssert($adapter->mutationPlan() === (new AppointmentMutationPlan())->toArray(), 'Gate 8D mutation plan delegated');
cut01dClinicalAssert($adapter->mutationPlan()['executes_operations'] === false, 'mutation plan executes nothing');

$reference = 'appointment-ref:opaque-14';
$boundary = $adapter->clinicalBoundary($reference);
cut01dClinicalAssert(array_keys($boundary) === [
    'appointment_reference_digest',
    'event_owner',
    'agenda_appointment_is_clinical_encounter',
    'clinical_event_schema',
    'clinical_retries',
    'clinical_dlq',
    'clinical_retention',
    'clinical_compensations',
    'outbox_implemented',
    'saga_implemented',
    'worker_implemented',
    'queue_implemented',
    'clinical_requests_executed',
], 'clinical boundary keys exact');
cut01dClinicalAssert(
    $boundary['appointment_reference_digest'] === 'sha256:' . hash('sha256', $reference),
    'opaque reference digest exact'
);
cut01dClinicalAssert(!str_contains(serialize($boundary), $reference), 'raw appointment reference absent');
cut01dClinicalAssert($boundary['event_owner'] === 'agenda', 'Agenda owns event');
cut01dClinicalAssert($boundary['agenda_appointment_is_clinical_encounter'] === false, 'appointment is not encounter');
foreach (['clinical_event_schema', 'clinical_retries', 'clinical_dlq', 'clinical_retention', 'clinical_compensations'] as $key) {
    cut01dClinicalAssert($boundary[$key] === 'UNRESOLVED_PENDING_PARAMETER_APPROVAL', 'clinical parameter unresolved');
}
foreach (['outbox_implemented', 'saga_implemented', 'worker_implemented', 'queue_implemented'] as $key) {
    cut01dClinicalAssert($boundary[$key] === false, 'asynchronous runtime absent');
}
cut01dClinicalAssert($boundary['clinical_requests_executed'] === 0, 'no clinical requests');
foreach (['has space', 'https://example.test', 'ref/path', 'ref?query=1'] as $unsafe) {
    cut01dClinicalThrows(static fn() => $adapter->clinicalBoundary($unsafe), 'unsafe appointment reference rejected');
}

$registry = new ClosedPg03CutoverFeatureFlagRegistry($config);
$readiness = $adapter->readiness($registry, new RejectingPg03ObservabilityPort());
cut01dClinicalAssert($readiness === [
    'mode' => 'dormant_harness_only',
    'feature_configured' => false,
    'feature_effective' => false,
    'observability_availability' => 'unavailable',
    'observability_sink_configured' => false,
    'clinical_parameters_approved' => false,
    'activation_authorized' => false,
    'runtime_wiring' => false,
    'ready' => false,
], 'lifecycle readiness exact');

$protected = [
    'modules/agenda/services/ClinicalEncounterBridge.php' => 'faa3dda72331a8e2402b63c16ea655f77c362eb21abc32e8767a242e989eee10',
    'modules/agenda/tests/Gate8DAppointmentLifecycleIdempotencyTest.php' => 'ae024a823c7654f55c7aa43ebdb736918244662890746163e8102224f8fc0279',
    'modules/patients/tests/Gate8GPatientIdentityPersistenceMigrationTest.php' => '4e4f47e17ae7a2cb67c1c6bc1a21cae6127189c333c850c2ca5ac84791e97261',
    'modules/platform/contracts/AuditTrailPort.php' => '96b422a0833f8b5865619586c076ff0fb75b96c6b879fb6b8c501a2ef91aa21c',
    'modules/patients/identity/persistence/PatientIdentityPersistencePort.php' => '4a1fd1b522d53bece58c3927a32cb3b18af84093e3992389ea88804824248fb9',
];
foreach ($protected as $path => $digest) {
    cut01dClinicalAssert(hash_file('sha256', $root . '/' . $path) === $digest, 'protected file stable');
}
cut01dClinicalAssert(
    cut01dClinicalDirectoryDigest($root, ['docs/clinical', 'modules/clinical'])
        === '20e37766e4a02408889cd6702ab00f573fe66bab2e40417816896b8f9c5db4aa',
    'Clinical directories byte equivalent'
);

$repositorySource = cut01dClinicalSource($root . '/modules/agenda/repositories/AppointmentWriteRepository.php');
cut01dClinicalAssert(substr_count($repositorySource, 'CanonicalAppointmentLifecycleAdapter::canonicalAppointmentLifecycleEnabled($config)') === 1, 'repository flag reference exact');
cut01dClinicalAssert(substr_count($repositorySource, 'CanonicalAppointmentLifecycleAdapter::class') === 1, 'repository class reference exact');
cut01dClinicalAssert(!str_contains($repositorySource, 'new CanonicalAppointmentLifecycleAdapter'), 'repository never instantiates adapter');
cut01dClinicalAssert(!str_contains($repositorySource, '->clinicalBoundary('), 'repository never calls clinical boundary');
cut01dClinicalAssert(!str_contains($repositorySource, '->mutationPlan('), 'repository never calls mutation plan');
cut01dClinicalAssert(!str_contains($repositorySource, '->readiness('), 'repository never calls readiness');

echo "Cut01DClinicalBoundaryHarnessTest PASS\n";
