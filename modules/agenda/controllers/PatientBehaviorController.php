<?php
namespace Agenda\Controllers;

use Agenda\Repositories\PatientBehaviorRepository;
use Agenda\Services\PatientBehaviorService;
use Agenda\Helpers as DbHelpers;

require_once __DIR__ . '/../repositories/PatientBehaviorRepository.php';
require_once __DIR__ . '/../services/PatientBehaviorService.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../helpers/db_helpers.php';

class PatientBehaviorController
{
    private ?PatientBehaviorService $service = null;
    private ?string $dbError = null;
    private array $actorContext = [];

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $repository = new PatientBehaviorRepository($pdo);
            $this->service = new PatientBehaviorService($repository);
        } catch (\RuntimeException $e) {
            $this->dbError = 'patient behavior not ready';
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function show(string $patientId, array $params = [])
    {
        if (DbHelpers\isQaModeNotReady()) {
            return $this->error('db_not_ready', 'patient behavior not ready');
        }

        if ($this->dbError) {
            return $this->error('db_not_ready', $this->dbError);
        }

        if (!is_string($patientId) || trim($patientId) === '') {
            return $this->error('invalid_params', 'patient_id is required', ['patient_id' => $patientId ?? null]);
        }

        $doctorId = trim((string)($this->actorContext['doctor_id'] ?? ''));
        if ($doctorId === '') {
            return $this->error('forbidden', 'doctor scope required', [
                'patient_id' => $patientId,
                'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
            ]);
        }

        if (!$this->service) {
            return $this->error('db_not_ready', 'patient behavior not ready');
        }

        try {
            $data = $this->service->evaluatePatientBehavior($patientId, $doctorId);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'patient incidents not ready') {
                return $this->error('db_not_ready', 'patient incidents not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (\PDOException $e) {
            if (DbHelpers\shouldTreatAsNotReady($e)) {
                return $this->error('db_not_ready', 'patient incidents not ready');
            }
            return $this->error('db_error', 'database error');
        }

        return $this->success($data, [
            'patient_id' => $patientId,
            'doctor_id_effective' => $doctorId,
            'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
        ]);
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
