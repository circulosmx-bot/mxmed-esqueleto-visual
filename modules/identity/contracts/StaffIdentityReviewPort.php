<?php
declare(strict_types=1);
namespace Identity\Contracts;
use PDO;
/** Staff-specific authority. Must return a current completed review under this transaction's locks. */
interface StaffIdentityReviewPort
{
    public function currentApproved(PDO $transaction,string $accountId,int $credentialVersion): StaffIdentityReviewSnapshot;
}
