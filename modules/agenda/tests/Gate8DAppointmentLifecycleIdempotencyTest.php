<?php
declare(strict_types=1);

require_once __DIR__ . '/../contracts/AppointmentLifecycleContract.php';
foreach (glob(__DIR__ . '/../appointments/*.php') as $file) require_once $file;

use Agenda\Appointments\AppointmentConcurrencyGuard;
use Agenda\Appointments\AppointmentDomainException;
use Agenda\Appointments\AppointmentIdempotencyDecision;
use Agenda\Appointments\AppointmentIdempotencyGuard;
use Agenda\Appointments\AppointmentIdempotencyKey;
use Agenda\Appointments\AppointmentLifecycleDefinition;
use Agenda\Appointments\AppointmentLifecycleMachine;
use Agenda\Appointments\AppointmentMutationPlan;
use Agenda\Appointments\AppointmentOperationFingerprint;
use Agenda\Appointments\AppointmentSlotClaim;
use Agenda\Appointments\AppointmentSlotIdentity;
use Agenda\Appointments\AppointmentSnapshot;
use Agenda\Appointments\AppointmentTransitionCommand;
use Agenda\Contracts\AppointmentLifecycleContract;

function gate8dAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function gate8dThrows(callable $callback, string $reason, string $message): void
{
    try { $callback(); }
    catch (AppointmentDomainException $error) {
        if ($error->reason() === $reason) return;
        throw new RuntimeException($message . ' (' . $error->reason() . ')');
    }
    throw new RuntimeException($message);
}

function gate8dSlot(
    string $profile = 'profile-gate8d',
    string $consultorio = 'consultorio-gate8d',
    string $start = '2026-07-21T09:00:00-06:00',
    string $end = '2026-07-21T09:30:00-06:00',
    string $timezone = 'America/Mexico_City'
): AppointmentSlotIdentity {
    return new AppointmentSlotIdentity($profile, $consultorio, $timezone, $start, $end);
}

function gate8dSnapshot(string $appointment = 'appointment-gate8d', string $state = 'tentative', int $version = 1, ?AppointmentSlotIdentity $slot = null): AppointmentSnapshot
{
    $slot ??= gate8dSlot();
    return new AppointmentSnapshot($appointment, $slot->profileId(), $slot->consultorioId(), $state, $version, $slot, 1);
}

function gate8dCommand(
    string $appointment = 'appointment-gate8d',
    int $expectedVersion = 1,
    string $from = 'tentative',
    string $to = 'confirmed',
    string $operation = 'operation-gate8d',
    string $key = 'idempotency-gate8d',
    string $reason = 'confirmation',
    ?AppointmentSlotIdentity $requestedSlot = null
): AppointmentTransitionCommand {
    return new AppointmentTransitionCommand(
        $operation,
        $key,
        'correlation-gate8d',
        $appointment,
        $expectedVersion,
        $from,
        $to,
        $reason,
        'account-gate8d',
        'operator-gate8d',
        '2026-07-21T08:30:00-06:00',
        $requestedSlot
    );
}

function gate8dSha256(string $path): string
{
    $digest = hash_file('sha256', $path);
    gate8dAssert(is_string($digest), 'protected file exists: ' . $path);
    return $digest;
}

function gate8dSource(array $paths): string
{
    $source = '';
    foreach ($paths as $path) {
        $lines = file($path);
        gate8dAssert(is_array($lines), 'source is readable: ' . $path);
        $source .= implode('', $lines);
    }
    return $source;
}

$definition = new AppointmentLifecycleDefinition();
$expectedStates = ['tentative', 'pending_otp', 'pending', 'scheduled', 'confirmed', 'canceled', 'no_show'];
$expectedMatrix = [
    'tentative' => ['confirmed', 'canceled'],
    'pending_otp' => ['confirmed', 'canceled'],
    'pending' => ['confirmed', 'canceled'],
    'scheduled' => ['confirmed', 'canceled'],
    'confirmed' => ['tentative', 'canceled', 'no_show'],
    'canceled' => [],
    'no_show' => [],
];

