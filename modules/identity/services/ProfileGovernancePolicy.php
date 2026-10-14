<?php
declare(strict_types=1);

namespace Identity\Services;

use Platform\Contracts\{AuthorizationPlane,AuthorizationRequirement,CapabilitySet,RiskLevel};

/** DEC-011A/B/C policy projection. It grants nothing and has no runtime route. */
final class ProfileGovernancePolicy
{
    public static function firstDirectorBootstrap(string $targetAccountId):AuthorizationRequirement
    {
        return new AuthorizationRequirement(AuthorizationPlane::GOVERNANCE_EMERGENCY,RiskLevel::R3,
            'bootstrap_first_director','internal_staff',$targetAccountId,true,true,false,false,false,[],
            new \Platform\Contracts\ScopeSet(),new CapabilitySet(),true,true,true,true,true,true);
    }

    public static function initialOwnershipApproval(string $profileDoctorId):AuthorizationRequirement
    {
        return new AuthorizationRequirement(AuthorizationPlane::INTERNAL_OPERATOR,RiskLevel::R3,
            'approve_initial_ownership','profile_doctor',$profileDoctorId,true,true,false,true,false,[],
            new \Platform\Contracts\ScopeSet(),new CapabilitySet([InternalCapabilityCatalog::APPROVE_INITIAL_PROFILE_OWNERSHIP]),
            true,true,true,true,true,true);
    }

    public static function assistedProfileAdministration(string $action,string $profileDoctorId):AuthorizationRequirement
    {
        $capability=match($action){
            'create'=>InternalCapabilityCatalog::CREATE_PHYSICIAN_PROFILE,
            'curate'=>InternalCapabilityCatalog::CURATE_PHYSICIAN_PROFILE,
            'publication'=>InternalCapabilityCatalog::MANAGE_PROFILE_PUBLICATION,
            'assist'=>InternalCapabilityCatalog::ASSIST_PROFILE_OWNER,
            default=>throw new \InvalidArgumentException('unknown_assisted_profile_action'),
        };
        return new AuthorizationRequirement(AuthorizationPlane::INTERNAL_OPERATOR,RiskLevel::R2,
            'profile_'.$action,'profile_doctor',$profileDoctorId,true,true,false,true,false,[],
            new \Platform\Contracts\ScopeSet(),new CapabilitySet([$capability]),true,false,true,false,false,true);
    }
}
