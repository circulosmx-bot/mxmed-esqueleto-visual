<?php
namespace Agenda\Controllers;

use Agenda\Repositories\PatientFlagsRepository;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/PatientFlagsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class PatientFlagsController
{
    private ?PatientFlagsRepository $repository = null;
    private ?string $dbError = null;

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new PatientFlagsRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = 'patient flags not ready';
        } catch (PDOException $e) {
            $this->dbError = 'patient flags not ready';
        }
    }

    public function index(string $patientId, array $params = [])
    {
        if ($this->dbError) {
            return $this->error('db_not_ready', $this->dbError);
        }

        if (!is_string($patientId) || trim($patientId) === '') {
            return $this->error('invalid_params', 'patient_id is required', ['patient_id' => $patientId ?? null]);
        }

        $activeOnlyParam = $params['active_only'] ?? null;
        if ($activeOnlyParam !== null && !in_array($activeOnlyParam, ['0', '1'], true)) {
            return $this->error('invalid_params', 'active_only must be 0 or 1', ['active_only' => $activeOnlyParam]);
        }
        $activeOnly = $activeOnlyParam !== '0';
        $limit = $this->normalizeLimit($params['limit'] ?? null);

        try {
            $flags = $this->repository->listByPatientId($patientId, $activeOnly, $limit);
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', $e->getMessage());
        } catch (PDOException $e) {
            return $this->error('db_error', $e->getMessage());
        }

        return $this->success($flags, ['patient_id' => $patientId, 'active_only' => $activeOnly ? 1 : 0, 'limit' => $limit, 'count' => count($flags)]);
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
