<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
use Identity\Contracts\IdentityAccount;
/** Explicit validity/key contract. No product default TTL or implicit ratification. */
final class InvitationPolicy
{
    public function __construct(public readonly int $validitySeconds,public readonly string $validityReference,
        public readonly string $keyId,private readonly string $recipientKey)
    {
        if($validitySeconds<1||$validitySeconds>PHP_INT_MAX-2147483647||strlen($recipientKey)<32
            ||!preg_match('/^[A-Za-z0-9_.:-]{8,128}$/D',$keyId)||!preg_match('/^[A-Za-z0-9_.:-]{8,128}$/D',$validityReference))throw new \InvalidArgumentException('invitation_policy_required');
    }
    public function recipient(string $case,string $email):string
    {return hash_hmac('sha256',"staff-admission-recipient\0".$case."\0".IdentityAccount::normalizeEmail($email),$this->recipientKey);}
    public function request(string $action,array $body):string
    {ksort($body);return hash_hmac('sha256',"staff-admission-operation\0".json_encode([$action,$body],JSON_THROW_ON_ERROR),$this->recipientKey);}
}
