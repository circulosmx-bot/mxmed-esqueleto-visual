<?php
declare(strict_types=1);

namespace Identity\Http;

use Identity\Contracts\AuthenticatedAccessContext;
use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Services\CurrentSubscriptionReadModelService;

final class CanonicalClinicalIdentityContext
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $membershipId,
        public readonly string $doctorId,
        public readonly string $roleCode,
        public readonly string $scopeCode,
        public readonly string $sessionId,
        public readonly array $capability
    ) {}

    public function toArray(): array
    {
        return ['account_id'=>$this->accountId,'membership_id'=>$this->membershipId,
            'doctor_id'=>$this->doctorId,'role_code'=>$this->roleCode,'scope_code'=>$this->scopeCode,
            'session_id'=>$this->sessionId,'capability'=>$this->capability];
    }
}

final class CanonicalClinicalIdentityResolution
{
    public function __construct(public readonly ?CanonicalClinicalIdentityContext $context,
        public readonly string $reason, public readonly int $httpStatus) {}
    public function allowed(): bool { return $this->context !== null; }
}

final class CanonicalDoctorMembershipPolicy
{
    /** @return array{membership:?array,reason:string} */
    public function select(array $memberships): array
    {
        $doctorMemberships=array_values(array_filter($memberships,static fn(mixed $row):bool=>is_array($row)
            &&trim((string)($row['profile_doctor_id']??''))!==''));
        if(count($doctorMemberships)!==1)return ['membership'=>null,'reason'=>count($doctorMemberships)===0?'membership_missing':'membership_ambiguous'];
        $membership=$doctorMemberships[0];
        // Non-medical appointment is never physician-shell/clinical authority,
        // including a later administrator/owner label. Separate granular consumer required.
        if(($membership['assignment_source']??'')==='owner_operator_invitation')
            return ['membership'=>null,'reason'=>'nonmedical_operator_requires_granular_consumer'];
        if(($membership['status']??'')!=='active'||($membership['entity_group_id']??null)!==null
            ||!in_array((string)($membership['scope_code']??''),['profile','profile_doctor'],true)
            ||!in_array((string)($membership['role_code']??''),['owner','administrator','collaborator'],true))
            return ['membership'=>null,'reason'=>'membership_invalid'];
        return ['membership'=>$membership,'reason'=>'allowed'];
    }
}

/** Resolves one immutable, request-scoped clinical authority from Identity. */
final class CanonicalClinicalIdentityResolver
{
    public function __construct(private IdentityHttpComposition $identity) {}

    public static function fromProcessEnvironment(): self
    {
        return new self(IdentityHttpComposition::fromProcessEnvironment());
    }

    public function resolve(array $cookies, string $capabilityId): CanonicalClinicalIdentityResolution
    {
        try {
            $access=(new CanonicalHttpSessionResolver($this->identity->sessions()))->resolve($cookies);
            if(!$access instanceof AuthenticatedAccessContext)return $this->deny('authentication_required',401);
            $memberships=$this->identity->memberships()->activeForAccount($access->accountId());
            $selection=(new CanonicalDoctorMembershipPolicy())->select($memberships);
            if(!is_array($selection['membership']))return $this->deny($selection['reason'],403);
            $membership=$selection['membership'];$doctorId=trim((string)$membership['profile_doctor_id']);
            $profile=$this->identity->pdo()->prepare('SELECT COUNT(*) FROM profiles_doctors WHERE doctor_id=:doctor_id');
            $profile->execute(['doctor_id'=>$doctorId]);
            if((int)$profile->fetchColumn()!==1)return $this->deny('doctor_profile_missing',403);
            $subscription=(new CurrentSubscriptionReadModelService(new CurrentSubscriptionRepository($this->identity->pdo())))
                ->resolveForEntity('doctor',$doctorId);
            $decision=$this->identity->authorization()->authorize($access,'profile_doctor',$doctorId,$capabilityId,$subscription);
            if(!$decision->allowed())return $this->deny('capability_denied',403);
            return new CanonicalClinicalIdentityResolution(new CanonicalClinicalIdentityContext(
                $access->accountId(),(string)$membership['membership_id'],$doctorId,(string)$membership['role_code'],
                (string)$membership['scope_code'],(string)$access->session()->sessionId(),$decision->capability()??[]
            ),'allowed',200);
        }catch(\Throwable){return $this->deny('authority_unavailable',503);}
    }

    private function deny(string $reason,int $status):CanonicalClinicalIdentityResolution
    {return new CanonicalClinicalIdentityResolution(null,$reason,$status);}
}
