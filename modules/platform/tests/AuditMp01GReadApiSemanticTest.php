<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
foreach (['CanonicalAuditEventType', 'CanonicalAuditReasonCode', 'CanonicalAuditResult', 'CanonicalAuditRetentionClass', 'CanonicalAuditSeverity', 'TrustedActorContext'] as $name) {
    require_once $root . '/modules/platform/contracts/' . $name . '.php';
}
foreach (['SourceModuleCatalog', 'CanonicalAuditPolicyRegistry'] as $name) {
    require_once $root . '/modules/platform/services/' . $name . '.php';
}
$read = $root . '/modules/platform/audit/read/';
foreach (['AuditReadAccess', 'TrustedAuditReadAuthority', 'AuditReadFilter', 'AuditReadCursorCodec', 'AuditReadProjection', 'AuditReadQuery', 'AuditReadAuthorization', 'AuditReadPage'] as $name) {
    require_once $read . $name . '.php';
}
require_once $read . 'contracts/AuditReadRepositoryPort.php';
require_once $read . 'contracts/SelfSecuritySubjectResolverPort.php';
require_once $read . 'SelfSecurityTimelinePolicy.php';
require_once $read . 'AuditReadService.php';

use Platform\Audit\Read\AuditReadAccess;
use Platform\Audit\Read\AuditReadAuthorizer;
use Platform\Audit\Read\AuditReadCursor;
use Platform\Audit\Read\AuditReadCursorCodec;
use Platform\Audit\Read\AuditReadFilter;
use Platform\Audit\Read\AuditReadProjection;
use Platform\Audit\Read\AuditReadQuery;
use Platform\Audit\Read\AuditReadService;
use Platform\Audit\Read\AuthorizedAuditRead;
use Platform\Audit\Read\Contracts\AuditReadRepositoryPort;
use Platform\Audit\Read\Contracts\SelfSecuritySubjectResolverPort;
use Platform\Audit\Read\SelfSecurityTimelinePolicy;
use Platform\Audit\Read\TrustedAuditReadAuthority;
use Platform\Contracts\TrustedActorContext;
use Platform\Services\CanonicalAuditPolicyRegistry;
use Platform\Services\SourceModuleCatalog;

$semanticTotal = 0; $semanticPass = 0; $negativeTotal = 0; $negativeBlocked = 0;
function mp01gOk(bool $condition, string $name): void
{
    global $semanticTotal, $semanticPass;
    $semanticTotal++;
    if (!$condition) throw new RuntimeException('semantic:' . $name);
    $semanticPass++;
}
function mp01gBlocked(callable $probe, string $name): void
{
    global $negativeTotal, $negativeBlocked;
    $negativeTotal++;
    try { $probe(); } catch (Throwable) { $negativeBlocked++; return; }
    throw new RuntimeException('negative_escaped:' . $name);
}

final class Mp01gFakeReadRepository implements AuditReadRepositoryPort
{
    /** @param list<array<string,mixed>> $rows */
    public function __construct(public array $rows) {}
    public int $lastLimit = 0;
    public ?AuthorizedAuditRead $lastRead = null;
    public function fetch(AuthorizedAuditRead $read, ?AuditReadCursor $after, int $limit): array
    {
        $this->lastLimit = $limit; $this->lastRead = $read; return $this->rows;
    }
}

/** Test-only trusted backend binding authority; no request input or reverse lookup. */
final class Mp01gFakeSubjectResolver implements SelfSecuritySubjectResolverPort
{
    /** @param array<string,string> $bindings */
    public function __construct(private array $bindings) {}
    public function bind(string $targetType, string $targetId, string $accountId): void
    {
        $this->bindings[$targetType . ':' . $targetId] = $accountId;
    }
    public function assertBelongsToSelf(array $canonicalAuditRow, string $trustedAccountIdentityId): void
    {
        $type = $canonicalAuditRow['target_type'] ?? null;
        $id = $canonicalAuditRow['target_id'] ?? null;
        if (!is_string($type) || !is_string($id)) throw new DomainException('unresolvable_self_subject');
        $bound = $this->bindings[$type . ':' . $id] ?? null;
        if (!is_string($bound) || !hash_equals($trustedAccountIdentityId, $bound)) {
            throw new DomainException('self_subject_binding_denied');
        }
    }
}