// 1-13: catálogo canónico y matriz exhaustiva derivados de Gate 8A.
gate8dAssert($definition->lifecycleId() === 'pg03-appointment-lifecycle', 'lifecycle id exact');
gate8dAssert($definition->version() === 1, 'lifecycle version exact');
gate8dAssert($definition->states() === $expectedStates && count($definition->states()) === 7, 'seven exact states');
gate8dAssert($definition->terminalStates() === ['canceled', 'no_show'], 'two exact terminal states');
gate8dAssert($definition->slotOccupyingStates() === ['tentative', 'pending_otp', 'pending', 'scheduled', 'confirmed'], 'five exact slot states');
gate8dAssert($definition->matrix() === $expectedMatrix, 'exact transition matrix');
$allowedCount = 0;
$deniedCount = 0;
foreach ($expectedStates as $from) {
    foreach ($expectedStates as $to) {
        $decision = $definition->evaluate($from, $to);
        $base = AppointmentLifecycleContract::transition($from, $to);
        gate8dAssert($decision->allowed() === $base->allowed(), 'matrix equals Gate 8A for ' . $from . ' to ' . $to);
        if ($decision->allowed()) {
            $allowedCount++;
            gate8dAssert(in_array($to, $expectedMatrix[$from], true), 'allowed transition is declared');
        } else {
            $deniedCount++;
            gate8dAssert($decision->reason() === 'invalid_transition' && $decision->httpStatus() === 409, 'denied transition closes with 409');
        }
    }
}
gate8dAssert($allowedCount === 11 && $deniedCount === 38, 'matrix contains 11 allowed and 38 denied');
$unknown = $definition->evaluate('legacy_unknown', 'confirmed');
gate8dAssert(!$unknown->allowed() && $unknown->reason() === 'unknown_appointment_state' && $unknown->httpStatus() === 409, 'unknown state fails closed');
gate8dAssert(!$definition->clinicalEncounter() && !AppointmentLifecycleContract::agendaAppointmentIsClinicalEncounter(), 'Agenda is not a clinical encounter');
foreach (['rescheduled', 'in_progress', 'finished'] as $legacyState) {
    gate8dAssert(!$definition->isState($legacyState), $legacyState . ' is not canonical');
}

// 14-30: snapshot, optimistic version y transición.
$slot = gate8dSlot();
$snapshot = gate8dSnapshot(slot: $slot);
gate8dAssert($snapshot->appointmentId() === 'appointment-gate8d' && $snapshot->aggregateVersion() === 1, 'valid snapshot');
try {
    (new ReflectionClass($snapshot))->getProperty('state')->setValue($snapshot, 'canceled');
    throw new RuntimeException('snapshot accepted mutation');
} catch (Error) {}
gate8dThrows(fn() => gate8dSnapshot(version: 0), 'aggregate_version_conflict', 'aggregate version below one closes');
gate8dThrows(fn() => new AppointmentSnapshot('appointment', 'profile-gate8d', 'consultorio-gate8d', 'tentative', 1, $slot, 2), 'lifecycle_version_mismatch', 'lifecycle mismatch closes');
$machine = new AppointmentLifecycleMachine();
$appointmentMismatch = $machine->evaluate($snapshot, gate8dCommand(appointment: 'other-appointment'), null, [], $definition);
gate8dAssert(!$appointmentMismatch->allowed() && $appointmentMismatch->reason() === 'appointment_mismatch', 'appointment mismatch closes');
$stateMismatch = $machine->evaluate($snapshot, gate8dCommand(from: 'pending'), null, [], $definition);
gate8dAssert(!$stateMismatch->allowed() && $stateMismatch->reason() === 'state_mismatch', 'from-state mismatch closes');
$versionMismatch = $machine->evaluate($snapshot, gate8dCommand(expectedVersion: 2), null, [], $definition);
gate8dAssert(!$versionMismatch->allowed() && $versionMismatch->reason() === 'aggregate_version_conflict' && $versionMismatch->httpStatus() === 409, 'expected version mismatch closes');
$validCommand = gate8dCommand();
$valid = $machine->evaluate($snapshot, $validCommand, null, [], $definition);
gate8dAssert($valid->allowed() && $valid->aggregateVersionResult() === 2 && $valid->nextSnapshot()?->aggregateVersion() === 2, 'valid transition increments exactly one');
$invalid = $machine->evaluate($snapshot, gate8dCommand(to: 'pending'), null, [], $definition);
gate8dAssert(!$invalid->allowed() && $invalid->aggregateVersionResult() === null, 'invalid transition does not increment');
gate8dAssert($valid->event() !== null && $invalid->event() === null, 'only valid transition creates event');
gate8dAssert($valid->event()?->sequence() === $valid->nextSnapshot()?->aggregateVersion(), 'event sequence equals next version');
gate8dAssert($valid->event()?->actorRealId() === 'account-gate8d' && $valid->event()?->actorEffectiveId() === 'operator-gate8d', 'event attributes both actors');
$eventPayload = $valid->event()?->toArray() ?? [];
foreach (['patient', 'name', 'phone', 'email', 'clinical_reason', 'payload'] as $forbiddenField) {
    gate8dAssert(!array_key_exists($forbiddenField, $eventPayload), 'event omits ' . $forbiddenField);
}
$validAgain = $machine->evaluate($snapshot, $validCommand, null, [], $definition);
gate8dAssert($valid->event()?->eventId() === $validAgain->event()?->eventId(), 'event id deterministic');
gate8dAssert($valid->event()?->toArray() === $validAgain->event()?->toArray(), 'same input creates same event');
gate8dThrows(fn() => gate8dCommand(from: 'legacy_state'), 'unknown_appointment_state', 'unknown command state closes');
gate8dThrows(fn() => new AppointmentTransitionCommand('operation', 'key', 'correlation', 'appointment', 1, 'tentative', 'confirmed', 'unsafe/reason', 'actor', 'effective', '2026-07-21T08:30:00-06:00'), 'invalid_reason', 'unsafe reason closes');
gate8dThrows(fn() => new AppointmentTransitionCommand('operation', 'key', 'correlation', 'appointment', 1, 'tentative', 'confirmed', 'reason', 'unsafe/actor', 'effective', '2026-07-21T08:30:00-06:00'), 'invalid_actor', 'unsafe actor closes');
gate8dThrows(fn() => new AppointmentTransitionCommand('operation', 'key', 'correlation', 'appointment', 1, 'tentative', 'confirmed', 'reason', 'actor', 'effective', '2026-07-21 08:30:00'), 'invalid_timestamp', 'implicit command timestamp closes');
$terminal = $machine->evaluate($snapshot, gate8dCommand(to: 'canceled'), null, [], $definition);
gate8dAssert($terminal->allowed() && $terminal->slotClaim() !== null && !$terminal->slotClaim()->active(), 'terminal transition releases active claim');
$foreignRequestedSlot = gate8dSlot(profile: 'profile-other');
$invalidRequestedSlot = $machine->evaluate($snapshot, gate8dCommand(requestedSlot: $foreignRequestedSlot), null, [], $definition);
gate8dAssert(!$invalidRequestedSlot->allowed() && $invalidRequestedSlot->reason() === 'invalid_slot', 'foreign requested slot closes');

