<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;

use Identity\Accreditation\ProtectedPhysicianEvidencePort;
use Identity\Adapters\{CanonicalGovernanceAuditAdapter,CanonicalInitialOwnerEligibilityAdapter,CanonicalProfileMembershipRoleChangeAuditAdapter,PdoGovernanceCaseFinalizationAdapter,PdoProfileOwnershipApprovalAdapter,PdoProfileOwnershipStepUpAdapter};
use Identity\Contracts\{AccountMembership,AuthenticatedAccessContext,CanonicalProfileReference,Clock,ProfileOperatorOwnershipBoundary,SystemClock};
use Identity\Http\IdentityHttpComposition;
use Identity\Repositories\{AccountMembershipRepository,ProfessionalAccountBindingProjection,ProfileOwnershipStepUpRepository};
use Identity\Services\{GovernanceCaseSecurity,GovernanceRuntime,GovernanceSecurityContext,ProfileMembershipRoleChangeService,ProfileOwnershipStepUpIssuanceService,R3ProfileGovernanceService,StaffMfaService};
use PDO;

/** Exact request adapter around the canonical ownership lifecycle. Approval is
 * separate from C4 eligibility and the applicant's own acceptance/step-up. */
final class RequestOwnershipService
{
    private GovernanceRuntime $runtime;
    private PDO $pdo;
    private CanonicalInitialOwnerEligibilityAdapter $eligibility;
    public function __construct(IdentityHttpComposition $identity,private GovernanceSecurityContext $security,StaffMfaService $mfa,
        ProtectedPhysicianEvidencePort $evidence,private string $originId,?ProfileOperatorOwnershipBoundary $operators=null,private Clock $clock=new SystemClock())
    {
        $this->pdo=$identity->pdo();$p=$this->pdo;$authority=new RequestOwnershipApproverAuthority($p,$originId);
        $eligibility=new CanonicalInitialOwnerEligibilityAdapter(new ProfessionalAccountBindingProjection($p,$clock,evidence:$evidence));
        $this->eligibility=$eligibility;
        $bindings=new GovernanceCaseSecurity($p,$security,$eligibility,null,new CanonicalGovernanceAuditAdapter($p),$authority);
        $cases=new R3ProfileGovernanceService($p,$mfa,$authority,$clock,operators:$operators,security:$bindings);
        $proofs=new ProfileOwnershipStepUpRepository($p);
        $stepup=new ProfileOwnershipStepUpIssuanceService($p,$identity->authentication(),$proofs,$clock,scopedAuthority:$authority);
        $roles=new ProfileMembershipRoleChangeService($p,new AccountMembershipRepository($p),new PdoProfileOwnershipApprovalAdapter($p,$authority,$clock),
            new PdoProfileOwnershipStepUpAdapter($p,$proofs,$clock),new CanonicalProfileMembershipRoleChangeAuditAdapter($p),$clock,
            finalization:new PdoGovernanceCaseFinalizationAdapter($p,$clock),operators:$operators,eligibility:$eligibility,security:$bindings);
        $this->runtime=new GovernanceRuntime($p,$cases,$mfa,$stepup,$roles,$clock);
    }
    private function application():array
    {
        $q=$this->pdo->prepare('SELECT * FROM auth_physician_profile_request_decisions WHERE origin_id=? ORDER BY resolution_version DESC LIMIT 1');$q->execute([$this->originId]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['state']!=='APPROVED')throw new \RuntimeException('request_ownership_approved_request_required');return $r;
    }
    private function reviewer(AuthenticatedAccessContext $actor):array
    {
        $this->security->binding($actor);$r=$this->application();
        (new RequestAuthority($this->pdo))->assert($actor,$r['request_type']==='CLAIM_EXISTING_PROFILE'?RequestAuthority::CLAIM:RequestAuthority::NEW_PROFILE,$r['account_id']);
        if($r['reviewer_account_id']!==$actor->accountId())throw new \RuntimeException('request_ownership_reviewer_required');return $r;
    }
    private function applicant(AuthenticatedAccessContext $actor):array
    {
        $this->security->binding($actor);$r=$this->application();if($r['account_id']!==$actor->accountId())throw new \RuntimeException('request_ownership_applicant_required');return $r;
    }
    private function caseId():?string
    {
        $q=$this->pdo->prepare('SELECT case_id FROM auth_physician_profile_request_ownership WHERE origin_id=? ORDER BY link_version DESC LIMIT 1');$q->execute([$this->originId]);$id=$q->fetchColumn();return is_string($id)?$id:null;
    }
    /** False only for a confirmed expired/revoked binding; storage failures propagate. */
    private function reusable(array $case):bool
    {
        if(!in_array($case['status'],['OPEN','APPROVED'],true)||new \DateTimeImmutable($case['expires_at'],new \DateTimeZone('UTC'))<=$this->clock->now())return false;
        $context=json_decode($case['security_context'],true,32,JSON_THROW_ON_ERROR);
        try{
            $this->security->assertLive($context['requester']);
            if($case['status']==='APPROVED'){
                $q=$this->pdo->prepare("SELECT expires_at FROM auth_profile_ownership_approvals WHERE operation_id=? AND status='APPROVED'");$q->execute([$case['operation_id']]);$expires=$q->fetchColumn();
                if(!is_string($expires)||new \DateTimeImmutable($expires,new \DateTimeZone('UTC'))<=$this->clock->now())return false;
                $q=$this->pdo->prepare("SELECT c.reauthentication_context FROM auth_governance_case_endorsements e JOIN auth_staff_mfa_proofs p ON p.proof_id=e.mfa_proof_id JOIN auth_staff_mfa_challenges c ON c.challenge_id=p.challenge_id WHERE e.case_id=? AND e.status='APPROVED'");$q->execute([$case['case_id']]);$bindings=$q->fetchAll(PDO::FETCH_COLUMN);
                if(count($bindings)!==1)return false;foreach($bindings as $raw)$this->security->assertLive(json_decode($raw,true,32,JSON_THROW_ON_ERROR),true);
            }
        }catch(\RuntimeException $e){if(in_array($e->getMessage(),['governance_session_invalidated','governance_reauthentication_expired'],true))return false;throw $e;}
        return true;
    }
    /** First physician membership, accepted by the exact legitimate applicant.
     * Never creates an internal staff member or an Advisor collaborator seat. */
    public function prepareMembership(AuthenticatedAccessContext $applicant):array
    {
        $this->applicant($applicant);$binding=$this->security->binding($applicant);
        if($this->pdo->inTransaction())throw new \RuntimeException('request_ownership_outer_transaction');
        try{
            $this->pdo->beginTransaction();
            $q=$this->pdo->prepare('SELECT * FROM auth_physician_enrollment_origins WHERE origin_id=? FOR UPDATE');$q->execute([$this->originId]);$origin=$q->fetch(PDO::FETCH_ASSOC);
            $r=$this->application();
            if(!is_array($origin)||$r['account_id']!==$applicant->accountId()||(int)$origin['intake_version']!==(int)$r['intake_version'])throw new \RuntimeException('request_ownership_application_changed');
            $authority=new RequestOwnershipApproverAuthority($this->pdo,$this->originId);
            $authority->assertAuthorized($this->pdo,$r['reviewer_account_id'],$r['account_id'],$r['profile_doctor_id'],(int)$r['reviewer_credential_version']);
            $q=$this->pdo->prepare('SELECT account_id FROM auth_accounts WHERE account_id=? FOR UPDATE');$q->execute([$applicant->accountId()]);
            $q=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$q->execute([$r['profile_doctor_id']]);if($q->fetchColumn()===false)throw new \RuntimeException('request_ownership_profile_missing');
            $this->eligibility->assertEligible($this->pdo,$applicant->accountId(),$r['profile_doctor_id']);
            $repo=new AccountMembershipRepository($this->pdo);$members=$repo->lockActivePhysicianMembershipsForAccount($applicant->accountId());
            if($members){
                if(count($members)!==1||$members[0]['profile_doctor_id']!==$r['profile_doctor_id']||!in_array($members[0]['role_code'],['collaborator','administrator'],true))throw new \RuntimeException('request_ownership_membership_conflict');
                $result=['membership_id'=>$members[0]['membership_id'],'profile_doctor_id'=>$r['profile_doctor_id'],'created'=>false];
            }else{
                $membership='request_'.bin2hex(random_bytes(16));
                $repo->create(new AccountMembership($membership,$applicant->accountId(),CanonicalProfileReference::profile($r['profile_doctor_id']),'collaborator','profile','active','physician_profile_request'));
                (new RequestAudit($this->pdo))->append($applicant,$origin,'MEMBERSHIP_PREPARED',(int)$r['resolution_version'],'APPROVED','APPROVED',null,$r['profile_doctor_id']);
                $result=['membership_id'=>$membership,'profile_doctor_id'=>$r['profile_doctor_id'],'created'=>true];
            }
            $this->security->assertLive($binding);$this->pdo->commit();return $result;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    /** Reviewer initiates the existing R3 case only for the applicant's legitimate membership. */
    public function begin(AuthenticatedAccessContext $reviewer):array
    {
        $r=$this->reviewer($reviewer);$existing=$this->caseId();
        if($existing!==null){
            $q=$this->pdo->prepare('SELECT * FROM auth_governance_cases WHERE case_id=?');$q->execute([$existing]);$prior=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($prior))throw new \RuntimeException('request_ownership_case_missing');
            if($prior['status']==='CONSUMED'||$this->reusable($prior))return $this->status($reviewer);
            // Reuse the canonical revocation writer, then append a new linkage.
            // A lost response is resolved by status(); no committed owner is retried.
            if(in_array($prior['status'],['OPEN','APPROVED'],true))$this->runtime->cases->expireOrRevoke($reviewer,$existing,true);
            elseif(!in_array($prior['status'],['EXPIRED','REVOKED'],true))throw new \RuntimeException('request_ownership_case_recovery_denied');
        }
        $q=$this->pdo->prepare("SELECT membership_id FROM auth_account_memberships WHERE account_id=? AND profile_doctor_id=? AND status='active' AND role_code IN ('collaborator','administrator') AND scope_code IN ('profile','profile_doctor')");
        $q->execute([$r['account_id'],$r['profile_doctor_id']]);$members=$q->fetchAll(PDO::FETCH_COLUMN);
        if(count($members)!==1)throw new \RuntimeException('request_ownership_canonical_membership_required');
        $this->runtime->cases->openInitialOwnerCase($reviewer,$members[0],'Solicitud de perfil aprobada: '.$this->originId);
        return $this->status($reviewer);
    }
    public function challenge(AuthenticatedAccessContext $reviewer,string $email,string $password,array $dimensions=[]):array
    {
        $this->reviewer($reviewer);$id=$this->caseId()??throw new \RuntimeException('request_ownership_case_required');
        return $this->runtime->cases->issueEndorsementChallenge($reviewer,$id,$email,$password,$dimensions);
    }
    public function endorse(AuthenticatedAccessContext $reviewer,string $proofId,string $proof):array
    {
        $this->reviewer($reviewer);$id=$this->caseId()??throw new \RuntimeException('request_ownership_case_required');
        $this->runtime->cases->endorse($reviewer,$id,$proofId,$proof);return $this->status($reviewer);
    }
    public function applicantStepUp(AuthenticatedAccessContext $applicant,string $email,string $password,array $dimensions=[]):array
    {
        $this->applicant($applicant);$id=$this->caseId()??throw new \RuntimeException('request_ownership_case_required');
        return $this->runtime->issueBeneficiaryStepUp($applicant,$id,$email,$password,$dimensions);
    }
    public function finalize(AuthenticatedAccessContext $applicant,string $proof):array
    {
        $this->applicant($applicant);$id=$this->caseId()??throw new \RuntimeException('request_ownership_case_required');
        $this->runtime->finalizeInitialOwner($applicant,$id,$proof);return $this->status($applicant);
    }
    /** Lookup after a lost response, without retrying any mutation. */
    public function status(AuthenticatedAccessContext $actor):array
    {
        $this->security->binding($actor);$r=$this->application();
        if($actor->accountId()!==$r['account_id'])(new RequestAuthority($this->pdo))->assert($actor,RequestAuthority::READ,$r['account_id']);
        $q=$this->pdo->prepare('SELECT c.case_id,c.operation_id,c.security_context,c.status,c.expires_at,m.account_id,m.profile_doctor_id,m.role_code,m.status membership_status FROM auth_physician_profile_request_ownership l JOIN auth_governance_cases c ON c.case_id=l.case_id JOIN auth_account_memberships m ON m.membership_id=l.membership_id WHERE l.origin_id=? AND l.resolution_id=? ORDER BY l.link_version DESC LIMIT 1');
        $q->execute([$this->originId,$r['resolution_id']]);$row=$q->fetch(PDO::FETCH_ASSOC);
        $activated=is_array($row)&&$row['status']==='CONSUMED'&&$row['role_code']==='owner'&&$row['membership_status']==='active'&&$row['account_id']===$r['account_id']&&$row['profile_doctor_id']===$r['profile_doctor_id'];
        $ready=false;$canBegin=false;$canChallenge=false;$prerequisite=$activated?'Titularidad activada para el solicitante.':'La activación requiere acreditación profesional vigente, vínculo del solicitante y su aceptación personal.';
        if(!$activated){
            $ownTransaction=!$this->pdo->inTransaction();
            try{
                if($ownTransaction)$this->pdo->beginTransaction();
                (new RequestOwnershipApproverAuthority($this->pdo,$this->originId))->assertAuthorized($this->pdo,$r['reviewer_account_id'],$r['account_id'],$r['profile_doctor_id'],(int)$r['reviewer_credential_version']);
                $this->eligibility->assertEligible($this->pdo,$r['account_id'],$r['profile_doctor_id']);$ready=true;
                $q=$this->pdo->prepare("SELECT membership_id FROM auth_account_memberships WHERE account_id=? AND profile_doctor_id=? AND status='active' AND role_code IN ('collaborator','administrator')");$q->execute([$r['account_id'],$r['profile_doctor_id']]);$hasMembership=$q->fetchColumn()!==false;
                $isReviewer=$actor->accountId()===$r['reviewer_account_id'];$current=$row===false||$this->reusable($row);
                $canBegin=$isReviewer&&$hasMembership&&(!is_array($row)||!$current);$canChallenge=$isReviewer&&is_array($row)&&$row['status']==='OPEN'&&$current;
                $prerequisite=!$hasMembership?'El solicitante debe aceptar primero su vínculo profesional.':(!$current?'La autorización anterior ya no es utilizable. Puedes preparar una nueva; el historial se conserva.':(is_array($row)&&$row['status']==='APPROVED'?'Falta la aceptación personal del solicitante para activar la titularidad.':'La titularidad está preparada para la autorización del revisor.'));
                if($ownTransaction)$this->pdo->commit();
            }catch(\RuntimeException $e){if($ownTransaction&&$this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof \PDOException)throw $e;$prerequisite='La titularidad conserva un requisito pendiente: acreditación y vínculo vigentes, perfil gratuito sin propietario y autorización actual del revisor.';}
        }
        return ['application_id'=>$this->originId,'profile_doctor_id'=>$r['profile_doctor_id'],'request_state'=>$r['state'],
            'ownership_activated'=>$activated,'case_id'=>$row['case_id']??null,'case_state'=>$row['status']??null,'expires_at'=>$row['expires_at']??null,
            'can_begin'=>$canBegin,'can_challenge'=>$canChallenge,'can_endorse'=>$canChallenge,'can_prepare'=>$ready&&$actor->accountId()===$r['account_id']&&!$activated,'prerequisite_label'=>$prerequisite,
            'next_step'=>$activated?null:(!is_array($row)?'CANONICAL_C4_MEMBERSHIP_AND_OWNER_CASE_REQUIRED':($row['status']==='OPEN'?'REVIEWER_ENDORSEMENT_REQUIRED':($row['status']==='APPROVED'?'APPLICANT_ACCEPTANCE_REQUIRED':'CASE_RECOVERY_REQUIRED')))];
    }
}