/** @return array<string,mixed> */
function mp01gRow(string $eventType, string $targetId, string $createdAt, string $hex, string $source = 'AUTH', string $targetType = 'ACCOUNT', ?string $realActorId = null): array
{
    $policy = CanonicalAuditPolicyRegistry::canonical()->policyFor($eventType);
    $pair = $policy['allowed_result_reason_pairs'][0];
    return [
        'event_id' => str_repeat($hex, 64), 'created_at' => $createdAt, 'occurred_at' => $createdAt, 'event_type' => $eventType,
        'severity' => $policy['severity'], 'result' => $pair['result'], 'reason_code' => $pair['reason_code'],
        'real_actor_type' => 'ACCOUNT', 'real_actor_id' => $realActorId ?? $targetId,
        'effective_entity_type' => null, 'effective_entity_id' => null,
        'source_module' => $source, 'source_route' => 'POST /identity/security',
        'target_type' => $targetType, 'target_id' => $targetId,
        'request_id' => '11111111-1111-4111-8111-' . str_repeat($hex, 12),
        'correlation_id' => '22222222-2222-4222-8222-' . str_repeat($hex, 12),
        'retention_class' => $policy['retention_class'],
        'metadata_json' => '{"private":true}', 'writer_internal_metadata' => ['ip_hmac_key_version' => 'hidden'],
        'ip_hmac' => 'hidden', 'user_agent_summary' => 'hidden', 'session_id' => 'hidden',
        'previous_hash' => str_repeat('a', 64), 'event_hash' => str_repeat('b', 64),
    ];
}
function mp01gActor(string $identity): TrustedActorContext
{
    return TrustedActorContext::fromTrustedBackend([
        'authenticated_identity_id' => $identity, 'real_actor_type' => 'ACCOUNT', 'real_actor_id' => $identity,
        'actor_role' => 'MEMBER', 'actor_scope' => 'SELF', 'target_type' => 'ACCOUNT', 'target_id' => $identity,
        'authorization_provenance' => 'session_backend', 'trust_source' => 'backend_trusted',
    ]);
}

$modules = new SourceModuleCatalog();
$canonical = CanonicalAuditPolicyRegistry::canonical();
$subjects = new Mp01gFakeSubjectResolver(['ACCOUNT:acct_1' => 'acct_1', 'ACCOUNT:acct_2' => 'acct_2']);
$selfPolicy = new SelfSecurityTimelinePolicy($canonical, $subjects);
$codec = new AuditReadCursorCodec(str_repeat('cursor-secret-', 4));
$authorizer = new AuditReadAuthorizer();
$rows = [
    mp01gRow('AUTH_LOGIN_SUCCEEDED', 'acct_1', '2026-08-15T01:00:00.000002Z', 'f'),
    mp01gRow('AUTH_PASSWORD_CHANGED', 'acct_1', '2026-08-15T01:00:00.000001Z', 'e'),
];
$repository = new Mp01gFakeReadRepository($rows);
$service = new AuditReadService($repository, $authorizer, $selfPolicy, $codec);
$selfAuthority = TrustedAuditReadAuthority::fromTrustedBackend(mp01gActor('acct_1'), [AuditReadAccess::SELF_SECURITY]);
$emptyFilter = AuditReadFilter::fromArray([], $modules);
$selfQuery = AuditReadQuery::selfSecurity($emptyFilter, null, 1);
$page = $service->read($selfQuery, $selfAuthority);

