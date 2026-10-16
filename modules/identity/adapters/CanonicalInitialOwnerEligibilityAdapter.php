<?php
declare(strict_types=1);
namespace Identity\Adapters;
use Identity\Contracts\InitialOwnerEligibilityPort;
use PDO;

/** VID01/CRD02 facts do not prove that this Identity account is the physician.
 * C4/ADM02 has no approved effective account/accreditation binding yet. */
final class CanonicalInitialOwnerEligibilityAdapter implements InitialOwnerEligibilityPort
{
    public function assertEligible(PDO $transaction, string $accountId, string $doctorId): void
    {
        throw new \RuntimeException('ownership_accreditation_account_binding_contract_unavailable');
    }
}
