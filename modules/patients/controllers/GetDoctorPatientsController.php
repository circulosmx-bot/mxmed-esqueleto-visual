<?php
namespace Patients\Controllers;

use Patients\Repositories\PatientsRepository;
use Agenda\Helpers as DbHelpers;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/PatientsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../agenda/helpers/db_helpers.php';

class GetDoctorPatientsController
{
    private ?PatientsRepository $repo = null;
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
            $this->repo = new PatientsRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
        } catch (PDOException $e) {
            $this->dbError = 'db_error';
        }
    }

    public function handle(string $doctorId, array $query = []): array
    {
        $metaBase = ['visibility' => ['contact' => 'masked']];

        if ($this->qaNotReady) {
            return $this->error('db_not_ready', 'patients db not ready', $metaBase);
        }
        if ($this->dbError || !$this->repo) {
            return $this->error('db_not_ready', 'patients db not ready', $metaBase);
        }
        if (trim($doctorId) === '') {
            return $this->error('invalid_params', 'doctor_id required', $metaBase);
        }

        $limit = 50;
        if (isset($query['limit'])) {
            $limit = (int)$query['limit'];
            if ($limit < 1 || $limit > 200) {
                return $this->error('invalid_params', 'limit out of range', $metaBase);
            }
        }

        try {
            $patients = $this->repo->findPatientsByDoctorId($doctorId, $limit);
            return $this->success($patients, [
                'visibility' => ['contact' => 'masked'],
                'paging' => ['limit' => $limit],
            ]);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'patients not ready') {
                return $this->error('db_not_ready', 'patients db not ready', $metaBase);
            }
            return $this->error('db_error', 'database error', $metaBase);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', $metaBase);
        }
    }

    private function success(array $data, array $meta = []): array
    {
        return ['ok' => true, 'error' => null, 'message' => '', 'data' => $data, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }

    private function error(string $code, string $message, array $meta = []): array
    {
        return ['ok' => false, 'error' => $code, 'message' => $message, 'data' => null, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }
}
