<?php
declare(strict_types=1);

namespace Agenda\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/** Trusted domain service; organization membership alone grants no access to it. */
final class HealthcareProviderCoverageService
{
    private const OPERATIONAL_STATES = ['ACTIVE', 'INACTIVE'];
    private const VERIFICATION_STATES = ['VERIFIED', 'REJECTED'];

    public function __construct(private PDO $pdo)
    {
    }

    public function initializeProvider(string $groupId): array
    {
        $this->assertOrganization($groupId);
        $this->pdo->prepare('INSERT INTO healthcare_organization_provider_status(group_id)
            VALUES(:group_id) ON DUPLICATE KEY UPDATE group_id=group_id')->execute(['group_id' => $groupId]);
        return $this->requireProvider($groupId);
    }

    public function setProviderOperationalState(string $groupId, string $state): array
    {
        $this->requireProvider($groupId);
        $this->assertOperational($state);
        $this->pdo->prepare('UPDATE healthcare_organization_provider_status SET operational_state=:state
            WHERE group_id=:group_id')->execute(['state' => $state, 'group_id' => $groupId]);
        return $this->requireProvider($groupId);
    }

    public function setProviderVerification(string $groupId, string $state, string $actorUserId, ?string $note = null): array
    {
        $this->requireProvider($groupId);
        $this->assertVerification($state, $actorUserId, $note);
        $this->pdo->prepare('UPDATE healthcare_organization_provider_status
            SET verification_state=:state,verification_actor_user_id=:actor,verification_at=NOW(),verification_note=:note
            WHERE group_id=:group_id')->execute([
                'state' => $state, 'actor' => $actorUserId, 'note' => $this->note($note), 'group_id' => $groupId,
            ]);
        return $this->requireProvider($groupId);
    }

    public function createServiceArea(string $groupId, string $locationUuid, int $studyTypeId, string $scopeType, string $postalCode): array
    {
        $regionKey = $this->regionKey($scopeType, $postalCode);
        $this->validatePostalCatalog($postalCode);
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $offering = $this->requireOffering($groupId, $locationUuid, $studyTypeId, true);
            if (!in_array($offering['service_mode'], ['HOME_SERVICE', 'MOBILE'], true)) {
                throw new InvalidArgumentException('service_area_requires_home_or_mobile_offering');
            }
            $this->pdo->prepare('INSERT INTO healthcare_organization_location_study_service_areas
                (offering_id,scope_type,region_key) VALUES(:offering_id,:scope_type,:region_key)')->execute([
                    'offering_id' => $offering['offering_id'], 'scope_type' => $scopeType, 'region_key' => $regionKey,
                ]);
            $id = (int)$this->pdo->lastInsertId();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (PDOException $e) {
            if ($ownsTransaction) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('service_area_already_exists', 0, $e);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $this->requireArea($groupId, $locationUuid, $studyTypeId, $id);
    }

    public function setServiceAreaOperationalState(string $groupId, string $locationUuid, int $studyTypeId, int $areaId, string $state): array
    {
        $area = $this->requireArea($groupId, $locationUuid, $studyTypeId, $areaId);
        $this->assertOperational($state);
        if ($state === 'ACTIVE' && !in_array($area['service_mode'], ['HOME_SERVICE', 'MOBILE'], true)) {
            throw new InvalidArgumentException('service_area_requires_home_or_mobile_offering');
        }
        $this->updateArea($groupId, $locationUuid, $studyTypeId, $areaId,
            'a.operational_state=:state', ['state' => $state]);
        return $this->requireArea($groupId, $locationUuid, $studyTypeId, $areaId);
    }

    public function setServiceAreaVerification(string $groupId, string $locationUuid, int $studyTypeId, int $areaId,
        string $state, string $actorUserId, ?string $note = null): array
    {
        $this->requireArea($groupId, $locationUuid, $studyTypeId, $areaId);
        $this->assertVerification($state, $actorUserId, $note);
        $this->updateArea($groupId, $locationUuid, $studyTypeId, $areaId,
            'a.verification_state=:state,a.verification_actor_user_id=:actor,a.verification_at=NOW(),a.verification_note=:note',
            ['state' => $state, 'actor' => $actorUserId, 'note' => $this->note($note)]);
        return $this->requireArea($groupId, $locationUuid, $studyTypeId, $areaId);
    }

    private function requireProvider(string $groupId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM healthcare_organization_provider_status WHERE group_id=:group_id');
        $stmt->execute(['group_id' => $groupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('provider_status_not_found');
        }
        return $row;
    }

    private function assertOrganization(string $groupId): void
    {
        if (trim($groupId) === '') {
            throw new InvalidArgumentException('group_id_required');
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM medical_groups WHERE group_id=:group_id');
        $stmt->execute(['group_id' => $groupId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('organization_not_found');
        }
    }

    private function requireOffering(string $groupId, string $locationUuid, int $studyTypeId, bool $lock = false): array
    {
        if ($studyTypeId < 1) {
            throw new InvalidArgumentException('invalid_study_type_id');
        }
        $stmt = $this->pdo->prepare('SELECT o.offering_id,o.service_mode
            FROM healthcare_organization_location_study_offerings o
            JOIN healthcare_organization_locations l ON l.location_id=o.location_id
            WHERE l.group_id=:group_id AND l.location_uuid=:location_uuid AND o.study_type_id=:study_type_id'
            .($lock ? ' FOR UPDATE' : ''));
        $stmt->execute(['group_id' => $groupId, 'location_uuid' => $locationUuid, 'study_type_id' => $studyTypeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('offering_not_found');
        }
        return $row;
    }

    private function requireArea(string $groupId, string $locationUuid, int $studyTypeId, int $areaId): array
    {
        if ($areaId < 1) {
            throw new InvalidArgumentException('invalid_service_area_id');
        }
        $stmt = $this->pdo->prepare('SELECT a.*,o.service_mode
            FROM healthcare_organization_location_study_service_areas a
            JOIN healthcare_organization_location_study_offerings o ON o.offering_id=a.offering_id
            JOIN healthcare_organization_locations l ON l.location_id=o.location_id
            WHERE l.group_id=:group_id AND l.location_uuid=:location_uuid
              AND o.study_type_id=:study_type_id AND a.service_area_id=:area_id');
        $stmt->execute(['group_id' => $groupId, 'location_uuid' => $locationUuid,
            'study_type_id' => $studyTypeId, 'area_id' => $areaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('service_area_not_found');
        }
        return $row;
    }

    private function updateArea(string $groupId, string $locationUuid, int $studyTypeId, int $areaId, string $set, array $fields): void
    {
        $stmt = $this->pdo->prepare('UPDATE healthcare_organization_location_study_service_areas a
            JOIN healthcare_organization_location_study_offerings o ON o.offering_id=a.offering_id
            JOIN healthcare_organization_locations l ON l.location_id=o.location_id
            SET '.$set.' WHERE l.group_id=:group_id AND l.location_uuid=:location_uuid
              AND o.study_type_id=:study_type_id AND a.service_area_id=:area_id');
        $stmt->execute($fields + ['group_id' => $groupId, 'location_uuid' => $locationUuid,
            'study_type_id' => $studyTypeId, 'area_id' => $areaId]);
    }

    private function regionKey(string $scopeType, string $postalCode): string
    {
        if ($scopeType !== 'POSTAL_CODE' || !preg_match('/^[0-9]{5}$/D', $postalCode)) {
            throw new InvalidArgumentException('invalid_region');
        }
        return 'MX|CP|'.$postalCode;
    }

    private function validatePostalCatalog(string $postalCode): void
    {
        $exists = $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema=DATABASE() AND table_name='catalog_cp_colonias'")->fetchColumn();
        if ((int)$exists === 0) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM catalog_cp_colonias WHERE cp=:cp AND is_active=1 LIMIT 1');
        $stmt->execute(['cp' => $postalCode]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('postal_code_not_in_active_catalog');
        }
    }

    private function assertOperational(string $state): void
    {
        if (!in_array($state, self::OPERATIONAL_STATES, true)) {
            throw new InvalidArgumentException('invalid_operational_state');
        }
    }

    private function assertVerification(string $state, string $actorUserId, ?string $note): void
    {
        if (!in_array($state, self::VERIFICATION_STATES, true)
            || trim($actorUserId) === '' || mb_strlen($actorUserId) > 64
            || ($note !== null && mb_strlen($note) > 500)) {
            throw new InvalidArgumentException('invalid_verification_transition');
        }
    }

    private function note(?string $note): ?string
    {
        $value = trim((string)$note);
        return $value === '' ? null : $value;
    }
}
