<?php
namespace Patients\Controllers;

use Patients\Repositories\PatientsRepository;
use Agenda\Helpers as DbHelpers;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/PatientsRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../agenda/helpers/db_helpers.php';

class UpsertEditablePatientContactsController
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

    public function handle(string $doctorId, string $patientId, array $payload): array
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

        $phoneContact = $this->resolvePhoneContact($payload);
        if ($phoneContact === null) {
            return $this->error('invalid_params', 'phone contact required', 400, $meta);
        }
        $phone = $this->normalizeMxPhoneForStorage((string)($phoneContact['value'] ?? ''));
        if ($phone === '') {
            return $this->error('invalid_params', 'valid phone value required', 400, $meta);
        }
        $preferredContactMethod = $this->normalizePreferredContactMethod($phoneContact['preferred_contact_method'] ?? null);

        try {
            if (!$this->repo->patientExists($patientId)) {
                return $this->error('not_found', 'patient_id unknown', 404, $meta);
            }
            if (!$this->repo->hasActiveDoctorPatientLink($doctorId, $patientId)) {
                return $this->error('forbidden', 'doctor patient link required', 403, $meta);
            }
            $contacts = $this->repo->upsertPrimaryEditablePhone($patientId, $phone, $preferredContactMethod);
            return $this->success(['contacts' => $contacts], $meta);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'patients not ready') {
                return $this->error('db_not_ready', 'patients db not ready', 503, $meta);
            }
            if ($msg === 'patient not found') {
                return $this->error('not_found', 'patient_id unknown', 404, $meta);
            }
            return $this->error('db_error', 'database error', 500, $meta);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', 500, $meta);
        }
    }

    private function resolvePhoneContact(array $payload): ?array
    {
        $contacts = $payload['contacts'] ?? null;
        if (!is_array($contacts)) {
            return null;
        }
        $firstPhone = null;
        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }
            if (($contact['type'] ?? '') !== 'phone') {
                continue;
            }
            if ($firstPhone === null) {
                $firstPhone = $contact;
            }
            if (($contact['is_primary'] ?? false) === true || (string)($contact['is_primary'] ?? '') === '1') {
                return $contact;
            }
        }
        return $firstPhone;
    }

    private function normalizeMxPhoneForStorage(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 12 && substr($digits, 0, 2) === '52') {
            return '+52' . substr($digits, 2);
        }
        if (strlen($digits) === 10) {
            return '+52' . $digits;
        }
        return '';
    }

    private function normalizePreferredContactMethod($value): string
    {
        $safe = trim((string)($value ?? ''));
        if (in_array($safe, ['phone', 'whatsapp', 'none'], true)) {
            return $safe;
        }
        return 'phone';
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
