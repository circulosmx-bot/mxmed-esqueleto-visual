<?php
declare(strict_types=1);
require_once __DIR__.'/../http/CanonicalClinicalIdentityResolver.php';

use Identity\Http\CanonicalDoctorMembershipPolicy;

function canonicalClinicalAssert(bool $condition,string $message):void
{if(!$condition)throw new RuntimeException($message);}

$policy=new CanonicalDoctorMembershipPolicy();
$active=['membership_id'=>'m1','profile_doctor_id'=>'1','entity_group_id'=>null,
    'role_code'=>'collaborator','scope_code'=>'profile','status'=>'active'];
canonicalClinicalAssert($policy->select([$active])['membership']===$active,'one valid membership selected');
canonicalClinicalAssert($policy->select([])['reason']==='membership_missing','missing membership denied');
canonicalClinicalAssert($policy->select([$active,array_replace($active,['membership_id'=>'m2','profile_doctor_id'=>'2'])])['reason']==='membership_ambiguous','multiple memberships denied');
canonicalClinicalAssert($policy->select([array_replace($active,['status'=>'revoked'])])['reason']==='membership_invalid','revoked membership denied');
canonicalClinicalAssert($policy->select([array_replace($active,['scope_code'=>'organization'])])['reason']==='membership_invalid','wrong scope denied');
canonicalClinicalAssert($policy->select([array_replace($active,['entity_group_id'=>'group1'])])['reason']==='membership_invalid','mixed target denied');
echo "CanonicalClinicalIdentityResolverTest PASS\n";
