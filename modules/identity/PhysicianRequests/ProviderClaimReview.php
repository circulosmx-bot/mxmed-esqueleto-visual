<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\GovernanceSecurityContext;
use PDO;
/** Transport integration of the existing ORGANIZATION claim writer, never physician ownership.
 * Explicit composition supplies a narrower exact-case cohort; no default global queue.
 */
final class ProviderClaimReview
{
    private \Agenda\Services\HealthcareOrganizationClaimService $service;
    public function __construct(private PDO $pdo,private GovernanceSecurityContext $security,private array $cases)
    {
        require_once dirname(__DIR__,2).'/agenda/services/HealthcareOrganizationClaimService.php';
        $this->service=new \Agenda\Services\HealthcareOrganizationClaimService($pdo);
    }
    public function read(AuthenticatedAccessContext $actor,?string $id=null):array
    {
        $this->live($actor);
        if($id!==null){$this->scope($id);return $this->named($this->service->readForReview($actor,$id));}
        $rows=[];foreach($this->cases as $claim)$rows[]=$this->named($this->service->readForReview($actor,$claim));
        return ['claims'=>$rows,'domain'=>'HEALTHCARE_ORGANIZATION'];
    }
    public function decide(AuthenticatedAccessContext $actor,string $id,string $decision,string $reason):array
    {
        $this->live($actor);$this->scope($id);
        return $this->service->decide($actor,$id,$decision,$reason);
    }
    private function named(array $claim):array
    {
        $q=$this->pdo->prepare('SELECT display_name FROM medical_groups WHERE group_id=?');$q->execute([$claim['group_id']]);
        return $claim+['organization_name'=>$q->fetchColumn()?:'Organización proveedora'];
    }
    private function live(AuthenticatedAccessContext $actor):void
    {
        $this->security->assertLive($this->security->binding($actor));
        // Even an empty queue must not conceal a revoked/absent grant.
        (new RequestAuthority($this->pdo))->assertActiveStaff($actor,false);
        if(!(new \Identity\Repositories\InternalOperatorGrantRepository($this->pdo))->activeCapabilities($actor->accountId())->contains('provider_claim_review'))throw new \RuntimeException('provider_claim_review_denied');
    }
    private function scope(string $id):void
    {if(!in_array($id,$this->cases,true))throw new \RuntimeException('provider_claim_scope_denied');}
}
