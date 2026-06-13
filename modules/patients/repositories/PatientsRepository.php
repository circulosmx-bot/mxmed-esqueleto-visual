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
        $patient['addresses'] = $this->fetchAddressRows($patientId);
        $patient['profile'] = $this->fetchProfileRow($patientId);
        return $patient;
    }

    public function patientExists(string $patientId): bool
    {
        $this->ensureTables();

        return $this->fetchPatient($patientId) !== null;
    }

    public function hasActiveDoctorPatientLink(string $doctorId, string $patientId): bool
    {
        $this->ensureTables();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM patients_doctor_links
             WHERE doctor_id = :doctor_id
               AND patient_id = :patient_id
               AND status = :status'
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'patient_id' => $patientId,
            'status' => 'active',
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function fetchEditableContacts(string $patientId): array
    {
        $this->ensureTables();

        return $this->fetchEditableContactsRows($patientId);
    }

    public function upsertPrimaryEditablePhone(string $patientId, string $phone, ?string $preferredContactMethod = 'phone'): array
    {
        $this->ensureTables();

        if (!$this->fetchPatient($patientId)) {
            throw new RuntimeException('patient not found');
        }

        $now = (new \DateTime('now', new \DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
        $existing = $this->fetchPrimaryPhoneContactRow($patientId);
        $preferred = $this->cleanNullableString($preferredContactMethod) ?? 'phone';

        $this->pdo->beginTransaction();
        try {
            if ($existing) {
                $contactId = (string)$existing['contact_id'];
                $this->pdo->prepare(
                    'UPDATE patients_contacts
                     SET is_primary = 0
                     WHERE patient_id = :patient_id
                       AND phone IS NOT NULL
                       AND phone <> \'\'
                       AND contact_id <> :contact_id'
                )->execute([
                    'patient_id' => $patientId,
                    'contact_id' => $contactId,
                ]);
                $this->pdo->prepare(
                    'UPDATE patients_contacts
                     SET phone = :phone,
                         preferred_contact_method = :preferred_contact_method,
                         is_primary = 1
                     WHERE contact_id = :contact_id
                       AND patient_id = :patient_id'
                )->execute([
                    'phone' => $phone,
                    'preferred_contact_method' => $preferred,
                    'contact_id' => $contactId,
                    'patient_id' => $patientId,
                ]);
            } else {
                $this->pdo->prepare(
                    'UPDATE patients_contacts
                     SET is_primary = 0
                     WHERE patient_id = :patient_id
                       AND phone IS NOT NULL
                       AND phone <> \'\''
                )->execute([
                    'patient_id' => $patientId,
                ]);
                $this->pdo->prepare(
                    'INSERT INTO patients_contacts (contact_id, patient_id, phone, email, preferred_contact_method, is_primary, created_at)
                     VALUES (:contact_id, :patient_id, :phone, NULL, :preferred_contact_method, 1, :created_at)'
                )->execute([
                    'contact_id' => $this->generateId('c_'),
                    'patient_id' => $patientId,
                    'phone' => $phone,
                    'preferred_contact_method' => $preferred,
                    'created_at' => $now,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->fetchEditableContactsRows($patientId);
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

    public function searchPatientsByDoctorId(string $doctorId, string $query, int $limit = 25): array
    {
        $this->ensureTables();

        $safeQuery = trim($query);
        if ($safeQuery === '') {
            return [];
        }

        $patients = $this->fetchDoctorPatientsSearchRows($doctorId);
        $matches = [];

        foreach ($patients as $patient) {
            $match = $this->resolvePatientSearchMatch($patient, $safeQuery);
            if ($match === null) {
                continue;
            }
            $item = $this->formatPatientSearchResult($patient, $match);
            $item['_score'] = $match['score'];
            $matches[] = $item;
        }

        usort($matches, static function (array $a, array $b): int {
            $scoreCmp = ((int)($b['_score'] ?? 0)) <=> ((int)($a['_score'] ?? 0));
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }
            return strcasecmp((string)($a['display_name'] ?? ''), (string)($b['display_name'] ?? ''));
        });

        $limited = array_slice($matches, 0, max(1, $limit));
        foreach ($limited as &$item) {
            unset($item['_score']);
        }
        return $limited;
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

    public function upsertPrimaryAddress(string $patientId, array $address): array
    {
        $this->ensureTables();

        if (!$this->fetchPatient($patientId)) {
            throw new RuntimeException('patient not found');
        }

        $now = (new \DateTime('now', new \DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
        $existing = $this->fetchPrimaryAddressRow($patientId);
        $values = $this->normalizeAddressInput($address, $existing);

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'UPDATE patients_addresses SET is_primary = 0, updated_at = :updated_at WHERE patient_id = :patient_id'
            )->execute([
                'updated_at' => $now,
                'patient_id' => $patientId,
            ]);

            if ($existing) {
                $addressId = $existing['address_id'];
                $stmt = $this->pdo->prepare(
                    'UPDATE patients_addresses
                     SET address_type = :address_type,
                         is_primary = :is_primary,
                         country = :country,
                         postal_code = :postal_code,
                         colony = :colony,
                         state = :state,
                         municipality = :municipality,
                         locality = :locality,
                         street = :street,
                         exterior_number = :exterior_number,
                         interior_number = :interior_number,
                         floor = :floor,
                         catalog_cp_colonia_id = :catalog_cp_colonia_id,
                         updated_at = :updated_at
                     WHERE address_id = :address_id AND patient_id = :patient_id'
                );
                $stmt->execute($values + [
                    'is_primary' => 1,
                    'updated_at' => $now,
                    'address_id' => $addressId,
                    'patient_id' => $patientId,
                ]);
            } else {
                $addressId = $this->generateId('a_');
                $stmt = $this->pdo->prepare(
                    'INSERT INTO patients_addresses
                     (address_id, patient_id, address_type, is_primary, country, postal_code, colony, state, municipality, locality, street, exterior_number, interior_number, floor, catalog_cp_colonia_id, created_at, updated_at)
                     VALUES
                     (:address_id, :patient_id, :address_type, :is_primary, :country, :postal_code, :colony, :state, :municipality, :locality, :street, :exterior_number, :interior_number, :floor, :catalog_cp_colonia_id, :created_at, :updated_at)'
                );
                $stmt->execute($values + [
                    'address_id' => $addressId,
                    'patient_id' => $patientId,
                    'is_primary' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $saved = $this->fetchPrimaryAddressRow($patientId);
        if (!$saved) {
            throw new RuntimeException('database error');
        }
        return $saved;
    }

    public function fetchAddresses(string $patientId): array
    {
        $this->ensureTables();
        return $this->fetchAddressRows($patientId);
    }

    public function fetchProfile(string $patientId): ?array
    {
        $this->ensureTables();
        return $this->fetchProfileRow($patientId);
    }

    public function upsertProfile(string $patientId, array $profile): array
    {
        $this->ensureTables();

        if (!$this->fetchPatient($patientId)) {
            throw new RuntimeException('patient not found');
        }

        $now = (new \DateTime('now', new \DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
        $existing = $this->fetchProfileRow($patientId);
        $values = $this->normalizeProfileInput($profile, $existing);

        $this->pdo->beginTransaction();
        try {
            if ($existing) {
                $profileId = $existing['profile_id'];
                $stmt = $this->pdo->prepare(
                    'UPDATE patients_profiles
                     SET first_name = :first_name,
                         paternal_last_name = :paternal_last_name,
                         maternal_last_name = :maternal_last_name,
                         marital_status = :marital_status,
                         occupation = :occupation,
                         updated_at = :updated_at
                     WHERE profile_id = :profile_id AND patient_id = :patient_id'
                );
                $stmt->execute($values + [
                    'updated_at' => $now,
                    'profile_id' => $profileId,
                    'patient_id' => $patientId,
                ]);
            } else {
                $profileId = $this->generateId('pr_');
                $stmt = $this->pdo->prepare(
                    'INSERT INTO patients_profiles
                     (profile_id, patient_id, first_name, paternal_last_name, maternal_last_name, marital_status, occupation, created_at, updated_at)
                     VALUES
                     (:profile_id, :patient_id, :first_name, :paternal_last_name, :maternal_last_name, :marital_status, :occupation, :created_at, :updated_at)'
                );
                $stmt->execute($values + [
                    'profile_id' => $profileId,
                    'patient_id' => $patientId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $saved = $this->fetchProfileRow($patientId);
        if (!$saved) {
            throw new RuntimeException('database error');
        }
        return $saved;
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

    private function fetchDoctorPatientsSearchRows(string $doctorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                p.patient_id,
                p.display_name,
                p.status,
                p.birthdate,
                p.sex,
                p.created_at AS patient_created_at,
                p.updated_at AS patient_updated_at,
                pf.profile_id,
                pf.first_name,
                pf.paternal_last_name,
                pf.maternal_last_name,
                c.contact_id,
                c.phone,
                c.email,
                c.preferred_contact_method,
                c.is_primary,
                c.created_at AS contact_created_at
             FROM patients_doctor_links l
             JOIN patients_patients p ON p.patient_id = l.patient_id
             LEFT JOIN patients_profiles pf ON pf.patient_id = p.patient_id
             LEFT JOIN patients_contacts c ON c.patient_id = p.patient_id
             WHERE l.doctor_id = :doctor_id
               AND l.status = :link_status
               AND p.status = :patient_status
             ORDER BY p.display_name ASC, c.is_primary DESC, c.created_at ASC, c.contact_id ASC'
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'link_status' => 'active',
            'patient_status' => 'active',
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $patients = [];

        foreach ($rows as $row) {
            $patientId = (string)($row['patient_id'] ?? '');
            if ($patientId === '') {
                continue;
            }
            if (!isset($patients[$patientId])) {
                $profile = null;
                if (!empty($row['profile_id'])) {
                    $profile = [
                        'profile_id' => $row['profile_id'],
                        'patient_id' => $patientId,
                        'first_name' => $row['first_name'] ?? null,
                        'paternal_last_name' => $row['paternal_last_name'] ?? null,
                        'maternal_last_name' => $row['maternal_last_name'] ?? null,
                    ];
                }
                $patients[$patientId] = [
                    'patient_id' => $patientId,
                    'display_name' => $row['display_name'] ?? '',
                    'status' => $row['status'] ?? '',
                    'birthdate' => $row['birthdate'] ?? null,
                    'sex' => $row['sex'] ?? null,
                    'created_at' => $row['patient_created_at'] ?? null,
                    'updated_at' => $row['patient_updated_at'] ?? null,
                    'profile' => $profile,
                    'contacts_raw' => [],
                ];
            }
            if (!empty($row['contact_id'])) {
                $patients[$patientId]['contacts_raw'][] = [
                    'contact_id' => $row['contact_id'],
                    'phone' => $row['phone'] ?? null,
                    'email' => $row['email'] ?? null,
                    'preferred_contact_method' => $row['preferred_contact_method'] ?? null,
                    'is_primary' => (bool)($row['is_primary'] ?? false),
                    'created_at' => $row['contact_created_at'] ?? null,
                ];
            }
        }

        return array_values($patients);
    }

    private function resolvePatientSearchMatch(array $patient, string $query): ?array
    {
        $safeQuery = trim($query);
        if ($safeQuery === '') {
            return null;
        }

        $phoneDigits = $this->normalizeDigits($safeQuery);
        $phoneOnlyQuery = $phoneDigits !== '' && preg_replace('/[\d\s()+\-\.\/]/', '', $safeQuery) === '';
        if ($phoneOnlyQuery && strlen($phoneDigits) >= 4) {
            $phoneMatch = $this->resolvePhoneSearchMatch($patient, $phoneDigits);
            if ($phoneMatch !== null) {
                return $phoneMatch;
            }
        }

        $emailMatch = $this->resolveEmailSearchMatch($patient, $safeQuery);
        if ($emailMatch !== null) {
            return $emailMatch;
        }

        $textMatch = $this->resolveTextSearchMatch($patient, $safeQuery);
        if ($textMatch !== null) {
            return $textMatch;
        }

        if (!$phoneOnlyQuery && $phoneDigits !== '' && strlen($phoneDigits) >= 4) {
            return $this->resolvePhoneSearchMatch($patient, $phoneDigits);
        }

        return null;
    }

    private function resolvePhoneSearchMatch(array $patient, string $phoneDigits): ?array
    {
        $queryDigits = $this->normalizeDigits($phoneDigits);
        if (strlen($queryDigits) < 4) {
            return null;
        }
        $queryLast10 = $this->lastDigits($queryDigits, 10);
        foreach (($patient['contacts_raw'] ?? []) as $contact) {
            $contactDigits = $this->normalizeDigits((string)($contact['phone'] ?? ''));
            if ($contactDigits === '') {
                continue;
            }
            $contactLast10 = $this->lastDigits($contactDigits, 10);
            if (strlen($queryDigits) >= 10 && $queryLast10 !== '' && $contactLast10 === $queryLast10) {
                return [
                    'type' => 'phone',
                    'field' => 'contacts.phone',
                    'confidence' => 'exact_last10',
                    'label' => 'Coincidencia por teléfono',
                    'score' => 100,
                ];
            }
            if (strlen($queryDigits) < 10 && strlen($queryDigits) >= 4 && strpos($contactLast10, $queryDigits) !== false) {
                return [
                    'type' => 'phone',
                    'field' => 'contacts.phone',
                    'confidence' => 'partial',
                    'label' => 'Coincidencia parcial por teléfono',
                    'score' => 45,
                ];
            }
        }
        return null;
    }

    private function resolveEmailSearchMatch(array $patient, string $query): ?array
    {
        $normalizedQuery = strtolower(trim($query));
        if ($normalizedQuery === '' || strpos($normalizedQuery, '@') === false) {
            return null;
        }
        foreach (($patient['contacts_raw'] ?? []) as $contact) {
            $email = strtolower(trim((string)($contact['email'] ?? '')));
            if ($email !== '' && strpos($email, $normalizedQuery) !== false) {
                return [
                    'type' => 'email',
                    'field' => 'contacts.email',
                    'confidence' => $email === $normalizedQuery ? 'exact' : 'partial',
                    'label' => 'Coincidencia por correo',
                    'score' => $email === $normalizedQuery ? 85 : 55,
                ];
            }
        }
        return null;
    }

    private function resolveTextSearchMatch(array $patient, string $query): ?array
    {
        $tokens = $this->tokenizeSearchText($query);
        if (empty($tokens)) {
            return null;
        }

        $profile = is_array($patient['profile'] ?? null) ? $patient['profile'] : [];
        $displayName = (string)($patient['display_name'] ?? '');
        $profileFullName = trim(implode(' ', array_filter([
            (string)($profile['first_name'] ?? ''),
            (string)($profile['paternal_last_name'] ?? ''),
            (string)($profile['maternal_last_name'] ?? ''),
        ])));
        $searchableText = $this->normalizeSearchText(implode(' ', array_filter([
            $displayName,
            $profileFullName,
            (string)($profile['first_name'] ?? ''),
            (string)($profile['paternal_last_name'] ?? ''),
            (string)($profile['maternal_last_name'] ?? ''),
            (string)($patient['patient_id'] ?? ''),
        ])));

        foreach ($tokens as $token) {
            if (strpos($searchableText, $token) === false) {
                return null;
            }
        }

        $normalizedQuery = $this->normalizeSearchText($query);
        $normalizedDisplayName = $this->normalizeSearchText($displayName);
        $normalizedProfileFullName = $this->normalizeSearchText($profileFullName);
        $field = 'display_name';
        $confidence = 'name_contains';
        $score = 70;
        if ($normalizedDisplayName !== '' && $normalizedDisplayName === $normalizedQuery) {
            $confidence = 'exact';
            $score = 95;
        } elseif ($normalizedProfileFullName !== '' && strpos($normalizedProfileFullName, $normalizedQuery) !== false) {
            $field = 'profile_name';
            $score = 75;
        } elseif (strpos($this->normalizeSearchText((string)($profile['paternal_last_name'] ?? '')), $normalizedQuery) !== false) {
            $field = 'profile_last_name';
            $score = 72;
        } elseif (strpos($this->normalizeSearchText((string)($profile['maternal_last_name'] ?? '')), $normalizedQuery) !== false) {
            $field = 'profile_last_name';
            $score = 72;
        } elseif (strpos($this->normalizeSearchText((string)($patient['patient_id'] ?? '')), $normalizedQuery) !== false) {
            $field = 'patient_id';
            $confidence = 'partial';
            $score = 50;
        }

        return [
            'type' => $field === 'patient_id' ? 'patient_id' : 'name',
            'field' => $field,
            'confidence' => $confidence,
            'label' => $field === 'patient_id' ? 'Coincidencia por ID de paciente' : 'Coincidencia por nombre',
            'score' => $score,
        ];
    }

    private function formatPatientSearchResult(array $patient, array $match): array
    {
        $profile = is_array($patient['profile'] ?? null) ? $patient['profile'] : null;
        $contacts = [];
        foreach (($patient['contacts_raw'] ?? []) as $contact) {
            $masked = $this->maskSearchContact($contact);
            if ($masked !== null) {
                $contacts[] = $masked;
            }
        }

        return [
            'patient_id' => $patient['patient_id'],
            'display_name' => $patient['display_name'],
            'status' => $patient['status'],
            'birthdate' => $patient['birthdate'],
            'sex' => $patient['sex'],
            'age' => $this->calculateAge($patient['birthdate'] ?? null),
            'profile' => $profile ? [
                'first_name' => $profile['first_name'] ?? null,
                'paternal_last_name' => $profile['paternal_last_name'] ?? null,
                'maternal_last_name' => $profile['maternal_last_name'] ?? null,
            ] : null,
            'contacts' => $contacts,
            'match' => [
                'type' => $match['type'],
                'field' => $match['field'],
                'confidence' => $match['confidence'],
                'label' => $match['label'],
            ],
        ];
    }

    private function maskSearchContact(array $contact): ?array
    {
        $base = [
            'contact_id' => $contact['contact_id'] ?? '',
            'is_primary' => (bool)($contact['is_primary'] ?? false),
            'preferred_contact_method' => $contact['preferred_contact_method'] ?? null,
            'created_at' => $contact['created_at'] ?? null,
        ];
        if (!empty($contact['phone'])) {
            return $base + [
                'type' => 'phone',
                'value_masked' => $this->maskPhone((string)$contact['phone']),
            ];
        }
        if (!empty($contact['email'])) {
            return $base + [
                'type' => 'email',
                'value_masked' => $this->maskEmail((string)$contact['email']),
            ];
        }
        return null;
    }

    private function normalizeSearchText(string $value): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
        }
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $map = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function tokenizeSearchText(string $value): array
    {
        $normalized = $this->normalizeSearchText($value);
        if ($normalized === '') {
            return [];
        }
        return array_values(array_filter(explode(' ', $normalized), static fn($token) => $token !== ''));
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function lastDigits(string $value, int $length): string
    {
        $digits = $this->normalizeDigits($value);
        if ($digits === '') {
            return '';
        }
        return strlen($digits) > $length ? substr($digits, -$length) : $digits;
    }

    private function calculateAge(?string $birthdate): ?int
    {
        $safeBirthdate = trim((string)($birthdate ?? ''));
        if ($safeBirthdate === '') {
            return null;
        }
        try {
            $birth = new \DateTime($safeBirthdate, new \DateTimeZone('America/Mexico_City'));
            $today = new \DateTime('today', new \DateTimeZone('America/Mexico_City'));
            if ($birth > $today) {
                return null;
            }
            return (int)$birth->diff($today)->y;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchAddressRows(string $patientId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT address_id, patient_id, address_type, is_primary, country, postal_code, colony, state, municipality, locality, street, exterior_number, interior_number, floor, catalog_cp_colonia_id, created_at, updated_at
             FROM patients_addresses
             WHERE patient_id = :patient_id
             ORDER BY is_primary DESC, created_at ASC, address_id ASC'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'normalizeAddressRow'], $rows);
    }

    private function fetchPrimaryAddressRow(string $patientId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT address_id, patient_id, address_type, is_primary, country, postal_code, colony, state, municipality, locality, street, exterior_number, interior_number, floor, catalog_cp_colonia_id, created_at, updated_at
             FROM patients_addresses
             WHERE patient_id = :patient_id AND is_primary = 1
             ORDER BY created_at ASC, address_id ASC
             LIMIT 1'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeAddressRow($row) : null;
    }

    private function fetchProfileRow(string $patientId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT profile_id, patient_id, first_name, paternal_last_name, maternal_last_name, marital_status, occupation, created_at, updated_at
             FROM patients_profiles
             WHERE patient_id = :patient_id
             LIMIT 1'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeProfileRow($row) : null;
    }

    private function fetchPrimaryPhoneContactRow(string $patientId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT contact_id, patient_id, phone, email, preferred_contact_method, is_primary, created_at
             FROM patients_contacts
             WHERE patient_id = :patient_id
               AND phone IS NOT NULL
               AND phone <> \'\'
               AND is_primary = 1
             ORDER BY created_at ASC, contact_id ASC
             LIMIT 1'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function normalizeProfileInput(array $profile, ?array $base = null): array
    {
        return [
            'first_name' => $this->cleanProfileString($profile, 'first_name', $base),
            'paternal_last_name' => $this->cleanProfileString($profile, 'paternal_last_name', $base),
            'maternal_last_name' => $this->cleanProfileString($profile, 'maternal_last_name', $base),
            'marital_status' => $this->cleanProfileString($profile, 'marital_status', $base),
            'occupation' => $this->cleanProfileString($profile, 'occupation', $base),
        ];
    }

    private function normalizeProfileRow(array $row): array
    {
        return $row;
    }

    private function cleanProfileString(array $profile, string $key, ?array $base): ?string
    {
        if (array_key_exists($key, $profile)) {
            return $this->cleanNullableString($profile[$key]);
        }
        return $base[$key] ?? null;
    }

    private function normalizeAddressInput(array $address, ?array $base = null): array
    {
        return [
            'address_type' => array_key_exists('address_type', $address) ? $this->cleanStringOrDefault($address['address_type'], 'home') : (string)($base['address_type'] ?? 'home'),
            'country' => strtoupper(array_key_exists('country', $address) ? $this->cleanStringOrDefault($address['country'], 'MX') : (string)($base['country'] ?? 'MX')),
            'postal_code' => $this->cleanAddressString($address, 'postal_code', $base),
            'colony' => $this->cleanAddressString($address, 'colony', $base),
            'state' => $this->cleanAddressString($address, 'state', $base),
            'municipality' => $this->cleanAddressString($address, 'municipality', $base),
            'locality' => $this->cleanAddressString($address, 'locality', $base),
            'street' => $this->cleanAddressString($address, 'street', $base),
            'exterior_number' => $this->cleanAddressString($address, 'exterior_number', $base),
            'interior_number' => $this->cleanAddressString($address, 'interior_number', $base),
            'floor' => $this->cleanAddressString($address, 'floor', $base),
            'catalog_cp_colonia_id' => $this->cleanAddressInt($address, 'catalog_cp_colonia_id', $base),
        ];
    }

    private function normalizeAddressRow(array $row): array
    {
        $row['is_primary'] = (bool)$row['is_primary'];
        $row['catalog_cp_colonia_id'] = $row['catalog_cp_colonia_id'] !== null ? (int)$row['catalog_cp_colonia_id'] : null;
        return $row;
    }

    private function cleanNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function cleanStringOrDefault($value, string $default): string
    {
        $text = $this->cleanNullableString($value);
        return $text === null ? $default : $text;
    }

    private function cleanAddressString(array $address, string $key, ?array $base): ?string
    {
        if (array_key_exists($key, $address)) {
            return $this->cleanNullableString($address[$key]);
        }
        return $base[$key] ?? null;
    }

    private function cleanNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int)$value;
        return $int > 0 ? $int : null;
    }

    private function cleanAddressInt(array $address, string $key, ?array $base): ?int
    {
        if (array_key_exists($key, $address)) {
            return $this->cleanNullableInt($address[$key]);
        }
        return $base[$key] ?? null;
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

    private function fetchEditableContactsRows(string $patientId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT contact_id, phone, email, preferred_contact_method, is_primary, created_at
             FROM patients_contacts
             WHERE patient_id = :patient_id
             ORDER BY is_primary DESC, created_at ASC, contact_id ASC'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $contacts = [];
        foreach ($rows as $row) {
            $base = [
                'contact_id' => $row['contact_id'],
                'is_primary' => (bool)$row['is_primary'],
                'preferred_contact_method' => $row['preferred_contact_method'] ?? null,
                'created_at' => $row['created_at'],
            ];
            if (!empty($row['phone'])) {
                $phone = (string)$row['phone'];
                $contacts[] = $base + [
                    'type' => 'phone',
                    'value' => $phone,
                    'value_masked' => $this->maskPhone($phone),
                ];
                continue;
            }
            if (!empty($row['email'])) {
                $email = (string)$row['email'];
                $contacts[] = $base + [
                    'type' => 'email',
                    'value' => $email,
                    'value_masked' => $this->maskEmail($email),
                ];
            }
        }
        return $contacts;
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
        foreach (['patients_patients', 'patients_profiles', 'patients_contacts', 'patients_addresses', 'patients_doctor_links'] as $table) {
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
