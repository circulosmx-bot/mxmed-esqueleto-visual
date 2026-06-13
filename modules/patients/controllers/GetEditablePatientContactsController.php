<?php
namespace Patients\Controllers;

use Patients\Repositories\PatientsRepository;
use Agenda\Helpers as DbHelpers;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/PatientsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../agenda/helpers/db_helpers.php';

class GetEditablePatientContactsController
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

    public function handle(string $doctorId, string $patientId): array
    {
        $meta = ['visibility' => ['contacts' => 'editable_private']];
        if ($this->qaNotReady) {
            return $this->error('db_not_ready', 'patients db not ready', 503, $meta);
        }
        if ($this->dbError || !$this->repo) {
            return $this->error('db_not_ready', 'patients db not ready', 503, $meta);
        }
        if (trim($doctorId) === '' || trim($patientId) === '') {
            return $this->error('invalid_params', 'doctor_id and patient_id required', 400, $meta);
        }

        try {
            if (!$this->repo->patientExists($patientId)) {
                return $this->error('not_found', 'patient_id unknown', 404, $meta);
            }
            if (!$this->repo->hasActiveDoctorPatientLink($doctorId, $patientId)) {
                return $this->error('forbidden', 'doctor patient link required', 403, $meta);
            }
            $contacts = $this->repo->fetchEditableContacts($patientId);
            return $this->success(['contacts' => $contacts], $meta);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'patients not ready') {
                return $this->error('db_not_ready', 'patients db not ready', 503, $meta);
            }
            return $this->error('db_error', 'database error', 500, $meta);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', 500, $meta);
        }
    }

    private function success(array $data, array $meta = []): array
    {
        return ['ok' => true, 'error' => null, 'message' => '', 'data' => $data, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }

    private function error(string $code, string $message, int $httpStatus, array $meta = []): array
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
            'http_status' => $httpStatus,
        ];
    }
}
