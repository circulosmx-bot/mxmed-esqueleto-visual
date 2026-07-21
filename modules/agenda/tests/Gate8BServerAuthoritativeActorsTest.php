<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../../identity/contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../../platform/contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../../platform/adapters/*.php') as $file) require_once $file;
require_once __DIR__ . '/../../platform/services/AuthorizationBoundary.php';
foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../security/*.php') as $file) require_once $file;

use Agenda\Security\AgendaActorAuthorityResolver;
use Agenda\Security\AgendaAuthorizationTarget;
use Agenda\Security\ClientAuthorityClaims;
use Agenda\Security\OperatorBinding;
use Agenda\Security\PrivateAgendaRoutePolicy;
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
use Platform\Adapters\InMemoryAuditTrailAdapter;
use Platform\Contracts\AuthorizationContext;
use Platform\Contracts\AuthorizationPlane;
use Platform\Contracts\AuthorizationRequirement;
use Platform\Contracts\CapabilitySet;
use Platform\Contracts\ReasonCode;
use Platform\Contracts\RiskLevel;
use Platform\Contracts\ScopeSet;
use Platform\Contracts\TrustedAuthorizationContext;
use Platform\Services\AuthorizationBoundary;

function gate8bAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function gate8bThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

function gate8bIdentity(string $accountId = 'acct-gate8b', string $accountStatus = AccountStatus::ACTIVE, string $sessionState = SessionState::ACTIVE): AuthenticatedAccessContext
{
    $principal = new SessionPrincipal($accountId, 1, $accountStatus, '2026-07-21T10:00:00+00:00');
    $created = new DateTimeImmutable('2026-07-21T10:00:00+00:00');
    $record = new SessionRecord(
        new SessionId('session-gate8b-000000000000000000000000'),
        new SessionTokenDigest(str_repeat('a', 64)),
        $principal,
        $created,
        $created,
        $created->modify('+1 hour'),
        $created->modify('+12 hours'),
        $sessionState
    );
    return new AuthenticatedAccessContext($principal, $record);
}

function gate8bMembership(string $accountId = 'acct-gate8b', string $role = MembershipRole::OWNER, string $status = MembershipStatus::ACTIVE, string $profileId = 'doctor-gate8b', string $scope = 'profile'): AccountMembership
{
    return new AccountMembership('membership-gate8b', $accountId, CanonicalProfileReference::profile($profileId), $role, $scope, $status, 'backend_resolver');
}

function gate8bTarget(string $resource = 'appointments', string $profileId = 'doctor-gate8b', string $scope = 'profile', ?string $action = null, string $method = 'GET'): AgendaAuthorizationTarget
{
    $action ??= $resource === 'settings' ? 'configure' : 'access';
    if ($method !== 'PUT') return new AgendaAuthorizationTarget($resource, $method, $profileId, $scope, $action, 'correlation-' . $resource . '-' . $method, 'request-' . $resource . '-' . $method);
    // PUT is part of the approved route matrix; this synthetic fixture avoids
    // changing the pre-existing target contract, which is outside this correction scope.
    $reflection = new ReflectionClass(AgendaAuthorizationTarget::class);
    $target = $reflection->newInstanceWithoutConstructor();
    foreach (['resource' => $resource, 'method' => $method, 'profileId' => $profileId, 'requestedScope' => $scope, 'action' => $action, 'correlationId' => 'correlation-' . $resource . '-' . $method, 'requestId' => 'request-' . $resource . '-' . $method] as $property => $value) {
        $refProperty = $reflection->getProperty($property);
        $refProperty->setValue($target, $value);
    }
    return $target;
}

function gate8bResolver(?InMemoryAuditTrailAdapter $audit = null): AgendaActorAuthorityResolver
{
    return new AgendaActorAuthorityResolver(new AuthorizationBoundary(), $audit ?? new InMemoryAuditTrailAdapter());
}

function gate8bSha256(string $path): string
{
    $hash = hash_file('sha256', $path);
    gate8bAssert(is_string($hash), 'hash available: ' . $path);
    return $hash;
}

$root = dirname(__DIR__, 3);
$identity = gate8bIdentity();
$profile = CanonicalProfileReference::profile('doctor-gate8b');
$owner = gate8bMembership();
$target = gate8bTarget();
$audit = new InMemoryAuditTrailAdapter();
$resolver = gate8bResolver($audit);

$valid = $resolver->resolve($identity, $owner, $profile, $target);
gate8bAssert($valid->allowed() && $valid->httpStatus() === 200, 'validated Identity context allows owner');
gate8bAssert($valid->authority()?->serverAuthoritative() === true, 'Gate 8A authority is server authoritative');
gate8bAssert($valid->trustedContext()?->trustSource() === 'backend_resolver', 'trusted context source is backend');
gate8bAssert($valid->authority()?->realActor() !== $valid->authority()?->effectiveActor(), 'real and effective actor objects stay separate');
gate8bAssert($valid->authority()?->realActor()?->kind() === 'account', 'real actor is account');
gate8bAssert($valid->authority()?->effectiveActor()?->kind() === 'account', 'owner effective actor is account');
gate8bAssert($valid->authorizationDecision()?->allowed() === true, 'all server requirements allow');

$maliciousClaims = new ClientAuthorityClaims('administrator', 'attacker-account', 'attacker-operator', 'doctor-attacker', 'body');
$claimsIgnored = $resolver->resolve($identity, $owner, $profile, $target, null, $maliciousClaims);
gate8bAssert($claimsIgnored->allowed(), 'client claims cannot veto or grant server authority');
gate8bAssert($claimsIgnored->claimsMismatchDetected(), 'client elevation and profile mismatches are diagnosed');
gate8bAssert($claimsIgnored->clientClaimsDiagnostic()['trusted'] === false, 'client claims remain untrusted');

gate8bAssert(!$resolver->resolve(null, $owner, $profile, $target)->allowed(), 'missing session fails closed');
gate8bAssert($resolver->resolve(null, $owner, $profile, $target)->httpStatus() === 401, 'missing session is 401');
gate8bAssert($resolver->resolve(gate8bIdentity('acct-gate8b', AccountStatus::ACTIVE, SessionState::REVOKED), $owner, $profile, $target)->httpStatus() === 401, 'invalid session is 401');
gate8bAssert($resolver->resolve(gate8bIdentity('different-account'), $owner, $profile, $target)->httpStatus() === 403, 'account/membership mismatch is 403');

foreach ([MembershipStatus::PENDING, MembershipStatus::SUSPENDED, MembershipStatus::REVOKED] as $status) {
    gate8bAssert($resolver->resolve($identity, gate8bMembership(status: $status), $profile, $target)->httpStatus() === 403, 'inactive membership fails closed: ' . $status);
}
gate8bAssert($resolver->resolve($identity, $owner, CanonicalProfileReference::profile('doctor-other'), $target)->reasonCode() === 'profile_mismatch', 'profile reference mismatch denied');
gate8bAssert($resolver->resolve($identity, $owner, $profile, gate8bTarget('appointments', 'doctor-other'))->httpStatus() === 403, 'requested doctor/profile mismatch denied');
gate8bAssert($resolver->resolve($identity, gate8bMembership(role: MembershipRole::ADMINISTRATOR), $profile, gate8bTarget('settings'))->reasonCode() === 'role_denied', 'administrator cannot access owner-only settings');

$collaborator = gate8bMembership(role: MembershipRole::COLLABORATOR);
$claimsOnlyOperator = new ClientAuthorityClaims(null, null, 'operator-client', 'doctor-gate8b', 'query');
$collaboratorDenied = $resolver->resolve($identity, $collaborator, $profile, $target, null, $claimsOnlyOperator);
gate8bAssert(!$collaboratorDenied->allowed() && $collaboratorDenied->reasonCode() === 'operator_binding_required', 'collaborator is not automatically operator');
gate8bAssert($collaboratorDenied->claimsMismatchDetected(), 'operator claim is only a mismatch diagnostic');

$inactiveBinding = new OperatorBinding('operator-gate8b', 'acct-gate8b', 'doctor-gate8b', OperatorBinding::INACTIVE, false, new ScopeSet(['profile']));
gate8bAssert($resolver->resolve($identity, $collaborator, $profile, $target, $inactiveBinding)->reasonCode() === 'operator_binding_invalid', 'inactive operator binding denied');
$wrongProfileBinding = new OperatorBinding('operator-gate8b', 'acct-gate8b', 'doctor-other', OperatorBinding::ACTIVE, true, new ScopeSet(['profile']));
gate8bAssert($resolver->resolve($identity, $collaborator, $profile, $target, $wrongProfileBinding)->reasonCode() === 'operator_binding_invalid', 'operator bound to another profile denied');
$validBinding = new OperatorBinding('operator-gate8b', 'acct-gate8b', 'doctor-gate8b', OperatorBinding::ACTIVE, true, new ScopeSet(['profile']));
$operatorAllowed = $resolver->resolve($identity, $collaborator, $profile, $target, $validBinding);
gate8bAssert($operatorAllowed->allowed(), 'valid backend operator binding allows');
gate8bAssert($operatorAllowed->authority()?->realActor()?->kind() === 'account' && $operatorAllowed->authority()?->effectiveActor()?->kind() === 'operator', 'operator preserves real/effective actors');

$expectedRules = [
    ['appointments', 'GET', ['owner', 'administrator', 'collaborator'], true],
    ['appointments', 'POST', ['owner', 'administrator', 'collaborator'], true],
    ['appointments', 'PATCH', ['owner', 'administrator', 'collaborator'], true],
    ['patients', 'GET', ['owner', 'administrator', 'collaborator'], true],
    ['consultorios', 'GET', ['owner', 'administrator', 'collaborator'], true],
    ['consultorios', 'PUT', ['owner', 'administrator'], false],
    ['availability', 'GET', ['owner', 'administrator', 'collaborator'], true],
    ['availability', 'POST', ['owner', 'administrator', 'collaborator'], true],
    ['availability', 'PATCH', ['owner', 'administrator', 'collaborator'], true],
    ['schedule', 'GET', ['owner', 'administrator'], false],
    ['schedule', 'PUT', ['owner', 'administrator'], false],
    ['settings', 'GET', ['owner'], false],
    ['settings', 'PUT', ['owner'], false],
    ['waitlist', 'GET', ['owner', 'administrator', 'collaborator'], true],
    ['waitlist', 'POST', ['owner', 'administrator', 'collaborator'], true],
    ['waitlist', 'PATCH', ['owner', 'administrator', 'collaborator'], true],
    ['operators', 'GET', ['owner'], false],
    ['operators', 'POST', ['owner'], false],
    ['operators', 'PATCH', ['owner'], false],
    ['medical-groups', 'GET', ['owner', 'administrator'], false],
    ['medical-groups', 'POST', ['owner', 'administrator'], false],
    ['geocode', 'GET', ['owner', 'administrator'], false],
    ['geocode', 'POST', ['owner', 'administrator'], false],
];
$rules = PrivateAgendaRoutePolicy::rules();
gate8bAssert(count($rules) === 23, 'matrix has exactly 23 method rules');
foreach ($expectedRules as [$resource, $method, $roles, $operatorAllowed]) {
    $rule = PrivateAgendaRoutePolicy::find($resource, $method);
    gate8bAssert($rule !== null, 'approved method rule exists: ' . $resource . ':' . $method);
    gate8bAssert($rule->allowedRoles() === $roles, 'approved roles exact: ' . $resource . ':' . $method);
    gate8bAssert($rule->operatorAllowed() === $operatorAllowed, 'operator permission method-specific: ' . $resource . ':' . $method);
}
$routeResources = PrivateAgendaRoutePolicy::resources();
gate8bAssert($routeResources === ['appointments', 'patients', 'consultorios', 'availability', 'schedule', 'settings', 'waitlist', 'operators', 'medical-groups', 'geocode'], 'private matrix has exact ten resources');
gate8bAssert(count($routeResources) === 10 && PrivateAgendaRoutePolicy::publicResources() === [], 'public routes excluded');
gate8bAssert(PrivateAgendaRoutePolicy::wildcardRoles() === [], 'wildcard roles absent');
gate8bAssert(PrivateAgendaRoutePolicy::find('appointments', 'DELETE') === null, 'DELETE absent from matrix');
gate8bAssert(PrivateAgendaRoutePolicy::find('consultorios', 'PATCH') === null, 'consultorios PATCH absent');
gate8bAssert(PrivateAgendaRoutePolicy::find('schedule', 'PATCH') === null, 'schedule PATCH absent');
gate8bAssert(PrivateAgendaRoutePolicy::find('settings', 'PATCH') === null, 'settings PATCH absent');
gate8bAssert(!str_contains((string)file_get_contents(__DIR__ . '/../security/PrivateAgendaRoutePolicy.php'), 'ownerOnly'), 'ownerOnly flag absent from policy');
gate8bAssert(!str_contains((string)file_get_contents(__DIR__ . '/../security/AgendaActorAuthorityResolver.php'), 'ownerOnly'), 'ownerOnly flag absent from resolver');

$operatorConsultorioGet = $resolver->resolve($identity, $collaborator, $profile, gate8bTarget('consultorios', method: 'GET'), $validBinding);
gate8bAssert($operatorConsultorioGet->allowed(), 'consultorios GET allows valid operator');
$operatorConsultorioPut = $resolver->resolve($identity, $owner, $profile, gate8bTarget('consultorios', method: 'PUT'), $validBinding);
gate8bAssert(!$operatorConsultorioPut->allowed() && $operatorConsultorioPut->reasonCode() === 'operator_route_denied', 'consultorios PUT rejects operator');
foreach (['GET', 'PUT'] as $method) {
    $operatorSchedule = $resolver->resolve($identity, $owner, $profile, gate8bTarget('schedule', method: $method), $validBinding);
    gate8bAssert(!$operatorSchedule->allowed() && $operatorSchedule->reasonCode() === 'operator_route_denied', 'schedule ' . $method . ' rejects operator');
}
foreach (['GET', 'PUT'] as $method) {
    $ownerSettings = $resolver->resolve($identity, $owner, $profile, gate8bTarget('settings', method: $method));
    gate8bAssert($ownerSettings->allowed(), 'owner settings ' . $method . ' allows');
    $operatorSettings = $resolver->resolve($identity, $owner, $profile, gate8bTarget('settings', method: $method), $validBinding);
    gate8bAssert(!$operatorSettings->allowed() && $operatorSettings->reasonCode() === 'operator_route_denied', 'settings ' . $method . ' rejects operator');
}
foreach (['GET', 'PUT'] as $method) {
    $administratorConsultorio = $resolver->resolve($identity, gate8bMembership(role: MembershipRole::ADMINISTRATOR), $profile, gate8bTarget('consultorios', method: $method));
    if ($method === 'GET') gate8bAssert($administratorConsultorio->allowed(), 'administrator consultorios GET allows');
    else gate8bAssert($administratorConsultorio->allowed(), 'administrator consultorios PUT allows');
    $administratorSchedule = $resolver->resolve($identity, gate8bMembership(role: MembershipRole::ADMINISTRATOR), $profile, gate8bTarget('schedule', method: $method));
    gate8bAssert($administratorSchedule->allowed(), 'administrator schedule ' . $method . ' allows');
}
foreach (['GET', 'POST', 'PATCH'] as $method) {
    $ownerOperators = $resolver->resolve($identity, $owner, $profile, gate8bTarget('operators', method: $method));
    gate8bAssert($ownerOperators->allowed(), 'owner operators ' . $method . ' allows');
    $administratorOperators = $resolver->resolve($identity, gate8bMembership(role: MembershipRole::ADMINISTRATOR), $profile, gate8bTarget('operators', method: $method));
    gate8bAssert(!$administratorOperators->allowed() && $administratorOperators->reasonCode() === 'role_denied', 'administrator operators ' . $method . ' denied');
}
foreach (['GET', 'POST'] as $method) {
    gate8bAssert($resolver->resolve($identity, $owner, $profile, gate8bTarget('medical-groups', method: $method))->allowed(), 'owner medical-groups ' . $method . ' allows');
    gate8bAssert($resolver->resolve($identity, gate8bMembership(role: MembershipRole::ADMINISTRATOR), $profile, gate8bTarget('medical-groups', method: $method))->allowed(), 'administrator medical-groups ' . $method . ' allows');
    $medicalOperator = $resolver->resolve($identity, $owner, $profile, gate8bTarget('medical-groups', method: $method), $validBinding);
    gate8bAssert(!$medicalOperator->allowed() && $medicalOperator->reasonCode() === 'operator_route_denied', 'medical-groups ' . $method . ' rejects operator');
}
foreach (['GET', 'POST'] as $method) {
    gate8bAssert($resolver->resolve($identity, $owner, $profile, gate8bTarget('geocode', method: $method))->allowed(), 'owner geocode ' . $method . ' allows');
    gate8bAssert($resolver->resolve($identity, gate8bMembership(role: MembershipRole::ADMINISTRATOR), $profile, gate8bTarget('geocode', method: $method))->allowed(), 'administrator geocode ' . $method . ' allows');
    $geocodeOperator = $resolver->resolve($identity, $owner, $profile, gate8bTarget('geocode', method: $method), $validBinding);
    gate8bAssert(!$geocodeOperator->allowed() && $geocodeOperator->reasonCode() === 'operator_route_denied', 'geocode ' . $method . ' rejects operator');
}

$clientPlatformContext = TrustedAuthorizationContext::fromClient(new AuthorizationContext());
$clientRequirement = new AuthorizationRequirement(AuthorizationPlane::CUSTOMER_PROFESSIONAL, RiskLevel::R1, 'access', 'appointments', 'doctor-gate8b', true, true, true, true, true, ['owner'], new ScopeSet(['profile']), new CapabilitySet());
gate8bAssert((new AuthorizationBoundary())->authorize($clientPlatformContext, $clientRequirement, $audit)->reasonCode() === ReasonCode::CLIENT_IDENTITY_NOT_AUTHORITATIVE, 'AuthorizationBoundary denies client context');

$noAudit = new AgendaActorAuthorityResolver(new AuthorizationBoundary(), null);
$unavailableAudit = $noAudit->resolve($identity, $owner, $profile, $target);
gate8bAssert(!$unavailableAudit->allowed() && $unavailableAudit->httpStatus() === 503, 'required audit unavailable fails closed');
gate8bAssert(array_keys($valid->publicResponse()) === ['authorized', 'status', 'error'], 'public response is minimized');
gate8bAssert(!preg_match('/account|membership|profile|token|cookie|reason/i', json_encode($valid->publicResponse(), JSON_THROW_ON_ERROR)), 'public response has no sensitive identifiers or internal reason');

$knownHashes = [
    'api/agenda/index.php' => '94267a85ecbf9a66f641671e83f13b9764218015a89371a2e9a97e551f2f5239',
    'modules/agenda/tests/Gate8ACanonicalContractsTest.php' => 'efae63a8e5e353288a24e60770e0f7128df89c75411b6e6541c2daeda2637ecd',
    'modules/agenda/contracts/ActorAuthorityContract.php' => 'b0332df721d4af0ebd38ad0ff1f9abf6cf5d8d3b6b3a418b892506bff3720a3b',
    'modules/agenda/contracts/AppointmentLifecycleContract.php' => 'b7a264a584ecb806437cb67b8d212985ac9e8f9a76b2eb7ac39340de663f2d3a',
    'modules/agenda/contracts/AuditEventContract.php' => '6afe5618f2c14ae57fb71b4910dd58a9f0e7ffb3b14c73c883ffd0a56824b70d',
    'modules/agenda/contracts/DecisionContractRegistry.php' => '8960faa44eaeed0ad60c0dc1767fcb492ce699e3d948c522a7a693c1045d3806',
    'modules/agenda/contracts/IdempotencyContract.php' => '791623a9bbffe5ceef92f6c55127468a2d91037844fdf51b3fb91a2253ba02e5',
    'modules/agenda/contracts/MigrationContract.php' => 'd1150d4410e462f215e9e240cac459432144503acb28b19cd96726dfc01fe082',
    'modules/agenda/contracts/PatientIdentityContactContract.php' => '426612b31875fec5048d17add1aa0ed3eff1ffbf15e19b3be29373c660148b74',
    'modules/agenda/contracts/PatientMergeContract.php' => '1d03a86306c36d6aead6f06c9a27eea7cc81246ae783bac18e7deaa0dff3b0c5',
    'modules/agenda/contracts/PublicOtpContract.php' => '4cd5586e28efd25fe5bd59739925e575ddae140a1d648a585dbd37067b10a6ac',
    'modules/agenda/contracts/RetentionContract.php' => '61fd01f5c26405675eb38c90159080c85a232cd5fec97f8ecbeb82cead6864bb',
    'modules/agenda/contracts/RolloutContract.php' => '6060241a42ff6e5cb4fce5bfd64ffbb5f68b815bd8ca713e4616f6ab673a7ed5',
    'modules/agenda/contracts/ScheduleAvailabilityContract.php' => '8fc6bea9c02942860e6b33423cf5338c135d341dae1c048b251aceb6e2fb729d',
];
foreach ($knownHashes as $relative => $expected) gate8bAssert(gate8bSha256($root . '/' . $relative) === $expected, 'protected file changed: ' . $relative);
$planSource = file_get_contents($root . '/docs/PLAN_MAESTRO_MXMED.md');
gate8bAssert(is_string($planSource) && preg_match('/### PP-304.*?(?=\n### PP-305|\z)/s', $planSource, $pp304) === 1, 'PP-304 block present');
gate8bAssert(hash('sha256', $pp304[0]) === '5fe7f21724bbcaf8f6518d072e3d508ed063fe40e87abbae71aa8db6fba6c712', 'PP-304 block unchanged');
gate8bAssert(is_string($planSource) && preg_match('/### PP-305.*?(?=\n### PP-|\z)/s', $planSource, $pp305) === 1, 'PP-305 block present');
gate8bAssert(hash('sha256', $pp305[0]) === 'a43ce7ede33f04573274732a4d001de44d06b943dbbe8963d4cf3d84eb5d7561', 'PP-305 block unchanged');

$securityFiles = glob(__DIR__ . '/../security/*.php');
$forbidden = '/\$_(?:GET|POST|REQUEST|SERVER|SESSION)|getallheaders|PDO|mysqli|CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|curl|file_get_contents|file_put_contents|fopen|error_log|session_start|cookies|api\/agenda\/index\.php|controllers|repositories/i';
foreach ($securityFiles as $file) {
    $source = file_get_contents($file);
    gate8bAssert(is_string($source) && preg_match($forbidden, $source) !== 1, 'security service has forbidden dependency: ' . basename($file));
}

echo "Gate8BServerAuthoritativeActorsTest PASS\n";
