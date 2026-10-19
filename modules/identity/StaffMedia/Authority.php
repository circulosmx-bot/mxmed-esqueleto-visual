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
        // Only the creating active Director may prepare a portrait before account binding.
        // Own applicant/evidence policy is unchanged; no blanket administrative evidence access.
        if($this->portraitDirectory&&$case&&$case['state']==='PREPARING'&&$case['bound_account_id']===null&&$case['creator_account_id']===$actor->accountId()){
            $q=$this->portraitDirectory->prepare("SELECT 1 FROM internal_staff WHERE account_id=? AND governance_class='DIRECTOR' AND status='ACTIVE' FOR UPDATE");
            $q->execute([$actor->accountId()]);return (bool)$q->fetchColumn();
        }
        if(!$this->portraitDirectory||!$case||!in_array($case['state'],['PREPARING','PERSON_CONFIRMED'],true)||!$case['bound_account_id']||$case['bound_account_id']===$actor->accountId())return false;
        $q=$this->portraitDirectory->prepare("SELECT 1 FROM internal_staff d JOIN auth_staff_admission_completions c ON c.case_id=? AND c.account_id=? JOIN internal_staff t ON t.account_id=c.account_id JOIN auth_accounts a ON a.account_id=t.account_id WHERE d.account_id=? AND d.governance_class='DIRECTOR' AND d.status='ACTIVE' AND t.status<>'REMOVED' AND a.status='active' FOR UPDATE");
        $q->execute([$case['case_id'],$case['bound_account_id'],$actor->accountId()]);return (bool)$q->fetchColumn();
    }
    public function evidence(AuthenticatedAccessContext $actor,?array $case):bool{return $this->own($actor,$case);}
    private function own(AuthenticatedAccessContext $actor,?array $case):bool
    {return $case!==null&&in_array($case['state'],['PREPARING','PERSON_CONFIRMED'],true)&&$case['bound_account_id']===$actor->accountId()&&(int)$case['binding_credential_version']===$actor->principal()->credentialVersion()&&is_string($case['binding_reference'])&&$case['binding_reference']!=='';}
}
