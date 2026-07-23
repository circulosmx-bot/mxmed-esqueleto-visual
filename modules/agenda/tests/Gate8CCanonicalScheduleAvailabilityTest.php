<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../availability/*.php') as $file) require_once $file;

use Agenda\Availability\AvailabilityCalculationRequest;
use Agenda\Availability\AvailabilityOverride;
use Agenda\Availability\CanonicalAvailabilityCalculator;
use Agenda\Availability\CanonicalAvailabilityException;
use Agenda\Availability\CanonicalScheduleVersion;
use Agenda\Availability\CanonicalScheduleVersionSelector;
use Agenda\Availability\CollisionWindow;
use Agenda\Availability\HolidayClosure;
use Agenda\Availability\WeeklyScheduleWindow;
use Agenda\Contracts\ScheduleAvailabilityContract;

function gate8cAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function gate8cThrows(callable $callback, string $reason, string $message): void
{
    try { $callback(); }
    catch (CanonicalAvailabilityException $error) {
        if ($error->reason() === $reason) return;
        throw new RuntimeException($message . ' (' . $error->reason() . ')');
    }
    throw new RuntimeException($message);
}

function gate8cVersion(string $consultorio = 'consultorio-a', string $timezone = 'America/Mexico_City', int $duration = 30, int $gap = 10, ?array $windows = null): CanonicalScheduleVersion
{
    $windows ??= [
        new WeeklyScheduleWindow(2, '09:00', '11:00'),
        new WeeklyScheduleWindow(3, '13:00', '14:00'),
    ];
    return new CanonicalScheduleVersion('schedule-' . $consultorio, 1, 'doctor-gate8c', $consultorio, $timezone, '2026-07-01', null, $duration, $gap, $windows);
}

function gate8cRequest(array $versions, array $overrides = [], array $holidays = [], array $collisions = [], string $consultorio = 'consultorio-a'): AvailabilityCalculationRequest
{
    return new AvailabilityCalculationRequest('doctor-gate8c', $consultorio, '2026-07-21', $versions, $overrides, $holidays, $collisions);
}

function gate8cSha256(string $path): string
{
    $hash = hash_file('sha256', $path);
    gate8cAssert(is_string($hash), 'hash exists: ' . $path);
    return $hash;
}

$root = dirname(__DIR__, 3);
$valid = gate8cVersion();
gate8cAssert($valid->version() === 1 && $valid->profileId() === 'doctor-gate8c', 'canonical version is valid');
gate8cAssert($valid->timezone() === 'America/Mexico_City' && $valid->durationMinutes() === 30 && $valid->gapMinutes() === 10, 'canonical attributes preserved');
gate8cThrows(fn() => new CanonicalScheduleVersion('bad', 1, 'doctor-gate8c', 'consultorio-a', 'Not/Timezone', '2026-07-01', null, 30, 0, []), 'invalid_timezone', 'invalid IANA timezone closes');
gate8cThrows(fn() => new WeeklyScheduleWindow(0, '09:00', '10:00'), 'invalid_weekday', 'weekday below one closes');
gate8cThrows(fn() => new WeeklyScheduleWindow(8, '09:00', '10:00'), 'invalid_weekday', 'weekday above seven closes');
gate8cThrows(fn() => new WeeklyScheduleWindow(2, '9:00', '10:00'), 'invalid_window', 'malformed time closes');
gate8cThrows(fn() => new WeeklyScheduleWindow(2, '10:00', '10:00'), 'invalid_window', 'zero window closes');
gate8cThrows(fn() => new WeeklyScheduleWindow(2, '11:00', '10:00'), 'invalid_window', 'inverted window closes');
gate8cThrows(fn() => new CanonicalScheduleVersion('bad', 1, 'doctor-gate8c', 'consultorio-a', 'UTC', '2026-07-01', null, 30, 0, [new WeeklyScheduleWindow(2, '09:00', '10:00'), new WeeklyScheduleWindow(2, '09:30', '11:00')]), 'overlapping_windows', 'overlapping weekly windows close');
gate8cAssert(count((new CanonicalScheduleVersion('adjacent', 1, 'doctor-gate8c', 'consultorio-a', 'UTC', '2026-07-01', null, 30, 0, [new WeeklyScheduleWindow(2, '09:00', '10:00'), new WeeklyScheduleWindow(2, '10:00', '11:00')]))->windows()) === 2, 'adjacent weekly windows are valid');
gate8cThrows(fn() => gate8cVersion(duration: 4), 'invalid_duration', 'short duration closes');
gate8cThrows(fn() => gate8cVersion(duration: 721), 'invalid_duration', 'long duration closes');
gate8cThrows(fn() => gate8cVersion(gap: -1), 'invalid_gap', 'negative gap closes');
gate8cThrows(fn() => gate8cVersion(gap: 721), 'invalid_gap', 'long gap closes');
gate8cThrows(fn() => new CanonicalScheduleVersion('range', 1, 'doctor-gate8c', 'consultorio-a', 'UTC', '2026-07-02', '2026-07-01', 30, 0, []), 'invalid_effective_range', 'invalid effective range closes');
try { $reflection = new ReflectionClass($valid); $reflection->getProperty('version')->setValue($valid, 9); throw new RuntimeException('immutable version accepted mutation'); }
catch (Error) { }

$selector = new CanonicalScheduleVersionSelector();
gate8cAssert($selector->select([$valid], 'doctor-gate8c', 'consultorio-a', '2026-07-21') === $valid, 'selector finds one effective version');
gate8cThrows(fn() => $selector->select([], 'doctor-gate8c', 'consultorio-a', '2026-07-21'), 'canonical_schedule_missing', 'missing version closes');
gate8cThrows(fn() => $selector->select([$valid, new CanonicalScheduleVersion('schedule-2', 2, 'doctor-gate8c', 'consultorio-a', 'UTC', '2026-07-15', null, 30, 0, [])], 'doctor-gate8c', 'consultorio-a', '2026-07-21'), 'canonical_schedule_ambiguous', 'ambiguous version closes');
gate8cThrows(fn() => $selector->select([$valid], 'other-profile', 'consultorio-a', '2026-07-21'), 'profile_mismatch', 'profile mismatch closes');
gate8cThrows(fn() => $selector->select([$valid], 'doctor-gate8c', 'other-consultorio', '2026-07-21'), 'consultorio_mismatch', 'consultorio mismatch closes');

$calculator = new CanonicalAvailabilityCalculator($selector);
$base = $calculator->calculate(gate8cRequest([$valid]));
gate8cAssert($base->windows() === [['start' => '09:00', 'end' => '11:00']], 'weekday base windows selected');
gate8cAssert(count($base->slots()) === 3 && $base->slots()[1]['start'] === '09:40' && $base->slots()[2]['end'] === '10:50', 'duration and gap generate canonical slots');
gate8cAssert($base->slots()[0]['date'] === '2026-07-21' && $base->slots()[0]['timezone'] === 'America/Mexico_City', 'slots preserve date and timezone');

$holiday = new HolidayClosure('2026-07-21', 'synthetic-holiday');
$open = new AvailabilityOverride('open-holiday', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'open', ['start' => '09:00', 'end' => '11:00'], false, 'backend-fixture');
$holidayResult = $calculator->calculate(gate8cRequest([$valid], [$open], [$holiday]));
gate8cAssert($holidayResult->appliedHoliday() === 'synthetic-holiday' && $holidayResult->windows() === [['start' => '09:00', 'end' => '11:00']], 'holiday closes but explicit open reopens');
$fullClose = new AvailabilityOverride('close-day', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'close', null, true, 'backend-fixture');
gate8cAssert($calculator->calculate(gate8cRequest([$valid], [$fullClose]))->windows() === [], 'full-day close removes availability');
$close = new AvailabilityOverride('close-part', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'close', ['start' => '09:30', 'end' => '10:30'], false, 'backend-fixture');
$reopen = new AvailabilityOverride('open-part', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'open', ['start' => '10:00', 'end' => '10:20'], false, 'backend-fixture');
$precedence = $calculator->calculate(gate8cRequest([$valid], [$close, $reopen]));
gate8cAssert($precedence->windows() === [['start' => '09:00', 'end' => '09:30'], ['start' => '10:00', 'end' => '10:20'], ['start' => '10:30', 'end' => '11:00']], 'close then open precedence is stable');
$collision = new CollisionWindow('collision-1', 'doctor-gate8c', 'consultorio-a', '2026-07-21', '10:20', '10:40', 'appointment_projection');
$collisionResult = $calculator->calculate(gate8cRequest([$valid], [$close, $reopen], [], [$collision]));
gate8cAssert($collisionResult->windows() === [['start' => '09:00', 'end' => '09:30'], ['start' => '10:00', 'end' => '10:20'], ['start' => '10:40', 'end' => '11:00']], 'collision subtracts after overrides');
gate8cAssert($collisionResult->collisionCount() === 1, 'collision count is deterministic');
gate8cAssert($calculator->calculate(gate8cRequest([$valid], [], [], [new CollisionWindow('adjacent', 'doctor-gate8c', 'consultorio-a', '2026-07-21', '11:00', '12:00', 'fixture')]))->windows() === [['start' => '09:00', 'end' => '11:00']], 'adjacent collision does not affect window');
$overlapA = new CollisionWindow('overlap-a', 'doctor-gate8c', 'consultorio-a', '2026-07-21', '09:30', '10:00', 'fixture');
$overlapB = new CollisionWindow('overlap-b', 'doctor-gate8c', 'consultorio-a', '2026-07-21', '09:45', '10:30', 'fixture');
gate8cAssert($calculator->calculate(gate8cRequest([$valid], [], [], [$overlapA, $overlapB]))->windows() === [['start' => '09:00', 'end' => '09:30'], ['start' => '10:30', 'end' => '11:00']], 'overlapping collisions normalize');

$inactive = new AvailabilityOverride('inactive', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'close', ['start' => '09:00', 'end' => '11:00'], false, 'fixture', false);
$otherProfile = new AvailabilityOverride('other-profile', 'other', 'consultorio-a', '2026-07-21', 'close', null, true, 'fixture');
$otherConsultorio = new AvailabilityOverride('other-consultorio', 'doctor-gate8c', 'consultorio-b', '2026-07-21', 'close', null, true, 'fixture');
$otherCollisionProfile = new CollisionWindow('other-profile-collision', 'other', 'consultorio-a', '2026-07-21', '09:00', '11:00', 'fixture');
$otherCollisionConsultorio = new CollisionWindow('other-consultorio-collision', 'doctor-gate8c', 'consultorio-b', '2026-07-21', '09:00', '11:00', 'fixture');
$isolated = $calculator->calculate(gate8cRequest([$valid], [$inactive, $otherProfile, $otherConsultorio], [], [$otherCollisionProfile, $otherCollisionConsultorio]));
gate8cAssert($isolated->windows() === [['start' => '09:00', 'end' => '11:00'],], 'inactive and foreign inputs are ignored');

$consultorioB = gate8cVersion('consultorio-b', 'America/New_York', 30, 0, [new WeeklyScheduleWindow(2, '13:00', '14:00')]);
$bResult = $calculator->calculate(gate8cRequest([$valid, $consultorioB], [], [], [], 'consultorio-b'));
gate8cAssert($bResult->consultorioId() === 'consultorio-b' && $bResult->timezone() === 'America/New_York' && $bResult->windows() === [['start' => '13:00', 'end' => '14:00']], 'consultorios remain isolated without fallback');

gate8cThrows(fn() => new CanonicalScheduleVersion('unsafe/id', 1, 'doctor-gate8c', 'consultorio-a', 'UTC', '2026-07-01', null, 30, 0, []), 'canonical_schedule_missing', 'unsafe version id closes');
gate8cThrows(fn() => new AvailabilityOverride('unsafe/id', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'open', ['start' => '09:00', 'end' => '10:00'], false, 'backend-fixture'), 'invalid_override', 'unsafe override id closes');
gate8cThrows(fn() => new AvailabilityOverride('safe-id', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'open', ['start' => '09:00', 'end' => '10:00'], false, 'unsafe/source'), 'invalid_override', 'unsafe override source closes');
gate8cThrows(fn() => new CollisionWindow('unsafe/id', 'doctor-gate8c', 'consultorio-a', '2026-07-21', '09:00', '10:00', 'fixture'), 'invalid_collision', 'unsafe collision id closes');
gate8cThrows(fn() => new CollisionWindow('safe-collision', 'doctor-gate8c', 'consultorio-a', '2026-07-21', '09:00', '10:00', 'unsafe/source'), 'invalid_collision', 'unsafe collision source closes');
gate8cThrows(fn() => new AvailabilityOverride('ambiguous-open', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'open', ['start' => '09:00', 'end' => '10:00'], true, 'fixture'), 'invalid_override', 'full-day window combination closes');
gate8cThrows(fn() => new AvailabilityOverride('ambiguous-close', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'close', null, false, 'fixture'), 'invalid_override', 'partial null-window combination closes');
gate8cAssert((new AvailabilityOverride('full-day-valid', 'doctor-gate8c', 'consultorio-a', '2026-07-21', 'open', null, true, 'fixture'))->fullDay(), 'full-day null-window combination remains valid');

$inactiveHoliday = new HolidayClosure('2026-07-21', 'inactive-holiday', false);
$otherDateHoliday = new HolidayClosure('2026-07-22', 'other-date-holiday');
$semanticOverrides = [$close, $open];
$semanticCollisions = [$collision, $overlapA, $overlapB];
$permutationOne = $calculator->calculate(gate8cRequest([$valid, $consultorioB], $semanticOverrides, [$holiday, $inactiveHoliday, $otherDateHoliday], $semanticCollisions));
$permutationTwo = $calculator->calculate(gate8cRequest([$consultorioB, $valid], array_reverse($semanticOverrides), [$otherDateHoliday, $inactiveHoliday, $holiday], array_reverse($semanticCollisions)));
gate8cAssert($permutationOne->toArray() === $permutationTwo->toArray(), 'all collection permutations are byte-equivalent');
gate8cAssert($permutationOne->contract()->toArray() === $permutationTwo->contract()->toArray(), 'permuted contracts are equivalent');
gate8cAssert($permutationOne->appliedOverrideIds() === ['close-part', 'open-holiday'], 'applied override ids are ordered');
$duplicateOverrideResult = $calculator->calculate(gate8cRequest([$valid], [$open, $open]));
gate8cAssert($duplicateOverrideResult->appliedOverrideIds() === ['open-holiday'], 'duplicate override id is not duplicated');
gate8cAssert((new AvailabilityCalculationRequest('doctor-gate8c', 'consultorio-a', '2026-07-21', [$consultorioB, $valid], array_reverse($semanticOverrides), [$otherDateHoliday, $holiday], array_reverse($semanticCollisions)))->versions()[0] === $valid, 'request versions are canonicalized');

$foreignAndInactive = $calculator->calculate(gate8cRequest([$valid], [$open, $inactive, $otherProfile, $otherConsultorio], [$holiday, $inactiveHoliday, $otherDateHoliday], [$collision, $otherCollisionProfile, $otherCollisionConsultorio]));
$minimal = $foreignAndInactive->contract()->toArray();
gate8cAssert(count($minimal['overrides']) === 1 && $minimal['overrides'][0]['id'] === 'open-holiday', 'read model exposes only applicable active override');
gate8cAssert(count($minimal['holidays']) === 1 && $minimal['holidays'][0]['name'] === 'synthetic-holiday', 'read model exposes only applicable active holiday');
gate8cAssert(count($minimal['collisions']) === 1 && $minimal['collisions'][0]['id'] === 'collision-1', 'read model exposes only applicable active collision');
gate8cAssert(!str_contains(serialize($minimal), 'other-profile') && !str_contains(serialize($minimal), 'other-consultorio'), 'foreign resources are not exposed');

$serializedOne = serialize($collisionResult->toArray());
$serializedTwo = serialize($calculator->calculate(gate8cRequest([$valid], [$close, $reopen], [], [$collision]))->toArray());
gate8cAssert($serializedOne === $serializedTwo, 'same input is byte-equivalent');
gate8cAssert($collisionResult->contract() instanceof ScheduleAvailabilityContract && $collisionResult->isReadModel() && !$collisionResult->editableAuthority(), 'result wraps read model contract');
gate8cAssert($collisionResult->toArray()['mode'] === 'calculated_read_model' && $collisionResult->toArray()['profile_id'] === 'doctor-gate8c' && $collisionResult->toArray()['consultorio_id'] === 'consultorio-a', 'read model metadata is canonical');

$protected = [
    'api/agenda/index.php' => '7b8eecc5b8eb1ee677394702a70b5e9c126898f1b0e4840f3110b5b1f3a884bd',
    'modules/agenda/tests/Gate8ACanonicalContractsTest.php' => 'efae63a8e5e353288a24e60770e0f7128df89c75411b6e6541c2daeda2637ecd',
    'modules/agenda/tests/Gate8BServerAuthoritativeActorsTest.php' => '2b8b301cbb64b60d77d2795bb2857fc0b676fb05d936188d49dce4f592a4bda8',
    'docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8A_CONTRATOS_CANONICOS.md' => 'c02b8192f06fe867b9139a209aaec8caa442becb53dca43b6403c83e5c40a078',
    'docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8B_AUTORIDAD_SERVER_SIDE.md' => '608c2ec7d5167940322354e3e33590fe4813308537f22c11315a991de4820d1a',
    'modules/agenda/security/AgendaActorAuthorityResolver.php' => '2b47a8d25d328c7f594ee40d7f86392f4b6ac87c4fb600349dfa5a9fac5df5a8',
    'modules/agenda/security/PrivateAgendaRoutePolicy.php' => '11961f9b4a05eb9fd420b028bd5b7983d2c0168756c922a8da5b1b03811d4244',
    'docs/PLAN_MAESTRO_MXMED.md' => '4072f2f3357464b79b5d38dffab721a3cd1011a8e6a36ba849dc37d15f91134e',
];
foreach ($protected as $relative => $expected) {
    if ($relative === 'docs/PLAN_MAESTRO_MXMED.md') continue;
    gate8cAssert(gate8cSha256($root . '/' . $relative) === $expected, 'protected file unchanged: ' . $relative);
}
$plan = file_get_contents($root . '/docs/PLAN_MAESTRO_MXMED.md');
gate8cAssert(is_string($plan), 'plan is readable');
preg_match('/### PP-304 .*?(?=### PP-305 )/s', $plan, $pp304Match);
preg_match('/### PP-305 .*?(?=### PP-306 )/s', $plan, $pp305Match);
preg_match('/### PP-306 .*?(?=### PP-[0-9]+ —|\z)/s', $plan, $pp306Match);
gate8cAssert(isset($pp304Match[0]) && hash('sha256', $pp304Match[0]) === 'f2e9f99bf45bd93d457ab987064731b6ab04996fe27874cc06670ea849392cb4', 'PP-304 remains byte-equivalent');
gate8cAssert(isset($pp305Match[0]) && hash('sha256', $pp305Match[0]) === '3d3b6b177d9363cb9cac928f992fcf57f6ba18093a1bcd935c1979b81c4e8288', 'PP-305 remains byte-equivalent');
gate8cAssert(isset($pp306Match[0]) && hash('sha256', $pp306Match[0]) === '30501ff147af8d92266893b048d01616419208d88d9e7bad22895790de34f444', 'PP-306 remains byte-equivalent');
gate8cAssert(substr_count($plan, '### PP-306 —') === 1, 'PP-306 occurs exactly once');
gate8cAssert(is_file($root . '/modules/agenda/controllers/ScheduleController.php') && is_file($root . '/modules/agenda/repositories/ScheduleRepository.php'), 'legacy surfaces remain present');

echo "Gate8CCanonicalScheduleAvailabilityTest PASS\n";
