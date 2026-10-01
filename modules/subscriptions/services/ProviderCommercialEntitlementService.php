<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use Agenda\Services\ProviderCommercialEntitlementPort;
use PDO;

require_once __DIR__.'/../../agenda/services/HealthcareOrganizationManagementAuthorizationService.php';

/** Organization-level commercial product decision; never a clinical authorization. */
final class ProviderCommercialEntitlementService implements ProviderCommercialEntitlementPort
{
    public const ENTITY_TYPE='provider_organization';
    public const MATCHING='provider_matching_participation';
    public const PUBLIC_PROFILE='provider_public_profile_publish';
    private const CAPABILITIES=[
        'provider_profile_manage','provider_locations_manage','provider_offerings_manage',
        'provider_service_areas_manage',self::MATCHING,self::PUBLIC_PROFILE,
    ];

    public function __construct(private PDO $pdo) {}

    public function isEntitled(string $accountId,string $groupId,string $action): bool
    {
        return $this->hasCapability($groupId,$action);
    }

    public function hasCapability(string $groupId,string $capability): bool
    {
        if ($groupId==='' || !in_array($capability,self::CAPABILITIES,true)) return false;
        try {
            $stmt=$this->pdo->prepare('SELECT 1 FROM healthcare_provider_commercial_active_capabilities
                WHERE group_id=? AND capability=? LIMIT 1');
            $stmt->execute([$groupId,$capability]);
            return $stmt->fetchColumn()!==false;
        } catch (\Throwable) {
            return false;
        }
    }
}
