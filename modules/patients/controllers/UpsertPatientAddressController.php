<?php
declare(strict_types=1);

namespace Patients\Controllers;

use Patients\Repositories\PatientsRepository;
use Agenda\Helpers as DbHelpers;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/PatientsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../agenda/helpers/db_helpers.php';

class UpsertPatientAddressController
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

        $errors = $this->validate($payload);
        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid params', $meta + ['fields' => $errors]);
        }

        try {
            $address = $this->repo->upsertPrimaryAddress($patientId, $payload);
            return $this->success(['address' => $address], $meta);
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

    private function validate(array $p): array
    {
        $errors = [];
        $allowed = [
            'address_type',
            'is_primary',
            'country',
            'postal_code',
            'colony',
            'state',
            'municipality',
            'locality',
            'street',
            'exterior_number',
            'interior_number',
            'floor',
            'catalog_cp_colonia_id',
        ];
        foreach ($p as $key => $value) {
            if (!in_array((string)$key, $allowed, true)) {
                $errors[(string)$key] = 'unknown';
            }
        }

        $this->validateShortText($p, 'address_type', 32, $errors);
        if (isset($p['address_type']) && trim((string)$p['address_type']) !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', trim((string)$p['address_type']))) {
            $errors['address_type'] = 'invalid';
        }

        if (isset($p['is_primary']) && !is_bool($p['is_primary']) && !in_array($p['is_primary'], [0, 1, '0', '1'], true)) {
            $errors['is_primary'] = 'must_be_boolean';
        }

        $this->validateShortText($p, 'country', 3, $errors);
        if (isset($p['country']) && trim((string)$p['country']) !== '' && !preg_match('/^[A-Za-z]{2,3}$/', trim((string)$p['country']))) {
            $errors['country'] = 'invalid';
        }

        if (isset($p['postal_code']) && trim((string)$p['postal_code']) !== '' && !preg_match('/^\d{5}$/', trim((string)$p['postal_code']))) {
            $errors['postal_code'] = 'invalid_format';
        }

        foreach (['colony', 'state', 'municipality', 'locality', 'street'] as $key) {
            $this->validateShortText($p, $key, 190, $errors);
        }
        foreach (['exterior_number', 'interior_number', 'floor'] as $key) {
            $this->validateShortText($p, $key, 32, $errors);
        }

        if (array_key_exists('catalog_cp_colonia_id', $p) && $p['catalog_cp_colonia_id'] !== null && $p['catalog_cp_colonia_id'] !== '') {
            $id = filter_var($p['catalog_cp_colonia_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $errors['catalog_cp_colonia_id'] = 'invalid';
            }
        }

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

    private function success(array $data, array $meta = []): array
    {
        return ['ok' => true, 'error' => null, 'message' => '', 'data' => $data, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }

    private function error(string $code, string $message, array $meta = []): array
    {
        return ['ok' => false, 'error' => $code, 'message' => $message, 'data' => null, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }
}
