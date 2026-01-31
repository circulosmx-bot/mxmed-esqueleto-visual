<?php
namespace Agenda\Controllers;

use Agenda\Repositories\AvailabilityRepository;
use Agenda\Services\HolidayMxProvider;
use DateTime;
use DateTimeZone;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/AvailabilityRepository.php';
require_once __DIR__ . '/../services/HolidayMxProvider.php';
require_once __DIR__ . '/../../api/_lib/db.php';

class AvailabilityController
{
    private ?AvailabilityRepository $repository = null;
    private ?string $dbError = null;

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new AvailabilityRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function index(array $params = [])
    {
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

        if ($isHoliday) {
            return $this->success(
                [
                    'date' => $date,
                    'timezone' => AvailabilityRepository::TIMEZONE,
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                    'windows' => [],
                ],
                $this->buildMeta($doctorId, $consultorioId, $date, true, $holidayName)
            );
        }

        try {
            $windows = $this->repository->getBaseWindowsForDate($doctorId, $consultorioId, $date);
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', $e->getMessage());
        } catch (PDOException $e) {
            return $this->error('db_error', $e->getMessage());
        }

        return $this->success(
            [
                'date' => $date,
                'timezone' => AvailabilityRepository::TIMEZONE,
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'windows' => $windows,
            ],
            $this->buildMeta($doctorId, $consultorioId, $date, false)
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

    private function buildMeta(string $doctorId, string $consultorioId, string $date, bool $isHoliday, ?string $holidayName = null): array
    {
        $meta = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'date' => $date,
            'is_holiday' => $isHoliday,
        ];
        if ($isHoliday && $holidayName) {
            $meta['holiday_name'] = $holidayName;
        }
        return $meta;
    }
}