mp01gOk(AuditReadAccess::capabilities() === ['AUDIT_READ_SELF_SECURITY', 'AUDIT_READ_INTERNAL_SCOPED', 'AUDIT_READ_ADMIN_PRIVILEGED'], 'finite_capability_catalog');
mp01gOk(count($selfPolicy->eligibleEventTypes()) === 12, 'canonical_self_timeline_eligibility_count');
mp01gOk($selfQuery->scope === AuditReadAccess::SCOPE_SELF_ACCOUNT && $selfQuery->scopeValue === 'TRUSTED_SELF', 'self_identity_not_request_supplied');
mp01gOk(count($page->items) === 1 && $repository->lastLimit === 2, 'bounded_page_plus_one');
mp01gOk(array_keys($page->items[0]) === (AuditReadProjection::named(AuditReadProjection::SELF_SECURITY))->fields(), 'self_projection_exact');
$forbidden = ['target_id', 'metadata_json', 'writer_internal_metadata', 'ip_hmac', 'user_agent_summary', 'session_id', 'previous_hash', 'event_hash'];
mp01gOk(array_intersect($forbidden, array_keys($page->items[0])) === [], 'self_projection_minimized');
mp01gOk($page->nextCursor !== null, 'keyset_next_cursor_present');
$decoded = $codec->decode((string)$page->nextCursor);
mp01gOk($decoded->eventId === str_repeat('f', 64) && $decoded->createdAt === '2026-08-15T01:00:00.000002Z', 'cursor_round_trip');
mp01gOk($page->accessAuditIntent['auditable'] === true && $page->accessAuditIntent['emission_active'] === false, 'read_access_auditable_but_dormant');
mp01gOk($page->accessAuditIntent['requester_identity_id'] === 'acct_1' && $page->accessAuditIntent['scope_value'] === 'acct_1', 'trusted_self_identity_enforced');
mp01gOk($page->historyAvailability === 'AVAILABLE', 'history_availability_explicit');

$internalAuthority = TrustedAuditReadAuthority::fromTrustedBackend(mp01gActor('support_1'), [AuditReadAccess::INTERNAL_SCOPED]);
$internalQuery = AuditReadQuery::internalScoped($emptyFilter, AuditReadAccess::INTERNAL_SCOPED, AuditReadAccess::SCOPE_ACCOUNT, 'acct_1', AuditReadAccess::REASON_SUPPORT, AuditReadProjection::named(AuditReadProjection::INTERNAL_SCOPED), null, 2);
$internalPage = $service->read($internalQuery, $internalAuthority);
mp01gOk(count($internalPage->items) === 2, 'internal_scoped_read');
mp01gOk($internalPage->accessAuditIntent['access_reason'] === AuditReadAccess::REASON_SUPPORT, 'controlled_access_reason');
mp01gOk(!array_key_exists('metadata_json', $internalPage->items[0]), 'internal_raw_metadata_excluded');

$adminAuthority = TrustedAuditReadAuthority::fromTrustedBackend(mp01gActor('admin_1'), [AuditReadAccess::ADMIN_PRIVILEGED]);
$oneRowRepo = new Mp01gFakeReadRepository([$rows[0]]);
$adminService = new AuditReadService($oneRowRepo, $authorizer, $selfPolicy, $codec);
$adminFilter = AuditReadFilter::fromArray(['event_type' => 'AUTH_LOGIN_SUCCEEDED'], $modules);
$adminQuery = AuditReadQuery::internalScoped($adminFilter, AuditReadAccess::ADMIN_PRIVILEGED, AuditReadAccess::SCOPE_EVENT_TYPE, 'AUTH_LOGIN_SUCCEEDED', AuditReadAccess::REASON_SECURITY, AuditReadProjection::named(AuditReadProjection::ADMIN_PRIVILEGED));
$adminPage = $adminService->read($adminQuery, $adminAuthority);
mp01gOk($adminPage->items[0]['real_actor_id'] === 'acct_1', 'privileged_projection_authorized');
mp01gOk(!array_key_exists('metadata_json', $adminPage->items[0]) && !array_key_exists('event_hash', $adminPage->items[0]), 'privileged_projection_still_minimized');
mp01gOk($selfPolicy->isEligible('AUTH_LOGIN_SUCCEEDED') && !$selfPolicy->isEligible('ROLE_ASSIGNED') && !$selfPolicy->isEligible('UNKNOWN'), 'canonical_policy_fail_closed');
mp01gOk($selfQuery->sort === 'created_at_desc_event_id_desc', 'deterministic_fixed_sort');
mp01gOk(hash_equals($page->accessAuditIntent['filter_fingerprint'], hash('sha256', '[]')), 'deterministic_filter_fingerprint');
mp01gOk(count(AuditReadFilter::supported()) === 16, 'schema_supported_filter_count');
mp01gOk($subjects instanceof SelfSecuritySubjectResolverPort, 'trusted_subject_resolver_contract');
mp01gOk(count($page->items) === 1, 'account_target_self_binding');

