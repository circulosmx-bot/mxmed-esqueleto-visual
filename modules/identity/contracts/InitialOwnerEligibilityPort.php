<?php
declare(strict_types=1);
namespace Identity\Contracts;
use PDO;

/** Trusted eligibility evidence, never a browser assertion or an ownership role. */
interface InitialOwnerEligibilityPort
{
    public function assertEligible(PDO $transaction, string $accountId, string $doctorId): void;
}
