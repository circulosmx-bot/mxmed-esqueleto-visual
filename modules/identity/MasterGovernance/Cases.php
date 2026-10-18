<?php
declare(strict_types=1);
namespace Identity\MasterGovernance;
use Identity\Contracts\{AuthenticatedAccessContext,StaffIdentityReviewPort,Clock,SystemClock};
use Identity\Services\{GovernanceSecurityContext,StaffMfaService};
use Identity\Adapters\CanonicalGovernanceAuditAdapter;
use PDO;
/** Case authority only. All staff/grant/delegation SQL remains in InternalGovernanceService. */
final class Cases
{
    public function __construct(private PDO $pdo,private GovernanceSecurityContext $security,private StaffMfaService $mfa,
        private StaffIdentityReviewPort $reviews,private Clock $clock=new SystemClock())
    {if(!$mfa->usesConnection($pdo))throw new \RuntimeException('master_connection_mismatch');}
    public function usesConnection(PDO $p):bool{return $p===$this->pdo;}
    public function authority(AuthenticatedAccessContext $actor):void
    {$this->security->binding($actor);Policy::director($this->pdo,$actor->accountId(),$actor->principal()->credentialVersion());}
    public function open(AuthenticatedAccessContext $actor,string $operation,string $target,?string $capability,string $reason):array
    {
        Policy::command($operation,$capability);$this->authority($actor);
        if(!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D',$target)||strlen(trim($reason))<8||strlen($reason)>500)throw new \RuntimeException('master_invalid_request');
        return $this->transaction(function()use($actor,$operation,$target,$capability,$reason){
            Policy::lockAccounts($this->pdo,$actor->accountId(),$target);Policy::director($this->pdo,$actor->accountId(),$actor->principal()->credentialVersion(),true);
            $snapshot=$this->snapshot($operation,$target,$capability);$binding=$this->security->binding($actor);
            $id='master_'.bin2hex(random_bytes(16));$uuid=(new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();$expires=$this->sql($this->clock->now()->modify('+5 minutes'));
            $intent=['case_id'=>$id,'operation_id'=>$uuid,'actor'=>$binding,'operation'=>$operation,'target'=>$target,'capability'=>$capability,'snapshot'=>$snapshot,'expires_at'=>$expires];
            $context=['kind'=>'internal_master_governance','version'=>1,'intent'=>$intent,'digest'=>Policy::digest($intent),'result'=>null];
            $q=$this->pdo->prepare("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,reason,status,expires_at,security_context,created_at) VALUES(?,?,?,?,?,?,'OPEN',?,?,?)");
            $q->execute([$id,$uuid,$operation,$actor->accountId(),$target,trim($reason),$expires,json_encode($context,JSON_THROW_ON_ERROR),$this->sql($this->clock->now())]);
            $this->audit($actor,'MASTER_CASE_OPENED',$target,$id);return $this->view($this->row($id));
        });
    }
    public function read(AuthenticatedAccessContext $actor,string $id):array
    {
        $this->authority($actor);$case=$this->row($id);Policy::context($case);
        if($case['requester_account_id']!==$actor->accountId())throw new \RuntimeException('master_denied');
        $view=$this->view($case);
        if($case['status']==='CONSUMED'){
            $result=$view['result'];
            if(($result['operation_id']??null)!==$case['operation_id']||($result['account_id']??null)!==$case['beneficiary_account_id'])throw new \RuntimeException('master_result_unreconciled');
            $q=$this->pdo->prepare("SELECT event_id FROM platform_audit_events WHERE event_id=? AND correlation_id=? AND action='INTERNAL_GOVERNANCE_CHANGED' AND real_actor_reference=? AND resource_reference=?");
            $q->execute([hash('sha256',$result['audit_event_id']??''),$case['operation_id'],'account:'.$case['requester_account_id'],$case['beneficiary_account_id']]);
            if($q->fetchColumn()===false)throw new \RuntimeException('master_result_unreconciled');
        }
        return $view;
    }
    /** Explicit digest confirms only the server-issued intent; no client authority fields accepted. */
    public function confirm(AuthenticatedAccessContext $actor,string $id,string $digest,string $email,string $password,array $dimensions):array
    {
        $this->authority($actor);$this->read($actor,$id);
        $reauth=$this->security->reauthenticate($actor,$email,$password,$dimensions);
        return $this->transaction(function()use($actor,$id,$digest,$reauth){
            $case=$this->locked($actor,$id);$c=Policy::context($case);
            if(!hash_equals($c['digest'],$digest))throw new \RuntimeException('master_context_denied');
            $this->revalidate($actor,$case,$c);
            // Exactly one confirmation/challenge per case; a changed/expired intent needs a new review.
            $q=$this->pdo->prepare('SELECT challenge_id FROM auth_staff_mfa_challenges WHERE case_id=? FOR UPDATE');$q->execute([$id]);
            if($q->fetchColumn()!==false)throw new \RuntimeException('master_conflict');
            $r=$this->mfa->issueChallenge($actor->accountId(),$case['operation'],$id,$case['beneficiary_account_id'],$case['beneficiary_account_id'],null,$reauth+['master_intent_digest'=>$c['digest']]);
            $this->audit($actor,'MASTER_CASE_CONFIRMED',$case['beneficiary_account_id'],$id);$r['expires_at']=$case['expires_at'];return $r;
        });
    }
    public function verify(AuthenticatedAccessContext $actor,string $id,string $challengeId,string $nonce,string $code):array
    {
        $this->authority($actor);$case=$this->row($id);$c=Policy::context($case);$this->sameActor($actor,$c);
        $q=Policy::row($this->pdo,'SELECT case_id FROM auth_staff_mfa_challenges WHERE challenge_id=?',[$challengeId]);
        if($q['case_id']!==$id)throw new \RuntimeException('master_context_denied');
        $r=$this->mfa->verifyChallenge($actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$challengeId,$nonce,$code);
        $r['expires_at']=$case['expires_at'];return $r;
    }
    /** Internal route derivation; authorization and equality are checked again under the writer locks. */
    public function command(string $id):array
    {$c=Policy::context($this->row($id));$i=$c['intent'];return [Policy::command($i['operation'],$i['capability']),$i['target'],$i['capability']];}
    public function consume(PDO $p,AuthenticatedAccessContext $actor,string $id,string $proofId,string $rawProof,string $command,string $target,?string $capability):array
    {
        if($p!==$this->pdo||!$p->inTransaction())throw new \RuntimeException('master_transaction_required');
        $case=$this->locked($actor,$id);$c=Policy::context($case);$i=$c['intent'];
        if([$command,$target,$capability]!==[Policy::command($i['operation'],$i['capability']),$i['target'],$i['capability']])throw new \RuntimeException('master_context_denied');
        $this->revalidate($actor,$case,$c);
        // Lock the challenge before proof/factor, consistent with verification.
        $q=$p->prepare('SELECT * FROM auth_staff_mfa_challenges WHERE case_id=? ORDER BY challenge_id FOR UPDATE');$q->execute([$id]);$challenge=$q->fetch(PDO::FETCH_ASSOC);
        if(!$challenge||$challenge['status']!=='CONSUMED')throw new \RuntimeException('master_context_denied');
        $proof=$this->mfa->consumeProof($p,$proofId,$rawProof,$actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$i['operation'],$id,$target,$target,null,$i['operation_id']);
        if($proof['challenge_id']!==$challenge['challenge_id'])throw new \RuntimeException('master_context_denied');
        // Final authority point: after all authority/proof locks, immediately before governance SQL.
        $reauth=json_decode($challenge['reauthentication_context'],true,32,JSON_THROW_ON_ERROR);
        $this->security->assertLive($reauth,true);Policy::director($p,$actor->accountId(),$actor->principal()->credentialVersion(),true);
        return $case;
    }
    public function finish(PDO $p,array $case,array $after,string $auditId):array
    {
        if($p!==$this->pdo||!$p->inTransaction())throw new \RuntimeException('master_transaction_required');
        $c=Policy::context($case);$c['result']=['account_id'=>$case['beneficiary_account_id'],...$after,'audit_event_id'=>$auditId,'operation_id'=>$case['operation_id']];$now=$this->sql($this->clock->now());
        $q=$p->prepare("UPDATE auth_governance_cases SET status='CONSUMED',approved_at=?,consumed_at=?,security_context=? WHERE case_id=? AND status='OPEN'");
        $q->execute([$now,$now,json_encode($c,JSON_THROW_ON_ERROR),$case['case_id']]);if($q->rowCount()!==1)throw new \RuntimeException('master_conflict');
        return $c['result'];
    }
    public function close(AuthenticatedAccessContext $actor,string $id,bool $expire=false):array
    {
        $this->authority($actor);
        return $this->transaction(function()use($actor,$id,$expire){
            $case=$this->locked($actor,$id);Policy::context($case);
            // Closing an unusable intent is allowed from a fresh session of the same Director.
            if($case['requester_account_id']!==$actor->accountId())throw new \RuntimeException('master_denied');
            $this->security->binding($actor);
            Policy::director($this->pdo,$actor->accountId(),$actor->principal()->credentialVersion(),true);
            if($case['status']!=='OPEN'||($expire&&new \DateTimeImmutable($case['expires_at'],new \DateTimeZone('UTC'))>$this->clock->now()))throw new \RuntimeException('master_conflict');
            $state=$expire?'EXPIRED':'REVOKED';
            $this->pdo->prepare("UPDATE auth_staff_mfa_challenges SET status=? WHERE case_id=? AND status='PENDING'")->execute([$state,$id]);
            $this->pdo->prepare("UPDATE auth_staff_mfa_proofs SET status=? WHERE case_id=? AND status='VERIFIED'")->execute([$state,$id]);
            $this->pdo->prepare("UPDATE auth_governance_cases SET status=? WHERE case_id=? AND status='OPEN'")->execute([$state,$id]);
            $this->audit($actor,'MASTER_CASE_'.$state,$case['beneficiary_account_id'],$id);return $this->view($this->row($id));
        });
    }
    private function snapshot(string $operation,string $target,?string $capability):array
    {
        $command=Policy::command($operation,$capability);
        $row=Policy::row($this->pdo,'SELECT a.status account_status,c.credential_version,s.governance_class,s.status staff_status,s.updated_at FROM auth_accounts a JOIN auth_account_credentials c ON c.account_id=a.account_id JOIN internal_staff s ON s.account_id=a.account_id WHERE a.account_id=? FOR UPDATE',[$target]);
        $row['credential_version']=(int)$row['credential_version'];$appoint=$command==='appoint_master';
        if($row['governance_class']!==($appoint?'ADVISOR':'MASTER_ADMIN'))throw new \RuntimeException('master_target_ineligible');
        if(in_array($command,['appoint_master','grant','delegate'],true)&&($row['account_status']!=='active'||$row['staff_status']!=='ACTIVE'))throw new \RuntimeException('master_target_ineligible');
        $review=null;
        if($appoint){$v=$this->reviews->currentApproved($this->pdo,$target,$row['credential_version']);
            if($v->accountId!==$target||$v->credentialVersion!==$row['credential_version'])throw new \RuntimeException('master_review_mismatch');$review=$v->values();}
        $q=$this->pdo->prepare('SELECT grant_id,capability,status,revoked_at FROM internal_operator_grants WHERE account_id=? ORDER BY grant_id FOR UPDATE');$q->execute([$target]);$grants=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$this->pdo->prepare('SELECT delegation_id,capability,status,revoked_at FROM internal_capability_delegations WHERE master_account_id=? ORDER BY delegation_id FOR UPDATE');$q->execute([$target]);$delegations=$q->fetchAll(PDO::FETCH_ASSOC);
        if(in_array($command,['grant','revoke','delegate','revoke_delegation'],true)){
            $rows=in_array($command,['grant','revoke'],true)?$grants:$delegations;$active=false;
            foreach($rows as $r)if($r['capability']===$capability&&$r['status']==='ACTIVE'&&$r['revoked_at']===null)$active=true;
            if($active===in_array($command,['grant','delegate'],true))throw new \RuntimeException('master_conflict');
        }
        return ['staff'=>$row,'review'=>$review,'grants'=>$grants,'delegations'=>$delegations];
    }
    private function revalidate(AuthenticatedAccessContext $actor,array $case,array $c):void
    {
        $this->sameActor($actor,$c);$this->security->assertUnexpired($case['expires_at']);
        if($case['status']!=='OPEN')throw new \RuntimeException('master_conflict');
        Policy::director($this->pdo,$actor->accountId(),$actor->principal()->credentialVersion(),true);$i=$c['intent'];
        if(!hash_equals(Policy::digest($this->snapshot($i['operation'],$i['target'],$i['capability'])),Policy::digest($i['snapshot'])))throw new \RuntimeException('master_conflict');
    }
    private function sameActor(AuthenticatedAccessContext $actor,array $c):void
    {if(!hash_equals(Policy::digest($this->security->binding($actor)),Policy::digest($c['intent']['actor'])))throw new \RuntimeException('master_context_denied');}
    private function row(string $id):array{return Policy::row($this->pdo,'SELECT * FROM auth_governance_cases WHERE case_id=?',[$id]);}
    private function locked(AuthenticatedAccessContext $actor,string $id):array
    {$r=$this->row($id);Policy::lockAccounts($this->pdo,$actor->accountId(),$r['beneficiary_account_id']);return Policy::row($this->pdo,'SELECT * FROM auth_governance_cases WHERE case_id=? FOR UPDATE',[$id]);}
    private function view(array $case):array
    {$c=Policy::context($case);$i=$c['intent'];return ['case_id'=>$case['case_id'],'operation_id'=>$case['operation_id'],'operation'=>$i['operation'],'target'=>$i['target'],'capability'=>$i['capability'],'expected_staff'=>$i['snapshot']['staff'],'intent_digest'=>$c['digest'],'status'=>$case['status'],'expires_at'=>$case['expires_at'],'result'=>$c['result'],'refresh_required'=>true];}
    private function audit(AuthenticatedAccessContext $actor,string $op,string $target,string $id):void
    {(new CanonicalGovernanceAuditAdapter($this->pdo))->append($actor,$op,$target,$id);}
    private function sql(\DateTimeImmutable $d):string{return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
    private function transaction(callable $fn):array
    {if($this->pdo->inTransaction())throw new \RuntimeException('master_outer_transaction');try{$this->pdo->beginTransaction();$r=$fn();$this->pdo->commit();return $r;}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
}
