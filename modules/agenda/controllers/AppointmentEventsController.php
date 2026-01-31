<?php
namespace Agenda\Controllers;

use Agenda\Repositories\AppointmentEventsRepository;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/AppointmentEventsRepository.php';
require_once __DIR__ . '/../../api/_lib/db.php';

class AppointmentEventsController
{
    private ?AppointmentEventsRepository $repository = null;
    private ?string $dbError = null;

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new AppointmentEventsRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function index(string $appointmentId, array $params = [])
    {
        if ($this->dbError) {
            return $this->error('db_not_ready', $this->dbError);
        }

        if (!is_string($appointmentId) || trim($appointmentId) === '') {
            return $this->error('invalid_params', 'appointment_id is required', ['appointment_id' => $appointmentId ?? null]);
        }

        $limit = $this->normalizeLimit($params['limit'] ?? null);

        try {
            $events = $this->repository->listByAppointmentId($appointmentId, $limit);
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', $e->getMessage());
        } catch (PDOException $e) {
            return $this->error('db_error', $e->getMessage());
        }

        return $this->success($events, ['appointment_id' => $appointmentId, 'limit' => $limit, 'count' => count($events)]);
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
