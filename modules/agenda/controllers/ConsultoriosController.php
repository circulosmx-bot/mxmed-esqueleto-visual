<?php
namespace Agenda\Controllers;

use Agenda\Repositories\ConsultoriosRepository;
use PDOException;

require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class ConsultoriosController
{
    private ?ConsultoriosRepository $repository = null;
    private ?string $dbError = null;

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new ConsultoriosRepository($pdo);
        } catch (\RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function index(array $params = [])
    {
        if ($this->dbError) {
            return $this->error('db_not_ready', 'consultorios table not identified yet');
        }
        if (empty($params['doctor_id'])) {
            return $this->error('invalid_params', 'doctor_id is required', ['doctor_id' => $params['doctor_id'] ?? null]);
        }
        try {
            $data = $this->repository->listByDoctor($params['doctor_id']);
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', 'consultorios table not identified yet');
        } catch (PDOException $e) {
            return $this->error('db_error', $e->getMessage());
        }
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => [],
        ];
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
}
