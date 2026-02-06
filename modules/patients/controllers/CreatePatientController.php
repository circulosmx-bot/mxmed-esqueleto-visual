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

class CreatePatientController
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

    public function handle(array $payload): array
    {
        $meta = ['visibility' => ['contact' => 'masked']];

        if ($this->qaNotReady || $this->dbError || !$this->repo) {
            return $this->error('db_not_ready', 'patients db not ready', $meta);
        }

        $errors = $this->validate($payload);
        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid params', $meta + ['fields' => $errors]);
        }

        try {
            $patient = $this->repo->createPatient($payload);
            return $this->success($patient, $meta);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'patients not ready') {
                return $this->error('db_not_ready', 'patients db not ready', $meta);
            }
            return $this->error('db_error', 'database error', $meta);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', $meta);
        }
    }

    private function validate(array $p): array
    {
        $errors = [];
        $forbidden = ['patient_id', 'notes_admin', 'audit', 'links', 'consent_id', 'created_at', 'updated_at'];
        foreach ($forbidden as $key) {
            if (array_key_exists($key, $p)) {
                $errors[$key] = 'forbidden';
            }
        }

        if (!isset($p['display_name']) || trim((string)$p['display_name']) === '') {
            $errors['display_name'] = 'required';
        }

        // Validate birthdate format if present
        if (isset($p['birthdate']) && $p['birthdate'] !== null && $p['birthdate'] !== '') {
            $bd = \DateTime::createFromFormat('Y-m-d', (string)$p['birthdate']);
            if (!$bd || $bd->format('Y-m-d') !== (string)$p['birthdate']) {
                $errors['birthdate'] = 'invalid_format';
            }
        }

        // Validate contacts
        if (isset($p['contacts'])) {
            if (!is_array($p['contacts'])) {
                $errors['contacts'] = 'must_be_array';
            } else {
                if (count($p['contacts']) > 5) {
                    $errors['contacts'] = 'too_many';
                }
                foreach ($p['contacts'] as $idx => $c) {
                    if (!is_array($c)) {
                        $errors["contacts[$idx]"] = 'must_be_object';
                        continue;
                    }
                    if (array_key_exists('phone', $c) && array_key_exists('email', $c)) {
                        $errors["contacts[$idx]"] = 'phone_or_email_only';
                    }
                    if (array_key_exists('phone', $c) || array_key_exists('email', $c)) {
                        $errors["contacts[$idx]"] = 'use_value_field';
                    }
                    $type = $c['type'] ?? null;
                    $value = $c['value'] ?? null;
                    if (!in_array($type, ['phone', 'email'], true)) {
                        $errors["contacts[$idx].type"] = 'invalid';
                    }
                    if (trim((string)$value) === '') {
                        $errors["contacts[$idx].value"] = 'required';
                    }
                }
            }
        }

        return $errors;
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