$subjects->bind('SESSION', 'session_123', 'acct_1');
$sessionRow = mp01gRow('AUTH_SESSION_CREATED', 'session_123', '2026-08-15T01:01:00.000001Z', '9', 'SESSION', 'SESSION', 'support_admin');
$sessionPage = (new AuditReadService(new Mp01gFakeReadRepository([$sessionRow]), $authorizer, $selfPolicy, $codec))->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority);
mp01gOk(count($sessionPage->items) === 1 && $sessionRow['target_id'] !== 'acct_1', 'session_subject_binding_without_target_equality');
mp01gOk($sessionRow['real_actor_id'] !== 'acct_1', 'session_subject_resolved_by_ownership_not_actor');

$authHmac = 'auth-id-v1:' . str_repeat('a', 64);
$subjects->bind('AUTH_IDENTIFIER_HMAC', $authHmac, 'acct_1');
$hmacRow = mp01gRow('AUTH_LOGIN_FAILED', $authHmac, '2026-08-15T01:02:00.000001Z', '8', 'AUTH', 'AUTH_IDENTIFIER_HMAC', 'UNKNOWN');
$hmacPage = (new AuditReadService(new Mp01gFakeReadRepository([$hmacRow]), $authorizer, $selfPolicy, $codec))->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority);
mp01gOk(count($hmacPage->items) === 1 && !array_key_exists('target_id', $hmacPage->items[0]), 'auth_identifier_hmac_trusted_binding_without_exposure');

$subjects->bind('STEP_UP_CHALLENGE', 'challenge_123', 'acct_1');
$stepRow = mp01gRow('STEP_UP_CHALLENGE_SUCCEEDED', 'challenge_123', '2026-08-15T01:03:00.000001Z', '7', 'SECURITY', 'STEP_UP_CHALLENGE', 'security_system');
$stepPage = (new AuditReadService(new Mp01gFakeReadRepository([$stepRow]), $authorizer, $selfPolicy, $codec))->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority);
mp01gOk(count($stepPage->items) === 1, 'future_step_up_target_trusted_binding');

