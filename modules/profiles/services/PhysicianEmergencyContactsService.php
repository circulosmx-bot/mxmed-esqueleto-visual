<?php
declare(strict_types=1);

namespace Profiles\Services;

use PDO;
use Profiles\Repositories\DoctorContactPointsRepository;
use RuntimeException;

require_once __DIR__ . '/../repositories/DoctorContactPointsRepository.php';

final class PhysicianEmergencyContactsService
{
    public function __construct(
        private PDO $pdo,
        private DoctorContactPointsRepository $contacts
    ) {}

    /** @return array{phones:list<string>,fingerprint:string,count:int} */
    public function state(string $doctorId, bool $lock = false): array
    {
        $doctorId = trim($doctorId);
        if ($doctorId === '') throw new RuntimeException('invalid_emergency_contact_scope');
        $sql = "SELECT contact_point_id,value,normalized_value,emergency_position,scope,is_public,
                       use_for_public_profile,use_for_security,use_for_platform_admin,use_for_appointments,updated_at
                  FROM doctor_contact_points
                 WHERE doctor_id = :doctor_id
                   AND type = 'emergency_phone'
                   AND deleted_at IS NULL
                 ORDER BY emergency_position ASC, contact_point_id ASC" . ($lock ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doctor_id' => $doctorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $parts = [];
        $phones = [];
        foreach ($rows as $row) {
            $position = (int)($row['emergency_position'] ?? 0);
            if ($position < 1 || $position > 2) throw new RuntimeException('emergency_contact_invariant_failed');
            $phones[$position] = trim((string)($row['value'] ?? ''));
            $parts[] = implode(':', [
                (string)($row['contact_point_id'] ?? ''),
                (string)$position,
                (string)($row['normalized_value'] ?? ''),
                (string)($row['scope'] ?? ''),
                (string)($row['is_public'] ?? 0),
                (string)($row['use_for_public_profile'] ?? 0),
                (string)($row['updated_at'] ?? ''),
            ]);
        }
        ksort($phones, SORT_NUMERIC);
        return [
            'phones' => array_values($phones),
            'fingerprint' => hash('sha256', implode('|', $parts)),
            'count' => count($phones),
        ];
    }

