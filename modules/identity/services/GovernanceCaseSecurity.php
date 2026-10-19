<?php
declare(strict_types=1);
namespace Identity\Services;
use Identity\Contracts\{AuthenticatedAccessContext,CaseScopedProfileOwnershipAuthorityPort,InitialOwnerEligibilityPort,ProfileOwnershipApproverAuthorityPort};
use Identity\Adapters\{Ed25519ExternalTrustAnchorAdapter,PdoProfileOwnershipApproverAuthorityAdapter,CanonicalGovernanceAuditAdapter};
use PDO;

/** Versioned bindings on the existing R3 case; never another membership writer. */
final class GovernanceCaseSecurity
{
    public function __construct(private PDO $pdo,public readonly GovernanceSecurityContext $sessions,
        private InitialOwnerEligibilityPort $eligibility,public readonly ?Ed25519ExternalTrustAnchorAdapter $trust,
        public readonly CanonicalGovernanceAuditAdapter $audit,private ?ProfileOwnershipApproverAuthorityPort $ownershipAuthority=null) {}
    private function authority():ProfileOwnershipApproverAuthorityPort{return $this->ownershipAuthority??new PdoProfileOwnershipApproverAuthorityAdapter($this->pdo);}
    /** Separate closed staff variant; does not relax the ownership v2 contract below. */
    public static function staff(PDO $pdo,GovernanceSecurityContext $security,StaffMfaService $mfa,
        \Identity\Contracts\StaffIdentityReviewPort $reviews,\Identity\Contracts\Clock $clock): \Identity\MasterGovernance\Cases
    {return new \Identity\MasterGovernance\Cases($pdo,$security,$mfa,$reviews,$clock);}

    public function usesConnection(PDO $pdo):bool{return $pdo===$this->pdo;}

    public static function snapshot(array $m):array
    {
        $out=[];
        foreach(['membership_id','account_id','profile_doctor_id','entity_group_id','role_code','scope_code','status','assignment_source','updated_at'] as $k)$out[$k]=$m[$k]??null;
        return $out;
    }
    public static function version(array $m):string{return hash('sha256',json_encode(self::snapshot($m),JSON_THROW_ON_ERROR));}

    public function capture(AuthenticatedAccessContext $requester,array $membership,?array $replacement=null):array
    {
        foreach([$membership,$replacement] as $m){if($m===null)continue;
            if(($m['entity_group_id']??null)!==null||!in_array($m['scope_code'],['profile','profile_doctor'],true)
                ||$m['status']!=='active')throw new \RuntimeException('governance_profile_membership_required');}
        $binding=$this->sessions->binding($requester);
        $beneficiary=$replacement['account_id']??$membership['account_id'];
        $this->activeBeneficiary($beneficiary);
        if(in_array($requester->accountId(),[$membership['account_id'],$beneficiary],true))throw new \RuntimeException('governance_requester_independence_required');
        $this->authority()->assertAuthorized($this->pdo,$requester->accountId(),$beneficiary,
            $membership['profile_doctor_id'],$requester->principal()->credentialVersion());
        $this->eligibility->assertEligible($this->pdo,$beneficiary,$membership['profile_doctor_id']);
        return ['version'=>2,'requester'=>$binding,'membership'=>self::snapshot($membership),
            'replacement'=>$replacement===null?null:self::snapshot($replacement),'trust_anchor_reference'=>null];
    }

    public function attach(AuthenticatedAccessContext $requester,array $case,string $envelope):array
    {
        if($case['requester_account_id']!==$requester->accountId()||$case['status']!=='OPEN')throw new \RuntimeException('governance_external_evidence_denied');
        $this->sessions->binding($requester);
        $context=$this->context($case);
        $this->activeBeneficiary($case['beneficiary_account_id']);
        $this->sessions->assertLive($context['requester']);
        $this->authority()->assertAuthorized($this->pdo,$requester->accountId(),$case['beneficiary_account_id'],
            $case['profile_doctor_id']??'staff-mfa-recovery',$requester->principal()->credentialVersion());
        if($context['trust_anchor_reference']!==null)throw new \RuntimeException('governance_external_evidence_already_bound');
        if($this->trust===null)throw new \RuntimeException('governance_external_trust_required');
        $context['trust_anchor_reference']=$this->trust->stage($envelope,$this->expected($case,$context));
        return $context;
    }

