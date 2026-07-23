<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../../patients/identity/*.php') as $file) {
    require_once $file;
}
require_once __DIR__ . '/../../patients/identity/adapters/CanonicalPatientIdentityAdapter.php';
require_once __DIR__ . '/../../patients/identity/persistence/PdoPatientIdentityPersistenceAdapter.php';
require_once __DIR__ . '/../../platform/contracts/Pg03CutoverFeatureFlagPort.php';

use Patients\Identity\Adapters\CanonicalPatientIdentityAdapter;
use Patients\Identity\CanonicalPatientId;
use Patients\Identity\PatientIdentityCandidate;
use Patients\Identity\PatientIdentityCandidateSet;
use Patients\Identity\PatientIdentityEvidence;
use Patients\Identity\PatientIdentityMutationPlan;
use Patients\Identity\PatientIdentityResolutionRequest;
use Patients\Identity\Persistence\PatientIdentityPersistencePort;
use Patients\Identity\Persistence\PdoPatientIdentityPersistenceAdapter;
use Platform\Contracts\ClosedPg03CutoverFeatureFlagRegistry;

function cut01dRollbackAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut01dRollbackPublicMethods(string $class): array
{
    $methods = array_map(
        static fn(ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );
    $methods = array_values(array_filter($methods, static fn(string $method): bool => $method !== '__construct'));
    sort($methods, SORT_STRING);
    return $methods;
}

function cut01dRollbackOpaque(string $label): string
{
    return $label . '-' . hash('sha256', 'cut01d:' . $label);
}

function cut01dRollbackArgument(ReflectionParameter $parameter): mixed
{
    $type = $parameter->getType();
    if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
        return null;
    }
    return match ($type instanceof ReflectionNamedType ? $type->getName() : '') {
        'string' => 'opaque-reference',
        'array' => [],
        default => null,
    };
}

function cut01dRollbackSource(string $path): string
{
    $lines = file($path);
    cut01dRollbackAssert(is_array($lines), 'rollback source readable');
    return implode('', $lines);
}

$root = dirname(__DIR__, 3);
$config = require $root . '/modules/agenda/config/agenda.php';
$adapter = new CanonicalPatientIdentityAdapter();
cut01dRollbackAssert(cut01dRollbackPublicMethods(CanonicalPatientIdentityAdapter::class) === [
    'canonicalPatientIdentityEnabled',
    'mutationPlan',
    'readiness',
    'resolvePreview',
], 'patient adapter API exact');
foreach ([
    [],
    ['feature_flags' => []],
    ['feature_flags' => ['canonical_patient_identity' => null]],
    ['feature_flags' => ['canonical_patient_identity' => 'true']],
    ['feature_flags' => ['canonical_patient_identity' => 1]],
] as $fixture) {
    cut01dRollbackAssert(!CanonicalPatientIdentityAdapter::canonicalPatientIdentityEnabled($fixture), 'patient flag fails closed');
}
cut01dRollbackAssert(
    CanonicalPatientIdentityAdapter::canonicalPatientIdentityEnabled(
        ['feature_flags' => ['canonical_patient_identity' => true]]
    ),
    'literal true detected'
);

$identity = new PatientIdentityEvidence(hash('sha256', 'name'));
$patientId = new CanonicalPatientId('p_cut01d');
$candidateSet = new PatientIdentityCandidateSet([
    new PatientIdentityCandidate($patientId, $identity, 1, true),
]);
$request = new PatientIdentityResolutionRequest(
    cut01dRollbackOpaque('operation'),
    cut01dRollbackOpaque('correlation'),
    'private_authenticated',
    'canonical_patient_id',
    $patientId,
    null,
    null,
    cut01dRollbackOpaque('account'),
    cut01dRollbackOpaque('operator'),
    '2026-07-23T10:00:00-06:00'
);
$decision = $adapter->resolvePreview($request, $candidateSet);
cut01dRollbackAssert($decision->status() === 'already_canonical', 'preview delegates to resolver');
cut01dRollbackAssert(!$decision->mutationAllowed() && !$decision->mergeAllowed(), 'preview remains pure');
cut01dRollbackAssert($adapter->mutationPlan() === (new PatientIdentityMutationPlan())->toArray(), 'patient mutation plan delegated');
cut01dRollbackAssert($adapter->mutationPlan()['executes_operations'] === false, 'patient plan executes nothing');

$registry = new ClosedPg03CutoverFeatureFlagRegistry($config);
cut01dRollbackAssert($adapter->readiness($registry) === [
    'mode' => 'dormant_preview_only',
    'feature_configured' => false,
    'feature_effective' => false,
    'persistence_configured' => false,
    'writes_enabled' => false,
    'backfill_enabled' => false,
    'activation_authorized' => false,
    'runtime_wiring' => false,
    'ready' => false,
], 'patient readiness exact');

$persistence = new PdoPatientIdentityPersistenceAdapter();
cut01dRollbackAssert($persistence instanceof PatientIdentityPersistencePort, 'placeholder implements existing port');
cut01dRollbackAssert(!$persistence->configured(), 'placeholder not configured');
cut01dRollbackAssert($persistence->readiness() === [
    'mode' => 'rejecting_placeholder',
    'configured' => false,
    'activation_authorized' => false,
    'writes_enabled' => false,
    'backfill_enabled' => false,
    'database_connections_opened' => 0,
    'sql_executed' => 0,
    'ready' => false,
], 'persistence readiness exact');

$interface = new ReflectionClass(PatientIdentityPersistencePort::class);
$implementation = new ReflectionClass(PdoPatientIdentityPersistenceAdapter::class);
foreach ($interface->getMethods() as $method) {
    cut01dRollbackAssert($implementation->hasMethod($method->getName()), 'persistence signature implemented');
    $arguments = array_map('cut01dRollbackArgument', $method->getParameters());
    try {
        $implementation->getMethod($method->getName())->invokeArgs($persistence, $arguments);
    } catch (ReflectionException $error) {
        throw new RuntimeException('persistence method invocation failed', 0, $error);
    } catch (RuntimeException $error) {
        cut01dRollbackAssert($error->getMessage() === 'patient_identity_persistence_not_configured', 'persistence rejects exact');
        continue;
    }
    throw new RuntimeException('persistence operation did not reject');
}

cut01dRollbackAssert($registry->readiness()['all_effective_disabled'] === true, 'eleven flags effective disabled');
cut01dRollbackAssert($registry->readiness()['mode'] === 'r0_registry_only', 'rollout registry disabled');

$persistenceSource = cut01dRollbackSource(
    $root . '/modules/patients/identity/persistence/PdoPatientIdentityPersistenceAdapter.php'
);
foreach ([
    'new ' . 'P' . 'DO',
    'mxmed_' . 'pdo',
    'CREATE ' . 'TABLE',
    'ALTER ' . 'TABLE',
    'INSERT ' . 'INTO',
    'DELETE ' . 'FROM',
] as $forbidden) {
    cut01dRollbackAssert(!str_contains($persistenceSource, $forbidden), 'placeholder contains no executable persistence dependency');
}
$document = cut01dRollbackSource($root . '/docs/MXMED_IMPLEMENTACION_V2_PG03_CUT01_D.md');
cut01dRollbackAssert(str_contains($document, 'git revert --no-commit <ACTIVITY14_COMMIT>'), 'safe return documented');
foreach (['sin reset', 'rebase', 'amend', 'force-push', 'MIGRATIONS_CREATED=0', 'MIGRATIONS_APPLIED=0'] as $constraint) {
    cut01dRollbackAssert(str_contains($document, $constraint), 'rollback constraint documented');
}
cut01dRollbackAssert(str_contains($document, 'CLINICAL_REQUESTS_EXECUTED=0'), 'no Clinical runtime documented');

echo "Cut01DRollbackContractTest PASS\n";
