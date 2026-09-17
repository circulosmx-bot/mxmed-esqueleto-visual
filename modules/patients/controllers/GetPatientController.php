<?php
namespace Patients\Controllers;

use Patients\Repositories\PatientsRepository;
use Agenda\Helpers as DbHelpers;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/PatientsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../agenda/helpers/db_helpers.php';

class GetPatientController
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

    public function handle(string $patientId): array
    {
        $meta = ['visibility' => ['contact' => 'masked']];
        if (session_status() === PHP_SESSION_NONE) session_start();
        $sessionDoctorId = trim((string)($_SESSION['doctor_id'] ?? $_SESSION['active_doctor_id'] ?? $_SESSION['mxmed_doctor_id'] ?? ''));
        $sessionUserId = trim((string)($_SESSION['user_id'] ?? $_SESSION['mxmed_user_id'] ?? $_SESSION['auth_user_id'] ?? $_SESSION['actor_user_id'] ?? ''));
        if ($sessionDoctorId === '' || $sessionUserId === '') {
            return $this->error('unauthorized', 'authentication required', $meta, 401);
        }
        if ($this->qaNotReady) {
            return $this->error('db_not_ready', 'patients db not ready', $meta);
        }
        if ($this->dbError) {
            return $this->error('db_not_ready', 'patients db not ready', $meta);
        }
        if (!$this->repo) {
            return $this->error('db_not_ready', 'patients db not ready', $meta);
        }
        if (trim($patientId) === '') {
            return $this->error('invalid_params', 'patient_id required', $meta);
        }
        try {
            // The link is checked before any patient payload is loaded. Foreign and unknown
            // IDs share one response so this endpoint cannot enumerate other doctors' patients.
            if (!$this->repo->hasActiveDoctorPatientLink($sessionDoctorId, $patientId)) {
                return $this->error('not_found', 'patient_id unknown', $meta, 404);
            }
            $patient = $this->repo->findPatientById($patientId);
            if (!$patient) {
                return $this->error('not_found', 'patient_id unknown', $meta, 404);
            }
            return $this->success($patient, $meta);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'patients not ready') {
                return $this->error('db_not_ready', 'patients db not ready', $meta);
            }
            return $this->error('db_error', 'database error', $meta);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', $meta);
        }
    }

    private function success(array $data, array $meta = []): array
    {
        return ['ok' => true, 'error' => null, 'message' => '', 'data' => $data, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }

    private function error(string $code, string $message, array $meta = [], ?int $httpStatus = null): array
    {
        $response = ['ok' => false, 'error' => $code, 'message' => $message, 'data' => null, 'meta' => empty($meta) ? (object)[] : (object)$meta];
        if ($httpStatus !== null) $response['http_status'] = $httpStatus;
        return $response;
    }
}
