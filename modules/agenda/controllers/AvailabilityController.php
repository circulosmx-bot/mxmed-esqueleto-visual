<?php
namespace Agenda\Controllers;

use Agenda\Repositories\AvailabilityRepository;
use Agenda\Services\HolidayMxProvider;
use Agenda\Helpers as DbHelpers;
use DateTime;
use DateTimeZone;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/AvailabilityRepository.php';
require_once __DIR__ . '/../services/HolidayMxProvider.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class AvailabilityController
{
    private ?AvailabilityRepository $repository = null;
    private ?string $dbError = null;
    private bool $qaNotReady = false;

    public function __construct()
    {
        $this->qaNotReady = DbHelpers\isQaModeNotReady();
        if ($this->qaNotReady) {
            return;
        }
        try {
            $pdo = mxmed_pdo();
            $this->repository = new AvailabilityRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function index(array $params = [])
    {
        if ($this->qaNotReady) {
            return $this->error('db_not_ready', 'availability base schedule not ready');
        }
        if ($this->dbError) {
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

        $holiday = HolidayMxProvider::isHoliday($date);
        $isHoliday = $holiday['is_holiday'];
        $holidayName = $holiday['name'];

        $overrides = [];
        $overridesEnabled = false;

        $closeOverrides = array_values(array_filter($overrides, fn($override) => $override['type'] === 'close'));
        $openOverrides = array_values(array_filter($overrides, fn($override) => $override['type'] === 'open'));
        $hasOpen = !empty($openOverrides);
        $hasCloseFullDay = $this->hasFullDayClose($closeOverrides, $date);
        $shouldLoadBase = (!$isHoliday || !empty($closeOverrides) || $hasOpen) && (!$hasCloseFullDay || $hasOpen);

        $baseWindows = [];
        if ($shouldLoadBase) {
            try {
                $baseWindows = $this->repository->getBaseWindowsForDate($doctorId, $consultorioId, $date);
            } catch (RuntimeException $e) {
                return $this->error('db_not_ready', $e->getMessage());
            } catch (PDOException $e) {
                return $this->error('db_error', $e->getMessage());
            }
        }

        $windows = $baseWindows;
        if (!empty($closeOverrides)) {
            $windows = $this->subtractIntervals($windows, $closeOverrides);
        }
        if (!empty($openOverrides)) {
            // Layer C may reopen/override holidays in a later phase (not implemented yet).
            $windows = array_merge($windows, $this->buildOverrideWindows($openOverrides));
        }

        $windows = $this->sortWindows($windows);
        $isOverride = !empty($overrides);
        $overrideTypes = $isOverride ? array_values(array_unique(array_map(fn($override) => $override['type'], $overrides))) : [];

        return $this->success(
            [
                'date' => $date,
                'timezone' => AvailabilityRepository::TIMEZONE,
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'windows' => $windows,
            ],
            $this->buildMeta($doctorId, $consultorioId, $date, $isHoliday, $holidayName, $isOverride, $overrideTypes, $overridesEnabled)
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
        bool $overridesEnabled = false
    ): array {
        $meta = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'date' => $date,
            'is_holiday' => $isHoliday,
            'overrides_enabled' => $overridesEnabled,
            'is_override' => $isOverride,
            'override_types' => $overrideTypes,
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
        $segmentEnd = $this->toTimestamp($segment['end_at']);
        $closeStart = $this->toTimestamp($close['start_at']);
        $closeEnd = $this->toTimestamp($close['end_at']);

        if ($closeEnd <= $segmentStart || $closeStart >= $segmentEnd) {
            return [$segment];
        }

        $parts = [];
        if ($closeStart > $segmentStart) {
            $parts[] = [
                'start_at' => $segment['start_at'],
                'end_at' => $this->formatTimestamp(min($closeStart, $segmentEnd)),
                'source' => $segment['source'] ?? 'A',
            ];
        }
        if ($closeEnd < $segmentEnd) {
            $parts[] = [
                'start_at' => $this->formatTimestamp(max($closeEnd, $segmentStart)),
                'end_at' => $segment['end_at'],
                'source' => $segment['source'] ?? 'A',
            ];
        }

        return $parts;
    }

    private function sortWindows(array $windows): array
    {
        usort($windows, fn($a, $b) => strcmp($a['start_at'], $b['start_at']));
        return $windows;
    }

    private function hasFullDayClose(array $closes, string $date): bool
    {
        if (empty($closes)) {
            return false;
        }
        $startOfDay = "{$date} 00:00:00";
        $endOfDay = "{$date} 23:59:59";
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
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime, new DateTimeZone(AvailabilityRepository::TIMEZONE));
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