    public function assertIndependentApprover(array $case,string $actor):void
    {
        $context=$this->context($case);
        if(in_array($actor,[PhysicianOwnershipPolicy::routine($case)?null:$case['requester_account_id'],$case['beneficiary_account_id'],$context['membership']['account_id']??null],true))throw new \RuntimeException('governance_approver_conflict');
    }
    private function activeBeneficiary(string $account):void
    {
        $q=$this->pdo->prepare("SELECT a.account_id FROM auth_accounts a JOIN auth_account_credentials c ON c.account_id=a.account_id WHERE a.account_id=? AND a.status='active' AND a.email_verified_at IS NOT NULL AND c.credential_version>=1 FOR UPDATE");$q->execute([$account]);
        if($q->fetchColumn()===false)throw new \RuntimeException('governance_beneficiary_ineligible');
    }

    public function assertCase(array $case,bool $consume=false):void
    {
        $this->sessions->assertUnexpired($case['expires_at']);
        if($this->ownershipAuthority instanceof CaseScopedProfileOwnershipAuthorityPort)$this->ownershipAuthority->assertCase($case);
        $context=$this->context($case);
        $this->activeBeneficiary($case['beneficiary_account_id']);
        $this->sessions->assertLive($context['requester']);
        $this->authority()->assertAuthorized($this->pdo,$case['requester_account_id'],
            $case['beneficiary_account_id'],$case['profile_doctor_id']??'staff-mfa-recovery',$context['requester']['credential_version']);
        foreach(['membership','replacement'] as $k){
            $snapshot=$context[$k]??null;if($snapshot===null)continue;
            $s=$this->pdo->prepare('SELECT * FROM auth_account_memberships WHERE membership_id=? FOR UPDATE');$s->execute([$snapshot['membership_id']]);$current=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($current)||self::snapshot($current)!==self::snapshot($snapshot))throw new \RuntimeException('governance_approved_membership_version_changed');
        }
        if($case['operation']!=='MFA_RECOVERY')$this->eligibility->assertEligible($this->pdo,$case['beneficiary_account_id'],$case['profile_doctor_id']);
        if($case['status']==='APPROVED')$this->assertEndorsements($case,$context);
        if(PhysicianOwnershipPolicy::routine($case))return;
        if(!is_string($context['trust_anchor_reference']??null))throw new \RuntimeException('governance_external_evidence_required');
        if($this->trust===null)throw new \RuntimeException('governance_external_trust_required');
        $this->trust->assertCase($this->expected($case,$context)+['trust_anchor_reference'=>$context['trust_anchor_reference']],$consume);
    }

    private function assertEndorsements(array $case,array $context):void
    {
        $s=$this->pdo->prepare("SELECT e.approver_account_id,e.staff_session_id endorsement_session,e.credential_version endorsement_version,p.*,c.reauthentication_context,f.status factor_status
            FROM auth_governance_case_endorsements e JOIN auth_staff_mfa_proofs p ON p.proof_id=e.mfa_proof_id
            JOIN auth_staff_mfa_challenges c ON c.challenge_id=p.challenge_id JOIN auth_staff_mfa_factors f ON f.factor_id=p.factor_id
            WHERE e.case_id=? AND e.status='APPROVED' ORDER BY e.endorsement_slot FOR UPDATE");
        $s->execute([$case['case_id']]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);$ids=[];
        $required=PhysicianOwnershipPolicy::requiredApprovals($case);
        if(count($rows)!==$required)throw new \RuntimeException('governance_approval_count_required');
        foreach($rows as $row){
            $id=$row['approver_account_id'];$ids[]=$id;
            $mfaOperation=$this->ownershipAuthority instanceof CaseScopedProfileOwnershipAuthorityPort?$this->ownershipAuthority->mfaOperation():$case['operation'];
            if(in_array($id,[PhysicianOwnershipPolicy::routine($case)?null:$case['requester_account_id'],$case['beneficiary_account_id'],$context['membership']['account_id']??null],true)
                ||$row['staff_session_id']!==$row['endorsement_session']||(int)$row['credential_version']!==(int)$row['endorsement_version']||$row['consumed_by_reference']!==$case['case_id']||$row['actor_account_id']!==$id||$row['operation']!==$mfaOperation||$row['case_id']!==$case['case_id']
                ||$row['target_account_id']!==$case['beneficiary_account_id']||$row['beneficiary_account_id']!==$case['beneficiary_account_id']
                ||$row['profile_doctor_id']!==$case['profile_doctor_id']||$row['status']!=='CONSUMED'||$row['factor_status']!=='ACTIVE')throw new \RuntimeException('governance_approval_binding_denied');
            $binding=json_decode((string)$row['reauthentication_context'],true,32,JSON_THROW_ON_ERROR);
            if(($binding['account_id']??null)!==$id||($binding['session_id']??null)!==$row['staff_session_id']
                ||($binding['credential_version']??null)!==(int)$row['credential_version'])throw new \RuntimeException('governance_approval_session_mismatch');
            $this->sessions->assertLive($binding,true);
            $this->authority()->assertAuthorized($this->pdo,$id,$case['beneficiary_account_id'],
                $case['profile_doctor_id']??'staff-mfa-recovery',(int)$row['credential_version']);
        }
        if(count(array_unique($ids))!==$required)throw new \RuntimeException('governance_approver_independence_required');
    }

    public function assertOperation(string $operationId,bool $consume):void
    {
        $s=$this->pdo->prepare('SELECT * FROM auth_governance_cases WHERE operation_id=? FOR UPDATE');$s->execute([$operationId]);$case=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($case)||$case['status']!=='APPROVED')throw new \RuntimeException('governance_case_unavailable');
        $this->assertCase($case,$consume);
    }

    public function assertSeatLimit(string $profile):void
    {
        $exists=(int)$this->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_profile_operator_invitations'")->fetchColumn();
        if($exists===0)return;
        $s=$this->pdo->prepare("SELECT (SELECT COUNT(DISTINCT m.account_id) FROM auth_account_memberships m
            WHERE m.profile_doctor_id=? AND m.entity_group_id IS NULL AND m.status IN ('pending','active','suspended')
            AND NOT EXISTS (SELECT 1 FROM auth_account_memberships o WHERE o.account_id=m.account_id AND o.profile_doctor_id=m.profile_doctor_id AND o.role_code='owner' AND o.status='active'))
            +(SELECT COUNT(*) FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='pending' AND expires_at>UTC_TIMESTAMP(6))");
        $s->execute([$profile,$profile]);if((int)$s->fetchColumn()>2)throw new \RuntimeException('governance_operator_two_seat_limit');
    }

    public function context(array $case):array
    {
        $context=json_decode((string)($case['security_context']??''),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($context)||($context['version']??null)!==2||($context['requester']['account_id']??null)!==$case['requester_account_id']
            ||$case['requester_account_id']===$case['beneficiary_account_id'])throw new \RuntimeException('governance_v2_case_required');
        return $context;
    }
    private function expected(array $case,array $context):array
    {
        return ['contract_version'=>2,'operation'=>$case['operation'],'operation_id'=>$case['operation_id'],
            'case_reference'=>$case['case_id'],'target_account_id'=>$case['beneficiary_account_id'],
            'profile_doctor_id'=>$case['profile_doctor_id'],'membership_id'=>$case['target_membership_id'],
            'membership_version'=>isset($context['membership'])?self::version($context['membership']):null,
            'requester_account_id'=>$case['requester_account_id']];
    }
}
