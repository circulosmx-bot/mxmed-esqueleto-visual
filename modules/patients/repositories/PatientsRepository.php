<?php
namespace Patients\Repositories;

use PDO;
use RuntimeException;

class PatientsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findPatientById(string $patientId): ?array
    {
        $this->ensureTables();

        $patient = $this->fetchPatient($patientId);
        if (!$patient) {
            return null;
        }
        $contacts = $this->fetchMaskedContacts($patientId);
        $patient['contacts'] = $contacts;
        return $patient;
    }

    public function findPatientsByDoctorId(string $doctorId, int $limit = 50): array
    {
        $this->ensureTables();

        $stmt = $this->pdo->prepare(
            'SELECT p.patient_id, p.display_name, p.status
             FROM patients_doctor_links l
             JOIN patients_patients p ON p.patient_id = l.patient_id
             WHERE l.doctor_id = :doctor_id AND l.status = :status
             ORDER BY p.display_name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':doctor_id', $doctorId);
        $stmt->bindValue(':status', 'active');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['contacts'] = $this->fetchMaskedContacts($row['patient_id']);
        }
        return $rows;
    }

    public function createPatient(array $input): array
    {
        $this->ensureTables();

        $patientId = $this->generateId('p_');
        $contacts = $input['contacts'] ?? [];
        $doctorId = $input['doctor_id'] ?? null;

        $now = (new \DateTime('now', new \DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $this->insertPatient($patientId, $input, $now);
            $maskedContacts = $this->insertContacts($patientId, $contacts, $now);
            $links = $this->insertLink($patientId, $doctorId, $now);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('database error');
        }

        return [
            'patient_id' => $patientId,
            'display_name' => $input['display_name'],
            'status' => 'active',
            'sex' => $input['sex'] ?? null,
            'birthdate' => $input['birthdate'] ?? null,
            'contacts' => $maskedContacts,
            'links' => $links,
            'audit' => [
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }

    private function fetchPatient(string $patientId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT patient_id, display_name, status, sex, birthdate, created_at, updated_at
             FROM patients_patients
             WHERE patient_id = :patient_id'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        // Enmascara campos sensibles
        $row['birthdate'] = $row['birthdate'] ?? null;
        return $row;
    }

    private function insertPatient(string $patientId, array $input, string $now): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO patients_patients (patient_id, display_name, status, birthdate, sex, created_at, updated_at)
             VALUES (:patient_id, :display_name, :status, :birthdate, :sex, :created_at, :updated_at)'
        );
        $stmt->execute([
            'patient_id' => $patientId,
            'display_name' => $input['display_name'],
            'status' => 'active',
            'birthdate' => $input['birthdate'] ?? null,
            'sex' => $input['sex'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertContacts(string $patientId, array $contacts, string $now): array
    {
        $masked = [];
        foreach ($contacts as $c) {
            $contactId = $this->generateId('c_');
            $type = $c['type'] ?? '';
            if (!in_array($type, ['phone', 'email'], true)) {
                throw new RuntimeException('invalid contact type');
            }
            $value = $c['value'] ?? null;
            $isPhone = $type === 'phone';
            $isEmail = $type === 'email';
            $phone = $isPhone ? $value : null;
            $email = $isEmail ? $value : null;

            $stmt = $this->pdo->prepare(
                'INSERT INTO patients_contacts (contact_id, patient_id, phone, email, preferred_contact_method, is_primary, created_at)
                 VALUES (:contact_id, :patient_id, :phone, :email, :preferred_contact_method, :is_primary, :created_at)'
            );
            $stmt->execute([
                'contact_id' => $contactId,
                'patient_id' => $patientId,
                'phone' => $phone,
                'email' => $email,
                'preferred_contact_method' => $c['preferred_contact_method'] ?? null,
                'is_primary' => isset($c['is_primary']) ? (int)$c['is_primary'] : 0,
                'created_at' => $now,
            ]);

            $masked[] = [
                'contact_id' => $contactId,
                'type' => $type,
                'value_masked' => $isPhone ? $this->maskPhone((string)$phone) : $this->maskEmail((string)$email),
                'is_primary' => isset($c['is_primary']) ? (bool)$c['is_primary'] : false,
                'preferred_contact_method' => $c['preferred_contact_method'] ?? null,
                'created_at' => $now,
            ];
        }
        return $masked;
    }

    private function insertLink(string $patientId, ?string $doctorId, string $now): array
    {
        if (!$doctorId) {
            return [];
        }
        $linkId = $this->generateId('l_');
        $stmt = $this->pdo->prepare(
            'INSERT INTO patients_doctor_links (link_id, doctor_id, patient_id, status, created_at)
             VALUES (:link_id, :doctor_id, :patient_id, :status, :created_at)'
        );
        $stmt->execute([
            'link_id' => $linkId,
            'doctor_id' => $doctorId,
            'patient_id' => $patientId,
            'status' => 'active',
            'created_at' => $now,
        ]);
        return [
            [
                'doctor_id' => $doctorId,
                'link_status' => 'active',
            ],
        ];
    }

    private function generateId(string $prefix): string
    {
        return $prefix . bin2hex(random_bytes(6));
    }

    private function fetchMaskedContacts(string $patientId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT contact_id, phone, email, preferred_contact_method, is_primary, created_at
             FROM patients_contacts WHERE patient_id = :patient_id'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $masked = [];
        foreach ($rows as $row) {
            $entry = [
                'contact_id' => $row['contact_id'],
                'is_primary' => (bool)$row['is_primary'],
                'preferred_contact_method' => $row['preferred_contact_method'] ?? null,
                'created_at' => $row['created_at'],
            ];
            if (!empty($row['phone'])) {
                $entry['type'] = 'phone';
                $entry['value_masked'] = $this->maskPhone($row['phone']);
            } elseif (!empty($row['email'])) {
                $entry['type'] = 'email';
                $entry['value_masked'] = $this->maskEmail($row['email']);
            } else {
                $entry['type'] = 'unknown';
            }
            $masked[] = $entry;
        }
        return $masked;
    }

    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', max(0, $len - 4)) . substr($phone, -4);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }
        $name = $parts[0];
        $domain = $parts[1];
        $visible = strlen($name) > 1 ? substr($name, 0, 1) : '*';
        return $visible . '***@' . $domain;
    }

    private function ensureTables(): void
    {
        foreach (['patients_patients', 'patients_contacts', 'patients_doctor_links'] as $table) {
            if (!$this->tableExists($table)) {
                throw new RuntimeException('patients not ready');
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
