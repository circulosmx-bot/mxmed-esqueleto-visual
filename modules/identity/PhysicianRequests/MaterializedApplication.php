<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;
use PDO;
/** Exact approved new-profile draft permits independent C4 source review without publication. */
final class MaterializedApplication
{
    public static function allows(PDO $pdo,array $origin,string $profile):bool
    {
        if(!$pdo->inTransaction())return false;
        // Old deployments have no extension: retain their closed behavior.
        $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_physician_profile_request_profiles'");$q->execute();if((int)$q->fetchColumn()!==1)return false;
        if($origin['initial_origin']!=='REQUEST_NEW_PROFILE'||$origin['original_profile_doctor_id']!==null)return false;
        $q=$pdo->prepare("SELECT d.resolution_id FROM auth_physician_profile_request_profiles l
          JOIN auth_physician_profile_request_decisions d ON d.resolution_id=l.resolution_id
          JOIN profiles_doctors p ON p.doctor_id=l.profile_doctor_id
          WHERE l.origin_id=? AND l.account_id=? AND l.profile_doctor_id=? AND d.state='APPROVED'
          AND d.account_id=l.account_id AND d.origin_id=l.origin_id AND d.profile_doctor_id=l.profile_doctor_id
          AND d.intake_version=? AND LOWER(p.plan_code)='free' AND p.profile_status='hidden' AND p.is_public_candidate=0
          AND NOT EXISTS(SELECT 1 FROM auth_physician_profile_request_decisions newer WHERE newer.origin_id=d.origin_id AND newer.resolution_version>d.resolution_version)
          AND NOT EXISTS(SELECT 1 FROM auth_account_memberships m WHERE m.profile_doctor_id=p.doctor_id AND m.role_code='owner' AND m.status='active') FOR UPDATE");
        $q->execute([$origin['origin_id'],$origin['account_id'],$profile,$origin['intake_version']]);return $q->fetchColumn()!==false;
    }
}
