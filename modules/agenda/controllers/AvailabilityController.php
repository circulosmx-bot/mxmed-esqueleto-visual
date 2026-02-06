<?php
namespace Agenda\Controllers;

use Agenda\Repositories\AvailabilityRepository;
use Agenda\Repositories\OverrideRepository;
use Agenda\Repositories\AppointmentCollisionsRepository;
use Agenda\Services\HolidayMxProvider;
use Agenda\Helpers as DbHelpers;
use DateTime;
use DateTimeZone;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/AvailabilityRepository.php';
require_once __DIR__ . '/../repositories/OverrideRepository.php';
require_once __DIR__ . '/../repositories/AppointmentCollisionsRepository.php';
require_once __DIR__ . '/../services/HolidayMxProvider.php';
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

    public function __construct()
    {
        $this->qaNotReady = DbHelpers\isQaModeNotReady();
        if ($this->qaNotReady) {
            return;
        }

        $pdo = null;

        // 1) Conexión + repo base (capa A)
        try {
            $pdo = mxmed_pdo();
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

    public function index(array $params = [])
    {
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

        $doctorId = $params['doctor_id'] ?? null;
        $consultorioId = $params['consultorio_id'] ?? null;
        $date = $params['date'] ?? null;

        $meta = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'date' => $date,
        ];

        if (!$this->isValidNumeric($doctorId)) {
            return $this->error('invalid_params', 'doctor_id must be numeric', $meta);
        }
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

        return $this->success(
            [
                'date' => $date,
                'timezone' => AvailabilityRepository::TIMEZONE,
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'windows' => $windows,
                'slots' => $slots,
            ],
            $this->buildMeta(
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
            )
        );
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
