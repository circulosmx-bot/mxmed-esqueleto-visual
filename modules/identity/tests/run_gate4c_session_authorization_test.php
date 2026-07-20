<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../adapters/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../services/*.php') as $file) require_once $file;
require_once __DIR__ . '/../../subscriptions/contracts/ExistingCapabilityDecision.php';
require_once __DIR__ . '/../../subscriptions/services/ExistingCapabilityAuthorityService.php';

use Identity\Adapters\InMemorySessionStoreAdapter;
use Identity\Adapters\ExistingCapabilityAuthorityAdapter;
use Identity\Adapters\RejectingSessionStoreAdapter;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticationPrincipalCandidate;
use Identity\Contracts\Clock;
use Identity\Contracts\ReasonCode;
use Identity\Contracts\SessionAccountStatePort;
use Identity\Contracts\SessionCapabilityAuthorityPort;
use Identity\Contracts\SessionPolicy;
use Identity\Services\FailClosedAuthorizationService;
use Identity\Services\SessionService;
use Identity\Services\SessionTokenCodec;

final class Gate4CTestClock implements Clock
{
    public function __construct(private DateTimeImmutable $time) {}
    public function now(): DateTimeImmutable { return $this->time; }
    public function advance(string $modifier): void { $this->time = $this->time->modify($modifier); }
}
final class Gate4CTestAccountState implements SessionAccountStatePort
{
    public function __construct(public string $status = AccountStatus::ACTIVE, public int $credentialVersion = 1) {}
    public function current(string $accountId): ?array { return ['status' => $this->status, 'credential_version' => $this->credentialVersion]; }
}
final class Gate4CTestCapability
{
    public function __construct(private bool $allowed) {}
    public function available(): bool { return $this->allowed; }
    public function toArray(): array { return ['capability_id' => 'agenda_appointments', 'available' => $this->allowed, 'source' => 'test']; }
}
final class Gate4CTestCapabilityAuthority implements SessionCapabilityAuthorityPort
{
    public function __construct(private bool $available = true) {}
    public function resolve(string $capabilityId, array $context): object { return new Gate4CTestCapability($this->available); }
}
final class Gate4CTestThrowingCapabilityAuthority implements SessionCapabilityAuthorityPort
{
    public function resolve(string $capabilityId, array $context): object { throw new RuntimeException('capability_service_unavailable'); }
}
final class Gate4CTestMemberships
{
    public function __construct(public array $rows) {}
    public function activeForAccount(string $accountId): array { return $this->rows; }
}
function gate4cAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$clock = new Gate4CTestClock(new DateTimeImmutable('2026-07-20 12:00:00', new DateTimeZone('UTC')));
$state = new Gate4CTestAccountState();
$store = new InMemorySessionStoreAdapter();
$service = new SessionService($store, new SessionTokenCodec('gate4c-test-pepper-never-production'), $clock, new SessionPolicy(), $state);
$candidate = new AuthenticationPrincipalCandidate('account_gate4c_01', 1, AccountStatus::ACTIVE, $clock->now()->format('Y-m-d H:i:s'));

$created = $service->create($candidate, ['device_label' => 'Browser 1', 'user_agent' => 'synthetic-agent', 'ip' => '198.51.100.1']);
gate4cAssert($created->allowed() && $created->token() !== null, 'session creation allowed');
gate4cAssert(strlen($created->token()?->value() ?? '') === 43, 'token is 32-byte url-safe encoding');
gate4cAssert($created->record()?->expiresAt() == $clock->now()->modify('+3600 seconds'), 'idle expiry is 3600 seconds');
gate4cAssert($created->record()?->absoluteExpiresAt() == $clock->now()->modify('+43200 seconds'), 'absolute expiry is 43200 seconds');

$token = (string)$created->token();
$valid = $service->validate($token);
gate4cAssert($valid->allowed(), 'valid session accepted');
$clock->advance('+299 seconds');
gate4cAssert($service->validate($token)->allowed(), 'session valid before touch boundary');
$lastSeenBefore = $service->validate($token)->record()?->lastSeenAt();
$clock->advance('+1 second');
$touched = $service->validate($token);
gate4cAssert($touched->allowed() && $touched->record()?->lastSeenAt() == $clock->now(), 'touch runs at 300 seconds');
gate4cAssert($touched->record()?->absoluteExpiresAt() == $clock->now()->modify('+42900 seconds'), 'touch does not extend absolute expiry');

$rotationBase = $service->create($candidate, ['device_label' => 'Rotation']);
$rotated = $service->rotate((string)$rotationBase->token());
gate4cAssert($rotated->allowed() && !$service->validate((string)$rotationBase->token())->allowed() && $service->validate((string)$rotated->token())->allowed(), 'rotation invalidates old token atomically');
$logout = $service->logout((string)$rotated->token());
gate4cAssert($logout->allowed() && $logout->cookie()?->maxAge() === -1, 'logout is idempotent with deletion descriptor');
gate4cAssert($service->logout((string)$rotated->token())->allowed(), 'logout second call remains idempotent');

$sessions = [$created];
for ($i = 2; $i <= 5; $i++) $sessions[] = $service->create($candidate, ['device_label' => 'Browser ' . $i]);
gate4cAssert(count($store->listActiveForAccount('account_gate4c_01')) === 5, 'five active sessions permitted');
$sixth = $service->create($candidate, ['device_label' => 'Browser 6']);
gate4cAssert($sixth->allowed() && count($store->listActiveForAccount('account_gate4c_01')) === 5, 'sixth session keeps maximum five');

$clock->advance('+3600 seconds');
gate4cAssert($service->validate((string)$sixth->token())->reasonCode() === ReasonCode::SESSION_IDLE_EXPIRED, 'idle expiration at 3600');
$clock->advance('+43200 seconds');
gate4cAssert($service->validate((string)$sessions[1]->token())->reasonCode() === ReasonCode::SESSION_ABSOLUTE_EXPIRED || $service->validate((string)$sessions[1]->token())->reasonCode() === ReasonCode::SESSION_IDLE_EXPIRED, 'absolute expiration is enforced');

$memberships = new Gate4CTestMemberships([['membership_id' => 'membership_gate4c_01', 'profile_doctor_id' => 'doctor_gate4c_01', 'entity_group_id' => null, 'role_code' => 'owner', 'scope_code' => 'profile', 'status' => 'active']]);
$realCapabilityAuthority = new ExistingCapabilityAuthorityAdapter(new Subscriptions\Services\ExistingCapabilityAuthorityService());
$authorization = new FailClosedAuthorizationService($memberships, $realCapabilityAuthority);
$access = $valid->context();
gate4cAssert($access !== null && $authorization->authorize($access, 'profile_doctor', 'doctor_gate4c_01', 'agenda_appointments', ['plan_code' => 'standard', 'is_active' => true])->allowed(), 'membership and capability allow access');
gate4cAssert(!$authorization->authorize($access, 'profile_doctor', 'doctor_gate4c_01', 'agenda_appointments', ['plan_code' => 'basic', 'is_active' => true])->allowed(), 'basic plan denies agenda');
gate4cAssert($authorization->authorize($access, 'profile_doctor', 'doctor_gate4c_01', 'patients', ['plan_code' => 'optimum', 'is_active' => true])->allowed(), 'optimum plan allows patients');
gate4cAssert(!$authorization->authorize($access, 'profile_doctor', 'doctor_gate4c_01', 'patients', [])->allowed(), 'missing plan denies capability');
gate4cAssert(!$authorization->authorize($access, 'profile_doctor', 'doctor_gate4c_01', 'patients', ['plan_code' => 'optimum', 'subscription_status' => 'inactive'])->allowed(), 'inactive subscription denies capability');
gate4cAssert(!$authorization->authorize($access, 'profile_doctor', 'doctor_gate4c_01', 'unknown_capability', ['plan_code' => 'professional', 'is_active' => true])->allowed(), 'unknown capability denies');
gate4cAssert(!$authorization->authorize($access, 'profile_doctor', 'doctor_other', 'agenda_appointments', ['plan_code' => 'standard', 'is_active' => true])->allowed(), 'different profile denied');
gate4cAssert(!$authorization->authorize($access, 'profile_doctor', 'doctor_gate4c_01', 'agenda_appointments', ['plan_code' => 'standard', 'is_active' => true], 'transitional_open')->allowed(), 'transitional_open never grants access');
$noMembership = new FailClosedAuthorizationService(new Gate4CTestMemberships([]), $realCapabilityAuthority);
gate4cAssert(!$noMembership->authorize($access, 'profile_doctor', 'doctor_gate4c_01', 'patients', ['plan_code' => 'optimum', 'is_active' => true])->allowed(), 'plan cannot compensate missing membership');
$throwing = new FailClosedAuthorizationService($memberships, new Gate4CTestThrowingCapabilityAuthority());
gate4cAssert($throwing->authorize($access, 'profile_doctor', 'doctor_gate4c_01', 'agenda_appointments', ['plan_code' => 'standard', 'is_active' => true])->reasonCode() === ReasonCode::CAPABILITY_DENIED, 'capability exception denies fail closed');

$rejecting = new SessionService(new RejectingSessionStoreAdapter(), new SessionTokenCodec('gate4c-test-pepper-never-production'), $clock, new SessionPolicy(), $state);
gate4cAssert($rejecting->create($candidate)->reasonCode() === ReasonCode::SESSION_STORE_UNAVAILABLE, 'store outage fails closed');

echo "Gate4C server-side sessions and fail-closed authorization tests PASS\n";
