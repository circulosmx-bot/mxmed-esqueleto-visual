<?php
declare(strict_types=1);
namespace Identity\Contracts;
final readonly class StaffIdentityReviewSnapshot
{
    public function __construct(public string $accountId,public int $credentialVersion,public string $reviewVersion)
    {
        if($accountId===''||$credentialVersion<1||$reviewVersion===''||strlen($reviewVersion)>128)throw new \RuntimeException('staff_review_invalid');
    }
    public function values():array{return ['account_id'=>$this->accountId,'credential_version'=>$this->credentialVersion,'review_version'=>$this->reviewVersion];}
}
