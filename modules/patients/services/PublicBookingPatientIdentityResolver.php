<?php
declare(strict_types=1);

namespace Patients\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Patients\Identity\CanonicalPatientId;
use Patients\Identity\LegacyPatientReference;
use Patients\Identity\PatientIdentityCandidate;
use Patients\Identity\PatientIdentityCandidateSet;
use Patients\Identity\PatientIdentityEvidence;
use Patients\Identity\PatientIdentityResolutionRequest;
use Patients\Identity\PatientIdentityResolver;

foreach (glob(__DIR__ . '/../identity/*.php') ?: [] as $identityFile) {
    require_once $identityFile;
}

/**
 * Public-booking boundary adapter for the canonical patient-identity resolver.
 *
 * It keeps raw booking data at the boundary, sends only normalized digests to
 * the domain resolver, and permits reuse only for a unique name + birth date +
 * contact match within the active doctor-patient relationship.
 */
final class PublicBookingPatientIdentityResolver
{
    public function __construct(private PDO $pdo, private ?PatientIdentityResolver $resolver = null)
    {
        $this->resolver ??= new PatientIdentityResolver();
    }

    /** @return array{status:string,patient_id:?string,match_tier:string} */
    public function resolve(string $doctorId, array $patient): array
    {
        $input = $this->normalizePatient($patient);
        if ($doctorId === '' || $input['name'] === '' || $input['birthdate'] === '' || ($input['phone'] === '' && $input['email'] === '')) {
            return $this->notFound();
        }

        try {
            $loadedCandidates = $this->loadNamedDoctorCandidates($doctorId, $input['name']);
            $candidateSet = new PatientIdentityCandidateSet(array_map(
                fn(array $candidate): PatientIdentityCandidate => $this->toDomainCandidate($candidate, $input),
                $loadedCandidates
            ));
            $decision = $this->resolver->resolve($this->toDomainRequest($doctorId, $input), $candidateSet);
        } catch (\Throwable) {
            // A booking must remain possible when historical identity cannot be
            // resolved. It must never fall back to a contact-only patient id.
            return ['status' => 'ambiguous', 'patient_id' => null, 'match_tier' => 'unresolved'];
        }

        if ($decision->status() === 'create_minimal_required') {
            return $this->notFound();
        }

        if ($decision->status() !== 'mapped_from_legacy' || $decision->resolvedPatientId() === null) {
            return ['status' => 'ambiguous', 'patient_id' => null, 'match_tier' => 'ambiguous'];
        }

        $patientId = $decision->resolvedPatientId()->value();
        foreach ($loadedCandidates as $candidate) {
            if ($candidate['patient_id'] === $patientId && $this->isPublicStrongMatch($candidate, $input)) {
                return ['status' => 'matched', 'patient_id' => $patientId, 'match_tier' => 'name_birthdate_contact_exact'];
            }
        }

        // The canonical resolver is deliberately broader for legacy migration.
        // Public booking applies the stricter evidence contract above.
        return ['status' => 'ambiguous', 'patient_id' => null, 'match_tier' => 'insufficient_public_evidence'];
    }

    /** @return array{status:string,patient_id:null,match_tier:string} */
    private function notFound(): array
    {
        return ['status' => 'not_found', 'patient_id' => null, 'match_tier' => 'no_match'];
    }

    /** @return array{name:string,birthdate:string,sex:?string,phone:string,email:string} */
    private function normalizePatient(array $patient): array
    {
        return [
            'name' => self::normalizeName((string)($patient['name'] ?? '')),
            'birthdate' => self::normalizeBirthdate((string)($patient['dob'] ?? $patient['birthdate'] ?? '')),
            'sex' => self::normalizeSex((string)($patient['gender'] ?? $patient['sex'] ?? '')),
            'phone' => self::normalizePhone((string)($patient['phone'] ?? '')),
            'email' => self::normalizeEmail((string)($patient['email'] ?? '')),
        ];
    }

