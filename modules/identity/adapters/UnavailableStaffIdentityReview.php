<?php
declare(strict_types=1);
namespace Identity\Adapters;
use Identity\Contracts\{StaffIdentityReviewPort,StaffIdentityReviewSnapshot};
/** No approved authoritative staff-admission reader is connected. No configuration bypass. */
final class UnavailableStaffIdentityReview implements StaffIdentityReviewPort
{
    public function currentApproved(\PDO $transaction,string $accountId,int $credentialVersion):StaffIdentityReviewSnapshot
    {throw new \RuntimeException('staff_identity_review_source_unavailable');}
}
