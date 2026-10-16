<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\{Clock,FirstDirectorBootstrapAuditPort,FirstDirectorBootstrapAuthorizationPort,SystemClock};
use PDO;

/** One-time backend writer. The deployment-authorized CLI is its only operational entry. */
final class FirstDirectorBootstrapService
{
    public function __construct(private PDO $pdo,private FirstDirectorBootstrapAuthorizationPort $authorization,
        private FirstDirectorBootstrapAuditPort $audit,private Clock $clock=new SystemClock(),private ?GovernanceSecurityContext $security=null){}

    public function execute(string $operationId,string $targetAccountId,?string $signedEvidence=null,?array $reauthentication=null,?string $expectedCase=null):array
    {
        if($this->authorization instanceof \Identity\Adapters\Ed25519ExternalTrustAnchorAdapter&&$this->security===null)throw new \RuntimeException('first_director_canonical_session_required');
        if($this->pdo->inTransaction())throw new \RuntimeException('first_director_outer_transaction_forbidden');
        try{
            $this->pdo->beginTransaction();
            $account=$this->pdo->prepare('SELECT status,email_verified_at FROM auth_accounts WHERE account_id=? FOR UPDATE');
            $account->execute([$targetAccountId]);$row=$account->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)||$row['status']!=='active'||$row['email_verified_at']===null)
                throw new \RuntimeException('first_director_target_ineligible');
            $credential=$this->pdo->prepare('SELECT credential_version FROM auth_account_credentials WHERE account_id=? FOR UPDATE');
            $credential->execute([$targetAccountId]);$credentialVersion=$credential->fetchColumn();
            if($credentialVersion===false||(int)$credentialVersion<1)
                throw new \RuntimeException('first_director_target_ineligible');
            if($this->security!==null){
                if(($reauthentication['account_id']??null)!==$targetAccountId||($reauthentication['credential_version']??null)!==(int)$credentialVersion)throw new \RuntimeException('first_director_reauthentication_required');
                $this->security->assertLive($reauthentication,true);
            }
            $membership=$this->pdo->prepare('SELECT membership_id FROM auth_account_memberships WHERE account_id=? LIMIT 1 FOR UPDATE');
            $membership->execute([$targetAccountId]);
            if($membership->fetchColumn()!==false)throw new \RuntimeException('first_director_staff_physician_separation_required');
            if($this->pdo->query("SELECT account_id FROM internal_staff WHERE governance_class='DIRECTOR' FOR UPDATE")->fetchColumn()!==false)
                throw new \RuntimeException('first_director_already_exists');
            if($signedEvidence!==null){
                if(!$this->authorization instanceof \Identity\Adapters\Ed25519ExternalTrustAnchorAdapter)throw new \RuntimeException('first_director_external_adapter_required');
                $expected=['contract_version'=>2,'operation'=>'FIRST_DIRECTOR_BOOTSTRAP','operation_id'=>$operationId,
                    'target_account_id'=>$targetAccountId,'profile_doctor_id'=>null,'membership_id'=>null,'membership_version'=>null,'requester_account_id'=>null];
                if($expectedCase!==null)$expected['case_reference']=$expectedCase;
                $this->authorization->stage($signedEvidence,$expected);
            }
            $evidence=$this->authorization->consume($this->pdo,$operationId,$targetAccountId);
            if($evidence->operationId!==$operationId||$evidence->targetAccountId!==$targetAccountId
                ||$evidence->expiresAt<=$this->clock->now())throw new \RuntimeException('first_director_authorization_denied');
            if($reauthentication!==null)$evidence=$evidence->withAcceptance($reauthentication);
            $insert=$this->pdo->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,'DIRECTOR','ACTIVE')");
            $insert->execute([$targetAccountId]);
            $this->audit->append($this->pdo,$evidence);
            if(!$this->pdo->commit())throw new \RuntimeException('first_director_commit_failed');
            return ['account_id'=>$targetAccountId,'governance_class'=>'DIRECTOR','status'=>'ACTIVE'];
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