mp01gBlocked(fn() => AuditReadFilter::fromArray(['unknown' => 'x'], $modules), 'unknown_filter');
mp01gBlocked(fn() => AuditReadFilter::fromArray(['role' => 'ADMIN'], $modules), 'request_role_injection');
mp01gBlocked(fn() => AuditReadQuery::selfSecurity(AuditReadFilter::fromArray(['target_id' => 'acct_2'], $modules)), 'self_target_override');
mp01gBlocked(fn() => AuditReadQuery::selfSecurity(AuditReadFilter::fromArray(['real_actor_id' => 'acct_2'], $modules)), 'self_actor_override');
mp01gBlocked(fn() => AuditReadFilter::fromArray(['created_at_from' => 'not-a-time'], $modules), 'invalid_time_filter');
mp01gBlocked(fn() => AuditReadFilter::fromArray(['target_id' => 'token:raw-secret'], $modules), 'secret_filter_value');
mp01gBlocked(fn() => AuditReadQuery::selfSecurity($emptyFilter, null, 101), 'oversized_page');
mp01gBlocked(fn() => AuditReadQuery::selfSecurity($emptyFilter, null, 0), 'zero_page');
mp01gBlocked(fn() => AuditReadQuery::selfSecurity($emptyFilter, null, 25, 'event_type_asc'), 'unsupported_sort');
mp01gBlocked(fn() => AuditReadQuery::selfSecurity($emptyFilter, 'not-a-cursor'), 'invalid_cursor_transport');
mp01gBlocked(fn() => $codec->decode(substr((string)$page->nextCursor, 0, -1) . 'A'), 'cursor_tampering');
mp01gBlocked(fn() => (new AuditReadCursorCodec(str_repeat('other-secret-', 4)))->decode((string)$page->nextCursor), 'cursor_wrong_key');
mp01gBlocked(fn() => new AuditReadCursorCodec('short'), 'cursor_weak_secret');
mp01gBlocked(fn() => TrustedAuditReadAuthority::fromTrustedBackend(mp01gActor('acct_1'), ['UNKNOWN_READ']), 'unknown_capability');
$noCapability = TrustedAuditReadAuthority::fromTrustedBackend(mp01gActor('acct_1'), []);
mp01gBlocked(fn() => $service->read($selfQuery, $noCapability), 'default_deny');
mp01gBlocked(fn() => AuditReadQuery::internalScoped($emptyFilter, AuditReadAccess::INTERNAL_SCOPED, 'WILDCARD', 'acct_1', AuditReadAccess::REASON_SUPPORT, AuditReadProjection::named(AuditReadProjection::INTERNAL_SCOPED)), 'unknown_scope');
mp01gBlocked(fn() => AuditReadQuery::internalScoped($emptyFilter, AuditReadAccess::INTERNAL_SCOPED, AuditReadAccess::SCOPE_ACCOUNT, '*', AuditReadAccess::REASON_SUPPORT, AuditReadProjection::named(AuditReadProjection::INTERNAL_SCOPED)), 'wildcard_scope');
mp01gBlocked(fn() => AuditReadQuery::internalScoped($emptyFilter, AuditReadAccess::INTERNAL_SCOPED, AuditReadAccess::SCOPE_ACCOUNT, 'acct_1', '', AuditReadProjection::named(AuditReadProjection::INTERNAL_SCOPED)), 'missing_reason');
mp01gBlocked(fn() => AuditReadQuery::internalScoped($emptyFilter, AuditReadAccess::INTERNAL_SCOPED, AuditReadAccess::SCOPE_ACCOUNT, 'acct_1', 'FREE_FORM', AuditReadProjection::named(AuditReadProjection::INTERNAL_SCOPED)), 'unknown_reason');
mp01gBlocked(fn() => AuditReadQuery::internalScoped($emptyFilter, AuditReadAccess::INTERNAL_SCOPED, AuditReadAccess::SCOPE_ACCOUNT, 'acct_1', AuditReadAccess::REASON_SUPPORT, AuditReadProjection::named(AuditReadProjection::ADMIN_PRIVILEGED)), 'privileged_projection_without_capability');
mp01gBlocked(fn() => AuditReadProjection::named('RAW_PERSISTENCE_ROW'), 'raw_projection');
$ineligibleService = new AuditReadService(new Mp01gFakeReadRepository([mp01gRow('ROLE_ASSIGNED', 'acct_1', '2026-08-15T01:00:00.000003Z', 'd', 'ROLE')]), $authorizer, $selfPolicy, $codec);
mp01gBlocked(fn() => $ineligibleService->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority), 'ineligible_self_event');
$crossService = new AuditReadService(new Mp01gFakeReadRepository([mp01gRow('AUTH_LOGIN_SUCCEEDED', 'acct_2', '2026-08-15T01:00:00.000003Z', 'c')]), $authorizer, $selfPolicy, $codec);
mp01gBlocked(fn() => $crossService->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority), 'cross_account_self_timeline');
$subjects->bind('SESSION', 'session_999', 'acct_2');
$crossSession = mp01gRow('AUTH_SESSION_CREATED', 'session_999', '2026-08-15T01:04:00.000001Z', '6', 'SESSION', 'SESSION', 'system_actor');
mp01gBlocked(fn() => (new AuditReadService(new Mp01gFakeReadRepository([$crossSession]), $authorizer, $selfPolicy, $codec))->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority), 'cross_account_session_binding');
$unboundHmac = mp01gRow('AUTH_LOGIN_FAILED', 'auth-id-v1:' . str_repeat('b', 64), '2026-08-15T01:05:00.000001Z', '5', 'AUTH', 'AUTH_IDENTIFIER_HMAC', 'UNKNOWN');
mp01gBlocked(fn() => (new AuditReadService(new Mp01gFakeReadRepository([$unboundHmac]), $authorizer, $selfPolicy, $codec))->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority), 'unbound_auth_identifier_hmac');
$crossHmacId = 'auth-id-v1:' . str_repeat('c', 64); $subjects->bind('AUTH_IDENTIFIER_HMAC', $crossHmacId, 'acct_2');
$crossHmac = mp01gRow('AUTH_PASSWORD_RECOVERY_REQUESTED', $crossHmacId, '2026-08-15T01:06:00.000001Z', '4', 'AUTH', 'AUTH_IDENTIFIER_HMAC', 'UNKNOWN');
mp01gBlocked(fn() => (new AuditReadService(new Mp01gFakeReadRepository([$crossHmac]), $authorizer, $selfPolicy, $codec))->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority), 'cross_account_auth_identifier_hmac');
$unboundStep = mp01gRow('STEP_UP_CHALLENGE_FAILED', 'challenge_unbound', '2026-08-15T01:07:00.000001Z', '3', 'SECURITY', 'STEP_UP_CHALLENGE', 'security_system');
mp01gBlocked(fn() => (new AuditReadService(new Mp01gFakeReadRepository([$unboundStep]), $authorizer, $selfPolicy, $codec))->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority), 'unbound_future_step_up_target');
$unorderedService = new AuditReadService(new Mp01gFakeReadRepository(array_reverse($rows)), $authorizer, $selfPolicy, $codec);
mp01gBlocked(fn() => $unorderedService->read(AuditReadQuery::selfSecurity($emptyFilter), $selfAuthority), 'unstable_order');
$tooManyService = new AuditReadService(new Mp01gFakeReadRepository([$rows[0], $rows[1], mp01gRow('AUTH_LOGOUT', 'acct_1', '2026-08-15T00:59:59.000001Z', 'd', 'SESSION')]), $authorizer, $selfPolicy, $codec);
mp01gBlocked(fn() => $tooManyService->read(AuditReadQuery::selfSecurity($emptyFilter, null, 1), $selfAuthority), 'repository_bound_violation');
$mismatchFilter = AuditReadFilter::fromArray(['event_type' => 'AUTH_LOGOUT'], $modules);
mp01gBlocked(fn() => $service->read(AuditReadQuery::selfSecurity($mismatchFilter), $selfAuthority), 'filter_mismatch');
$wrongScope = AuditReadQuery::internalScoped($emptyFilter, AuditReadAccess::INTERNAL_SCOPED, AuditReadAccess::SCOPE_ACCOUNT, 'acct_9', AuditReadAccess::REASON_SUPPORT, AuditReadProjection::named(AuditReadProjection::INTERNAL_SCOPED));
mp01gBlocked(fn() => $service->read($wrongScope, $internalAuthority), 'internal_scope_escape');
mp01gBlocked(fn() => AuditReadQuery::internalScoped($emptyFilter, AuditReadAccess::SELF_SECURITY, AuditReadAccess::SCOPE_SELF_ACCOUNT, 'acct_1', AuditReadAccess::REASON_SELF_SECURITY, AuditReadProjection::named(AuditReadProjection::SELF_SECURITY)), 'self_capability_internal_factory');

