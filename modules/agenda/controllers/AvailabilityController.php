<?php
namespace Agenda\Controllers;

use Agenda\Adapters\CanonicalAvailabilityCompareAdapter;
use Agenda\Repositories\AvailabilityRepository;
use Agenda\Repositories\OverrideRepository;
use Agenda\Repositories\AppointmentCollisionsRepository;
use Agenda\Repositories\ConsultoriosRepository;
use Agenda\Services\HolidayMxProvider;
use Agenda\Helpers\DoctorIdentity as DoctorIdentity;
use Agenda\Helpers as DbHelpers;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/AvailabilityRepository.php';
require_once __DIR__ . '/../adapters/CanonicalAvailabilityCompareAdapter.php';
require_once __DIR__ . '/../repositories/OverrideRepository.php';
require_once __DIR__ . '/../repositories/AppointmentCollisionsRepository.php';
require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';
require_once __DIR__ . '/../services/HolidayMxProvider.php';
require_once __DIR__ . '/../helpers/doctor_identity.php';
require_once __DIR__ . '/../config/agenda.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class AvailabilityController
{
    private ?AvailabilityRepository $repository = null;

    // null = ok, 'availability base schedule not ready' o 'database error'
    private ?string $dbError = null;

    private bool $qaNotReady = false;

    private ?OverrideRepository $overrideRepo = null;
    private bool $overridesConfigured = false; // config overrides_table tiene string
    private bool $overridesEnabled = false;    // tabla existe y repo activa
    private ?string $overrideDbError = null;   // null o 'database error'
    private ?AppointmentCollisionsRepository $collisionRepo = null;
    private bool $collisionsEnabled = false;
    private array $config = [];
    private ?PDO $pdo = null;
    private array $actorContext = [];
    private array $contextWarnings = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->qaNotReady = DbHelpers\isQaModeNotReady();
        if ($this->qaNotReady) {
            return;
        }

        // 1) Conexión + repo base (capa A)
        try {
            $pdo ??= mxmed_pdo();
            $this->pdo = $pdo;
            $this->repository = new AvailabilityRepository($pdo);
        } catch (RuntimeException $e) {
            // incluye: "availability base schedule not ready"
            $this->dbError = 'availability base schedule not ready';
        } catch (PDOException $e) {
            $this->dbError = 'database error';
        } catch (\Throwable $e) {
            $this->dbError = 'database error';
        }

        // 2) Config overrides (capa C) — nunca debe tumbar el endpoint
        try {
            $config = require __DIR__ . '/../config/agenda.php';
            $this->config = is_array($config) ? $config : [];
            $canonicalAvailabilityCompareAdapterClass = CanonicalAvailabilityCompareAdapter::canonicalAvailabilityCompareEnabled($this->config)
                ? CanonicalAvailabilityCompareAdapter::class
                : null;
        } catch (\Throwable $e) {
            $this->overridesConfigured = false;
            $this->overridesEnabled = false;
            return;
        }

        $table = trim((string)($this->config['overrides_table'] ?? ''));
        $this->overridesConfigured = ($table !== '');

        if ($pdo && $this->overridesConfigured) {
            try {
                $this->overrideRepo = new OverrideRepository($pdo);
                $this->overridesEnabled = $this->overrideRepo->isEnabled();
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'availability overrides not ready') {
                    $this->overridesEnabled = false;
                } else {
                    $this->overrideDbError = 'database error';
                }
            } catch (PDOException $e) {
                $this->overrideDbError = 'database error';
            } catch (\Throwable $e) {
                $this->overrideDbError = 'database error';
            }
        }

        // Repo de colisiones (citas del día) — degradación controlada
        if ($pdo) {
            try {
                $this->collisionRepo = new AppointmentCollisionsRepository($pdo, $this->config);
                $this->collisionsEnabled = true;
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'availability appointments not ready') {
                    $this->collisionsEnabled = false;
                } else {
                    $this->collisionsEnabled = false;
                }
            } catch (PDOException $e) {
                $this->collisionsEnabled = false;
            } catch (\Throwable $e) {
                $this->collisionsEnabled = false;
            }
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function index(array $params = [])
    {
        $this->contextWarnings = [];
        // QA not_ready mantiene el contrato previo
        if ($this->qaNotReady) {
            return $this->error('db_not_ready', 'availability base schedule not ready');
        }

        // Base DB error
        if ($this->dbError === 'database error') {
            return $this->error('db_error', 'database error');
        }
        if ($this->dbError || !$this->repository) {
            return $this->error('db_not_ready', 'availability base schedule not ready');
        }

        $doctorIdRequested = trim((string)($params['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error(
                (string)$doctorScope['error'],
                (string)$doctorScope['message'],
                (array)($doctorScope['meta'] ?? [])
            );
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $consultorioId = $params['consultorio_id'] ?? null;
        $date = $params['date'] ?? null;

        if ($this->pdo) {
            try {
                $doctorId = DoctorIdentity\resolveCanonicalDoctorId($this->pdo, $doctorId);
            } catch (\Throwable $e) {
                $doctorId = (string)$doctorScope['doctor_id'];
            }
        }

        $meta = [
            'doctor_id' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
            'consultorio_id' => $consultorioId,
            'date' => $date,
            'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
            'auth_warnings' => $this->contextWarnings,
        ];

        if (!$this->isValidNumeric($consultorioId)) {
            return $this->error('invalid_params', 'consultorio_id must be numeric', $meta);
        }
        if (!$this->isValidDate($date)) {
            return $this->error('invalid_params', 'date must be in YYYY-MM-DD format', $meta);
        }

        $slotMinutes = $this->normalizeSlotMinutes($params['slot_minutes'] ?? null);
        if ($slotMinutes === null) {
            return $this->error('invalid_params', 'slot_minutes must be between 5 and 720', $meta);
        }

        $holiday = HolidayMxProvider::isHoliday($date);
        $isHoliday = $holiday['is_holiday'];
        $holidayName = $holiday['name'];

        // Overrides (C)
        $overrides = [];
        $overridesEnabled = $this->overridesEnabled;

        if ($this->overrideDbError) {
            return $this->error('db_error', 'database error');
        }

        // Si está configurado en agenda.php pero no está lista la tabla => db_not_ready estable
        if ($this->overridesConfigured && !$this->overridesEnabled) {
            return $this->error('db_not_ready', 'availability overrides not ready');
        }

        if ($this->overridesEnabled && $this->overrideRepo) {
            try {
                $overrides = $this->overrideRepo->getOverridesForDate(
                    $doctorId,
                    $consultorioId,
                    $date
                );
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'availability overrides not ready') {
                    return $this->error('db_not_ready', 'availability overrides not ready');
                }
                return $this->error('db_error', 'database error');
            } catch (PDOException $e) {
                return $this->error('db_error', 'database error');
            } catch (\Throwable $e) {
                return $this->error('db_error', 'database error');
            }
        }

        $closeOverrides = array_values(array_filter($overrides, fn($override) => $override['type'] === 'close'));
        $openOverrides  = array_values(array_filter($overrides, fn($override) => $override['type'] === 'open'));
        $hasOpen = !empty($openOverrides);
        $hasCloseFullDay = $this->hasFullDayClose($closeOverrides, $date);

        $shouldLoadBase = (!$isHoliday || !empty($closeOverrides) || $hasOpen) && (!$hasCloseFullDay || $hasOpen);

        $baseWindows = [];
        if ($shouldLoadBase) {
            try {
                $baseWindows = $this->repository->getBaseWindowsForDate($doctorId, $consultorioId, $date);
            } catch (RuntimeException $e) {
                return $this->error('db_not_ready', 'availability base schedule not ready');
            } catch (PDOException $e) {
                return $this->error('db_error', 'database error');
            } catch (\Throwable $e) {
                return $this->error('db_error', 'database error');
            }
        }

        $windows = $baseWindows;

        if (!empty($closeOverrides)) {
            $windows = $this->subtractIntervals($windows, $closeOverrides);
        }
        if (!empty($openOverrides)) {
            // Layer C reabre rangos (incluye feriados)
            $windows = array_merge($windows, $this->buildOverrideWindows($openOverrides));
        }

        $windows = $this->deduplicateWindows($windows);

        $windowsBeforeCollisions = count($windows);

        $busyIntervals = [];
        $collisionsEnabled = $this->collisionsEnabled;
        if ($this->collisionsEnabled && $this->collisionRepo) {
            try {
                $busyIntervals = $this->collisionRepo->getBusyIntervalsForDate(
                    $doctorId,
                    $consultorioId,
                    $date
                );
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'availability appointments not ready') {
                    $collisionsEnabled = false;
                } else {
                    return $this->error('db_error', 'database error');
                }
            } catch (PDOException $e) {
                return $this->error('db_error', 'database error');
            } catch (\Throwable $e) {
                return $this->error('db_error', 'database error');
            }
        }

        if (!empty($busyIntervals)) {
            $windows = $this->subtractIntervals($windows, $busyIntervals);
        }

        $windows = $this->sortWindows($windows);
        $slots = $this->generateSlots($windows, $slotMinutes);

        $isOverride = !empty($overrides);
        $overrideTypes = $isOverride
            ? array_values(array_unique(array_map(fn($override) => $override['type'], $overrides)))
            : [];

        $metaOut = $this->buildMeta(
            $doctorId,
            $consultorioId,
            $date,
            $isHoliday,
            $holidayName,
            $isOverride,
            $overrideTypes,
            $overridesEnabled,
            $collisionsEnabled,
            count($busyIntervals),
            $windowsBeforeCollisions,
            count($slots),
            $slotMinutes
        );
        $metaOut['doctor_id_requested'] = ($doctorIdRequested !== '' ? $doctorIdRequested : null);
        $metaOut['auth_mode'] = trim((string)($this->actorContext['mode'] ?? ''));
        $metaOut['auth_warnings'] = $this->contextWarnings;

        return $this->success(
            [
                'date' => $date,
                'timezone' => AvailabilityRepository::TIMEZONE,
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'windows' => $windows,
                'slots' => $slots,
            ],
            $metaOut
        );
    }

    /**
     * Public read-only availability.
     *
     * Manual QA curl examples:
     * - next mode:
     *   curl -s "http://127.0.0.1:8090/api/agenda/index.php/public/availability?doctor_id=1&mode=next&days=3"
     * - week mode:
     *   curl -s "http://127.0.0.1:8090/api/agenda/index.php/public/availability?doctor_id=1&mode=week&week_offset=0"
     */
    public function publicAvailability(array $params = []): array
    {
        if ($this->qaNotReady) {
            return $this->error('db_not_ready', 'availability base schedule not ready');
        }
        if ($this->dbError === 'database error') {
            return $this->error('db_error', 'database error');
        }
        if ($this->dbError || !$this->repository) {
            return $this->error('db_not_ready', 'availability base schedule not ready');
        }

        $doctorIdRequested = trim((string)($params['doctor_id'] ?? ''));
        $doctorId = $doctorIdRequested;
        $requestedConsultorioId = $params['consultorio_id'] ?? null;
        $mode = strtolower(trim((string)($params['mode'] ?? 'next')));
        if ($mode === '') {
            $mode = 'next';
        }
        if ($doctorIdRequested === '') {
            return $this->error('invalid_params', 'doctor_id is required', [
                'doctor_id' => null,
                'consultorio_id' => $requestedConsultorioId,
                'mode' => $mode,
                'consultorio_id_used' => null,
            ]);
        }
        if ($this->pdo) {
            try {
                $doctorId = DoctorIdentity\resolveCanonicalDoctorId($this->pdo, $doctorIdRequested);
            } catch (\Throwable $e) {
                $doctorId = $doctorIdRequested;
            }
        }

        $meta = [
            'doctor_id' => $doctorId,
            'doctor_id_requested' => $doctorIdRequested,
            'consultorio_id' => $requestedConsultorioId,
            'mode' => $mode,
            'consultorio_id_used' => null,
        ];

        if (!in_array($mode, ['next', 'week'], true)) {
            return $this->error('invalid_params', 'mode must be next or week', $meta);
        }

        $slotMinutes = $this->normalizeSlotMinutes($params['slot_minutes'] ?? null);
        if ($slotMinutes === null) {
            return $this->error('invalid_params', 'slot_minutes must be between 5 and 720', $meta);
        }

        $limitPerDay = 12;
        if (array_key_exists('limit_per_day', $params) && $params['limit_per_day'] !== '' && $params['limit_per_day'] !== null) {
            if (!preg_match('/^-?\d+$/', (string)$params['limit_per_day'])) {
                return $this->error('invalid_params', 'limit_per_day must be numeric', $meta);
            }
            $limitPerDay = (int)$params['limit_per_day'];
            if ($limitPerDay < 0) {
                return $this->error('invalid_params', 'limit_per_day must be >= 0', $meta);
            }
            if ($limitPerDay > 200) {
                $limitPerDay = 200;
            }
            if ($limitPerDay === 0) {
                $limitPerDay = null;
            }
        }

        $timezone = new DateTimeZone(AvailabilityRepository::TIMEZONE);
        $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0, 0);
        $startDateRaw = isset($params['start_date']) ? trim((string)$params['start_date']) : '';
        $hasStartDate = ($startDateRaw !== '');
        $startDate = $today;
        if ($hasStartDate) {
            if (!$this->isValidDate($startDateRaw)) {
                $meta['start_date'] = $startDateRaw;
                return $this->error('invalid_params', 'start_date must be in YYYY-MM-DD format', $meta);
            }
            $startDate = (new DateTimeImmutable($startDateRaw, $timezone))->setTime(0, 0, 0);
        }

        if ($mode === 'week') {
            $weekOffset = 0;
            if (array_key_exists('week_offset', $params) && $params['week_offset'] !== '' && $params['week_offset'] !== null) {
                if (!preg_match('/^-?\d+$/', (string)$params['week_offset'])) {
                    return $this->error('invalid_params', 'week_offset must be numeric', $meta);
                }
                $weekOffset = (int)$params['week_offset'];
            }
            if ($weekOffset < 0) {
                $weekOffset = 0;
            } elseif ($weekOffset > 3) {
                $weekOffset = 3;
            }

            $weekStart = $today->modify('monday this week')->modify('+' . $weekOffset . ' week');
            $weekEnd = $weekStart->modify('+6 day');
            $consultorioId = $this->resolvePublicConsultorioIdForRange(
                (string)$doctorId,
                $requestedConsultorioId,
                $weekStart,
                7,
                $slotMinutes
            );
            if (!$this->isValidNumeric($consultorioId)) {
                return $this->error(
                    'invalid_params',
                    'consultorio_id must be numeric',
                    $meta
                );
            }
            $consultorioId = (string)$consultorioId;
            $meta['consultorio_id_used'] = $consultorioId;
            $days = [];

            for ($i = 0; $i < 7; $i++) {
                $date = $weekStart->modify('+' . $i . ' day');
                $dayResult = $this->publicDayAvailability(
                    (string)$doctorId,
                    (string)$consultorioId,
                    $date->format('Y-m-d'),
                    $slotMinutes
                );
                if (($dayResult['ok'] ?? false) !== true) {
                    $response = $dayResult['response'];
                    $errorMeta = (array)($response['meta'] ?? []);
                    $errorMeta['consultorio_id_used'] = $consultorioId;
                    $response['meta'] = (object)$errorMeta;
                    return $response;
                }
                $slots = $dayResult['slots'] ?? [];
                if ($limitPerDay !== null && count($slots) > $limitPerDay) {
                    $slots = array_slice($slots, 0, $limitPerDay);
                }
                if (!empty($slots)) {
                    $days[] = [
                        'date' => $date->format('Y-m-d'),
                        'weekday' => (int)$date->format('N'),
                        'slots' => $slots,
                    ];
                }
            }

            return [
                'ok' => true,
                'error' => null,
                'message' => empty($days) ? 'no availability' : 'availability found',
                'data' => [
                    'mode' => 'week',
                    'week_offset' => $weekOffset,
                    'week_start' => $weekStart->format('Y-m-d'),
                    'week_end' => $weekEnd->format('Y-m-d'),
                    'days' => $days,
                ],
                'meta' => [
                    'mode' => 'week',
                    'week_offset' => $weekOffset,
                    'slot_minutes' => $slotMinutes,
                    'limit_per_day' => $limitPerDay,
                    'consultorio_id_used' => $consultorioId,
                    'timezone' => AvailabilityRepository::TIMEZONE,
                ],
            ];
        }

        $daysRequested = 3;
        if (array_key_exists('days', $params) && $params['days'] !== '' && $params['days'] !== null) {
            if (!preg_match('/^-?\d+$/', (string)$params['days'])) {
                return $this->error('invalid_params', 'days must be numeric', $meta);
            }
            $daysRequested = (int)$params['days'];
        }
        if ($daysRequested < 1) {
            $daysRequested = 1;
        } elseif ($daysRequested > 7) {
            $daysRequested = 7;
        }

        $consultorioId = $this->resolvePublicConsultorioIdForRange(
            (string)$doctorId,
            $requestedConsultorioId,
            $startDate,
            90,
            $slotMinutes
        );
        if (!$this->isValidNumeric($consultorioId)) {
            return $this->error(
                'invalid_params',
                'consultorio_id must be numeric',
                $meta
            );
        }
        $consultorioId = (string)$consultorioId;
        $meta['consultorio_id_used'] = $consultorioId;

        $days = [];
        $maxScanDays = 90;
        for ($offset = 0; $offset < $maxScanDays && count($days) < $daysRequested; $offset++) {
            $date = $startDate->modify('+' . $offset . ' day');
            $dayResult = $this->publicDayAvailability(
                (string)$doctorId,
                (string)$consultorioId,
                $date->format('Y-m-d'),
                $slotMinutes
            );
            if (($dayResult['ok'] ?? false) !== true) {
                $response = $dayResult['response'];
                $errorMeta = (array)($response['meta'] ?? []);
                $errorMeta['consultorio_id_used'] = $consultorioId;
                if ($hasStartDate) {
                    $errorMeta['start_date_used'] = $startDate->format('Y-m-d');
                }
                $response['meta'] = (object)$errorMeta;
                return $response;
            }
            $slots = $dayResult['slots'] ?? [];
            if ($limitPerDay !== null && count($slots) > $limitPerDay) {
                $slots = array_slice($slots, 0, $limitPerDay);
            }
            if (!empty($slots)) {
                $days[] = [
                    'date' => $date->format('Y-m-d'),
                    'weekday' => (int)$date->format('N'),
                    'slots' => $slots,
                ];
            }
        }

        $nextMeta = [
            'mode' => 'next',
            'days_requested' => $daysRequested,
            'days_found' => count($days),
            'slot_minutes' => $slotMinutes,
            'limit_per_day' => $limitPerDay,
            'consultorio_id_used' => $consultorioId,
            'timezone' => AvailabilityRepository::TIMEZONE,
        ];
        if ($hasStartDate) {
            $nextMeta['start_date_used'] = $startDate->format('Y-m-d');
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => empty($days) ? 'no availability' : 'availability found',
            'data' => [
                'mode' => 'next',
                'days' => $days,
            ],
            'meta' => $nextMeta,
        ];
    }

    private function resolvePublicConsultorioIdForRange(
        string $doctorId,
        $requestedConsultorioId,
        DateTimeImmutable $startDate,
        int $scanDays,
        int $slotMinutes
    ): ?string
    {
        if ($this->isValidNumeric($requestedConsultorioId)) {
            return (string)$requestedConsultorioId;
        }

        try {
            $pdo = $this->pdo ?? mxmed_pdo();
        } catch (\Throwable $e) {
            return null;
        }

        $scheduledConsultorios = $this->resolveConsultoriosFromSchedule($pdo, $doctorId);
        foreach ($scheduledConsultorios as $candidate) {
            if ($this->consultorioHasPublicAvailability($doctorId, $candidate, $startDate, $scanDays, $slotMinutes)) {
                return $candidate;
            }
        }

        if (!empty($scheduledConsultorios)) {
            return $scheduledConsultorios[0];
        }
        return $this->resolveConsultorioFromCatalog($pdo, $doctorId);
    }

    private function consultorioHasPublicAvailability(
        string $doctorId,
        string $consultorioId,
        DateTimeImmutable $startDate,
        int $scanDays,
        int $slotMinutes
    ): bool {
        $safeScanDays = max(1, min(90, $scanDays));
        for ($offset = 0; $offset < $safeScanDays; $offset++) {
            $date = $startDate->modify('+' . $offset . ' day');
            $dayResult = $this->publicDayAvailability(
                $doctorId,
                $consultorioId,
                $date->format('Y-m-d'),
                $slotMinutes
            );
            if (($dayResult['ok'] ?? false) !== true) {
                continue;
            }
            $slots = $dayResult['slots'] ?? [];
            if (is_array($slots) && !empty($slots)) {
                return true;
            }
        }
        return false;
    }

    private function resolveConsultorioFromCatalog(PDO $pdo, string $doctorId): ?string
    {
        try {
            $repository = new ConsultoriosRepository($pdo);
            $rows = $repository->listByDoctor($doctorId);
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidate = $row['consultorio_id'] ?? $row['id'] ?? null;
            if ($this->isValidNumeric($candidate)) {
                return (string)$candidate;
            }
        }

        return null;
    }

    private function resolveConsultoriosFromSchedule(PDO $pdo, string $doctorId): array
    {
        $tableCandidates = [
            'consultorio_schedule',
            'consultorio_schedules',
            'consultorio_horarios',
            'consultorio_horarios_base',
            'agenda_consultorio_schedule',
        ];

        foreach ($tableCandidates as $tableName) {
            if (!$this->tableExists($pdo, $tableName)) {
                continue;
            }
            try {
                $activeFilter = $this->tableColumnExists($pdo, $tableName, 'is_active')
                    ? ' AND is_active = 1'
                    : '';
                $sql = sprintf(
                    'SELECT consultorio_id
                       FROM %s
                      WHERE doctor_id = :doctor_id
                        %s
                      GROUP BY consultorio_id
                      ORDER BY consultorio_id ASC',
                    $tableName,
                    $activeFilter
                );
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['doctor_id' => $doctorId]);
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($rows)) {
                continue;
            }
            $candidates = [];
            foreach ($rows as $candidate) {
                if ($this->isValidNumeric($candidate)) {
                    $candidates[] = (string)$candidate;
                }
            }
            if (!empty($candidates)) {
                return array_values(array_unique($candidates));
            }
        }

        return [];
    }

    private function tableExists(PDO $pdo, string $tableName): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
            $stmt->execute(['table' => $tableName]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tableColumnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema = DATABASE()
                    AND table_name = :table
                    AND column_name = :column'
            );
            $stmt->execute([
                'table' => $tableName,
                'column' => $columnName,
            ]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isValidNumeric($value): bool
    {
        if ($value === null) {
            return false;
        }
        return ctype_digit((string)$value);
    }

    private function isValidDate(?string $value): bool
    {
        if (!$value) {
            return false;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return $dt && $dt->format('Y-m-d') === $value;
    }

    private function normalizeSlotMinutes($value): ?int
    {
        if ($value === null || $value === '') {
            return 30;
        }
        $minutes = (int)$value;
        if ($minutes < 5 || $minutes > 720) {
            return null;
        }
        return $minutes;
    }

    private function publicDayAvailability(
        string $doctorId,
        string $consultorioId,
        string $dateYmd,
        int $slotMinutes
    ): array {
        $dayResponse = $this->index([
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'date' => $dateYmd,
            'slot_minutes' => $slotMinutes,
        ]);

        if (($dayResponse['ok'] ?? false) !== true) {
            $meta = (array)($dayResponse['meta'] ?? []);
            $meta['date'] = $dateYmd;
            return [
                'ok' => false,
                'response' => [
                    'ok' => false,
                    'error' => (string)($dayResponse['error'] ?? 'db_error'),
                    'message' => (string)($dayResponse['message'] ?? 'database error'),
                    'data' => null,
                    'meta' => (object)$meta,
                ],
            ];
        }

        $slots = [];
        $rawSlots = $dayResponse['data']['slots'] ?? [];
        if (is_array($rawSlots)) {
            foreach ($rawSlots as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                $startAt = (string)($slot['start_at'] ?? '');
                $endAt = (string)($slot['end_at'] ?? '');
                if ($startAt === '' || $endAt === '') {
                    continue;
                }
                $slots[] = [
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                ];
            }
        }

        usort($slots, static function (array $a, array $b): int {
            $cmp = strcmp($a['start_at'], $b['start_at']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['end_at'], $b['end_at']);
        });

        return [
            'ok' => true,
            'slots' => $slots,
        ];
    }

    private function resolveDoctorScope(string $doctorIdRequested, bool $doctorIsRequired): array
    {
        $doctorIdContext = trim((string)($this->actorContext['doctor_id'] ?? ''));
        $strictMode = ($this->actorContext['strict'] ?? false) === true;
        if ($doctorIdContext !== '') {
            if ($doctorIdRequested !== '' && $doctorIdRequested !== $doctorIdContext) {
                if ($strictMode) {
                    return [
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor scope mismatch',
                        'meta' => [
                            'doctor_id_requested' => $doctorIdRequested,
                            'doctor_id_context' => $doctorIdContext,
                        ],
                    ];
                }
                $this->contextWarnings[] = [
                    'type' => 'doctor_scope_mismatch',
                    'doctor_id_requested' => $doctorIdRequested,
                    'doctor_id_context' => $doctorIdContext,
                ];
            }
            return ['ok' => true, 'doctor_id' => $doctorIdContext];
        }
        if ($doctorIsRequired && $doctorIdRequested === '') {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'doctor_id is required',
                'meta' => [
                    'doctor_id' => null,
                ],
            ];
        }
        return ['ok' => true, 'doctor_id' => $doctorIdRequested];
    }

    private function error(string $code, string $message, array $meta = [])
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }

    private function success(array $data, array $meta = [])
    {
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }

    private function buildMeta(
        string $doctorId,
        string $consultorioId,
        string $date,
        bool $isHoliday,
        ?string $holidayName = null,
        bool $isOverride = false,
        array $overrideTypes = [],
        bool $overridesEnabled = false,
        bool $collisionsEnabled = false,
        int $busyCount = 0,
        int $windowsBeforeCollisions = 0,
        int $slotsCount = 0,
        int $slotMinutes = 30
    ): array {
        $meta = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'date' => $date,
            'is_holiday' => $isHoliday,
            'overrides_enabled' => $overridesEnabled,
            'is_override' => $isOverride,
            'override_types' => $overrideTypes,
            'collisions_enabled' => $collisionsEnabled,
            'busy_count' => $busyCount,
            'windows_before_collisions' => $windowsBeforeCollisions,
            'slots_count' => $slotsCount,
            'slot_minutes' => $slotMinutes,
        ];
        if ($isHoliday && $holidayName) {
            $meta['holiday_name'] = $holidayName;
        }
        return $meta;
    }

    private function buildOverrideWindows(array $overrides): array
    {
        return array_map(fn(array $override) => [
            'start_at' => $override['start_at'],
            'end_at' => $override['end_at'],
            'source' => 'C',
        ], $overrides);
    }

    private function subtractIntervals(array $windows, array $closes): array
    {
        $result = [];
        foreach ($windows as $window) {
            $segments = [$window];
            foreach ($closes as $close) {
                $temporary = [];
                foreach ($segments as $segment) {
                    $temporary = array_merge($temporary, $this->subtractSegment($segment, $close));
                }
                $segments = $temporary;
                if (empty($segments)) {
                    break;
                }
            }
            $result = array_merge($result, $segments);
        }
        return $result;
    }

    private function subtractSegment(array $segment, array $close): array
    {
        $segmentStart = $this->toTimestamp($segment['start_at']);
        $segmentEnd   = $this->toTimestamp($segment['end_at']);
        $closeStart   = $this->toTimestamp($close['start_at']);
        $closeEnd     = $this->toTimestamp($close['end_at']);

        if ($closeEnd <= $segmentStart || $closeStart >= $segmentEnd) {
            return [$segment];
        }

        $parts = [];
        if ($closeStart > $segmentStart) {
            $parts[] = [
                'start_at' => $segment['start_at'],
                'end_at'   => $this->formatTimestamp(min($closeStart, $segmentEnd)),
                'source'   => $segment['source'] ?? 'A',
            ];
        }
        if ($closeEnd < $segmentEnd) {
            $parts[] = [
                'start_at' => $this->formatTimestamp(max($closeEnd, $segmentStart)),
                'end_at'   => $segment['end_at'],
                'source'   => $segment['source'] ?? 'A',
            ];
        }

        return $parts;
    }

    private function sortWindows(array $windows): array
    {
        usort($windows, fn($a, $b) => strcmp($a['start_at'], $b['start_at']));
        return $windows;
    }

    private function deduplicateWindows(array $windows): array
    {
        $seen = [];
        $deduped = [];
        foreach ($windows as $window) {
            $key = sprintf('%s|%s|%s', $window['start_at'], $window['end_at'], $window['source'] ?? 'A');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $window;
        }
        return $deduped;
    }


    private function generateSlots(array $windows, int $slotMinutes): array
    {
        if ($slotMinutes <= 0) {
            return [];
        }
        $maxSlots = 5000;
        $slots = [];
        foreach ($windows as $window) {
            $startTs = $this->toTimestamp($window['start_at']);
            $endTs = $this->toTimestamp($window['end_at']);
            $step = $slotMinutes * 60;
            if ($step <= 0 || $startTs >= $endTs) {
                continue;
            }
            $cursor = $startTs;
            while ($cursor + $step <= $endTs) {
                $slots[] = [
                    'start_at' => $this->formatTimestamp($cursor),
                    'end_at' => $this->formatTimestamp($cursor + $step),
                ];
                $cursor += $step;
                if (count($slots) > $maxSlots) {
                    break;
                }
            }
        }
        return $slots;
    }

    private function hasFullDayClose(array $closes, string $date): bool
    {
        if (empty($closes)) {
            return false;
        }
        $startOfDay = "{$date} 00:00:00";
        $endOfDay   = "{$date} 23:59:59";
        foreach ($closes as $close) {
            if ($this->toTimestamp($close['start_at']) <= $this->toTimestamp($startOfDay)
                && $this->toTimestamp($close['end_at']) >= $this->toTimestamp($endOfDay)
            ) {
                return true;
            }
        }
        return false;
    }

    private function toTimestamp(string $datetime): int
    {
        $dt = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $datetime,
            new DateTimeZone(AvailabilityRepository::TIMEZONE)
        );
        if (!$dt) {
            return (int)strtotime($datetime);
        }
        return (int)$dt->format('U');
    }

    private function formatTimestamp(int $timestamp): string
    {
        $dt = new DateTime('@' . $timestamp);
        $dt->setTimezone(new DateTimeZone(AvailabilityRepository::TIMEZONE));
        return $dt->format('Y-m-d H:i:s');
    }
}
