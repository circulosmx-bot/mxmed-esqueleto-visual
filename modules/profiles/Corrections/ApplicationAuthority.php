<?php
declare(strict_types=1);
namespace Profiles\Corrections;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\GovernanceSecurityContext;
use PDO;

/** Uses canonical current Identity/staff/grant sources; never a medical membership. */
final class ApplicationAuthority
{
    public function __construct(public readonly ReportService $reports,public readonly GovernanceSecurityContext $security){}
    public function transaction(AuthenticatedAccessContext $actor,callable $fn,bool $read=false):mixed
    {
        if(!$read)$this->reports->scope?->workflow('correction_apply');$this->reports->scope?->account($actor->accountId());
        $p=$this->reports->pdo;if($p->inTransaction())throw new ReportDenied(503);
        $binding=$this->security->binding($actor);
        for($attempt=0;$attempt<4;$attempt++){
            $committing=false;
            try{$p->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$p->beginTransaction();$grants=$this->grants($actor,$read);$result=$fn($grants);
                if($this->grants($actor,$read)!==$grants)throw new ReportDenied(403);
                $this->security->assertLive($binding);$committing=true;if(!$p->commit())throw new ReportDenied(503);return $result;
            }catch(\Throwable $e){
                if($p->inTransaction())$p->rollBack();
                // Every callback uses this native connection only. Retry only a fully
                // aborted serialization transaction, never an ambiguous COMMIT/network error.
                if(!$committing&&$attempt<3&&$e instanceof \PDOException
                    &&($e->getCode()==='40001'||in_array((int)($e->errorInfo[1]??0),[1213,1205],true))){usleep(10000*($attempt+1));continue;}
                throw $e;
            }
        }
        throw new ReportDenied(503);
    }
    private function grants(AuthenticatedAccessContext $actor,bool $read):array
    {
        $q=$this->reports->query("SELECT a.status,a.email_verified_at,c.credential_version,s.status staff_status,s.governance_class
            FROM auth_accounts a JOIN auth_account_credentials c ON c.account_id=a.account_id JOIN internal_staff s ON s.account_id=a.account_id WHERE a.account_id=? FOR UPDATE",[$actor->accountId()]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['status']!=='active'||$r['email_verified_at']===null||$r['staff_status']!=='ACTIVE'
            ||!in_array($r['governance_class'],['DIRECTOR','MASTER_ADMIN'],true)||(int)$r['credential_version']!==$actor->principal()->credentialVersion())throw new ReportDenied(403);
        $rows=$this->reports->query("SELECT grant_id,capability FROM internal_operator_grants WHERE account_id=? AND capability IN (?,?) AND status='ACTIVE' AND revoked_at IS NULL ORDER BY capability FOR UPDATE",
            [$actor->accountId(),ApplicationCatalog::CAPABILITY,ReportCatalog::CAPABILITY])->fetchAll(PDO::FETCH_ASSOC);$grants=[];
        foreach($rows as $row){
            if($this->reports->scope!==null&&!$this->reports->scope->allowsStaff($actor->accountId(),$row['capability']))continue;
            if(isset($grants[$row['capability']]))throw new ReportDenied(403);$grants[$row['capability']]=hash('sha256',json_encode([$r,$row],JSON_THROW_ON_ERROR));
        }
        if(!isset($grants[ApplicationCatalog::CAPABILITY])&&(!$read||!isset($grants[ReportCatalog::CAPABILITY])))throw new ReportDenied(403);
        return $grants;
    }
}