// 31-49: idempotencia, fingerprint, replay y conflicto.
gate8dAssert((new AppointmentIdempotencyKey('safe.Key:01'))->value() === 'safe.Key:01', 'valid key accepted');
gate8dThrows(fn() => new AppointmentIdempotencyKey(''), 'invalid_idempotency_key', 'empty key closes');
gate8dThrows(fn() => new AppointmentIdempotencyKey('unsafe/key'), 'invalid_idempotency_key', 'unsafe key closes');
gate8dThrows(fn() => new AppointmentIdempotencyKey(str_repeat('a', 129)), 'invalid_idempotency_key', 'long key closes');
$fingerprintA = AppointmentOperationFingerprint::fromCommand($validCommand);
$fingerprintB = AppointmentOperationFingerprint::fromCommand(gate8dCommand());
$fingerprintDifferent = AppointmentOperationFingerprint::fromCommand(gate8dCommand(reason: 'different_reason'));
gate8dAssert($fingerprintA->algorithm() === 'sha256' && strlen($fingerprintA->value()) === 64, 'fingerprint is SHA-256');
gate8dAssert($fingerprintA->value() === $fingerprintB->value(), 'same command has stable fingerprint');
gate8dAssert($fingerprintA->value() !== $fingerprintDifferent->value(), 'semantic field changes fingerprint');
$idempotencyGuard = new AppointmentIdempotencyGuard();
gate8dAssert($idempotencyGuard->evaluate($validCommand, null)->status() === AppointmentIdempotencyDecision::NEW_OPERATION, 'no record is new operation');
$record = $valid->idempotencyRecord();
gate8dAssert($record !== null, 'valid result builds minimized record');
$replayGuard = $idempotencyGuard->evaluate($validCommand, $record);
gate8dAssert($replayGuard->status() === AppointmentIdempotencyDecision::REPLAY, 'same key and fingerprint replay');
$replay = $machine->evaluate($snapshot, $validCommand, $record, [], $definition);
gate8dAssert($replay->replayed() && $replay->httpStatus() === $record->originalHttpStatus(), 'replay preserves original HTTP status');
gate8dAssert($replay->resultDigest() === $record->resultDigest(), 'replay preserves result digest');
gate8dAssert(!$replay->mutationEffective() && $replay->aggregateVersionResult() === 2, 'replay does not increment version');
gate8dAssert($replay->event() === null && $replay->slotClaim() === null, 'replay creates no event or claim');
$conflictCommand = gate8dCommand(reason: 'different_reason');
$conflict = $machine->evaluate($snapshot, $conflictCommand, $record, [], $definition);
gate8dAssert(!$conflict->allowed() && $conflict->reason() === 'idempotency_conflict' && $conflict->httpStatus() === 409, 'different fingerprint conflicts with 409');
gate8dAssert($conflict->event() === null && !$conflict->mutationEffective(), 'idempotency conflict has no event or mutation');
$recordPayload = $record->toArray();
gate8dAssert(array_keys($recordPayload) === ['idempotency_key', 'operation_id', 'fingerprint', 'appointment_id', 'outcome_code', 'original_http_status', 'result_digest', 'aggregate_version_result', 'recorded_at'], 'record fields are exactly minimized');
foreach (['payload', 'patient', 'name', 'phone', 'email', 'clinical_reason', 'headers', 'cookies', 'tokens'] as $forbiddenField) {
    gate8dAssert(!array_key_exists($forbiddenField, $recordPayload), 'record omits ' . $forbiddenField);
}

