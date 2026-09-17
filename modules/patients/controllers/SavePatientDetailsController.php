<?php
declare(strict_types=1);

namespace Patients\Controllers;

use Patients\Repositories\PatientDetailsRepository;
use Patients\Validators\PatientNameValidator;
use Throwable;

require_once __DIR__ . '/../repositories/PatientsRepository.php';
require_once __DIR__ . '/../repositories/PatientDetailsRepository.php';
require_once __DIR__ . '/../validators/PatientNameValidator.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

final class SavePatientDetailsController
{
    public function handle(string $doctorId, string $patientId, array $payload): array
    {
        if (trim($doctorId) === '' || trim($patientId) === '') return $this->error('invalid_params', 400);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $sessionDoctorId = trim((string)($_SESSION['doctor_id'] ?? $_SESSION['active_doctor_id'] ?? $_SESSION['mxmed_doctor_id'] ?? ''));
        $sessionUserId = trim((string)($_SESSION['user_id'] ?? $_SESSION['mxmed_user_id'] ?? $_SESSION['auth_user_id'] ?? $_SESSION['actor_user_id'] ?? ''));
        if ($sessionDoctorId === '' || $sessionUserId === '') return $this->error('unauthorized', 401);
        if ($sessionDoctorId !== $doctorId) return $this->error('forbidden', 403);
        try {
            $data = $this->validate($payload);
        } catch (\InvalidArgumentException $error) {
            return $this->error('invalid_params', 422, $error->getMessage());
        }
        try {
            $saved = (new PatientDetailsRepository(mxmed_pdo()))->save($doctorId, $patientId, $data);
            return ['ok' => true, 'error' => null, 'message' => '', 'data' => $saved, 'meta' => ['visibility' => ['contacts' => 'editable_private']]];
        } catch (\RuntimeException $error) {
            if ($error->getMessage() === 'doctor patient link required') {
                return $this->error('forbidden', 403);
            }
            return $this->error('db_error', 500);
        } catch (Throwable $error) {
            return $this->error('db_error', 500);
        }
    }

