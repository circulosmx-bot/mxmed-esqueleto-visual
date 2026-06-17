<?php
declare(strict_types=1);

namespace Patients\Controllers;

use Patients\Repositories\PatientsRepository;
use Patients\Validators\PatientNameValidator;
use Agenda\Helpers as DbHelpers;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/PatientsRepository.php';
require_once __DIR__ . '/../validators/PatientNameValidator.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../agenda/helpers/db_helpers.php';

class UpsertPatientProfileController
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

    public function handle(string $patientId, array $payload): array
    {
        $meta = ['visibility' => ['contact' => 'masked']];

        if ($this->qaNotReady || $this->dbError || !$this->repo) {
            return $this->error('db_not_ready', 'patients db not ready', $meta);
        }
        if (trim($patientId) === '') {
            return $this->error('invalid_params', 'patient_id required', $meta);
        }

        $normalizedPayload = $payload;
        $errors = $this->validate($payload, $normalizedPayload);
        if (!empty($errors)) {
            $isNameError = count(array_intersect(array_keys($errors), ['first_name', 'paternal_last_name', 'maternal_last_name'])) > 0;
            return $this->error(
                'invalid_params',
                $isNameError ? PatientNameValidator::INVALID_MESSAGE : 'invalid params',
                $meta + ['fields' => $errors],
                $isNameError ? 422 : null
            );
        }

        try {
            $profile = $this->repo->upsertProfile($patientId, $normalizedPayload);
            return $this->success(['profile' => $profile], $meta);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'patients not ready') {
                return $this->error('db_not_ready', 'patients db not ready', $meta);
            }
            if ($msg === 'patient not found') {
                return $this->error('not_found', 'patient_id unknown', $meta);
            }
            return $this->error('db_error', 'database error', $meta);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', $meta);
        }
    }

    private function validate(array $p, array &$normalizedPayload): array
    {
        $errors = [];
        $normalizedPayload = $p;
        $allowed = [
            'first_name',
            'paternal_last_name',
            'maternal_last_name',
            'marital_status',
            'occupation',
        ];
        foreach ($p as $key => $value) {
            if (!in_array((string)$key, $allowed, true)) {
                $errors[(string)$key] = 'unknown';
            }
        }

        foreach (['first_name', 'paternal_last_name', 'maternal_last_name'] as $key) {
            $this->validatePatientNameText($p, $key, 120, $errors, $normalizedPayload);
        }
        $this->validateShortText($p, 'marital_status', 64, $errors);
        $this->validateShortText($p, 'occupation', 190, $errors);

        return $errors;
    }

    private function validateShortText(array $payload, string $key, int $max, array &$errors): void
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return;
        }
        if (!is_scalar($payload[$key])) {
            $errors[$key] = 'must_be_scalar';
            return;
        }
        if (mb_strlen(trim((string)$payload[$key]), 'UTF-8') > $max) {
            $errors[$key] = 'too_long';
        }
    }

    private function validatePatientNameText(array $payload, string $key, int $max, array &$errors, array &$normalizedPayload): void
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return;
        }
        if (!is_scalar($payload[$key])) {
            $errors[$key] = 'must_be_scalar';
            return;
        }

        $result = PatientNameValidator::validateNameValue($payload[$key], ['required' => false]);
        if (($result['valid'] ?? false) !== true) {
            $errors[$key] = $result['code'] ?? 'invalid_name';
            return;
        }

        $normalized = (string)($result['value'] ?? '');
        if (mb_strlen($normalized, 'UTF-8') > $max) {
            $errors[$key] = 'too_long';
            return;
        }
        $normalizedPayload[$key] = $normalized;
    }

    private function success(array $data, array $meta = []): array
    {
        return ['ok' => true, 'error' => null, 'message' => '', 'data' => $data, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }

    private function error(string $code, string $message, array $meta = [], ?int $httpStatus = null): array
    {
        $response = ['ok' => false, 'error' => $code, 'message' => $message, 'data' => null, 'meta' => empty($meta) ? (object)[] : (object)$meta];
        if ($httpStatus !== null) {
            $response['http_status'] = $httpStatus;
        }
        return $response;
    }
}
