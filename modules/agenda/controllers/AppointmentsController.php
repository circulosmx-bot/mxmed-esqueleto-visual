<?php
namespace Agenda\Controllers;

use Agenda\Repositories\AppointmentsRepository;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/AppointmentsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class AppointmentsController
{
    private ?AppointmentsRepository $repository = null;
    private ?string $dbError = null;

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new AppointmentsRepository($pdo);
        } catch (\RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function index(array $params = [])
    {
        if ($this->dbError) {
            return $this->error('db_not_ready', 'appointments table not ready');
        }

        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;
        if (!$from || !$to) {
            return $this->error('invalid_params', 'from and to parameters are required', ['from' => $from, 'to' => $to]);
        }
        $fromDatetime = $this->parseDatetime($from);
        $toDatetime = $this->parseDatetime($to);
        if (!$fromDatetime || !$toDatetime) {
            return $this->error('invalid_params', 'from/to must be valid datetimes', ['from' => $from, 'to' => $to]);
        }

        $doctorId = $params['doctor_id'] ?? null;
        $consultorioId = $params['consultorio_id'] ?? null;
        $limit = isset($params['limit']) ? min(500, (int)$params['limit']) : 200;

        try {
            $data = $this->repository->listByRange(
                $fromDatetime,
                $toDatetime,
                $doctorId,
                $consultorioId,
                $limit
            );
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'appointments table not ready') {
                return $this->error('db_not_ready', 'appointments table not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => (object)['count' => count($data)],
        ];
    }

    public function show($id)
    {
        if ($this->dbError) {
            return $this->error('db_not_ready', 'appointments table not ready');
        }
        if (!$id) {
            return $this->error('invalid_params', 'appointment id is required', ['id' => $id]);
        }
        try {
            $row = $this->repository->getById($id);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'appointments table not ready') {
                return $this->error('db_not_ready', 'appointments table not ready');
            }
            if ($msg === 'appointment not found') {
                return $this->error('not_found', 'appointment not found');
            }
            return $this->error('db_error', 'database error');
        } catch (\PDOException $e) {
            return $this->error('db_error', 'database error');
        }
        if (!$row) {
            return $this->error('not_found', 'appointment not found');
        }
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $row,
            'meta' => (object)[],
        ];
    }

    private function parseDatetime(string $value): ?string
    {
        $dt = date_create($value);
        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }

    private function error(string $code, string $message, array $meta = [])
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => (object)$meta,
        ];
    }
}
