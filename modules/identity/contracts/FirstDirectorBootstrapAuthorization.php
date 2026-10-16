<?php
declare(strict_types=1);

namespace Identity\Contracts;

/** Trusted R3 ceremony evidence. It can only be produced by a ratified external trust-anchor adapter. */
final readonly class FirstDirectorBootstrapAuthorization
{
    /** @param list<string> $ratifierAccountIds @param array<string,string> $approvalReferencesByRatifier */
    public function __construct(
        public string $operationId,
        public string $targetAccountId,
        public string $caseReference,
        public string $trustAnchorReference,
        public string $ratifierSessionReference,
        public array $ratifierAccountIds,
        public array $approvalReferencesByRatifier,
        public bool $reauthenticationVerified,
        public bool $mfaVerified,
        public \DateTimeImmutable $authorizedAt,
        public \DateTimeImmutable $expiresAt,
        public ?string $acceptingAccountId = null,
        public ?string $acceptingSessionId = null,
        public ?int $acceptingCredentialVersion = null,
        public ?string $acceptingReauthenticatedAt = null,
    ) {
        if(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$operationId)!==1)
            throw new \InvalidArgumentException('first_director_invalid_operation_id');
        foreach([$targetAccountId,$caseReference,$trustAnchorReference,$ratifierSessionReference] as $value)
            if(preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,127}$/D',$value)!==1)
                throw new \InvalidArgumentException('first_director_invalid_reference');
        if(count($ratifierAccountIds)!==2||count(array_unique($ratifierAccountIds,SORT_STRING))!==2
            ||in_array($targetAccountId,$ratifierAccountIds,true))
            throw new \InvalidArgumentException('first_director_independent_ratifiers_required');
        foreach($ratifierAccountIds as $id)if(preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D',$id)!==1)
            throw new \InvalidArgumentException('first_director_invalid_ratifier');
        $ratifiers=$ratifierAccountIds;$approvalRatifiers=array_keys($approvalReferencesByRatifier);
        sort($ratifiers,SORT_STRING);sort($approvalRatifiers,SORT_STRING);
        if($approvalRatifiers!==$ratifiers||count(array_unique(array_values($approvalReferencesByRatifier),SORT_STRING))!==2)
            throw new \InvalidArgumentException('first_director_dual_approval_required');
        foreach($approvalReferencesByRatifier as $reference)if(preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,127}$/D',$reference)!==1)
            throw new \InvalidArgumentException('first_director_invalid_approval');
        if(!$reauthenticationVerified||!$mfaVerified||$expiresAt<=$authorizedAt)
            throw new \InvalidArgumentException('first_director_r3_controls_required');
        if($acceptingAccountId!==null&&($acceptingAccountId!==$targetAccountId||strlen($acceptingSessionId??'')<8
            ||($acceptingCredentialVersion??0)<1||$acceptingReauthenticatedAt===null))throw new \InvalidArgumentException('first_director_acceptance_binding_invalid');
    }

    public function withAcceptance(array $binding):self
    {
        return new self($this->operationId,$this->targetAccountId,$this->caseReference,$this->trustAnchorReference,
            $this->ratifierSessionReference,$this->ratifierAccountIds,$this->approvalReferencesByRatifier,
            $this->reauthenticationVerified,$this->mfaVerified,$this->authorizedAt,$this->expiresAt,
            $binding['account_id'],$binding['session_id'],$binding['credential_version'],$binding['reauthenticated_at']);
    }
}
