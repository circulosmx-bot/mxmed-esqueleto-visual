<?php
namespace Patients\Controllers;

use Patients\Repositories\PatientsRepository;
use Agenda\Helpers as DbHelpers;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/PatientsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../agenda/helpers/db_helpers.php';

class SearchDoctorPatientsController
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
        $rawQuery = trim((string)($query['q'] ?? ''));
        $queryKind = $this->detectQueryKind($rawQuery);
        $metaBase = [
            'query' => $this->redactQueryForMeta($rawQuery, $queryKind),
            'query_kind' => $queryKind,
            'visibility' => ['contacts' => 'masked'],
        ];

        if (session_status() === PHP_SESSION_NONE) session_start();
        $sessionDoctorId = trim((string)($_SESSION['doctor_id'] ?? $_SESSION['active_doctor_id'] ?? $_SESSION['mxmed_doctor_id'] ?? ''));
        $sessionUserId = trim((string)($_SESSION['user_id'] ?? $_SESSION['mxmed_user_id'] ?? $_SESSION['auth_user_id'] ?? $_SESSION['actor_user_id'] ?? ''));
        if ($sessionDoctorId === '' || $sessionUserId === '') {
            return $this->error('unauthorized', 'authentication required', $metaBase, 401);
        }
        if ($sessionDoctorId !== $doctorId) {
            return $this->error('forbidden', 'doctor scope mismatch', $metaBase, 403);
        }

        if ($this->qaNotReady) {
            return $this->error('db_not_ready', 'patients db not ready', $metaBase);
        }
        if ($this->dbError || !$this->repo) {
            return $this->error('db_not_ready', 'patients db not ready', $metaBase);
        }
        if (trim($doctorId) === '') {
            return $this->error('invalid_params', 'doctor_id required', $metaBase);
        }

        $limit = 25;
        if (isset($query['limit'])) {
            $limit = (int)$query['limit'];
            if ($limit < 1 || $limit > 50) {
                return $this->error('invalid_params', 'limit out of range', $metaBase);
            }
        }

        if ($rawQuery === '') {
            return $this->success(['items' => []], $metaBase + [
                'count' => 0,
                'paging' => ['limit' => $limit],
            ]);
        }

        try {
            $items = $this->repo->searchPatientsByDoctorId($doctorId, $rawQuery, $limit);
            return $this->success(['items' => $items], $metaBase + [
                'count' => count($items),
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

    private function error(string $code, string $message, array $meta = [], ?int $httpStatus = null): array
    {
        $response = ['ok' => false, 'error' => $code, 'message' => $message, 'data' => null, 'meta' => empty($meta) ? (object)[] : (object)$meta];
        if ($httpStatus !== null) $response['http_status'] = $httpStatus;
        return $response;
    }

    private function detectQueryKind(string $query): string
    {
        if ($query === '') {
            return 'empty';
        }
        if (strpos($query, '@') !== false) {
            return 'email';
        }
        $digits = preg_replace('/\D+/', '', $query) ?? '';
        $nonPhoneChars = preg_replace('/[\d\s()+\-\.\/]/', '', $query) ?? '';
        if ($digits !== '' && $nonPhoneChars === '') {
            return 'phone';
        }
        return 'text';
    }

    private function redactQueryForMeta(string $query, string $queryKind): string
    {
        if ($queryKind === 'phone') {
            return '[phone]';
        }
        if ($queryKind === 'email') {
            return '[email]';
        }
        return $query;
    }
}
