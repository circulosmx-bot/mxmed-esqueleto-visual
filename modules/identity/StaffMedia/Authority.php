<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
use Identity\Contracts\AuthenticatedAccessContext;
/** Purpose checks default to own applicant. Owned workflow may opt into Director portrait editing only. */
final class Authority
{
    public function __construct(private ?\PDO $portraitDirectory=null){}
    public function portrait(AuthenticatedAccessContext $actor,?array $case):bool
    {
        if($this->own($actor,$case))return true;
        if(!$this->portraitDirectory||!$case||!in_array($case['state'],['PREPARING','PERSON_CONFIRMED'],true)||!$case['bound_account_id']||$case['bound_account_id']===$actor->accountId())return false;
        $q=$this->portraitDirectory->prepare("SELECT 1 FROM internal_staff d JOIN auth_staff_admission_completions c ON c.case_id=? AND c.account_id=? JOIN internal_staff t ON t.account_id=c.account_id JOIN auth_accounts a ON a.account_id=t.account_id WHERE d.account_id=? AND d.governance_class='DIRECTOR' AND d.status='ACTIVE' AND t.status<>'REMOVED' AND a.status='active' FOR UPDATE");
        $q->execute([$case['case_id'],$case['bound_account_id'],$actor->accountId()]);return (bool)$q->fetchColumn();
    }
    public function evidence(AuthenticatedAccessContext $actor,?array $case):bool{return $this->own($actor,$case);}
    private function own(AuthenticatedAccessContext $actor,?array $case):bool
    {return $case!==null&&in_array($case['state'],['PREPARING','PERSON_CONFIRMED'],true)&&$case['bound_account_id']===$actor->accountId()&&(int)$case['binding_credential_version']===$actor->principal()->credentialVersion()&&is_string($case['binding_reference'])&&$case['binding_reference']!=='';}
}
