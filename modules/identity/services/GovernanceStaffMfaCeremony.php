<?php
declare(strict_types=1);
namespace Identity\Services;
use Identity\Contracts\{AuthenticatedAccessContext,Clock,SystemClock};
use Identity\Adapters\CanonicalGovernanceAuditAdapter;
use PDO;

/** Controlled backend enrollment. No HTTP route; no secret is logged. */
final class GovernanceStaffMfaCeremony
{
    public function __construct(private PDO $pdo,private StaffMfaService $mfa,private GovernanceSecurityContext $security,
        private CanonicalGovernanceAuditAdapter $audit,private Clock $clock=new SystemClock(),private array $approvedRegistrars=[]){}
    public function begin(AuthenticatedAccessContext $subject,AuthenticatedAccessContext $registrar,
        string $subjectEmail,string $subjectPassword,string $registrarEmail,string $registrarPassword):array
    {
        $s=$this->security->reauthenticate($subject,$subjectEmail,$subjectPassword,['ip'=>'governance-ceremony']);
        $r=$this->security->reauthenticate($registrar,$registrarEmail,$registrarPassword,['ip'=>'governance-ceremony']);
        if(!in_array($registrar->accountId(),$this->approvedRegistrars,true))throw new \RuntimeException('mfa_registrar_not_deployment_approved');
        if($s['account_id']===$r['account_id'])throw new \RuntimeException('mfa_independent_registrar_required');
        if($this->pdo->inTransaction())throw new \RuntimeException('mfa_ceremony_outer_transaction');
        try{
            $this->pdo->beginTransaction();
            $q=$this->pdo->prepare('SELECT account_id FROM auth_accounts WHERE account_id IN (?,?) ORDER BY account_id FOR UPDATE');$q->execute([$subject->accountId(),$registrar->accountId()]);$q->fetchAll();
            $q=$this->pdo->prepare("SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id=? AND status IN ('ACTIVE','PENDING') FOR UPDATE");$q->execute([$subject->accountId()]);
            if($q->fetchColumn()!==false)throw new \RuntimeException('mfa_existing_factor_requires_cancellation_or_recovery');
            $result=$this->mfa->beginEnrollment($subject->accountId(),'MXMED',['subject'=>$s,'registrar'=>$r,'attempts'=>0,'expires_at'=>$this->clock->now()->modify('+5 minutes')->format(DATE_ATOM)]);
            $this->audit->append($registrar,'MFA_ENROLLMENT_STARTED',$subject->accountId(),$result['factor_id'],['factor_id'=>$result['factor_id']]);
            $this->pdo->commit();return $result;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function activate(AuthenticatedAccessContext $subject,string $factor,string $code):void
    {
        $this->security->binding($subject);
        $q=$this->pdo->prepare("SELECT enrollment_context FROM auth_staff_mfa_factors WHERE factor_id=? AND account_id=? AND status='PENDING'");$q->execute([$factor,$subject->accountId()]);$context=json_decode((string)$q->fetchColumn(),true,32,JSON_THROW_ON_ERROR);
        if(($context['subject']['session_id']??null)!==(string)$subject->session()->sessionId()
            ||($context['subject']['credential_version']??null)!==$subject->principal()->credentialVersion())throw new \RuntimeException('mfa_enrollment_session_changed');
        $this->mfa->activateEnrollment($subject->accountId(),$factor,$code,
            fn()=>$this->audit->append($subject,'MFA_ENROLLMENT_ACTIVATED',$subject->accountId(),$factor,['factor_id'=>$factor]));
    }
    public function cancel(AuthenticatedAccessContext $actor,string $factor):void
    {
        $this->security->binding($actor);
        if($this->pdo->inTransaction())throw new \RuntimeException('mfa_ceremony_outer_transaction');
        try{
            $this->pdo->beginTransaction();$q=$this->pdo->prepare("SELECT account_id,enrollment_context FROM auth_staff_mfa_factors WHERE factor_id=? AND status='PENDING' FOR UPDATE");$q->execute([$factor]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row))throw new \RuntimeException('mfa_pending_factor_required');
            $c=json_decode((string)$row['enrollment_context'],true,32,JSON_THROW_ON_ERROR);
            if(!in_array($actor->accountId(),[$row['account_id'],$c['registrar']['account_id']??null],true))throw new \RuntimeException('mfa_cancellation_denied');
            $q=$this->pdo->prepare("UPDATE auth_staff_mfa_factors SET status='REVOKED',revoked_at=? WHERE factor_id=? AND status='PENDING'");$q->execute([$this->clock->now()->format('Y-m-d H:i:s.u'),$factor]);
            $this->audit->append($actor,'MFA_ENROLLMENT_CANCELLED',$row['account_id'],$factor,['factor_id'=>$factor]);$this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
