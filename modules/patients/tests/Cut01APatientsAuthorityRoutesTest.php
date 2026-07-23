<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../../identity/contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../../platform/contracts/*.php') as $file) require_once $file;
require_once __DIR__ . '/../../platform/services/AuthorizationBoundary.php';
require_once __DIR__ . '/../composition/PatientsAuthorityCompositionRoot.php';

use Identity\Contracts\AccountMembership;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\CanonicalProfileReference;
use Identity\Contracts\MembershipRole;
use Identity\Contracts\MembershipStatus;
use Identity\Contracts\SessionId;
use Identity\Contracts\SessionPrincipal;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionState;
use Identity\Contracts\SessionTokenDigest;
use Patients\Composition\PatientsAuthorityCompositionRoot;
use Platform\Services\AuthorizationBoundary;

function cut01aPatientsAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function cut01aPatientsRead(string $path): string
{
    $source = file_get_contents($path);
    cut01aPatientsAssert(is_string($source), 'readable source required: ' . $path);
    return $source;
}

function cut01aPatientsIdentity(): AuthenticatedAccessContext
{
    $principal = new SessionPrincipal('acct-patients-cut01a', 1, AccountStatus::ACTIVE, '2026-07-22T11:00:00+00:00');
    $created = new DateTimeImmutable('2026-07-22T11:00:00+00:00');
    return new AuthenticatedAccessContext(
        $principal,
        new SessionRecord(
            new SessionId('session-patients-cut01a-0000000000000'),
            new SessionTokenDigest(str_repeat('b', 64)),
            $principal,
            $created,
            $created,
            $created->modify('+1 hour'),
            $created->modify('+12 hours'),
            SessionState::ACTIVE
        )
    );
}

function cut01aPatientsMembership(string $doctorId = 'doctor-patients-cut01a'): AccountMembership
{
    return new AccountMembership(
        'membership-patients-cut01a',
        'acct-patients-cut01a',
        CanonicalProfileReference::profile($doctorId),
        MembershipRole::OWNER,
        'profile',
        MembershipStatus::ACTIVE,
        'backend_resolver'
    );
}

$repo = dirname(__DIR__, 3);
$router = cut01aPatientsRead($repo . '/api/patients/index.php');
$rootSource = cut01aPatientsRead($repo . '/modules/patients/composition/PatientsAuthorityCompositionRoot.php');
$config = require $repo . '/modules/agenda/config/agenda.php';

$routes = [
    ['GET', '/patients/{}', "count(\$segments) === 2 && \$segments[0] === 'patients'"],
    ['GET', '/doctors/{}/patients/{}/contacts/editable', "count(\$segments) === 6 && \$segments[0] === 'doctors' && \$segments[2] === 'patients' && \$segments[4] === 'contacts' && \$segments[5] === 'editable'"],
    ['GET', '/doctors/{}/patients/search', "count(\$segments) === 4 && \$segments[0] === 'doctors' && \$segments[2] === 'patients' && \$segments[3] === 'search'"],
    ['GET', '/doctors/{}/patients', "count(\$segments) === 3 && \$segments[0] === 'doctors' && \$segments[2] === 'patients'"],
    ['POST', '/patients', "count(\$segments) === 1 && \$segments[0] === 'patients'"],
    ['POST', '/patients/{}/address', "count(\$segments) === 3 && \$segments[0] === 'patients' && \$segments[2] === 'address'"],
    ['POST', '/patients/{}/profile', "count(\$segments) === 3 && \$segments[0] === 'patients' && \$segments[2] === 'profile'"],
    ['PUT', '/doctors/{}/patients/{}/contacts/editable', "count(\$segments) === 6 && \$segments[0] === 'doctors' && \$segments[2] === 'patients' && \$segments[4] === 'contacts' && \$segments[5] === 'editable'"],
];
cut01aPatientsAssert(count($routes) === 8, 'exactly eight Patients routes represented');
cut01aPatientsAssert(count(array_filter($routes, static fn(array $route): bool => $route[0] === 'GET')) === 4, 'exactly four reads');
cut01aPatientsAssert(count(array_filter($routes, static fn(array $route): bool => $route[0] !== 'GET')) === 4, 'exactly four writes');
foreach ($routes as [$method, $path, $source]) {
    cut01aPatientsAssert(str_contains($router, $source), 'legacy route preserved: ' . $method . ' ' . $path);
}

$composition = new PatientsAuthorityCompositionRoot(new AuthorizationBoundary());
$identity = cut01aPatientsIdentity();
$membership = cut01aPatientsMembership();
$allowed = $composition->resolveServerAuthority(
    $identity,
    $membership,
    CanonicalProfileReference::profile('doctor-patients-cut01a'),
    'patient-target-cut01a',
    'owner',
    'read',
    'correlation-patients-cut01a',
    'request-patients-cut01a'
);
cut01aPatientsAssert($allowed->allowed(), 'server session, membership and ownership produce canonical allow');

$wrongDoctor = $composition->resolveServerAuthority(
    $identity,
    $membership,
    CanonicalProfileReference::profile('doctor-client-target'),
    'patient-target-cut01a',
    'owner',
    'read',
    'correlation-wrong-doctor',
    'request-wrong-doctor'
);
cut01aPatientsAssert(!$wrongDoctor->allowed(), 'doctor path target does not confer authority');

$deniedOwnership = $composition->resolveServerAuthority(
    $identity,
    $membership,
    CanonicalProfileReference::profile('doctor-patients-cut01a'),
    'patient-client-target',
    'denied',
    'write',
    'correlation-denied-owner',
    'request-denied-owner'
);
cut01aPatientsAssert(!$deniedOwnership->allowed(), 'patient target does not bypass server ownership');

$resolveParameters = (new ReflectionMethod($composition, 'resolveServerAuthority'))->getParameters();
$parameterNames = array_map(static fn(ReflectionParameter $parameter): string => $parameter->getName(), $resolveParameters);
cut01aPatientsAssert(!array_intersect($parameterNames, ['claims', 'headers', 'query', 'body', 'role', 'compatDev']), 'client claims are not authority inputs');
cut01aPatientsAssert(str_contains($rootSource, "new SubjectReference('patient', \$patientTarget)"), 'patient ID is modeled as affected target');
cut01aPatientsAssert(str_contains($rootSource, '$doctorTarget = $doctorMatchesMembership'), 'doctor ID is validated as target');
cut01aPatientsAssert(str_contains($rootSource, 'TrustedAuthorizationContext::fromBackend'), 'identity authority is server-side');
cut01aPatientsAssert(str_contains($rootSource, 'AuthorizationBoundary'), 'canonical AuthorizationBoundary is reused');
cut01aPatientsAssert(($config['feature_flags']['canonical_actor_authority'] ?? null) === false, 'literal false preserves legacy path');
cut01aPatientsAssert(!str_contains($router, 'resolveServerAuthority('), 'router does not execute canonical Patients authority');
cut01aPatientsAssert(!str_contains($router, 'new PatientsAuthorityCompositionRoot('), 'router does not instantiate canonical Patients root');
cut01aPatientsAssert(substr_count($router, '$controller = new ') === 8, 'eight legacy controller dispatches remain');
cut01aPatientsAssert(substr_count($router, 'invalid json') === 4, 'legacy invalid JSON payload contracts remain');
cut01aPatientsAssert(str_contains($router, "http_response_code(\$status);") && str_contains($router, 'echo json_encode($response);'), 'legacy status and payload response path remains');
cut01aPatientsAssert(!preg_match('/PatientIdentityResolver|PatientIdentityPersistence|Gate8F|Gate8G|\\bmerge\\b/i', $rootSource . "\n" . $router), 'patient identity, persistence and merge remain inactive');
cut01aPatientsAssert(!preg_match('/\\bPDO\\b|mxmed_pdo|->exec\\s*\\(|\\b(?:INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\\b|file_put_contents|fwrite/i', $rootSource), 'composition has zero DB, SQL and writes');

echo "Cut01APatientsAuthorityRoutesTest PASS\n";