    /** @param list<mixed> $phones
     *  @return array{phones:list<string>,fingerprint:string,count:int}
     */
    public function replacePrivateSet(string $doctorId, array $phones, string $expectedFingerprint): array
    {
        $doctorId = trim($doctorId);
        $expectedFingerprint = strtolower(trim($expectedFingerprint));
        if ($doctorId === '' || preg_match('/^[a-f0-9]{64}$/', $expectedFingerprint) !== 1 || count($phones) > 2) {
            throw new RuntimeException('invalid_emergency_contacts');
        }
        $prepared = [];
        $seen = [];
        foreach (array_values($phones) as $value) {
            if (!is_string($value) && !is_numeric($value)) throw new RuntimeException('invalid_emergency_contacts');
            $display = trim(strip_tags((string)$value));
            if ($display === '' || mb_strlen($display, 'UTF-8') > 255) throw new RuntimeException('invalid_emergency_contacts');
            $normalized = $this->contacts->normalizeValue('emergency_phone', $display);
            $digits = preg_replace('/\D/', '', $normalized) ?: '';
            if (strlen($digits) < 7 || strlen($digits) > 15 || isset($seen[$normalized])) {
                throw new RuntimeException('invalid_emergency_contacts');
            }
            $seen[$normalized] = true;
            $prepared[] = ['value' => $display, 'normalized' => $normalized];
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $profile = $this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id = :doctor_id FOR UPDATE');
            $profile->execute(['doctor_id' => $doctorId]);
            if ($profile->fetchColumn() === false) throw new RuntimeException('emergency_contact_profile_not_found');
            $current = $this->state($doctorId, true);
            if (!hash_equals($current['fingerprint'], $expectedFingerprint)) {
                throw new RuntimeException('stale_emergency_contacts');
            }
            $existingStmt = $this->pdo->prepare(
                "SELECT * FROM doctor_contact_points
                  WHERE doctor_id = :doctor_id AND type = 'emergency_phone' AND deleted_at IS NULL
                  ORDER BY emergency_position FOR UPDATE"
            );
            $existingStmt->execute(['doctor_id' => $doctorId]);
            $existing = [];
            foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $existing[(int)$row['emergency_position']] = $row;

            $update = $this->pdo->prepare(
                "UPDATE doctor_contact_points
                    SET value=:value,normalized_value=:normalized,label=:label,status='active',sort_order=:position,updated_at=NOW()
                  WHERE doctor_id=:doctor_id AND contact_point_id=:contact_point_id AND deleted_at IS NULL"
            );
            $insert = $this->pdo->prepare(
                "INSERT INTO doctor_contact_points
                  (doctor_id,consultorio_id,type,value,normalized_value,label,scope,is_public,is_verified,
                   verification_status,use_for_security,use_for_platform_admin,use_for_public_profile,
                   use_for_appointments,visibility_plan_min,status,sort_order,emergency_position,source)
                 VALUES (:doctor_id,NULL,'emergency_phone',:value,:normalized,:label,'private',0,0,
                   'unverified',0,0,0,0,NULL,'active',:sort_order,:position,'manual')"
            );
            foreach ($prepared as $index => $phone) {
                $position = $index + 1;
                $params = [
                    'doctor_id' => $doctorId,
                    'value' => $phone['value'],
                    'normalized' => $phone['normalized'],
                    'label' => sprintf('Teléfono de urgencias %02d', $position),
                    'position' => $position,
                    'sort_order' => $position,
                ];
                if (isset($existing[$position])) {
                    unset($params['sort_order']);
                    $params['contact_point_id'] = $existing[$position]['contact_point_id'];
                    $update->execute($params);
                    unset($existing[$position]);
                } else {
                    $insert->execute($params);
                }
            }
            if ($existing !== []) {
                $delete = $this->pdo->prepare(
                    "UPDATE doctor_contact_points SET deleted_at=NOW(),updated_at=NOW()
                      WHERE doctor_id=:doctor_id AND contact_point_id=:contact_point_id AND deleted_at IS NULL"
                );
                foreach ($existing as $row) $delete->execute(['doctor_id' => $doctorId, 'contact_point_id' => $row['contact_point_id']]);
            }
            $after = $this->state($doctorId, true);
            if ($after['count'] !== count($prepared)) throw new RuntimeException('emergency_contact_invariant_failed');
            if ($ownsTransaction) $this->pdo->commit();
            return $after;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** A distinct owner/administrator action controls public-profile use. */
    public function setPublicProfilePublication(string $doctorId, bool $publish, string $expectedFingerprint): array
    {
        $doctorId = trim($doctorId);
        $expectedFingerprint = strtolower(trim($expectedFingerprint));
        if ($doctorId === '' || preg_match('/^[a-f0-9]{64}$/', $expectedFingerprint) !== 1) {
            throw new RuntimeException('invalid_emergency_contacts');
        }
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $profile = $this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id = :doctor_id FOR UPDATE');
            $profile->execute(['doctor_id' => $doctorId]);
            if ($profile->fetchColumn() === false) throw new RuntimeException('emergency_contact_profile_not_found');
            $current = $this->state($doctorId, true);
            if (!hash_equals($current['fingerprint'], $expectedFingerprint)) throw new RuntimeException('stale_emergency_contacts');
            if ($current['count'] === 0 && $publish) throw new RuntimeException('invalid_emergency_contacts');
            $stmt = $this->pdo->prepare(
                "UPDATE doctor_contact_points
                    SET scope=:scope,is_public=:is_public,use_for_public_profile=:public_profile,
                        use_for_security=0,use_for_platform_admin=0,use_for_appointments=0,updated_at=NOW()
                  WHERE doctor_id=:doctor_id AND type='emergency_phone' AND deleted_at IS NULL"
            );
            $stmt->execute([
                'scope' => $publish ? 'public_profile' : 'private',
                'is_public' => $publish ? 1 : 0,
                'public_profile' => $publish ? 1 : 0,
                'doctor_id' => $doctorId,
            ]);
            $after = $this->state($doctorId, true);
            if ($ownsTransaction) $this->pdo->commit();
            return $after;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
