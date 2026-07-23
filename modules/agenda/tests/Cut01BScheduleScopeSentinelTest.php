<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../availability/*.php') as $file) require_once $file;
require_once __DIR__ . '/../adapters/CanonicalScheduleReadAdapter.php';
require_once __DIR__ . '/../adapters/CanonicalAvailabilityCompareAdapter.php';
require_once __DIR__ . '/../repositories/ScheduleRepository.php';

use Agenda\Adapters\CanonicalAvailabilityCompareAdapter;
use Agenda\Adapters\CanonicalScheduleReadAdapter;
use Agenda\Availability\AvailabilityCalculationRequest;
use Agenda\Availability\CanonicalAvailabilityCalculator;
use Agenda\Availability\CanonicalAvailabilityException;
use Agenda\Availability\CanonicalAvailabilityResult;
use Agenda\Availability\CanonicalScheduleVersion;
use Agenda\Availability\WeeklyScheduleWindow;
use Agenda\Repositories\ScheduleRepository;

function cut01bAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function cut01bThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (CanonicalAvailabilityException) {
        return;
    }
    throw new RuntimeException($message);
}

function cut01bParameters(array $changes = []): array
{
    return array_replace([
        'version_id' => 'schedule-cut01b',
        'version' => 3,
        'profile_id' => 'profile-cut01b',
        'timezone' => 'America/Mexico_City',
        'effective_from' => '2026-07-01',
        'effective_until' => '2026-08-01',
        'duration_minutes' => 30,
        'gap_minutes' => 10,
    ], $changes);
}

function cut01bSnapshot(string $source = 'consultorio_schedule', array $rows = []): array
{
    return [
        'source_table' => $source,
        'legacy_doctor_id' => 'legacy-doctor-cut01b',
        'consultorio_id' => 'consultorio-cut01b',
        'rows' => $rows ?: [
            ['weekday' => 2, 'start_time' => '09:00:00', 'end_time' => '11:00:00', 'is_active' => true],
            ['weekday' => 3, 'start_time' => '13:00', 'end_time' => '14:00', 'is_active' => true],
        ],
    ];
}

function cut01bCanonicalResult(): CanonicalAvailabilityResult
{
    $version = new CanonicalScheduleVersion(
        'schedule-compare',
        1,
        'profile-cut01b',
        'consultorio-cut01b',
        'America/Mexico_City',
        '2026-07-01',
        null,
        30,
        10,
        [new WeeklyScheduleWindow(2, '09:00', '11:00')]
    );
    return (new CanonicalAvailabilityCalculator())->calculate(
        new AvailabilityCalculationRequest(
            'profile-cut01b',
            'consultorio-cut01b',
            '2026-07-21',
            [$version]
        )
    );
}

function cut01bLegacyResponse(): array
{
    return [
        'ok' => true,
        'data' => [
            'date' => '2026-07-21',
            'timezone' => 'America/Mexico_City',
            'doctor_id' => 'profile-cut01b',
            'consultorio_id' => 'consultorio-cut01b',
            'windows' => [
                ['start_at' => '2026-07-21 09:00:00', 'end_at' => '2026-07-21 11:00:00'],
            ],
            'slots' => [
                ['start_at' => '2026-07-21 10:20:00', 'end_at' => '2026-07-21 10:50:00'],
                ['start_at' => '2026-07-21 09:00:00', 'end_at' => '2026-07-21 09:30:00'],
                ['start_at' => '2026-07-21 09:40:00', 'end_at' => '2026-07-21 10:10:00'],
            ],
        ],
    ];
}

$root = dirname(__DIR__, 3);
$config = require $root . '/modules/agenda/config/agenda.php';
cut01bAssert(($config['feature_flags']['canonical_schedule_read'] ?? null) === false, 'schedule flag defaults false');
cut01bAssert(($config['feature_flags']['canonical_availability_compare'] ?? null) === false, 'compare flag defaults false');
foreach ([[], ['feature_flags' => []], ['feature_flags' => ['canonical_schedule_read' => null]], ['feature_flags' => ['canonical_schedule_read' => 'true']], ['feature_flags' => ['canonical_schedule_read' => 1]], ['feature_flags' => ['canonical_schedule_read' => []]]] as $fixture) {
    cut01bAssert(!CanonicalScheduleReadAdapter::canonicalScheduleReadEnabled($fixture), 'schedule flag fails closed');
}
foreach ([[], ['feature_flags' => []], ['feature_flags' => ['canonical_availability_compare' => null]], ['feature_flags' => ['canonical_availability_compare' => 'true']], ['feature_flags' => ['canonical_availability_compare' => 1]], ['feature_flags' => ['canonical_availability_compare' => []]]] as $fixture) {
    cut01bAssert(!CanonicalAvailabilityCompareAdapter::canonicalAvailabilityCompareEnabled($fixture), 'compare flag fails closed');
}
cut01bAssert(CanonicalScheduleReadAdapter::canonicalScheduleReadEnabled(['feature_flags' => ['canonical_schedule_read' => true]]), 'literal schedule true is eligible');
cut01bAssert(CanonicalAvailabilityCompareAdapter::canonicalAvailabilityCompareEnabled(['feature_flags' => ['canonical_availability_compare' => true]]), 'literal compare true is eligible');

$scheduleAdapter = new CanonicalScheduleReadAdapter();
$sources = ['consultorio_schedule', 'consultorio_schedules', 'consultorio_horarios', 'consultorio_horarios_base', 'agenda_consultorio_schedule'];
foreach ($sources as $source) {
    cut01bAssert($scheduleAdapter->adapt(cut01bSnapshot($source), cut01bParameters())->version() === 3, 'allowed source adapts: ' . $source);
}
cut01bThrows(fn() => $scheduleAdapter->adapt(cut01bSnapshot('unknown_schedule'), cut01bParameters()), 'unknown source closes');
$adapted = $scheduleAdapter->adapt(cut01bSnapshot(), cut01bParameters());
cut01bAssert($adapted->profileId() === 'profile-cut01b' && $adapted->consultorioId() === 'consultorio-cut01b', 'explicit scopes preserved');
cut01bAssert($adapted->timezone() === 'America/Mexico_City' && $adapted->durationMinutes() === 30 && $adapted->gapMinutes() === 10, 'explicit timing preserved');
cut01bAssert($adapted->effectiveFrom() === '2026-07-01' && $adapted->effectiveUntil() === '2026-08-01' && $adapted->version() === 3, 'explicit range and version preserved');
cut01bAssert($adapted->windows()[0]->start() === '09:00' && $adapted->windows()[0]->end() === '11:00', 'zero seconds normalize');
$sentinel = cut01bSnapshot();
$sentinel['consultorio_id'] = '__all__';
cut01bThrows(fn() => $scheduleAdapter->adapt($sentinel, cut01bParameters()), 'sentinel closes');
cut01bThrows(fn() => $scheduleAdapter->adapt(cut01bSnapshot(), cut01bParameters(['timezone' => 'Not/AZone'])), 'invalid timezone closes');
$missingParameter = cut01bParameters();
unset($missingParameter['gap_minutes']);
cut01bThrows(fn() => $scheduleAdapter->adapt(cut01bSnapshot(), $missingParameter), 'missing explicit parameter closes');
$inactive = cut01bSnapshot(rows: [
    ['is_active' => false],
    ['weekday' => 2, 'start_time' => '09:00', 'end_time' => '10:00', 'is_active' => true],
]);
cut01bAssert(count($scheduleAdapter->adapt($inactive, cut01bParameters())->windows()) === 1, 'inactive row ignored');
cut01bThrows(fn() => $scheduleAdapter->adapt(cut01bSnapshot(rows: [['weekday' => 2, 'start_time' => '09:00', 'is_active' => true]]), cut01bParameters()), 'incomplete active row closes');
cut01bThrows(fn() => $scheduleAdapter->adapt(cut01bSnapshot(rows: [['weekday' => 2, 'start_time' => '09:00:01', 'end_time' => '10:00:00', 'is_active' => true]]), cut01bParameters()), 'nonzero seconds close');
cut01bThrows(fn() => $scheduleAdapter->adapt(cut01bSnapshot(rows: [
    ['weekday' => 2, 'start_time' => '09:00', 'end_time' => '10:00', 'is_active' => true],
    ['weekday' => 2, 'start_time' => '09:30', 'end_time' => '11:00', 'is_active' => true],
]), cut01bParameters()), 'overlap closes');
cut01bAssert($scheduleAdapter->adapt(cut01bSnapshot(), cut01bParameters())->toArray() === $scheduleAdapter->adapt(cut01bSnapshot(), cut01bParameters())->toArray(), 'same schedule input is deterministic');
$permuted = cut01bSnapshot(rows: array_reverse(cut01bSnapshot()['rows']));
cut01bAssert($adapted->toArray() === $scheduleAdapter->adapt($permuted, cut01bParameters())->toArray(), 'row permutation is canonical');

cut01bAssert(!CanonicalScheduleReadAdapter::isRealConsultorioId('__all__'), 'sentinel is not real');
cut01bAssert(!CanonicalScheduleReadAdapter::isRealConsultorioId('  '), 'blank consultorio is not real');
cut01bAssert(CanonicalScheduleReadAdapter::isRealConsultorioId('consultorio-alpha'), 'non-numeric consultorio can be real');

$compareAdapter = new CanonicalAvailabilityCompareAdapter();
$canonicalResult = cut01bCanonicalResult();
$equivalent = $compareAdapter->compare(cut01bLegacyResponse(), $canonicalResult);
cut01bAssert($equivalent['comparable'] && $equivalent['equal'] === true && $equivalent['reason'] === 'equivalent', 'equivalent response compares');
cut01bAssert(strlen($equivalent['legacy_digest']) === 64 && strlen($equivalent['canonical_digest']) === 64, 'digests are sha256');
cut01bAssert($equivalent === $compareAdapter->compare(cut01bLegacyResponse(), $canonicalResult), 'compare is deterministic');

$mismatches = [
    ['doctor_id', 'other-profile', 'profile_mismatch', 'profile_id'],
    ['consultorio_id', 'other-consultorio', 'consultorio_mismatch', 'consultorio_id'],
    ['consultorio_id', '__all__', 'consultorio_mismatch', 'consultorio_id'],
    ['date', '2026-07-22', 'date_mismatch', 'date'],
    ['timezone', 'UTC', 'timezone_mismatch', 'timezone'],
];
foreach ($mismatches as [$key, $value, $reason, $dimension]) {
    $legacy = cut01bLegacyResponse();
    $legacy['data'][$key] = $value;
    if ($key === 'date') {
        foreach (['windows', 'slots'] as $collection) {
            foreach ($legacy['data'][$collection] as &$interval) {
                $interval['start_at'] = str_replace('2026-07-21', $value, $interval['start_at']);
                $interval['end_at'] = str_replace('2026-07-21', $value, $interval['end_at']);
            }
            unset($interval);
        }
    }
    $result = $compareAdapter->compare($legacy, $canonicalResult);
    cut01bAssert(!$result['comparable'] && $result['equal'] === null && $result['reason'] === $reason && $result['differences'] === [$dimension], 'identity mismatch: ' . $reason);
}
$windowsMismatch = cut01bLegacyResponse();
$windowsMismatch['data']['windows'][0]['end_at'] = '2026-07-21 10:50:00';
cut01bAssert($compareAdapter->compare($windowsMismatch, $canonicalResult)['reason'] === 'windows_mismatch', 'windows mismatch detected');
$slotsMismatch = cut01bLegacyResponse();
array_pop($slotsMismatch['data']['slots']);
cut01bAssert($compareAdapter->compare($slotsMismatch, $canonicalResult)['reason'] === 'slots_mismatch', 'slots mismatch detected');
$bothMismatch = $windowsMismatch;
array_pop($bothMismatch['data']['slots']);
$bothResult = $compareAdapter->compare($bothMismatch, $canonicalResult);
cut01bAssert($bothResult['reason'] === 'windows_and_slots_mismatch' && $bothResult['differences'] === ['windows', 'slots'], 'joint mismatch detected');
$notOk = cut01bLegacyResponse();
$notOk['ok'] = false;
cut01bAssert($compareAdapter->compare($notOk, $canonicalResult)['reason'] === 'legacy_not_ok', 'legacy not-ok closes');
$invalidShape = cut01bLegacyResponse();
unset($invalidShape['data']['slots']);
cut01bAssert($compareAdapter->compare($invalidShape, $canonicalResult)['reason'] === 'legacy_shape_invalid', 'invalid shape closes');
$nonzeroSeconds = cut01bLegacyResponse();
$nonzeroSeconds['data']['slots'][0]['start_at'] = '2026-07-21 10:20:01';
cut01bAssert($compareAdapter->compare($nonzeroSeconds, $canonicalResult)['reason'] === 'legacy_shape_invalid', 'nonzero compare seconds close');
cut01bAssert(array_diff($bothResult['differences'], ['profile_id', 'consultorio_id', 'date', 'timezone', 'windows', 'slots']) === [], 'differences contain only dimensions');
cut01bAssert($equivalent['mode'] === 'diagnostic_read_only' && !str_contains(serialize($equivalent), 'phone') && !str_contains(serialize($equivalent), 'email'), 'compare output is read-only and payload-free');

$schedulePublic = array_map(static fn(ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(CanonicalScheduleReadAdapter::class))->getMethods(ReflectionMethod::IS_PUBLIC));
sort($schedulePublic);
cut01bAssert($schedulePublic === ['adapt', 'canonicalScheduleReadEnabled', 'isRealConsultorioId'], 'schedule adapter public API is exact');
$comparePublic = array_map(static fn(ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(CanonicalAvailabilityCompareAdapter::class))->getMethods(ReflectionMethod::IS_PUBLIC));
sort($comparePublic);
cut01bAssert($comparePublic === ['canonicalAvailabilityCompareEnabled', 'compare'], 'compare adapter public API is exact');
cut01bAssert((new ReflectionMethod(ScheduleRepository::class, 'canonicalReadSnapshot'))->isPublic(), 'repository exposes one canonical snapshot method');

$controllerContracts = [
    'AgendaSettingsController.php' => ['CanonicalScheduleReadAdapter', 'canonicalScheduleReadEnabled', 'CanonicalScheduleReadAdapter::class'],
    'AvailabilityController.php' => ['CanonicalAvailabilityCompareAdapter', 'canonicalAvailabilityCompareEnabled', 'CanonicalAvailabilityCompareAdapter::class'],
    'WaitlistController.php' => ['CanonicalScheduleReadAdapter', 'canonicalScheduleReadEnabled', 'CanonicalScheduleReadAdapter::class'],
];
foreach ($controllerContracts as $file => $needles) {
    $source = file_get_contents($root . '/modules/agenda/controllers/' . $file);
    cut01bAssert(is_string($source), 'controller readable: ' . $file);
    foreach ($needles as $needle) cut01bAssert(str_contains($source, $needle), 'dormant wiring present: ' . $file);
    cut01bAssert(!str_contains($source, 'new CanonicalScheduleReadAdapter') && !str_contains($source, 'new CanonicalAvailabilityCompareAdapter'), 'controller does not instantiate adapter: ' . $file);
    cut01bAssert(!str_contains($source, '->adapt(') && !str_contains($source, '->compare('), 'controller does not execute adapter: ' . $file);
}
$waitlistSource = file_get_contents($root . '/modules/agenda/controllers/WaitlistController.php');
$sentinelGuard = strpos($waitlistSource, "=== self::ANY_CONSULTORIO_ID");
$writeRepository = strpos($waitlistSource, 'new AppointmentWriteRepository');
cut01bAssert($sentinelGuard !== false && $writeRepository !== false && $sentinelGuard < $writeRepository, 'waitlist sentinel closes before appointment repository');

$forbidden = ['PDO', 'mysqli', '$_GET', '$_POST', '$_REQUEST', '$_SESSION', '$_COOKIE', '$_SERVER', 'getenv', 'getallheaders', 'header(', 'file_get_contents', 'file_put_contents', 'fopen', 'curl', 'error_log', 'random_bytes', 'random_int', 'time(', 'microtime', "DateTime('now')", 'SQL', 'INSERT', 'UPDATE', 'DELETE', 'CREATE TABLE', 'ALTER TABLE', 'DROP TABLE'];
foreach (['CanonicalScheduleReadAdapter.php', 'CanonicalAvailabilityCompareAdapter.php'] as $file) {
    $source = file_get_contents($root . '/modules/agenda/adapters/' . $file);
    foreach ($forbidden as $needle) cut01bAssert(!str_contains($source, $needle), 'adapter purity: ' . $file . ' excludes ' . $needle);
}

echo "Cut01BScheduleScopeSentinelTest PASS\n";
