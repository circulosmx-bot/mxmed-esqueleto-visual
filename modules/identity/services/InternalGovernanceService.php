<?php
declare(strict_types=1);
namespace Identity\Services;
use Identity\Http\CanonicalHttpSessionResolver;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use PDO;
use RuntimeException;
/** Backend-only. Future HTTP adapters must add method/CSRF validation; no HTTP route in IW01. */
final class InternalGovernanceService
{
    public function __construct(private PDO $pdo,private CanonicalHttpSessionResolver $sessions){}
    public function execute(array $cookies,string $operation,string $target,?string $capability=null):array
    {
        if(!in_array($operation,['register','appoint_master','remove_master','suspend','reactivate','grant','revoke','delegate','revoke_delegation'],true))throw new RuntimeException('governance_invalid_operation');
        $hasCapability=in_array($operation,['grant','revoke','delegate','revoke_delegation'],true);
        if($hasCapability!==($capability!==null))throw new RuntimeException('governance_invalid_request');
        $definition=$hasCapability?InternalCapabilityCatalog::require($capability):null;
        $session=$this->sessions->resolve($cookies);if(!$session)throw new RuntimeException('governance_denied');
        $actor=$session->accountId();if($target===''||strlen($target)>64||$this->pdo->inTransaction())throw new RuntimeException('governance_invalid_request');
        try{
            $this->pdo->beginTransaction();
            // Account locks serialize staff status, grants, class and delegation mutations.
            // All callers lock the same sorted pair, including Director revocation of a Master.
            $ids=array_unique([$actor,$target]);sort($ids,SORT_STRING);$accounts=[];$staff=[];
            foreach($ids as $id){$s=$this->pdo->prepare('SELECT status FROM auth_accounts WHERE account_id=? FOR UPDATE');$s->execute([$id]);$accounts[$id]=$s->fetchColumn();if($accounts[$id]===false)throw new RuntimeException('governance_account_not_found');
                $s=$this->pdo->prepare('SELECT governance_class,status FROM internal_staff WHERE account_id=? FOR UPDATE');$s->execute([$id]);$staff[$id]=$s->fetch(PDO::FETCH_ASSOC)?:null;}
            $a=$staff[$actor];$t=$staff[$target];
            if($accounts[$actor]!=='active'||!$a||$a['status']!=='ACTIVE')throw new RuntimeException('governance_denied');
            $director=$a['governance_class']==='DIRECTOR';
            if(!$director){
                if($a['governance_class']!=='MASTER_ADMIN'||$actor===$target||!$this->activeGrant($actor,InternalCapabilityCatalog::MANAGE_ADVISORS))throw new RuntimeException('governance_denied');
                if(!in_array($operation,['register','suspend','reactivate','grant','revoke'],true)||($t!==null&&$t['governance_class']!=='ADVISOR'))throw new RuntimeException('governance_denied');
                if($hasCapability&&(!$definition['delegable']||!$this->delegated($actor,$capability)))throw new RuntimeException('governance_denied');
            }
            if($t&&$t['governance_class']==='DIRECTOR'&&!in_array($operation,['grant','revoke'],true))throw new RuntimeException('governance_director_protected');
            if($operation==='register'){
                if($t||$accounts[$target]!=='active')throw new RuntimeException('governance_conflict');
                $this->pdo->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,'ADVISOR','ACTIVE')")->execute([$target]);
            }elseif($operation==='appoint_master'){
                if($accounts[$target]!=='active'||($t&&($t['governance_class']!=='ADVISOR'||$t['status']!=='ACTIVE')))throw new RuntimeException('governance_conflict');
                if(!$t)$this->pdo->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,'MASTER_ADMIN','ACTIVE')")->execute([$target]);
                else $this->pdo->prepare("UPDATE internal_staff SET governance_class='MASTER_ADMIN' WHERE account_id=?")->execute([$target]);
            }elseif($operation==='remove_master'){
                if(!$t||$t['governance_class']!=='MASTER_ADMIN')throw new RuntimeException('governance_conflict');
                $this->pdo->prepare("UPDATE internal_staff SET governance_class='ADVISOR' WHERE account_id=?")->execute([$target]);
                $this->pdo->prepare("UPDATE internal_capability_delegations SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP(6) WHERE master_account_id=? AND status='ACTIVE'")->execute([$target]);
                $this->revoke($target,InternalCapabilityCatalog::MANAGE_ADVISORS);
            }elseif(in_array($operation,['suspend','reactivate'],true)){
                $next=$operation==='suspend'?'SUSPENDED':'ACTIVE';if(!$t||$t['status']===$next)throw new RuntimeException('governance_conflict');
                $this->pdo->prepare('UPDATE internal_staff SET status=? WHERE account_id=?')->execute([$next,$target]);
            }elseif(in_array($operation,['grant','revoke'],true)){
                if(!$t||($definition['kind']==='governance'&&$t['governance_class']!=='MASTER_ADMIN'))throw new RuntimeException('governance_conflict');
                if($operation==='grant'){
                    if($this->activeGrant($target,$capability))throw new RuntimeException('governance_conflict');
                    $this->pdo->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(?,?,?,'ACTIVE')")->execute([$this->uuid(),$target,$capability]);
                }elseif(!$this->revoke($target,$capability))throw new RuntimeException('governance_conflict');
            }else{
                if(!$t||$t['governance_class']!=='MASTER_ADMIN'||!$definition['delegable'])throw new RuntimeException('governance_conflict');
                if($operation==='delegate'){
                    if($this->delegated($target,$capability))throw new RuntimeException('governance_conflict');
                    $this->pdo->prepare("INSERT INTO internal_capability_delegations(delegation_id,master_account_id,capability,authorized_by_account_id,status) VALUES(?,?,?,?,'ACTIVE')")->execute([$this->uuid(),$target,$capability,$actor]);
                }else{$s=$this->pdo->prepare("UPDATE internal_capability_delegations SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP(6) WHERE master_account_id=? AND capability=? AND status='ACTIVE'");$s->execute([$target,$capability]);if(!$s->rowCount())throw new RuntimeException('governance_conflict');}
            }
            $s=$this->pdo->prepare('SELECT governance_class,status FROM internal_staff WHERE account_id=?');$s->execute([$target]);$after=$s->fetch(PDO::FETCH_ASSOC);
            $writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),new JoinedPdoCanonicalAuditTransactionAdapter($this->pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
            $context=TrustedAuditContext::fromServer($actor,'account',$a['governance_class'],'internal_governance',$this->uuid(),$this->uuid(),(string)$session->session()->sessionId(),null,null,'ADMIN','internal-governance/'.$operation);
            $writer->append(new CanonicalAuditEventInput('INTERNAL_GOVERNANCE_CHANGED','SUCCESS','ADMIN_DECISION','account',$actor,'internal_staff',$target,['operation'=>$operation,'target_account_id'=>$target,'capability'=>$capability,'before'=>$t,'after'=>$after]),$context);
            if(!$this->pdo->commit())throw new RuntimeException('governance_commit_failed');return ['account_id'=>$target,...$after];
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function uuid():string{return (new RandomAuditUuidProvider())->generateCanonicalUuid();}
    private function activeGrant(string $id,string $cap):bool{$s=$this->pdo->prepare("SELECT grant_id FROM internal_operator_grants WHERE account_id=? AND capability=? AND status='ACTIVE' AND revoked_at IS NULL");$s->execute([$id,$cap]);return $s->fetchColumn()!==false;}
    private function revoke(string $id,string $cap):bool{$s=$this->pdo->prepare("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id=? AND capability=? AND status='ACTIVE'");$s->execute([$id,$cap]);return $s->rowCount()>0;}
    private function delegated(string $id,string $cap):bool{$s=$this->pdo->prepare("SELECT delegation_id FROM internal_capability_delegations WHERE master_account_id=? AND capability=? AND status='ACTIVE' AND revoked_at IS NULL");$s->execute([$id,$cap]);return $s->fetchColumn()!==false;}
}
