<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;

use Identity\Contracts\{AuthenticatedAccessContext,Clock,SessionState,SystemClock};
use Identity\Repositories\InternalOperatorGrantRepository;
use PDO;

/** Narrow request authority. The caller owns exact request binding and live-session
 * revalidation; mutations call assert(..., true) inside their shared transaction.
 * No role supplies an implicit capability, professional authority or ownership. */
final class RequestAuthority
{
    public const READ='physician_request_read';
    public const EVIDENCE='physician_request_evidence_read';
    public const CLAIM='physician_claim_resolve';
    public const NEW_PROFILE='physician_new_profile_resolve';
    public const CAPABILITIES=[self::READ,self::EVIDENCE,self::CLAIM,self::NEW_PROFILE];
    public const OPERATIONS=['PHYSICIAN_CLAIM_RESOLVE'=>self::CLAIM,'PHYSICIAN_NEW_PROFILE_RESOLVE'=>self::NEW_PROFILE];

    public function __construct(private PDO $pdo,private Clock $clock=new SystemClock()){}

    public function assert(AuthenticatedAccessContext $actor,string $capability,?string $applicantId=null,bool $lock=false):array
    {
        if(!in_array($capability,self::CAPABILITIES,true))throw new \InvalidArgumentException('physician_request_invalid_capability');
        $staff=$this->assertActiveStaff($actor,$lock);
        if($applicantId!==null&&$actor->accountId()===$applicantId)throw new \RuntimeException('physician_request_self_review_denied');
        $grants=(new InternalOperatorGrantRepository($this->pdo))->activeCapabilities($actor->accountId(),$lock);
        if(!$grants->contains($capability)||!$grants->contains(self::READ))throw new \RuntimeException('physician_request_capability_denied');
        return $staff+['capability'=>$capability];
    }

    /** Shared current staff boundary; it conveys no domain capability. */
    public function assertActiveStaff(AuthenticatedAccessContext $actor,bool $lock=false):array
    {
        if($lock&&!$this->pdo->inTransaction())throw new \RuntimeException('physician_request_transaction_required');
        if($actor->session()->state()!==SessionState::ACTIVE||$actor->session()->expiresAt()<=$this->clock->now()
            ||$actor->principal()->accountStatus()!=='active')throw new \RuntimeException('physician_request_session_denied');
        return $this->staff($actor->accountId(),$actor->principal()->credentialVersion(),$lock);
    }

    /** UI projection only; every reader/decision still revalidates assert(). */
    public function capabilities(AuthenticatedAccessContext $actor):array
    {
        try{$this->assert($actor,self::READ);}catch(\RuntimeException $e){return [];}
        $grants=(new InternalOperatorGrantRepository($this->pdo))->activeCapabilities($actor->accountId());
        return array_values(array_filter(self::CAPABILITIES,static fn(string $key):bool=>$grants->contains($key)));
    }

    public static function operationCapability(string $operation):?string{return self::OPERATIONS[$operation]??null;}

    /** Used by canonical StaffMfaService at issue, verify and proof consumption. */
    public static function allowsMfaOperation(PDO $pdo,string $accountId,string $operation):bool
    {
        $capability=self::operationCapability($operation);if($capability===null)return false;
        $self=new self($pdo);$lock=$pdo->inTransaction();
        try{$self->staff($accountId,null,$lock);}catch(\RuntimeException $e){return false;}
        $grants=(new InternalOperatorGrantRepository($pdo))->activeCapabilities($accountId,$lock);
        return $grants->contains(self::READ)&&$grants->contains($capability);
    }

    /** Permits enrollment of a specifically delegated Advisor without extending C4. */
    public static function eligibleMfaSubject(PDO $pdo,string $accountId):bool
    {
        foreach(array_keys(self::OPERATIONS) as $operation)if(self::allowsMfaOperation($pdo,$accountId,$operation))return true;
        return false;
    }

    private function staff(string $id,?int $version,bool $lock):array
    {
        $q=$this->pdo->prepare("SELECT a.account_id,s.governance_class,c.credential_version FROM auth_accounts a
            JOIN auth_account_credentials c ON c.account_id=a.account_id JOIN internal_staff s ON s.account_id=a.account_id
            WHERE a.account_id=? AND a.status='active' AND a.email_verified_at IS NOT NULL
            AND s.status='ACTIVE' AND s.governance_class IN ('DIRECTOR','MASTER_ADMIN','ADVISOR')".($lock?' FOR UPDATE':''));
        $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row)||(int)$row['credential_version']<1||($version!==null&&(int)$row['credential_version']!==$version))throw new \RuntimeException('physician_request_staff_denied');
        unset($row['credential_version']);return $row;
    }
}
