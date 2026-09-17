<?php
declare(strict_types=1);

namespace Patients\Repositories;

use PDO;
use RuntimeException;

/** One doctor-scoped, atomic save for the editable Datos generales surface. */
final class PatientDetailsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(string $doctorId, string $patientId, array $data): array
    {
        $this->pdo->beginTransaction();
        try {
            $scope = $this->pdo->prepare(
                'SELECT p.patient_id FROM patients_patients p
                 JOIN patients_doctor_links l ON l.patient_id = p.patient_id
                 WHERE p.patient_id = :patient_id AND p.status = \'active\'
                   AND l.doctor_id = :doctor_id AND l.status = \'active\'
                 FOR UPDATE'
            );
            $scope->execute(['patient_id' => $patientId, 'doctor_id' => $doctorId]);
            if (!$scope->fetchColumn()) {
                throw new RuntimeException('doctor patient link required');
            }

            $now = (new \DateTimeImmutable('now', new \DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
            $profile = $data['profile'];
            $displayName = $data['display_name'];
            $this->pdo->prepare(
                'UPDATE patients_patients SET display_name = COALESCE(:display_name, display_name),
                 birthdate = :birthdate, sex = :sex, updated_at = :updated_at
                 WHERE patient_id = :patient_id'
            )->execute([
                'display_name' => $displayName,
                'birthdate' => $data['birthdate'],
                'sex' => $data['sex'],
                'updated_at' => $now,
                'patient_id' => $patientId,
            ]);

            $existingProfile = $this->pdo->prepare('SELECT profile_id FROM patients_profiles WHERE patient_id = :patient_id FOR UPDATE');
            $existingProfile->execute(['patient_id' => $patientId]);
            $profileId = $existingProfile->fetchColumn();
            if ($profileId) {
                $this->pdo->prepare(
                    'UPDATE patients_profiles SET first_name = :first_name,
                     paternal_last_name = :paternal_last_name, maternal_last_name = :maternal_last_name,
                     marital_status = :marital_status, occupation = :occupation, updated_at = :updated_at
                     WHERE patient_id = :patient_id'
                )->execute($profile + ['updated_at' => $now, 'patient_id' => $patientId]);
            } elseif (array_filter($profile, static fn($value) => $value !== null && $value !== '')) {
                $this->pdo->prepare(
                    'INSERT INTO patients_profiles
                     (profile_id, patient_id, first_name, paternal_last_name, maternal_last_name, marital_status, occupation, created_at, updated_at)
                     VALUES (:profile_id, :patient_id, :first_name, :paternal_last_name, :maternal_last_name, :marital_status, :occupation, :created_at, :updated_at)'
                )->execute($profile + [
                    'profile_id' => 'pr_' . bin2hex(random_bytes(6)),
                    'patient_id' => $patientId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->saveContacts($patientId, $data['contacts'], $now);
            if ($data['address'] !== null) {
                $this->saveAddress($patientId, $data['address'], $now);
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        $reader = new PatientsRepository($this->pdo);
        $result = $reader->findPatientById($patientId);
        if (!$result) throw new RuntimeException('patient not found');
        $result['editable_contacts'] = $reader->fetchEditableContacts($patientId);
        return $result;
    }

    private function saveContacts(string $patientId, array $contacts, string $now): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT contact_id, phone, email, contact_role, preferred_contact_method, is_primary
             FROM patients_contacts WHERE patient_id = :patient_id
             ORDER BY is_primary DESC, created_at ASC, contact_id ASC FOR UPDATE'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $used = [];
        foreach (['mobile', 'home', 'contact', 'primary_email', 'alternate_email'] as $role) {
            $value = $contacts[$role];
            $isEmail = str_ends_with($role, 'email');
            $match = null;
            foreach ($rows as $row) {
                if (($row['contact_role'] ?? null) === $role) {
                    $match = $row;
                    break;
                }
            }
            if (!$match) {
                foreach ($rows as $row) {
                    if (isset($used[$row['contact_id']]) || !empty($row['contact_role'])) continue;
                    if ($isEmail ? !empty($row['email']) : !empty($row['phone'])) {
                        $match = $row;
                        break;
                    }
                }
            }
            if ($match) {
                $used[$match['contact_id']] = true;
                $this->pdo->prepare(
                    'UPDATE patients_contacts SET phone = :phone, email = :email,
                     contact_role = :contact_role, is_primary = :is_primary,
                     preferred_contact_method = :preferred_contact_method
                     WHERE patient_id = :patient_id AND contact_id = :contact_id'
                )->execute([
                    'phone' => $isEmail ? null : ($value !== '' ? $value : null),
                    'email' => $isEmail ? ($value !== '' ? $value : null) : null,
                    'contact_role' => $role,
                    'is_primary' => in_array($role, ['mobile', 'primary_email'], true) ? 1 : 0,
                    'preferred_contact_method' => $match['preferred_contact_method'] ?: ($isEmail ? 'email' : 'phone'),
                    'patient_id' => $patientId,
                    'contact_id' => $match['contact_id'],
                ]);
            } elseif ($value !== '') {
                $this->pdo->prepare(
                    'INSERT INTO patients_contacts
                     (contact_id, patient_id, phone, email, preferred_contact_method, contact_role, is_primary, created_at)
                     VALUES (:contact_id, :patient_id, :phone, :email, :preferred_contact_method, :contact_role, :is_primary, :created_at)'
                )->execute([
                    'contact_id' => 'c_' . bin2hex(random_bytes(6)),
                    'patient_id' => $patientId,
                    'phone' => $isEmail ? null : $value,
                    'email' => $isEmail ? $value : null,
                    'preferred_contact_method' => $isEmail ? 'email' : 'phone',
                    'contact_role' => $role,
                    'is_primary' => in_array($role, ['mobile', 'primary_email'], true) ? 1 : 0,
                    'created_at' => $now,
                ]);
            }
        }
    }

    private function saveAddress(string $patientId, array $address, string $now): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT address_id FROM patients_addresses WHERE patient_id = :patient_id
             ORDER BY is_primary DESC, created_at ASC, address_id ASC LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $addressId = $stmt->fetchColumn();
        if (!$addressId && !array_filter($address, static fn($value, $key) => $key !== 'country' && $value !== null && $value !== '', ARRAY_FILTER_USE_BOTH)) return;
        $this->pdo->prepare('UPDATE patients_addresses SET is_primary = 0 WHERE patient_id = :patient_id')
            ->execute(['patient_id' => $patientId]);
        $columns = [
            'country', 'postal_code', 'colony', 'state', 'municipality', 'locality',
            'street', 'exterior_number', 'interior_number', 'floor', 'catalog_cp_colonia_id'
        ];
        $params = ['patient_id' => $patientId] + $address;
        if ($addressId) {
            $set = implode(', ', array_map(static fn($key) => "$key = :$key", $columns));
            $this->pdo->prepare("UPDATE patients_addresses SET $set, is_primary = 1, updated_at = :updated_at WHERE address_id = :address_id AND patient_id = :patient_id")
                ->execute($params + ['updated_at' => $now, 'address_id' => $addressId]);
        } else {
            $names = implode(', ', $columns);
            $values = implode(', ', array_map(static fn($key) => ":$key", $columns));
            $this->pdo->prepare("INSERT INTO patients_addresses
                (address_id, patient_id, address_type, is_primary, $names, created_at, updated_at)
                VALUES (:address_id, :patient_id, 'home', 1, $values, :created_at, :updated_at)")
                ->execute($params + [
                    'address_id' => 'a_' . bin2hex(random_bytes(6)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
        }
    }
}
