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

        if (($query['view'] ?? '') === 'archive') {
            return $this->browseArchive($doctorId, $query, $metaBase);
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

    private function browseArchive(string $doctorId, array $query, array $metaBase): array
    {
        foreach (['q', 'gender', 'age', 'registration', 'sort', 'phone', 'date_from', 'date_to', 'page', 'limit'] as $key) {
            if (isset($query[$key]) && !is_scalar($query[$key])) {
                return $this->error('invalid_params', $key . ' invalid', $metaBase);
            }
        }
        $allowed = [
            'gender' => ['all', 'female', 'male', 'other'],
            'age' => ['all', 'under_18', '18_29', '30_44', '45_59', '60_plus'],
            'registration' => ['all', 'last_7', 'last_30', 'last_90', 'this_year'],
            'sort' => ['surname_asc', 'surname_desc', 'age_asc', 'age_desc', 'registered_desc', 'registered_asc',
                'last_consultation_desc', 'last_consultation_asc', 'next_appointment_asc', 'next_appointment_desc',
                'consultations_desc', 'consultations_asc'],
            'phone' => ['all', 'with', 'without'],
        ];
        $filters = ['q' => trim((string)($query['q'] ?? ''))];
        if (mb_strlen($filters['q']) > 120) {
            return $this->error('invalid_params', 'query too long', $metaBase);
        }
        foreach ($allowed as $key => $values) {
            $value = (string)($query[$key] ?? $values[0]);
            if (!in_array($value, $values, true)) {
                return $this->error('invalid_params', $key . ' invalid', $metaBase);
            }
            $filters[$key] = $value;
        }
        foreach (['date_from', 'date_to'] as $key) {
            $value = trim((string)($query[$key] ?? ''));
            if ($value !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || !checkdate((int)substr($value, 5, 2), (int)substr($value, 8, 2), (int)substr($value, 0, 4)))) {
                return $this->error('invalid_params', $key . ' invalid', $metaBase);
            }
            $filters[$key] = $value;
        }
        if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) {
            return $this->error('invalid_params', 'date range invalid', $metaBase);
        }
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $limit = filter_var($query['limit'] ?? 25, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1 || $page > 100000 || !in_array($limit, [25, 50, 100], true)) {
            return $this->error('invalid_params', 'paging invalid', $metaBase);
        }
        $filters['page'] = $page;
        $filters['limit'] = $limit;

        try {
            $result = $this->repo->browsePatientsByDoctorId($doctorId, $filters);
            return $this->success(['items' => $result['items']], [
                'visibility' => ['contact' => 'not_returned'],
                'total' => $result['total'],
                'filtered_total' => $result['filtered_total'],
                'paging' => ['page' => $page, 'limit' => $limit],
            ]);
        } catch (RuntimeException $e) {
            $notReady = $e->getMessage() === 'patients not ready';
            return $this->error($notReady ? 'db_not_ready' : 'db_error', $notReady ? 'patients db not ready' : 'database error', $metaBase);
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
}
