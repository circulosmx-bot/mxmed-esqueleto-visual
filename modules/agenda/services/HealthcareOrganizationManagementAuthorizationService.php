<?php
declare(strict_types=1);

namespace Agenda\Services;

use Agenda\Contracts\HealthcareOrganizationType;
use Identity\Contracts\AuthenticatedAccessContext;
use PDO;

require_once __DIR__.'/../contracts/HealthcareOrganizationType.php';

/** Future trusted subscription decision boundary; no implementation is wired in PROV04C. */
interface ProviderCommercialEntitlementPort
{
    public function isEntitled(string $accountId,string $groupId,string $action): bool;
}

/** Server-side administrative policy. This grants no clinical or governance capability. */
final class HealthcareOrganizationManagementAuthorizationService
{
    public const TEAM = 'provider_team_manage';
    public const PROFILE = 'provider_profile_manage';
    public const LOCATIONS = 'provider_locations_manage';
    public const OFFERINGS = 'provider_offerings_manage';
    public const SERVICE_AREAS = 'provider_service_areas_manage';

    private const ACTIONS = [self::TEAM,self::PROFILE,self::LOCATIONS,self::OFFERINGS,self::SERVICE_AREAS];
    private const PROVIDER_TYPES = [
        HealthcareOrganizationType::LABORATORY,HealthcareOrganizationType::DIAGNOSTIC_CENTER,
        HealthcareOrganizationType::CLINIC,HealthcareOrganizationType::HOSPITAL,
        HealthcareOrganizationType::DENTAL_ORGANIZATION,HealthcareOrganizationType::OTHER_HEALTHCARE_ORGANIZATION,
    ];

    public function __construct(private PDO $pdo,private ?ProviderCommercialEntitlementPort $entitlements=null) {}

    /**
     * A commercial action returns eligible=true but allowed=false unless a trusted
     * provider-entitlement authority is wired in a later phase and grants it.
     * Callers must use allowed, never eligible, as their final write gate.
     * Resource IDs: group_id, location_uuid, offering_id, service_area_id.
     */
    public function evaluate(?AuthenticatedAccessContext $actor, string $groupId, string $action,
        string $resourceType = 'organization', string|int|null $resourceId = null): array
    {
        $denied = ['eligible'=>false,'allowed'=>false,'entitlement_required'=>$action!==self::TEAM,
            'entitlement_satisfied'=>false,'role'=>null,'reason'=>'DENIED'];
        if (!in_array($action,self::ACTIONS,true) || $groupId==='') return $denied;
        if ($actor===null || $actor->principal()->accountStatus()!=='active'
            || $actor->session()->state()!=='active'
            || $actor->session()->expiresAt()<=new \DateTimeImmutable('now')
            || $actor->session()->principal()->accountId()!==$actor->accountId()) return $denied;
        try {
            $account=$this->pdo->prepare("SELECT 1 FROM auth_accounts WHERE account_id=? AND status='active' AND email_verified_at IS NOT NULL");
            $account->execute([$actor->accountId()]);
            if (!$account->fetchColumn()) return $denied;
            $group=$this->pdo->prepare('SELECT organization_type_key,status FROM medical_groups WHERE group_id=?');
            $group->execute([$groupId]);
            $org=$group->fetch(PDO::FETCH_ASSOC);
            if (!is_array($org) || !in_array($org['organization_type_key'],self::PROVIDER_TYPES,true)
                || !in_array($org['status'],['pending','verified'],true)
                || !$this->resourceBelongsTo($groupId,$resourceType,$resourceId)) return $denied;
            $member=$this->pdo->prepare("SELECT role_code,assignment_source FROM auth_account_memberships
                WHERE account_id=? AND entity_group_id=? AND profile_doctor_id IS NULL
                AND scope_code='organization' AND status='active' LIMIT 2");
            $member->execute([$actor->accountId(),$groupId]);
            $rows=$member->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows)!==1) return $denied;
            $role=$rows[0]['role_code'];
            // The initial owner is established only by governed PROV04B approval.
            if ($role==='owner' && $rows[0]['assignment_source']!=='provider_claim_approval') return $denied;
            if ($role==='administrator' && $rows[0]['assignment_source']!=='provider_invitation_acceptance') return $denied;
            if ($role==='collaborator' && $rows[0]['assignment_source']!=='provider_invitation_acceptance') return $denied;
            $eligible=$role==='owner' || ($role==='administrator' && $action!==self::TEAM);
            if (!$eligible) return array_replace($denied,['role'=>$role]);
            $gated=$action!==self::TEAM;
            $entitled=$gated && $this->entitlements!==null
                && $this->entitlements->isEntitled($actor->accountId(),$groupId,$action);
            return ['eligible'=>true,'allowed'=>!$gated || $entitled,'entitlement_required'=>$gated,
                'entitlement_satisfied'=>!$gated || $entitled,'role'=>$role,
                'reason'=>$gated && !$entitled?'COMMERCIAL_ENTITLEMENT_NOT_AVAILABLE':'ALLOWED'];
        } catch (\Throwable) {
            return $denied;
        }
    }

    private function resourceBelongsTo(string $groupId,string $type,string|int|null $id): bool
    {
        if ($type==='organization') return $id===null || (string)$id===$groupId;
        if ($id===null || (string)$id==='') return false;
        $sql=match ($type) {
            'location' => 'SELECT 1 FROM healthcare_organization_locations l WHERE l.location_uuid=:id AND l.group_id=:group_id',
            'offering' => 'SELECT 1 FROM healthcare_organization_location_study_offerings o JOIN healthcare_organization_locations l ON l.location_id=o.location_id WHERE o.offering_id=:id AND l.group_id=:group_id',
            'service_area' => 'SELECT 1 FROM healthcare_organization_location_study_service_areas a JOIN healthcare_organization_location_study_offerings o ON o.offering_id=a.offering_id JOIN healthcare_organization_locations l ON l.location_id=o.location_id WHERE a.service_area_id=:id AND l.group_id=:group_id',
            default => null,
        };
        if ($sql===null) return false;
        $stmt=$this->pdo->prepare($sql);
        $stmt->execute(['id'=>(string)$id,'group_id'=>$groupId]);
        return $stmt->fetchColumn()!==false;
    }
}
