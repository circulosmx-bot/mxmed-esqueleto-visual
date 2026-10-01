<?php
declare(strict_types=1);

namespace Agenda\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/** Trusted domain service. No HTTP/provider-account permission is implied by this class. */
final class HealthcareOrganizationDirectoryService
{
    private const LOCATION_FIELDS = [
        'branch_name' => 190, 'street' => 190, 'exterior_number' => 32,
        'interior_number' => 32, 'postal_code' => 5, 'colonia' => 190,
        'municipality' => 190, 'state_name' => 190, 'phone' => 32,
        'coordinate_source' => 32,
    ];
    private const OPERATIONAL_STATES = ['ACTIVE', 'INACTIVE'];
    private const VERIFICATION_STATES = ['VERIFIED', 'REJECTED'];

    public function __construct(private PDO $pdo)
    {
    }

    public function createLocation(string $groupId, array $data): array
    {
        $this->assertOrganization($groupId);
        $fields = $this->locationFields($data, true);
        $this->validateGeography($fields);
        $uuid = self::uuidV4();
        $sql = 'INSERT INTO healthcare_organization_locations
            (location_uuid,group_id,'.implode(',', array_keys($fields)).')
            VALUES (:location_uuid,:group_id,'.implode(',', array_map(static fn ($key) => ':'.$key, array_keys($fields))).')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['location_uuid' => $uuid, 'group_id' => $groupId] + $fields);
        return $this->requireLocation($groupId, $uuid);
    }

    public function updateLocation(string $groupId, string $locationUuid, array $data): array
    {
        $existing = $this->requireLocation($groupId, $locationUuid);
        $fields = $this->locationFields($data, false);
        if (array_key_exists('postal_code', $fields) || array_key_exists('colonia', $fields)) {
            $this->validateGeography($fields + $existing);
        }
        $this->updateScoped('healthcare_organization_locations', $fields,
            'group_id=:group_id AND location_uuid=:location_uuid',
            ['group_id' => $groupId, 'location_uuid' => $locationUuid]);
        return $this->requireLocation($groupId, $locationUuid);
    }

    public function setLocationVerification(string $groupId, string $locationUuid, string $state, string $actorUserId): array
    {
        $this->requireLocation($groupId, $locationUuid);
        $this->assertVerification($state, $actorUserId);
        $stmt = $this->pdo->prepare('UPDATE healthcare_organization_locations
            SET verification_state=:state,verification_actor_user_id=:actor,verification_at=NOW()
            WHERE group_id=:group_id AND location_uuid=:location_uuid');
        $stmt->execute(['state' => $state, 'actor' => $actorUserId, 'group_id' => $groupId, 'location_uuid' => $locationUuid]);
        return $this->requireLocation($groupId, $locationUuid);
    }

    public function createOffering(string $groupId, string $locationUuid, int $studyTypeId, array $data = []): array
    {
        $location = $this->requireLocation($groupId, $locationUuid);
        if ($studyTypeId < 1) {
            throw new InvalidArgumentException('invalid_study_type_id');
        }
        $fields = $this->offeringFields($data, true);
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $study = $this->pdo->prepare('SELECT is_active FROM clinical_study_types WHERE study_type_id=:id FOR UPDATE');
            $study->execute(['id' => $studyTypeId]);
            $active = $study->fetchColumn();
            if ($active === false) {
                throw new InvalidArgumentException('study_type_not_found');
            }
            if ((int)$active !== 1) {
                throw new InvalidArgumentException('study_type_inactive');
            }
            $sql = 'INSERT INTO healthcare_organization_location_study_offerings
                (location_id,study_type_id,'.implode(',', array_keys($fields)).')
                VALUES (:location_id,:study_type_id,'.implode(',', array_map(static fn ($key) => ':'.$key, array_keys($fields))).')';
            $this->pdo->prepare($sql)->execute(['location_id' => $location['location_id'], 'study_type_id' => $studyTypeId] + $fields);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (PDOException $e) {
            if ($ownsTransaction) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('offering_already_exists_or_invalid_reference', 0, $e);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $this->requireOffering($groupId, $locationUuid, $studyTypeId);
    }

    public function updateOffering(string $groupId, string $locationUuid, int $studyTypeId, array $data): array
    {
        $this->requireOffering($groupId, $locationUuid, $studyTypeId);
        $fields = $this->offeringFields($data, false);
        $this->updateScoped('healthcare_organization_location_study_offerings', $fields,
            'location_id IN (SELECT location_id FROM healthcare_organization_locations WHERE group_id=:group_id AND location_uuid=:location_uuid) AND study_type_id=:study_type_id',
            ['group_id' => $groupId, 'location_uuid' => $locationUuid, 'study_type_id' => $studyTypeId]);
        return $this->requireOffering($groupId, $locationUuid, $studyTypeId);
    }

    public function setOfferingVerification(string $groupId, string $locationUuid, int $studyTypeId, string $state, string $actorUserId): array
    {
        $this->requireOffering($groupId, $locationUuid, $studyTypeId);
        $this->assertVerification($state, $actorUserId);
        $stmt = $this->pdo->prepare('UPDATE healthcare_organization_location_study_offerings o
            JOIN healthcare_organization_locations l ON l.location_id=o.location_id
            SET o.verification_state=:state,o.verification_actor_user_id=:actor,o.verification_at=NOW()
            WHERE l.group_id=:group_id AND l.location_uuid=:location_uuid AND o.study_type_id=:study_type_id');
        $stmt->execute(['state' => $state, 'actor' => $actorUserId, 'group_id' => $groupId,
            'location_uuid' => $locationUuid, 'study_type_id' => $studyTypeId]);
        return $this->requireOffering($groupId, $locationUuid, $studyTypeId);
    }

    public function readOrganization(string $groupId): array
    {
        $this->assertOrganization($groupId);
        $stmt = $this->pdo->prepare('SELECT group_id,organization_type_key,display_name,status
            FROM medical_groups WHERE group_id=:group_id');
        $stmt->execute(['group_id' => $groupId]);
        $organization = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $this->pdo->prepare('SELECT operational_state,verification_state
            FROM healthcare_organization_provider_status WHERE group_id=:group_id');
        $stmt->execute(['group_id' => $groupId]);
        $organization['provider_status'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $this->pdo->prepare('SELECT location_id,location_uuid,branch_name,operational_state,verification_state,
            street,exterior_number,interior_number,postal_code,colonia,municipality,state_name,
            latitude,longitude,coordinate_source,phone
            FROM healthcare_organization_locations WHERE group_id=:group_id ORDER BY location_id');
        $stmt->execute(['group_id' => $groupId]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($locations as &$location) {
            $stmt = $this->pdo->prepare('SELECT o.offering_id,o.study_type_id,s.study_type_key,s.display_name_es,s.category_key,
                s.is_active AS study_type_active,o.operational_state,o.verification_state,o.service_mode,
                o.requires_appointment,o.preparation_instructions
                FROM healthcare_organization_location_study_offerings o
                JOIN clinical_study_types s ON s.study_type_id=o.study_type_id
                WHERE o.location_id=:location_id ORDER BY o.study_type_id');
            $stmt->execute(['location_id' => $location['location_id']]);
            $location['offerings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($location['offerings'] as &$offering) {
                $areas = $this->pdo->prepare('SELECT service_area_id,scope_type,region_key,operational_state,verification_state
                    FROM healthcare_organization_location_study_service_areas
                    WHERE offering_id=:offering_id ORDER BY service_area_id');
                $areas->execute(['offering_id' => $offering['offering_id']]);
                $offering['service_areas'] = $areas->fetchAll(PDO::FETCH_ASSOC);
                unset($offering['offering_id']);
            }
            unset($offering);
            unset($location['location_id']);
        }
        unset($location);
        $organization['locations'] = $locations;
        return $organization;
    }

    private function assertOrganization(string $groupId): void
    {
        if (trim($groupId) === '') {
            throw new InvalidArgumentException('group_id_required');
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM medical_groups WHERE group_id=:id');
        $stmt->execute(['id' => $groupId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('organization_not_found');
        }
    }

    private function validateGeography(array $fields): void
    {
        $cp = $fields['postal_code'] ?? null;
        $colonia = $fields['colonia'] ?? null;
        if ($cp === null || $colonia === null) {
            return;
        }
        $exists = $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema=DATABASE() AND table_name='catalog_cp_colonias'")->fetchColumn();
        if ((int)$exists === 0) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM catalog_cp_colonias
            WHERE cp=:cp AND colonia=:colonia AND is_active=1 LIMIT 1');
        $stmt->execute(['cp' => $cp, 'colonia' => $colonia]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('postal_colonia_not_in_active_catalog');
        }
    }

    private function requireLocation(string $groupId, string $locationUuid): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM healthcare_organization_locations
            WHERE group_id=:group_id AND location_uuid=:uuid');
        $stmt->execute(['group_id' => $groupId, 'uuid' => $locationUuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('location_not_found');
        }
        return $row;
    }

    private function requireOffering(string $groupId, string $locationUuid, int $studyTypeId): array
    {
        $stmt = $this->pdo->prepare('SELECT o.* FROM healthcare_organization_location_study_offerings o
            JOIN healthcare_organization_locations l ON l.location_id=o.location_id
            WHERE l.group_id=:group_id AND l.location_uuid=:uuid AND o.study_type_id=:study_type_id');
        $stmt->execute(['group_id' => $groupId, 'uuid' => $locationUuid, 'study_type_id' => $studyTypeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('offering_not_found');
        }
        return $row;
    }

    private function locationFields(array $data, bool $creating): array
    {
        $allowed = array_merge(array_keys(self::LOCATION_FIELDS), ['latitude','longitude','operational_state']);
        $this->assertKeys($data, $allowed);
        $fields = [];
        foreach (self::LOCATION_FIELDS as $key => $max) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $this->text($data[$key], $max, $key === 'branch_name');
            }
        }
        if ($creating && !isset($fields['branch_name'])) {
            throw new InvalidArgumentException('branch_name_required');
        }
        if (isset($fields['postal_code']) && !preg_match('/^[0-9]{5}$/D', $fields['postal_code'])) {
            throw new InvalidArgumentException('invalid_postal_code');
        }
        if (array_key_exists('operational_state', $data)) {
            $fields['operational_state'] = $this->state($data['operational_state']);
        }
        $hasLat = array_key_exists('latitude', $data);
        $hasLng = array_key_exists('longitude', $data);
        if ($hasLat !== $hasLng) {
            throw new InvalidArgumentException('coordinate_pair_required');
        }
        if ($hasLat) {
            $fields['latitude'] = $this->coordinate($data['latitude'], -90, 90);
            $fields['longitude'] = $this->coordinate($data['longitude'], -180, 180);
            if (($fields['latitude'] === null) !== ($fields['longitude'] === null)) {
                throw new InvalidArgumentException('coordinate_pair_required');
            }
            if ($fields['latitude'] !== null && !isset($fields['coordinate_source'])) {
                throw new InvalidArgumentException('coordinate_source_required');
            }
            if ($fields['latitude'] === null) {
                $fields['coordinate_source'] = null;
            }
        } elseif (isset($fields['coordinate_source']) && $creating) {
            throw new InvalidArgumentException('coordinate_pair_required');
        }
        if (!$creating && $fields === []) {
            throw new InvalidArgumentException('no_location_changes');
        }
        return $fields;
    }

    private function offeringFields(array $data, bool $creating): array
    {
        $this->assertKeys($data, ['operational_state','service_mode','requires_appointment','preparation_instructions']);
        $fields = [];
        if (array_key_exists('operational_state', $data)) {
            $fields['operational_state'] = $this->state($data['operational_state']);
        }
        if (array_key_exists('service_mode', $data)) {
            if (!in_array($data['service_mode'], ['ON_SITE','HOME_SERVICE','MOBILE'], true)) {
                throw new InvalidArgumentException('invalid_service_mode');
            }
            $fields['service_mode'] = $data['service_mode'];
        }
        if (array_key_exists('requires_appointment', $data)) {
            if (!is_bool($data['requires_appointment'])) {
                throw new InvalidArgumentException('invalid_requires_appointment');
            }
            $fields['requires_appointment'] = $data['requires_appointment'] ? 1 : 0;
        }
        if (array_key_exists('preparation_instructions', $data)) {
            $fields['preparation_instructions'] = $this->text($data['preparation_instructions'], 2000, false);
        }
        if ($creating && $fields === []) {
            $fields['service_mode'] = 'ON_SITE';
        }
        if (!$creating && $fields === []) {
            throw new InvalidArgumentException('no_offering_changes');
        }
        return $fields;
    }

    private function updateScoped(string $table, array $fields, string $where, array $scope): void
    {
        $set = implode(',', array_map(static fn ($key) => $key.'=:'.$key, array_keys($fields)));
        $this->pdo->prepare('UPDATE '.$table.' SET '.$set.' WHERE '.$where)->execute($fields + $scope);
    }

    private function assertKeys(array $data, array $allowed): void
    {
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('unsupported_field_'.$key);
            }
        }
    }

    private function text(mixed $value, int $max, bool $required): ?string
    {
        if ($value !== null && !is_string($value)) {
            throw new InvalidArgumentException('invalid_text');
        }
        $text = trim((string)$value);
        if ($required && $text === '') {
            throw new InvalidArgumentException('required_text');
        }
        if (mb_strlen($text) > $max) {
            throw new InvalidArgumentException('text_too_long');
        }
        return $text === '' ? null : $text;
    }

    private function coordinate(mixed $value, int $min, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_numeric($value) || (float)$value < $min || (float)$value > $max) {
            throw new InvalidArgumentException('invalid_coordinate');
        }
        return (string)$value;
    }

    private function state(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, self::OPERATIONAL_STATES, true)) {
            throw new InvalidArgumentException('invalid_operational_state');
        }
        return $value;
    }

    private function assertVerification(string $state, string $actorUserId): void
    {
        if (!in_array($state, self::VERIFICATION_STATES, true) || trim($actorUserId) === '' || mb_strlen($actorUserId) > 64) {
            throw new InvalidArgumentException('invalid_verification_transition');
        }
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