// 50-71: slot canónico, claims y simulación pura de doble reserva.
gate8dAssert($slot->timezone() === 'America/Mexico_City' && str_contains($slot->startAt(), '-06:00'), 'valid RFC3339 slot preserves IANA timezone');
gate8dThrows(fn() => gate8dSlot(timezone: 'Not/A_Zone'), 'invalid_slot', 'invalid timezone closes');
gate8dThrows(fn() => gate8dSlot(start: '2026-07-21T09:00:00', end: '2026-07-21T09:30:00'), 'invalid_slot', 'implicit offset closes');
gate8dThrows(fn() => gate8dSlot(end: '2026-07-21T09:00:00-06:00'), 'invalid_slot', 'equal slot closes');
gate8dThrows(fn() => gate8dSlot(start: '2026-07-21T10:00:00-06:00', end: '2026-07-21T09:00:00-06:00'), 'invalid_slot', 'reversed slot closes');
$slotCopy = gate8dSlot();
gate8dAssert($slot->slotKey() === $slotCopy->slotKey(), 'slot key deterministic');
gate8dAssert($slot->lockScope() === $slotCopy->lockScope(), 'lock scope deterministic');
gate8dAssert($slot->uniqueClaimKey() === $slotCopy->uniqueClaimKey(), 'unique claim key deterministic');
$adjacent = gate8dSlot(start: '2026-07-21T09:30:00-06:00', end: '2026-07-21T10:00:00-06:00');
$overlap = gate8dSlot(start: '2026-07-21T09:15:00-06:00', end: '2026-07-21T09:45:00-06:00');
gate8dAssert(!$slot->overlaps($adjacent), 'adjacent half-open slots do not overlap');
gate8dAssert($slot->overlaps($overlap), 'strict overlap conflicts');
$guard = new AppointmentConcurrencyGuard();
$activeClaim = new AppointmentSlotClaim('appointment-other', $slot, 'confirmed', 3, true);
gate8dAssert($guard->evaluate($adjacent, [$activeClaim])->allowed(), 'adjacent active claim allows');
$exactConflict = $guard->evaluate($slotCopy, [$activeClaim]);
gate8dAssert(!$exactConflict->allowed() && $exactConflict->reason() === 'slot_conflict' && $exactConflict->httpStatus() === 409, 'exact slot conflicts with 409');
gate8dAssert($exactConflict->conflictingAppointmentId() !== 'appointment-other' && str_starts_with((string) $exactConflict->conflictingAppointmentId(), 'sha256:'), 'conflicting appointment identity is minimized');
$otherProfileSlot = gate8dSlot(profile: 'profile-other');
$otherConsultorioSlot = gate8dSlot(consultorio: 'consultorio-other');
gate8dAssert($guard->evaluate($slot, [new AppointmentSlotClaim('foreign-profile', $otherProfileSlot, 'confirmed', 1, true)])->allowed(), 'other profile does not conflict');
gate8dAssert($guard->evaluate($slot, [new AppointmentSlotClaim('foreign-office', $otherConsultorioSlot, 'confirmed', 1, true)])->allowed(), 'other consultorio does not conflict');
gate8dAssert($guard->evaluate($slot, [new AppointmentSlotClaim('canceled', $slot, 'canceled', 2, false)])->allowed(), 'canceled claim does not block');
gate8dAssert($guard->evaluate($slot, [new AppointmentSlotClaim('no-show', $slot, 'no_show', 2, false)])->allowed(), 'no-show claim does not block');
gate8dAssert($guard->evaluate($slot, [new AppointmentSlotClaim('inactive', $slot, 'confirmed', 2, false)])->allowed(), 'inactive claim does not block');
gate8dAssert($guard->evaluate($slot, [$activeClaim], 'appointment-other')->allowed(), 'current appointment can be excluded');
gate8dThrows(fn() => new AppointmentSlotClaim('invalid', $slot, 'canceled', 1, true), 'invalid_claim', 'terminal active claim closes');
gate8dAssert($guard->evaluate($slot, ['untyped'])->reason() === 'invalid_claim', 'untyped claim closes');
$activeClaimB = new AppointmentSlotClaim('appointment-alpha', $overlap, 'tentative', 1, true);
$permutationOne = $guard->evaluate($slot, [$activeClaim, $activeClaimB]);
$permutationTwo = $guard->evaluate($slot, [$activeClaimB, $activeClaim]);
gate8dAssert($permutationOne->toArray() === $permutationTwo->toArray(), 'claim permutation does not change decision');

