<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;

use Identity\Contracts\CaseScopedProfileOwnershipAuthorityPort;
use Identity\Repositories\InternalOperatorGrantRepository;
use PDO;

/** Injected only into the exact approved application composition. It cannot
 * authorize another initial owner, an ownership transfer or platform recovery. */
final class RequestOwnershipApproverAuthority implements CaseScopedProfileOwnershipAuthorityPort
{
    public function __construct(private PDO $pdo,private string $originId){}
    private function current():array
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('request_ownership_transaction_required');
        $q=$this->pdo->prepare('SELECT account_id,intake_version,initial_origin,original_profile_doctor_id FROM auth_physician_enrollment_origins WHERE origin_id=? FOR UPDATE');$q->execute([$this->originId]);$origin=$q->fetch(PDO::FETCH_ASSOC);
        $q=$this->pdo->prepare('SELECT * FROM auth_physician_profile_request_decisions WHERE origin_id=? ORDER BY resolution_version DESC LIMIT 1 FOR UPDATE');$q->execute([$this->originId]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($origin)||!is_array($r)||$r['state']!=='APPROVED'||$r['account_id']!==$origin['account_id']
            ||(int)$r['intake_version']!==(int)$origin['intake_version']||!is_string($r['profile_doctor_id'])||$r['profile_doctor_id']==='')throw new \RuntimeException('request_ownership_approved_request_required');
        if($r['request_type']==='CLAIM_EXISTING_PROFILE'){
            if($origin['initial_origin']!=='CLAIM_EXISTING_PROFILE'||$origin['original_profile_doctor_id']!==$r['profile_doctor_id'])throw new \RuntimeException('request_ownership_target_mismatch');
        }elseif($r['request_type']==='REQUEST_NEW_PROFILE'){
            $q=$this->pdo->prepare('SELECT account_id,profile_doctor_id,resolution_id FROM auth_physician_profile_request_profiles WHERE origin_id=? FOR UPDATE');$q->execute([$this->originId]);$link=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($link)||$link['account_id']!==$r['account_id']||$link['profile_doctor_id']!==$r['profile_doctor_id']||$link['resolution_id']!==$r['resolution_id'])throw new \RuntimeException('request_ownership_materialization_required');
        }else throw new \RuntimeException('request_ownership_type_denied');
        $q=$this->pdo->prepare('SELECT * FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$q->execute([$r['profile_doctor_id']]);$profile=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($profile))throw new \RuntimeException('request_ownership_profile_missing');
        require_once __DIR__.'/../../subscriptions/services/PublicCommercialPlanSource.php';
        if((new \Subscriptions\Services\PublicCommercialPlanSource($this->pdo))->resolve($profile)['plan_code']!=='free')throw new \RuntimeException('request_ownership_free_plan_required');
        return $r;
    }
    public function binding():array
    {
        $r=$this->current();return ['origin_id'=>$this->originId,'resolution_id'=>$r['resolution_id'],'intake_version'=>(int)$r['intake_version'],
            'request_type'=>$r['request_type'],'account_id'=>$r['account_id'],'profile_doctor_id'=>$r['profile_doctor_id'],'capability'=>$this->capability($r)];
    }
    private function capability(array $r):string{return $r['request_type']==='CLAIM_EXISTING_PROFILE'?RequestAuthority::CLAIM:RequestAuthority::NEW_PROFILE;}
    public function approvalCapability():string{return $this->capability($this->current());}
    public function mfaOperation():string{return $this->approvalCapability()===RequestAuthority::CLAIM?'PHYSICIAN_CLAIM_RESOLVE':'PHYSICIAN_NEW_PROFILE_RESOLVE';}
    public function assertAuthorized(PDO $transaction,string $approverAccountId,string $targetAccountId,string $profileDoctorId,int $credentialVersion):void
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('request_ownership_transaction_required');
        $r=$this->current();
        if($approverAccountId===$targetAccountId||$r['account_id']!==$targetAccountId||$r['profile_doctor_id']!==$profileDoctorId
            ||$r['reviewer_account_id']!==$approverAccountId||(int)$r['reviewer_credential_version']!==$credentialVersion)throw new \RuntimeException('request_ownership_actor_mismatch');
        $q=$this->pdo->prepare("SELECT a.account_id FROM auth_accounts a JOIN auth_account_credentials c ON c.account_id=a.account_id
            JOIN internal_staff s ON s.account_id=a.account_id WHERE a.account_id=? AND a.status='active' AND a.email_verified_at IS NOT NULL
            AND c.credential_version=? AND s.status='ACTIVE' AND s.governance_class IN ('DIRECTOR','MASTER_ADMIN','ADVISOR') FOR UPDATE");
        $q->execute([$approverAccountId,$credentialVersion]);if($q->fetchColumn()===false)throw new \RuntimeException('request_ownership_actor_denied');
        $grants=(new InternalOperatorGrantRepository($this->pdo))->activeCapabilities($approverAccountId,true);
        if(!$grants->contains(RequestAuthority::READ)||!$grants->contains($this->capability($r)))throw new \RuntimeException('request_ownership_capability_denied');
        $q=$this->pdo->prepare("SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active' FOR UPDATE");$q->execute([$profileDoctorId]);
        if($q->fetchColumn()!==false)throw new \RuntimeException('request_ownership_existing_owner');
    }
    private function exact(array $case):array
    {
        $binding=$this->binding();$context=json_decode((string)($case['security_context']??''),true);
        $stored=$context['physician_request']??null;$exact=is_array($stored)&&count($stored)===count($binding);
        foreach($binding as $key=>$value)if(($stored[$key]??null)!==$value)$exact=false;
        if(($case['operation']??null)!=='INITIAL_OWNER'||($case['beneficiary_account_id']??null)!==$binding['account_id']
            ||($case['profile_doctor_id']??null)!==$binding['profile_doctor_id']||!$exact)
            throw new \RuntimeException('request_ownership_case_mismatch');
        return $binding;
    }
    public function bindCase(array $case):void
    {
        $b=$this->exact($case);$r=$this->current();
        if($case['requester_account_id']!==$r['reviewer_account_id'])throw new \RuntimeException('request_ownership_requester_mismatch');
        $q=$this->pdo->prepare('SELECT l.link_version,c.status FROM auth_physician_profile_request_ownership l JOIN auth_governance_cases c ON c.case_id=l.case_id WHERE l.origin_id=? ORDER BY l.link_version DESC LIMIT 1 FOR UPDATE');$q->execute([$this->originId]);$prior=$q->fetch(PDO::FETCH_ASSOC);
        if(is_array($prior)&&!in_array($prior['status'],['EXPIRED','REVOKED'],true))throw new \RuntimeException('request_ownership_live_case_conflict');
        $q=$this->pdo->prepare('INSERT INTO auth_physician_profile_request_ownership(origin_id,link_version,resolution_id,case_id,membership_id,created_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))');
        $q->execute([$this->originId,(int)($prior['link_version']??0)+1,$b['resolution_id'],$case['case_id'],$case['target_membership_id']]);
    }
    public function assertCase(array $case):void
    {
        $b=$this->exact($case);$q=$this->pdo->prepare('SELECT resolution_id,case_id,membership_id FROM auth_physician_profile_request_ownership WHERE origin_id=? ORDER BY link_version DESC LIMIT 1 FOR UPDATE');$q->execute([$this->originId]);$link=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($link)||$link['resolution_id']!==$b['resolution_id']||$link['case_id']!==($case['case_id']??null)||$link['membership_id']!==($case['target_membership_id']??null))throw new \RuntimeException('request_ownership_case_link_required');
    }
}
