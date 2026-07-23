<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../../identity/contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../../platform/contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../../platform/adapters/*.php') as $file) require_once $file;
require_once __DIR__ . '/../../platform/services/AuthorizationBoundary.php';
foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../security/*.php') as $file) require_once $file;
require_once __DIR__ . '/../composition/AgendaAuthorityCompositionRoot.php';
require_once __DIR__ . '/../../patients/composition/PatientsAuthorityCompositionRoot.php';

use Agenda\Composition\AgendaAuthorityCompositionRoot;
use Agenda\Security\AgendaActorAuthorityResolver;
use Agenda\Security\AgendaAuthorizationTarget;
use Agenda\Security\ClientAuthorityClaims;
use Agenda\Security\OperatorBinding;
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
use Platform\Adapters\InMemoryAuditTrailAdapter;
use Platform\Contracts\ScopeSet;
use Platform\Services\AuthorizationBoundary;

function cut01aAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function cut01aRead(string $path): string
{
    $source = file_get_contents($path);
    cut01aAssert(is_string($source), 'readable source required: ' . $path);
    return $source;
}

function cut01aIdentity(string $accountId = 'acct-cut01a'): AuthenticatedAccessContext
{
    $principal = new SessionPrincipal($accountId, 1, AccountStatus::ACTIVE, '2026-07-22T10:00:00+00:00');
    $created = new DateTimeImmutable('2026-07-22T10:00:00+00:00');
    $session = new SessionRecord(
        new SessionId('session-cut01a-0000000000000000000000'),
        new SessionTokenDigest(str_repeat('a', 64)),
        $principal,
        $created,
        $created,
        $created->modify('+1 hour'),
        $created->modify('+12 hours'),
        SessionState::ACTIVE
    );
    return new AuthenticatedAccessContext($principal, $session);
}

function cut01aMembership(
    string $accountId = 'acct-cut01a',
    string $role = MembershipRole::OWNER,
    string $profileId = 'doctor-cut01a'
): AccountMembership {
    return new AccountMembership(
        'membership-cut01a',
        $accountId,
        CanonicalProfileReference::profile($profileId),
        $role,
        'profile',
        MembershipStatus::ACTIVE,
        'backend_resolver'
    );
}

function cut01aTarget(
    string $resource = 'appointments',
    string $profileId = 'doctor-cut01a'
): AgendaAuthorizationTarget {
    return new AgendaAuthorizationTarget(
        $resource,
        'GET',
        $profileId,
        'profile',
        'access',
        'correlation-cut01a',
        'request-cut01a'
    );
}

$repo = dirname(__DIR__, 3);
$audit = new InMemoryAuditTrailAdapter();
$agendaRoot = new AgendaAuthorityCompositionRoot(new AuthorizationBoundary(), $audit);
$patientsRoot = new PatientsAuthorityCompositionRoot(new AuthorizationBoundary());
cut01aAssert($agendaRoot->resolver() instanceof AgendaActorAuthorityResolver, 'Agenda root reuses canonical resolver');
cut01aAssert($patientsRoot instanceof PatientsAuthorityCompositionRoot, 'Patients root is constructible');

$agendaConstructor = (new ReflectionClass($agendaRoot))->getConstructor();
$patientsConstructor = (new ReflectionClass($patientsRoot))->getConstructor();
cut01aAssert($agendaConstructor?->getNumberOfParameters() === 2, 'Agenda dependencies are explicit');
cut01aAssert($patientsConstructor?->getNumberOfParameters() === 1, 'Patients dependencies are explicit');

$identity = cut01aIdentity();
$owner = cut01aMembership();
$profile = CanonicalProfileReference::profile('doctor-cut01a');
$valid = $agendaRoot->resolve($identity, $owner, $profile, cut01aTarget());
cut01aAssert($valid->allowed(), 'server-side principal and membership authorize');
cut01aAssert($valid->authority()?->realActor()?->id() === 'acct-cut01a', 'real actor comes from server principal');

$elevatedClaims = new ClientAuthorityClaims(
    'owner',
    'attacker-account',
    'attacker-operator',
    'doctor-attacker',
    'compat_dev'
);
$collaborator = cut01aMembership(role: MembershipRole::COLLABORATOR);
$clientCannotElevate = $agendaRoot->resolve(
    $identity,
    $collaborator,
    $profile,
    cut01aTarget(),
    null,
    $elevatedClaims
);
cut01aAssert(!$clientCannotElevate->allowed(), 'elevated client role and compat_dev do not grant');
cut01aAssert($clientCannotElevate->reasonCode() === 'operator_binding_required', 'server membership keeps precedence');
cut01aAssert($clientCannotElevate->claimsMismatchDetected(), 'actor, profile and operator mismatches are diagnostic');
cut01aAssert($clientCannotElevate->clientClaimsDiagnostic()['trusted'] === false, 'client claims remain non-authoritative');

$profileMismatch = $agendaRoot->resolve(
    $identity,
    $owner,
    CanonicalProfileReference::profile('doctor-other'),
    cut01aTarget()
);
cut01aAssert(!$profileMismatch->allowed() && $profileMismatch->reasonCode() === 'profile_mismatch', 'profile mismatch denies');

$consultorioMismatch = $agendaRoot->resolve(
    $identity,
    $owner,
    $profile,
    cut01aTarget('consultorios', 'doctor-other')
);
cut01aAssert(!$consultorioMismatch->allowed(), 'consultorio target under another profile denies');

$wrongOperator = new OperatorBinding(
    'operator-cut01a',
    'acct-cut01a',
    'doctor-other',
    OperatorBinding::ACTIVE,
    true,
    new ScopeSet(['profile'])
);
$operatorMismatch = $agendaRoot->resolve(
    $identity,
    $collaborator,
    $profile,
    cut01aTarget(),
    $wrongOperator
);
cut01aAssert(!$operatorMismatch->allowed() && $operatorMismatch->reasonCode() === 'operator_binding_invalid', 'operator mismatch denies');

$config = require $repo . '/modules/agenda/config/agenda.php';
cut01aAssert(($config['feature_flags']['canonical_actor_authority'] ?? null) === false, 'configured flag is literal false');
foreach ([
    [],
    ['feature_flags' => []],
    ['feature_flags' => ['canonical_actor_authority' => null]],
    ['feature_flags' => ['canonical_actor_authority' => 'true']],
    ['feature_flags' => ['canonical_actor_authority' => '1']],
    ['feature_flags' => ['canonical_actor_authority' => 'yes']],
    ['feature_flags' => ['canonical_actor_authority' => 'on']],
    ['feature_flags' => ['canonical_actor_authority' => 1]],
    ['feature_flags' => ['canonical_actor_authority' => []]],
    ['feature_flags' => ['canonical_actor_authority' => new stdClass()]],
] as $invalidConfig) {
    cut01aAssert(!AgendaAuthorityCompositionRoot::canonicalActorAuthorityEnabled($invalidConfig), 'missing or invalid flag fails closed');
    cut01aAssert(!PatientsAuthorityCompositionRoot::canonicalActorAuthorityEnabled($invalidConfig), 'Patients flag evaluation fails closed');
}
cut01aAssert(AgendaAuthorityCompositionRoot::canonicalActorAuthorityEnabled(['feature_flags' => ['canonical_actor_authority' => true]]), 'only literal true is eligible');

$agendaRootSource = cut01aRead($repo . '/modules/agenda/composition/AgendaAuthorityCompositionRoot.php');
$patientsRootSource = cut01aRead($repo . '/modules/patients/composition/PatientsAuthorityCompositionRoot.php');
$configSource = cut01aRead($repo . '/modules/agenda/config/agenda.php');
$agendaRouter = cut01aRead($repo . '/api/agenda/index.php');
$patientsRouter = cut01aRead($repo . '/api/patients/index.php');
$compositionSource = $agendaRootSource . "\n" . $patientsRootSource;
cut01aAssert(!preg_match('/\$_(?:GET|POST|SERVER|SESSION|COOKIE|REQUEST)|getenv|localStorage|compat_dev/i', $compositionSource), 'composition roots do not read global or client overrides');
cut01aAssert(!preg_match('/\bPDO\b|mxmed_pdo|->exec\\s*\\(|\\b(?:INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\\b|file_put_contents|fwrite|error_log|curl_/i', $compositionSource), 'composition roots have zero DB, SQL, writes, logs or network');
cut01aAssert(!preg_match('/service[_ ]?locator|container->|\\$GLOBALS/i', $compositionSource), 'service locator global absent');
cut01aAssert(substr_count($configSource, "'canonical_actor_authority' => false") === 1, 'single server-side literal default');
cut01aAssert(!preg_match('/canonical_actor_authority[^\\n]*(?:getenv|\\$_|HTTP_|QA_MODE)/i', $configSource . "\n" . $agendaRouter . "\n" . $patientsRouter), 'request, client and environment overrides absent');
cut01aAssert(preg_match('/if \\(\\$cut01aCanonicalActorAuthorityEnabled === true\\) \\{\\s*\\/\\/[^\\n]*\\s*\\$cut01aAgendaAuthorityRootClass = AgendaAuthorityCompositionRoot::class;\\s*\\}/s', $agendaRouter) === 1, 'Agenda dormant branch only prepares class reference');
cut01aAssert(preg_match('/if \\(\\$cut01aCanonicalActorAuthorityEnabled === true\\) \\{\\s*\\/\\/[^\\n]*\\s*\\$cut01aPatientsAuthorityRootClass = PatientsAuthorityCompositionRoot::class;\\s*\\}/s', $patientsRouter) === 1, 'Patients dormant branch only prepares class reference');
cut01aAssert(!preg_match('/canonicalActorAuthorityEnabled\\([^)]*\\)\\s*;\\s*\\n\\s*(?:new|->resolve)/', $agendaRouter . "\n" . $patientsRouter), 'dormant wiring does not process canonical authority');

echo "Cut01AAuthorityCompositionRootsTest PASS\n";
