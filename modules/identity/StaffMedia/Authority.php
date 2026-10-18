<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
use Identity\Contracts\AuthenticatedAccessContext;
/** Separate purpose checks, deliberately own-applicant only. No directory or reviewer grant implied. */
final class Authority
{
    public function portrait(AuthenticatedAccessContext $actor,?array $case):bool{return $this->own($actor,$case);}
    public function evidence(AuthenticatedAccessContext $actor,?array $case):bool{return $this->own($actor,$case);}
    private function own(AuthenticatedAccessContext $actor,?array $case):bool
    {return $case!==null&&in_array($case['state'],['PREPARING','PERSON_CONFIRMED'],true)&&$case['bound_account_id']===$actor->accountId()&&(int)$case['binding_credential_version']===$actor->principal()->credentialVersion()&&is_string($case['binding_reference'])&&$case['binding_reference']!=='';}
}
