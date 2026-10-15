<?php
declare(strict_types=1);
namespace Identity\Services;

use Identity\Adapters\PdoProfileOwnershipApproverAuthorityAdapter;
use Identity\Contracts\{AuthenticatedAccessContext,Clock,SessionState,SystemClock};
use PDO;

/** Case creation, independent endorsement and bounded owner recovery. No runtime is enabled by constructing this service. */
final class R3ProfileGovernanceService
{
    public function __construct(private PDO $pdo,private StaffMfaService $mfa,
        private PdoProfileOwnershipApproverAuthorityAdapter $authority,private Clock $clock=new SystemClock()){}

    public function openInitialOwnerCase(AuthenticatedAccessContext $beneficiary,string $membershipId,string $reason):array
    {
        $this->reason($reason);$this->activeContext($beneficiary);$now=$this->clock->now();
        return $this->transaction(function()use($beneficiary,$membershipId,$reason,$now):array{
            $s=$this->pdo->prepare("SELECT membership_id,account_id,profile_doctor_id,role_code,scope_code,status,updated_at FROM auth_account_memberships WHERE membership_id=? FOR UPDATE");$s->execute([$membershipId]);$m=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($m)||$m['account_id']!==$beneficiary->accountId()||$m['status']!=='active'||!in_array($m['role_code'],['collaborator','administrator'],true)||!in_array($m['scope_code'],['profile','profile_doctor'],true)||$m['profile_doctor_id']===null)throw new \RuntimeException('governance_case_membership_denied');
            $o=$this->pdo->prepare("SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND scope_code IN ('profile','profile_doctor') AND role_code='owner' AND status='active' FOR UPDATE");$o->execute([$m['profile_doctor_id']]);if($o->fetchColumn()!==false)throw new \RuntimeException('governance_case_owner_exists');
            $case='govcase_'.bin2hex(random_bytes(16));$operation=$this->uuid();$expires=$now->modify('+30 minutes');
            $i=$this->pdo->prepare("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,target_membership_id,profile_doctor_id,reason,status,expires_at) VALUES(?,?,'INITIAL_OWNER',?,?,?,?,?,'OPEN',?)");
            $i->execute([$case,$operation,$beneficiary->accountId(),$beneficiary->accountId(),$membershipId,$m['profile_doctor_id'],$reason,$this->sql($expires)]);
            $this->event($case,'CASE_OPENED',$beneficiary->accountId(),$beneficiary->accountId(),$m['profile_doctor_id'],'USER_REQUEST',$operation);
            return ['case_id'=>$case,'operation_id'=>$operation,'operation'=>'INITIAL_OWNER','membership'=>$m,'expires_at'=>$expires];
        });
    }

    public function openOwnerRecoveryCase(AuthenticatedAccessContext $requester,string $operation,string $ownerMembershipId,?string $replacementMembershipId,string $reason):array
    {
        if(!in_array($operation,['OWNER_REMOVAL','OWNER_REPLACEMENT'],true)||($operation==='OWNER_REPLACEMENT')!==($replacementMembershipId!==null))throw new \InvalidArgumentException('governance_recovery_invalid_operation');
        $this->reason($reason);$this->activeContext($requester);$now=$this->clock->now();
        return $this->transaction(function()use($requester,$operation,$ownerMembershipId,$replacementMembershipId,$reason,$now):array{
            $this->activeApprover($requester->accountId(),$requester->principal()->credentialVersion(),'__recovery_target__','__recovery_profile__');
            $s=$this->pdo->prepare("SELECT * FROM auth_account_memberships WHERE membership_id=? AND role_code='owner' AND status='active' FOR UPDATE");$s->execute([$ownerMembershipId]);$owner=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($owner)||$owner['profile_doctor_id']===null)throw new \RuntimeException('governance_recovery_owner_denied');
            if($replacementMembershipId!==null){$r=$this->pdo->prepare("SELECT * FROM auth_account_memberships WHERE membership_id=? AND profile_doctor_id=? AND status='active' AND role_code IN ('administrator','collaborator') FOR UPDATE");$r->execute([$replacementMembershipId,$owner['profile_doctor_id']]);if(!is_array($r->fetch(PDO::FETCH_ASSOC)))throw new \RuntimeException('governance_recovery_replacement_denied');}
            $case='govcase_'.bin2hex(random_bytes(16));$operationId=$this->uuid();$expires=$now->modify('+30 minutes');
            $i=$this->pdo->prepare('INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,target_membership_id,replacement_membership_id,profile_doctor_id,reason,status,expires_at) VALUES(?,?,?,?,?,?,?,?,?,\'OPEN\',?)');
            $i->execute([$case,$operationId,$operation,$requester->accountId(),$owner['account_id'],$ownerMembershipId,$replacementMembershipId,$owner['profile_doctor_id'],$reason,$this->sql($expires)]);
            $this->event($case,'RECOVERY_CASE_OPENED',$requester->accountId(),$owner['account_id'],$owner['profile_doctor_id'],'SECURITY_RESPONSE',$reason);
            return ['case_id'=>$case,'operation_id'=>$operationId,'operation'=>$operation,'expires_at'=>$expires];
        });
    }

    public function openMfaRecoveryCase(AuthenticatedAccessContext $requester,string $staffAccountId,string $reason):array
    {
        $this->reason($reason);$this->activeContext($requester);$now=$this->clock->now();
        return $this->transaction(function()use($requester,$staffAccountId,$reason,$now):array{
            $this->activeApprover($requester->accountId(),$requester->principal()->credentialVersion(),$staffAccountId,'staff-mfa-recovery');
            $s=$this->pdo->prepare("SELECT f.factor_id FROM auth_accounts a JOIN internal_staff i ON i.account_id=a.account_id JOIN auth_staff_mfa_factors f ON f.account_id=a.account_id AND f.status='ACTIVE' WHERE a.account_id=? AND a.status='active' AND i.status='ACTIVE' FOR UPDATE");$s->execute([$staffAccountId]);if($s->fetchColumn()===false)throw new \RuntimeException('governance_mfa_recovery_target_denied');
            $case='govcase_'.bin2hex(random_bytes(16));$operationId=$this->uuid();$expires=$now->modify('+30 minutes');$i=$this->pdo->prepare("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,reason,status,expires_at) VALUES(?,?,'MFA_RECOVERY',?,?,?,'OPEN',?)");$i->execute([$case,$operationId,$requester->accountId(),$staffAccountId,$reason,$this->sql($expires)]);$this->event($case,'MFA_RECOVERY_CASE_OPENED',$requester->accountId(),$staffAccountId,null,'SECURITY_RESPONSE',$reason);return ['case_id'=>$case,'operation_id'=>$operationId,'operation'=>'MFA_RECOVERY','expires_at'=>$expires];
        });
    }

    public function endorse(AuthenticatedAccessContext $approver,string $caseId,string $proofId,string $rawProof):array
    {
        $this->activeContext($approver);$now=$this->clock->now();
        return $this->transaction(function()use($approver,$caseId,$proofId,$rawProof,$now):array{
            $s=$this->pdo->prepare("SELECT * FROM auth_governance_cases WHERE case_id=? FOR UPDATE");$s->execute([$caseId]);$c=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($c)||$c['status']!=='OPEN'||new \DateTimeImmutable($c['expires_at'],new \DateTimeZone('UTC'))<=$now||$approver->accountId()===$c['beneficiary_account_id']||$approver->accountId()===$c['requester_account_id'])throw new \RuntimeException('governance_endorsement_denied');
            $this->authority->assertAuthorized($this->pdo,$approver->accountId(),$c['beneficiary_account_id'],$c['profile_doctor_id']??'staff-bootstrap',$approver->principal()->credentialVersion());
            $proof=$this->mfa->consumeProof($this->pdo,$proofId,$rawProof,$approver->accountId(),(string)$approver->session()->sessionId(),$approver->principal()->credentialVersion(),$c['operation'],$caseId,$c['beneficiary_account_id'],$c['beneficiary_account_id'],$c['profile_doctor_id'],$caseId);
            $count=$this->pdo->prepare('SELECT COUNT(*) FROM auth_governance_case_endorsements WHERE case_id=? FOR UPDATE');$count->execute([$caseId]);$slot=(int)$count->fetchColumn()+1;if($slot>2)throw new \RuntimeException('governance_endorsement_complete');
            $id='endorsement_'.bin2hex(random_bytes(16));$i=$this->pdo->prepare("INSERT INTO auth_governance_case_endorsements(endorsement_id,case_id,endorsement_slot,approver_account_id,staff_session_id,credential_version,mfa_proof_id,status,approved_at,expires_at) VALUES(?,?,?,?,?,?,?,'APPROVED',?,?)");
            $i->execute([$id,$caseId,$slot,$approver->accountId(),(string)$approver->session()->sessionId(),$approver->principal()->credentialVersion(),$proofId,$this->sql($now),$c['expires_at']]);
            $this->event($caseId,'CASE_ENDORSED',$approver->accountId(),$c['beneficiary_account_id'],$c['profile_doctor_id'],'APPROVED',$proofId);
            if($slot===2){$u=$this->pdo->prepare("UPDATE auth_governance_cases SET status='APPROVED',approved_at=? WHERE case_id=? AND status='OPEN'");$u->execute([$this->sql($now),$caseId]);if($u->rowCount()!==1)throw new \RuntimeException('governance_case_approval_race');if($c['operation']==='INITIAL_OWNER')$this->materializeInitialOwnerApproval($c,$now);}
            return ['case_id'=>$caseId,'endorsement_id'=>$id,'endorsement_slot'=>$slot,'case_status'=>$slot===2?'APPROVED':'OPEN'];
        });
    }

    public function issueEndorsementChallenge(AuthenticatedAccessContext $approver,string $caseId):array
    {
        $this->activeContext($approver);$s=$this->pdo->prepare("SELECT operation,requester_account_id,beneficiary_account_id,profile_doctor_id,status,expires_at FROM auth_governance_cases WHERE case_id=?");$s->execute([$caseId]);$c=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($c)||$c['status']!=='OPEN'||new \DateTimeImmutable($c['expires_at'],new \DateTimeZone('UTC'))<=$this->clock->now()||in_array($approver->accountId(),[$c['requester_account_id'],$c['beneficiary_account_id']],true))throw new \RuntimeException('governance_endorsement_denied');
        $this->transaction(function()use($approver,$c):void{$this->activeApprover($approver->accountId(),$approver->principal()->credentialVersion(),$c['beneficiary_account_id'],$c['profile_doctor_id']??'staff-bootstrap');});
        return $this->mfa->issueChallenge($approver->accountId(),$c['operation'],$caseId,$c['beneficiary_account_id'],$c['beneficiary_account_id'],$c['profile_doctor_id']);
    }

    public function executeOwnerRecovery(AuthenticatedAccessContext $requester,string $caseId):array
    {
        $this->activeContext($requester);$now=$this->clock->now();return $this->transaction(function()use($requester,$caseId,$now):array{
            $s=$this->pdo->prepare("SELECT * FROM auth_governance_cases WHERE case_id=? FOR UPDATE");$s->execute([$caseId]);$c=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($c)||$c['requester_account_id']!==$requester->accountId()||!in_array($c['operation'],['OWNER_REMOVAL','OWNER_REPLACEMENT'],true)||$c['status']!=='APPROVED'||new \DateTimeImmutable($c['expires_at'],new \DateTimeZone('UTC'))<=$now)throw new \RuntimeException('governance_recovery_denied');
            $this->activeApprover($requester->accountId(),$requester->principal()->credentialVersion(),'__recovery_target__','__recovery_profile__');
            $endorse=$this->pdo->prepare("SELECT e.*,c.credential_version current_version FROM auth_governance_case_endorsements e JOIN auth_account_credentials c ON c.account_id=e.approver_account_id WHERE e.case_id=? AND e.status='APPROVED' ORDER BY e.endorsement_slot FOR UPDATE");$endorse->execute([$caseId]);$rows=$endorse->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==2)throw new \RuntimeException('governance_recovery_approval_denied');
            foreach($rows as $row){if((int)$row['credential_version']!==(int)$row['current_version'])throw new \RuntimeException('governance_recovery_approver_revoked');$this->authority->assertAuthorized($this->pdo,$row['approver_account_id'],$c['beneficiary_account_id'],$c['profile_doctor_id'],(int)$row['credential_version']);}
            $owner=$this->pdo->prepare("SELECT * FROM auth_account_memberships WHERE membership_id=? AND profile_doctor_id=? AND role_code='owner' AND status='active' FOR UPDATE");$owner->execute([$c['target_membership_id'],$c['profile_doctor_id']]);$old=$owner->fetch(PDO::FETCH_ASSOC);if(!is_array($old))throw new \RuntimeException('governance_recovery_state_conflict');
            $owners=$this->pdo->prepare("SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active' FOR UPDATE");$owners->execute([$c['profile_doctor_id']]);$ownerIds=$owners->fetchAll(PDO::FETCH_COLUMN);
            if($c['operation']==='OWNER_REMOVAL'&&count($ownerIds)<=1)throw new \RuntimeException('governance_recovery_last_owner_protected');
            if($c['operation']==='OWNER_REPLACEMENT'){$replacement=$this->pdo->prepare("SELECT * FROM auth_account_memberships WHERE membership_id=? AND profile_doctor_id=? AND status='active' AND role_code IN ('administrator','collaborator') FOR UPDATE");$replacement->execute([$c['replacement_membership_id'],$c['profile_doctor_id']]);$new=$replacement->fetch(PDO::FETCH_ASSOC);if(!is_array($new))throw new \RuntimeException('governance_recovery_replacement_denied');$u=$this->pdo->prepare("UPDATE auth_account_memberships SET role_code='owner' WHERE membership_id=? AND role_code=? AND status='active'");$u->execute([$new['membership_id'],$new['role_code']]);if($u->rowCount()!==1)throw new \RuntimeException('governance_recovery_race');}
            $d=$this->pdo->prepare("UPDATE auth_account_memberships SET role_code='administrator' WHERE membership_id=? AND role_code='owner' AND status='active'");$d->execute([$old['membership_id']]);if($d->rowCount()!==1)throw new \RuntimeException('governance_recovery_race');
            $u=$this->pdo->prepare("UPDATE auth_governance_cases SET status='CONSUMED',consumed_at=? WHERE case_id=? AND status='APPROVED'");$u->execute([$this->sql($now),$caseId]);$e=$this->pdo->prepare("UPDATE auth_governance_case_endorsements SET status='CONSUMED',consumed_at=? WHERE case_id=? AND status='APPROVED'");$e->execute([$this->sql($now),$caseId]);if($e->rowCount()!==2)throw new \RuntimeException('governance_recovery_endorsement_race');
            $this->event($caseId,$c['operation']==='OWNER_REPLACEMENT'?'OWNER_REPLACED':'OWNER_REMOVED',$c['requester_account_id'],$c['beneficiary_account_id'],$c['profile_doctor_id'],'APPROVED',$c['operation_id']);
            return ['case_id'=>$caseId,'operation'=>$c['operation'],'prior_owner_membership_id'=>$old['membership_id'],'replacement_membership_id'=>$c['replacement_membership_id']];
        });
    }

    public function executeMfaRecovery(AuthenticatedAccessContext $requester,string $caseId,string $factorId):array
    {
        $this->activeContext($requester);return $this->transaction(function()use($requester,$caseId,$factorId):array{$s=$this->pdo->prepare("SELECT requester_account_id,beneficiary_account_id,status FROM auth_governance_cases WHERE case_id=? AND operation='MFA_RECOVERY' FOR UPDATE");$s->execute([$caseId]);$c=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($c)||$c['requester_account_id']!==$requester->accountId()||$c['status']!=='APPROVED')throw new \RuntimeException('governance_mfa_recovery_denied');$this->activeApprover($requester->accountId(),$requester->principal()->credentialVersion(),$c['beneficiary_account_id'],'staff-mfa-recovery');$this->mfa->revokeFactor($this->pdo,$factorId,$c['beneficiary_account_id'],$caseId);return ['case_id'=>$caseId,'factor_status'=>'REVOKED'];});
    }

    private function materializeInitialOwnerApproval(array $c,\DateTimeImmutable $now):void
    {
        $membership=$this->pdo->prepare('SELECT role_code,scope_code FROM auth_account_memberships WHERE membership_id=? FOR UPDATE');$membership->execute([$c['target_membership_id']]);$m=$membership->fetch(PDO::FETCH_ASSOC);if(!is_array($m)||!in_array($m['role_code'],['collaborator','administrator'],true))throw new \RuntimeException('governance_case_membership_changed');
        $rows=$this->pdo->prepare('SELECT * FROM auth_governance_case_endorsements WHERE case_id=? ORDER BY endorsement_slot FOR UPDATE');$rows->execute([$c['case_id']]);$endorsements=$rows->fetchAll(PDO::FETCH_ASSOC);if(count($endorsements)!==2)throw new \RuntimeException('governance_case_dual_approval_required');
        $approval='approval_'.bin2hex(random_bytes(16));$decision='decision_'.bin2hex(random_bytes(16));$expires=$now->modify('+10 minutes');$caseExpiry=new \DateTimeImmutable($c['expires_at'],new \DateTimeZone('UTC'));if($caseExpiry<$expires)$expires=$caseExpiry;
        $a=$this->pdo->prepare("INSERT INTO auth_profile_ownership_approvals(approval_reference,operation_id,case_reference,decision_reference,approver_account_id,executor_account_id,target_account_id,membership_id,profile_doctor_id,expected_role,expected_scope,new_role,status,approved_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,'owner','APPROVED',?,?)");
        $a->execute([$approval,$c['operation_id'],$c['case_id'],$decision,$endorsements[0]['approver_account_id'],$c['beneficiary_account_id'],$c['beneficiary_account_id'],$c['target_membership_id'],$c['profile_doctor_id'],$m['role_code'],$m['scope_code'],$this->sql($now),$this->sql($expires)]);
        foreach($endorsements as $row){$i=$this->pdo->prepare("INSERT INTO auth_profile_ownership_approval_endorsements(endorsement_id,approval_reference,endorsement_slot,operation_id,case_reference,approver_account_id,target_account_id,membership_id,profile_doctor_id,capability,staff_session_id,credential_version,mfa_evidence_reference,status,approved_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,'profile_initial_ownership_approve',?,?,?,'APPROVED',?,?)");$i->execute(['final_'.$row['endorsement_id'],$approval,$row['endorsement_slot'],$c['operation_id'],$c['case_id'],$row['approver_account_id'],$c['beneficiary_account_id'],$c['target_membership_id'],$c['profile_doctor_id'],$row['staff_session_id'],$row['credential_version'],$row['mfa_proof_id'],$row['approved_at'],$this->sql($expires)]);}
        $this->event($c['case_id'],'CASE_APPROVED',$endorsements[1]['approver_account_id'],$c['beneficiary_account_id'],$c['profile_doctor_id'],'DUAL_APPROVAL',$approval);
    }

    private function activeApprover(string $actor,int $version,string $target,string $profile):void{$this->authority->assertAuthorized($this->pdo,$actor,$target,$profile,$version);}
    private function activeContext(AuthenticatedAccessContext $a):void{if($a->session()->state()!==SessionState::ACTIVE||$a->session()->expiresAt()<=$this->clock->now()||$a->principal()->accountStatus()!=='active')throw new \RuntimeException('governance_authentication_required');}
    private function reason(string $v):void{if(strlen(trim($v))<8||strlen($v)>500)throw new \InvalidArgumentException('governance_reason_required');}
    private function event(string $case,string $type,string $actor,string $target,?string $profile,string $reason,string $evidence):void{$s=$this->pdo->prepare('INSERT INTO auth_governance_events(event_id,case_id,event_type,actor_account_id,target_account_id,profile_doctor_id,reason_code,evidence_hash) VALUES(UUID(),?,?,?,?,?,?,?)');$s->execute([$case,$type,$actor,$target,$profile,$reason,hash('sha256',$evidence)]);}
    private function transaction(callable $fn):mixed{if($this->pdo->inTransaction())throw new \RuntimeException('governance_outer_transaction_forbidden');try{$this->pdo->beginTransaction();$r=$fn();$this->pdo->commit();return $r;}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
    private function sql(\DateTimeImmutable $v):string{return $v->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
    private function uuid():string{$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
}