    private function validate(array $payload): array
    {
        $this->onlyKeys($payload, ['profile', 'birthdate', 'sex', 'contacts', 'address']);
        $profile = $payload['profile'] ?? null;
        $contacts = $payload['contacts'] ?? null;
        $address = $payload['address'] ?? null;
        if (!is_array($profile) || !is_array($contacts) || !is_array($address)) {
            throw new \InvalidArgumentException('profile, contacts and address required');
        }
        $this->onlyKeys($profile, ['first_name', 'paternal_last_name', 'maternal_last_name', 'marital_status', 'occupation']);
        $normalizedProfile = [];
        foreach (['first_name', 'paternal_last_name', 'maternal_last_name'] as $key) {
            $result = PatientNameValidator::validateNameValue($profile[$key] ?? '', ['required' => false]);
            if (($result['valid'] ?? false) !== true || mb_strlen((string)$result['value'], 'UTF-8') > 120) {
                throw new \InvalidArgumentException("invalid $key");
            }
            $normalizedProfile[$key] = $result['value'] !== '' ? $result['value'] : null;
        }
        $hasName = $normalizedProfile['first_name'] !== null
            || $normalizedProfile['paternal_last_name'] !== null
            || $normalizedProfile['maternal_last_name'] !== null;
        if ($hasName && (!$normalizedProfile['first_name'] || !$normalizedProfile['paternal_last_name'])) {
            throw new \InvalidArgumentException('first_name and paternal_last_name required together');
        }
        foreach (['marital_status' => 64, 'occupation' => 190] as $key => $max) {
            $normalizedProfile[$key] = $this->text($profile[$key] ?? '', $max);
        }
        $displayName = $hasName
            ? implode(' ', array_filter([$normalizedProfile['first_name'], $normalizedProfile['paternal_last_name'], $normalizedProfile['maternal_last_name']]))
            : null;
        if ($displayName !== null && mb_strlen($displayName, 'UTF-8') > 160) {
            throw new \InvalidArgumentException('display_name too long');
        }

        $birthdate = $payload['birthdate'] ?? null;
        if ($birthdate === '') $birthdate = null;
        if ($birthdate !== null) {
            if (!is_string($birthdate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
                throw new \InvalidArgumentException('invalid birthdate');
            }
            [$year, $month, $day] = array_map('intval', explode('-', $birthdate));
            if (!checkdate($month, $day, $year)) throw new \InvalidArgumentException('invalid birthdate');
        }
        $sex = $payload['sex'] ?? null;
        if ($sex === '') $sex = null;
        if ($sex !== null && !in_array($sex, ['M', 'F', 'O'], true)) {
            throw new \InvalidArgumentException('invalid sex');
        }

        $this->onlyKeys($contacts, ['mobile', 'home', 'contact', 'primary_email', 'alternate_email']);
        $normalizedContacts = [];
        foreach (['mobile', 'home', 'contact'] as $key) {
            $value = $this->text($contacts[$key] ?? '', 32) ?? '';
            if ($value !== '' && !preg_match('/^\+?[0-9 ()-]{7,32}$/', $value)) {
                throw new \InvalidArgumentException("invalid $key");
            }
            $normalizedContacts[$key] = $value;
        }
        foreach (['primary_email', 'alternate_email'] as $key) {
            $value = $this->text($contacts[$key] ?? '', 190) ?? '';
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException("invalid $key");
            }
            $normalizedContacts[$key] = $value;
        }

        $limits = [
            'country' => 2, 'postal_code' => 16, 'colony' => 190, 'state' => 190,
            'municipality' => 190, 'locality' => 190, 'street' => 190,
            'exterior_number' => 32, 'interior_number' => 32, 'floor' => 32,
            'catalog_cp_colonia_id' => 0,
        ];
        $this->onlyKeys($address, array_keys($limits));
        $normalizedAddress = [];
        foreach ($limits as $key => $max) {
            if ($key === 'catalog_cp_colonia_id') {
                $raw = $address[$key] ?? null;
                if ($raw !== null && $raw !== '' && (!is_numeric($raw) || (int)$raw < 1)) {
                    throw new \InvalidArgumentException('invalid catalog_cp_colonia_id');
                }
                $normalizedAddress[$key] = $raw !== null && $raw !== '' ? (int)$raw : null;
                continue;
            }
            $normalizedAddress[$key] = $this->text($address[$key] ?? '', $max);
        }
        $normalizedAddress['country'] = strtoupper($normalizedAddress['country'] ?? 'MX');
        if (!preg_match('/^[A-Z]{2}$/', $normalizedAddress['country'])) throw new \InvalidArgumentException('invalid country');
        if ($normalizedAddress['postal_code'] !== null && !preg_match('/^\d{5}$/', $normalizedAddress['postal_code'])) {
            throw new \InvalidArgumentException('invalid postal_code');
        }
        return [
            'display_name' => $displayName,
            'profile' => $normalizedProfile,
            'birthdate' => $birthdate,
            'sex' => $sex,
            'contacts' => $normalizedContacts,
            'address' => $normalizedAddress,
        ];
    }

    private function text($value, int $max): ?string
    {
        if ($value !== null && !is_scalar($value)) throw new \InvalidArgumentException('invalid text');
        $text = trim((string)($value ?? ''));
        if (mb_strlen($text, 'UTF-8') > $max) throw new \InvalidArgumentException('text too long');
        return $text !== '' ? $text : null;
    }

    private function onlyKeys(array $payload, array $keys): void
    {
        foreach (array_keys($payload) as $key) {
            if (!in_array($key, $keys, true)) throw new \InvalidArgumentException("unknown field $key");
        }
    }

    private function error(string $code, int $status, string $message = ''): array
    {
        return ['ok' => false, 'error' => $code, 'message' => $message ?: $code,
            'data' => null, 'meta' => (object)[], 'http_status' => $status];
    }
}