echo 'SELF_TIMELINE_CANONICAL_ELIGIBLE_EVENTS=12/12' . PHP_EOL;
echo 'SELF_TIMELINE_USES_CANONICAL_POLICY=true' . PHP_EOL;
echo 'SELF_TIMELINE_EVENT_ELIGIBILITY_FAIL_CLOSED=true' . PHP_EOL;
echo 'SELF_TIMELINE_TARGET_OVERRIDE_ALLOWED=false' . PHP_EOL;
echo 'SELF_TIMELINE_DIRECT_TARGET_EQUALITY_ASSUMPTION=false' . PHP_EOL;
echo 'SELF_TIMELINE_SUBJECT_BINDING=TRUSTED_FAIL_CLOSED_RESOLVER' . PHP_EOL;
echo 'SELF_ACCOUNT_TARGET_BINDING_TEST=PASS' . PHP_EOL;
echo 'SELF_SESSION_TARGET_BINDING_TEST=PASS' . PHP_EOL;
echo 'SELF_AUTH_IDENTIFIER_HMAC_BINDING_TEST=PASS' . PHP_EOL;
echo 'SELF_STEP_UP_TARGET_BINDING_TEST=PASS' . PHP_EOL;
echo 'CROSS_ACCOUNT_BINDING_BLOCKED=true' . PHP_EOL;
echo 'UNRESOLVED_SUBJECT_BINDING_BLOCKED=true' . PHP_EOL;
echo 'DEFAULT_AUDIT_READ_DENY=true' . PHP_EOL;
echo 'UNKNOWN_READ_CAPABILITY_ALLOWED=false' . PHP_EOL;
echo 'AUDIT_READ_ACCESS_IS_AUDITABLE=true' . PHP_EOL;
echo 'AUDIT_READ_ACCESS_EMISSION_ACTIVE=false' . PHP_EOL;
echo 'SEMANTIC_TESTS=' . $semanticPass . '/' . $semanticTotal . '_PASS' . PHP_EOL;
echo 'NEGATIVES=' . $negativeBlocked . '/' . $negativeTotal . '_BLOCKED' . PHP_EOL;