$snapshotA = gate8dSnapshot('appointment-a', slot: $slot);
$snapshotB = gate8dSnapshot('appointment-b', slot: $slot);
$commandA = gate8dCommand('appointment-a', operation: 'operation-a', key: 'idempotency-a');
$commandB = gate8dCommand('appointment-b', operation: 'operation-b', key: 'idempotency-b');
$reservationA = $machine->evaluate($snapshotA, $commandA, null, [], $definition);
gate8dAssert($reservationA->allowed() && $reservationA->slotClaim()?->active(), 'first competing reservation is allowed and claims slot');
$reservationB = $machine->evaluate($snapshotB, $commandB, null, [$reservationA->slotClaim()], $definition);
gate8dAssert(!$reservationB->allowed() && $reservationB->reason() === 'slot_conflict' && $reservationB->httpStatus() === 409, 'second competing reservation conflicts');
gate8dAssert($reservationB->event() === null && $reservationB->slotClaim() === null, 'second reservation creates no event or claim');
$activeClaims = array_values(array_filter([$reservationA->slotClaim(), $reservationB->slotClaim()], static fn($claim): bool => $claim instanceof AppointmentSlotClaim && $claim->active()));
gate8dAssert(count($activeClaims) === 1, 'two synthetic reservations leave exactly one active claim');

// 72-82: plan transaccional declarativo exacto, sin ejecución.
$plan = new AppointmentMutationPlan();
$expectedSteps = [
    'begin_transaction', 'lock_idempotency_key', 'resolve_idempotency', 'lock_appointment',
    'lock_slot_scope', 'verify_expected_version', 'verify_lifecycle_transition',
    'verify_active_slot_uniqueness', 'persist_appointment', 'append_lifecycle_event',
    'persist_idempotency_result', 'commit',
];
gate8dAssert(count($plan->steps()) === 12 && $plan->steps() === $expectedSteps, 'mutation plan has exact twelve ordered steps');
gate8dAssert($plan->transactionRequired(), 'transaction required');
gate8dAssert($plan->idempotencyLockRequired(), 'idempotency lock required');
gate8dAssert($plan->appointmentLockRequired(), 'appointment lock required');
gate8dAssert($plan->slotLockRequired(), 'slot lock required');
gate8dAssert($plan->activeSlotUniqueConstraintRequired(), 'active unique claim required');
gate8dAssert($plan->appendEventInSameTransaction(), 'event must append in same transaction');
gate8dAssert($plan->idempotencyResultInSameTransaction(), 'idempotency result must persist in same transaction');
gate8dAssert($plan->failureAction() === 'rollback' && $plan->rollbackRequired() && !$plan->executesOperations(), 'rollback declared and operations not executed');

