<?php
namespace Agenda\Controllers;

use Agenda\Repositories\AppointmentEventsRepository;
use Agenda\Repositories\AppointmentsRepository;
use PDOException;
use RuntimeException;
use Agenda\Helpers as DbHelpers;

require_once __DIR__ . '/../repositories/AppointmentEventsRepository.php';
require_once __DIR__ . '/../repositories/AppointmentsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../helpers/db_helpers.php';

class AppointmentEventsController
{
    private ?AppointmentEventsRepository $repository = null;
    private ?AppointmentsRepository $appointmentsRepository = null;
    private ?string $dbError = null;
    private array $actorContext = [];

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new AppointmentEventsRepository($pdo);
            $this->appointmentsRepository = new AppointmentsRepository($pdo);
        } catch (\RuntimeException $e) {
            if (DbHelpers\shouldTreatAsNotReady($e)) {
                $this->dbError = 'appointment events not ready';
            } else {
                $this->dbError = $e->getMessage();
            }
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function index(string $appointmentId, array $params = [])
    {
        if (DbHelpers\isQaModeNotReady()) {
            return $this->error('db_not_ready', 'appointment events not ready');
        }

        if ($this->dbError) {
            return $this->error('db_not_ready', $this->dbError);
        }

        if (!is_string($appointmentId) || trim($appointmentId) === '') {
            return $this->error('invalid_params', 'appointment_id is required', ['appointment_id' => $appointmentId ?? null]);
        }

        $limit = $this->normalizeLimit($params['limit'] ?? null);
        $scopeGuard = $this->guardAppointmentScope($appointmentId);
        if (is_array($scopeGuard)) {
            return $scopeGuard;
        }

        try {
            $events = $this->repository->listByAppointmentId($appointmentId, $limit);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'appointment events not ready') {
                return $this->error('db_not_ready', 'appointment events not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (\PDOException $e) {
            if (DbHelpers\shouldTreatAsNotReady($e)) {
                return $this->error('db_not_ready', 'appointment events not ready');
            }
            return $this->error('db_error', 'database error');
        }

        return $this->success($events, ['appointment_id' => $appointmentId, 'limit' => $limit, 'count' => count($events)]);
    }

    private function guardAppointmentScope(string $appointmentId): ?array
    {
        if (!$this->appointmentsRepository) {
            return $this->error('db_not_ready', 'appointments table not ready');
        }

        try {
            $appointment = $this->appointmentsRepository->getById($appointmentId);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'appointments table not ready') {
                return $this->error('db_not_ready', 'appointments table not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        if (!$appointment) {
            return $this->error('not_found', 'appointment not found');
        }

        $doctorIdContext = trim((string)($this->actorContext['doctor_id'] ?? ''));
        $appointmentDoctorId = trim((string)($appointment['doctor_id'] ?? ''));
        if ($doctorIdContext === '' || $appointmentDoctorId === '' || $appointmentDoctorId === $doctorIdContext) {
            return null;
        }

        $strictMode = ($this->actorContext['strict'] ?? false) === true;
        if ($strictMode) {
            return $this->error('forbidden', 'appointment out of doctor scope', [
                'appointment_id' => $appointmentId,
            ]);
        }

        // En modo compat se devuelve not_found para no exponer existencia cruzada.
        return $this->error('not_found', 'appointment not found');
    }

    private function normalizeLimit($value): int
    {
        $limit = 200;
        if ($value !== null) {
            $limit = max(1, min(500, (int)$value));
        }
        return $limit;
    }

    private function success(array $data, array $meta = [])
    {
        return ['ok' => true, 'error' => null, 'message' => '', 'data' => $data, 'meta' => (object)$meta];
    }

    private function error(string $code, string $message, array $meta = [])
    {
        return ['ok' => false, 'error' => $code, 'message' => $message, 'data' => null, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }
}
