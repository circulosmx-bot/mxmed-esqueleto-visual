<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\AuthenticatedAccessContext;
use PDO;

/** Protected review discovery only. Candidates never select/change an immutable origin. */
final class ProfileRequestMatcher
{
    public function __construct(private EvidenceAuthority $a) {}
    public function candidates(AuthenticatedAccessContext $reviewer,string $requestId,?string $license,
        ?string $givenNames=null,?string $firstSurname=null,?string $specialty=null,?string $state=null,?string $municipality=null): array
    {
        return $this->a->transaction($reviewer,function()use($reviewer,$requestId,$license,$givenNames,$firstSurname,$specialty,$state,$municipality):array {
            $r=$this->a->request($requestId);$this->a->reviewer($reviewer->accountId(),$r['account_id'],$reviewer->principal()->credentialVersion());
            return ProfessionalMatchCandidates::find($this->a,$license,$givenNames,$firstSurname,$specialty,$state,$municipality)
                +['original_profile_id'=>$r['profile_doctor_id']];
        });
    }
}