// 83-94: byte-equivalence y preservación de gates previos.
$root = dirname(__DIR__, 3);
$protected = [
    'api/agenda/index.php' => '94267a85ecbf9a66f641671e83f13b9764218015a89371a2e9a97e551f2f5239',
    'modules/agenda/tests/Gate8ACanonicalContractsTest.php' => 'efae63a8e5e353288a24e60770e0f7128df89c75411b6e6541c2daeda2637ecd',
    'modules/agenda/tests/Gate8BServerAuthoritativeActorsTest.php' => '500f54f198269aca29d4066f33308c2c5d3a96b7155ad3af6801cee3fb95366f',
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
foreach ($protected as $relative => $expected) {
    gate8dAssert(gate8dSha256($root . '/' . $relative) === $expected, 'protected file byte-equivalent: ' . $relative);
}

$manifestGroups = [
    '/tmp/mxmed-activity08-gate8d-preflight-v2/gate8b-security-before.sha256',
    '/tmp/mxmed-activity08-gate8d-preflight-v2/gate8c-availability-before.sha256',
];
foreach ($manifestGroups as $manifestPath) {
    $manifestLines = file($manifestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    gate8dAssert(is_array($manifestLines), 'preflight manifest is readable');
    foreach ($manifestLines as $manifestLine) {
        [$expected, $relative] = preg_split('/\s+/', trim($manifestLine), 2);
        gate8dAssert(gate8dSha256($root . '/' . $relative) === $expected, 'Gate 8B/8C surface byte-equivalent: ' . $relative);
    }
}

$planLines = file($root . '/docs/PLAN_MAESTRO_MXMED.md');
gate8dAssert(is_array($planLines), 'Plan Maestro is readable');
$planText = implode('', $planLines);
preg_match('/### PP-304 .*?(?=### PP-305 )/s', $planText, $pp304);
preg_match('/### PP-305 .*?(?=### PP-306 )/s', $planText, $pp305);
preg_match('/### PP-306 .*?(?=### PP-307 )/s', $planText, $pp306);
preg_match('/### PP-307 .*?(?=### PP-[0-9]+ —|\z)/s', $planText, $pp307);
gate8dAssert(isset($pp304[0]) && hash('sha256', $pp304[0]) === 'f2e9f99bf45bd93d457ab987064731b6ab04996fe27874cc06670ea849392cb4', 'PP-304 byte-equivalent');
gate8dAssert(isset($pp305[0]) && hash('sha256', $pp305[0]) === '3d3b6b177d9363cb9cac928f992fcf57f6ba18093a1bcd935c1979b81c4e8288', 'PP-305 byte-equivalent');
gate8dAssert(isset($pp306[0]) && hash('sha256', $pp306[0]) === '30501ff147af8d92266893b048d01616419208d88d9e7bad22895790de34f444', 'PP-306 byte-equivalent');
gate8dAssert(isset($pp307[0]), 'PP-307 block present');
$pp307Normalized = rtrim($pp307[0], "\r\n") . "\n";
gate8dAssert(hash('sha256', $pp307Normalized) === '9b8fcb0498d2c764fc8e39d1f7a2d6d5bb2a1bb1b00cbdd938e9e653a7420b60', 'PP-307 byte-equivalent');
gate8dAssert(substr_count($planText, '### PP-307 —') === 1, 'PP-307 occurs exactly once');

$appointmentFiles = glob(__DIR__ . '/../appointments/*.php');
gate8dAssert(is_array($appointmentFiles) && count($appointmentFiles) === 18, 'appointment domain file count exact');
$appointmentSource = gate8dSource($appointmentFiles);
foreach (['$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_SESSION', '$_COOKIE', 'getallheaders', 'session_start', 'getenv', 'PDO', 'mysqli', 'CREATE TABLE', 'ALTER TABLE', 'DROP TABLE', 'FOR UPDATE', 'beginTransaction', 'file_get_contents', 'file_put_contents', 'fopen', 'error_log', 'random_bytes', 'random_int', 'mt_rand', 'microtime', 'hrtime', "new DateTime('now')", 'curl'] as $forbiddenToken) {
    gate8dAssert(!str_contains($appointmentSource, $forbiddenToken), 'appointment domain omits executable dependency: ' . $forbiddenToken);
}
gate8dAssert(preg_match('/(?<![A-Za-z])time\s*\(/i', $appointmentSource) !== 1, 'appointment domain does not read global clock');
gate8dAssert(!str_contains(strtolower($appointmentSource), 'ttl'), 'no idempotency TTL invented');
gate8dAssert(!str_contains($appointmentSource, 'Agenda\\Controllers') && !str_contains($appointmentSource, 'Agenda\\Repositories'), 'domain has no controller or repository dependencies');

echo "Gate8DAppointmentLifecycleIdempotencyTest PASS\n";