    /** @return list<array{patient_id:string,name:string,birthdate:string,sex:?string,phones:list<string>,emails:list<string>}> */
    private function loadNamedDoctorCandidates(string $doctorId, string $normalizedName): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.patient_id, p.display_name, p.birthdate, p.sex, c.phone, c.email
             FROM patients_doctor_links l
             JOIN patients_patients p ON p.patient_id = l.patient_id
             LEFT JOIN patients_contacts c ON c.patient_id = p.patient_id
             WHERE l.doctor_id = :doctor_id
               AND l.status = :link_status
               AND l.ended_at IS NULL
               AND p.status = :patient_status'
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'link_status' => 'active',
            'patient_status' => 'active',
        ]);

        $candidates = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $patientId = (string)($row['patient_id'] ?? '');
            if ($patientId === '' || self::normalizeName((string)($row['display_name'] ?? '')) !== $normalizedName) {
                continue;
            }
            if (!isset($candidates[$patientId])) {
                $candidates[$patientId] = [
                    'patient_id' => $patientId,
                    'name' => $normalizedName,
                    'birthdate' => self::normalizeBirthdate((string)($row['birthdate'] ?? '')),
                    'sex' => self::normalizeSex((string)($row['sex'] ?? '')),
                    'phones' => [],
                    'emails' => [],
                ];
            }
            $phone = self::normalizePhone((string)($row['phone'] ?? ''));
            $email = self::normalizeEmail((string)($row['email'] ?? ''));
            if ($phone !== '') $candidates[$patientId]['phones'][$phone] = $phone;
            if ($email !== '') $candidates[$patientId]['emails'][$email] = $email;
        }

        return array_values(array_map(static function (array $candidate): array {
            $candidate['phones'] = array_values($candidate['phones']);
            $candidate['emails'] = array_values($candidate['emails']);
            return $candidate;
        }, $candidates));
    }

    private function toDomainCandidate(array $candidate, array $input): PatientIdentityCandidate
    {
        $matchedPhone = in_array($input['phone'], $candidate['phones'], true) ? $input['phone'] : null;
        $matchedEmail = in_array($input['email'], $candidate['emails'], true) ? $input['email'] : null;
        return new PatientIdentityCandidate(
            new CanonicalPatientId($candidate['patient_id']),
            new PatientIdentityEvidence(
                self::reference('name', $candidate['name']),
                $candidate['birthdate'] === '' ? null : self::reference('birthdate', $candidate['birthdate']),
                $candidate['sex'],
                $matchedPhone === null ? null : self::reference('phone', $matchedPhone),
                $matchedEmail === null ? null : self::reference('email', $matchedEmail)
            ),
            1,
            true
        );
    }

    private function toDomainRequest(string $doctorId, array $input): PatientIdentityResolutionRequest
    {
        $fingerprint = self::reference('request', self::canonical($input));
        return new PatientIdentityResolutionRequest(
            'operation-' . self::reference('operation', $doctorId . '|' . $fingerprint),
            'correlation-' . self::reference('correlation', $doctorId . '|' . $fingerprint),
            'public_verified',
            'legacy_patient_key_hash',
            null,
            new LegacyPatientReference(self::reference('legacy', $doctorId . '|' . $fingerprint)),
            new PatientIdentityEvidence(
                self::reference('name', $input['name']),
                self::reference('birthdate', $input['birthdate']),
                $input['sex'],
                $input['phone'] === '' ? null : self::reference('phone', $input['phone']),
                $input['email'] === '' ? null : self::reference('email', $input['email'])
            ),
            'system-' . self::reference('actor', 'public-booking'),
            'system-' . self::reference('actor', 'public-booking'),
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s.u\\Z')
        );
    }

    private function isPublicStrongMatch(array $candidate, array $input): bool
    {
        return $candidate['name'] === $input['name']
            && $candidate['birthdate'] !== ''
            && hash_equals($candidate['birthdate'], $input['birthdate'])
            && (in_array($input['phone'], $candidate['phones'], true) || in_array($input['email'], $candidate['emails'], true));
    }

    private static function canonical(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function reference(string $kind, string $value): string
    {
        return hash('sha256', 'mxmed-public-booking-identity-v1|' . $kind . '|' . $value);
    }

    private static function normalizeName(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        return trim(preg_replace('/\\s+/', ' ', $value) ?? '');
    }

    private static function normalizePhone(string $value): string
    {
        return preg_replace('/\\D+/', '', trim($value)) ?? '';
    }

    private static function normalizeEmail(string $value): string
    {
        $value = trim($value);
        return $value === '' ? '' : (function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
    }

    private static function normalizeBirthdate(string $value): string
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
    }

    private static function normalizeSex(string $value): ?string
    {
        return match (trim($value)) {
            'M', 'male' => 'male',
            'F', 'female' => 'female',
            'No especifica', 'undisclosed' => 'undisclosed',
            'other' => 'other',
            default => null,
        };
    }
}
