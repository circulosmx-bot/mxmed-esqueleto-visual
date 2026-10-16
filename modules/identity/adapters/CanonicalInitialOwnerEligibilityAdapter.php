<?php
declare(strict_types=1);
namespace Identity\Adapters;
use Identity\Contracts\InitialOwnerEligibilityPort;
use PDO;

/** Uncomposed C4/ADM02 eligibility stays closed; no flag or legacy matching bypass. */
final class CanonicalInitialOwnerEligibilityAdapter implements InitialOwnerEligibilityPort
{
    public function __construct(private ?\Identity\Repositories\ProfessionalAccountBindingProjection $projection=null){}
    public function assertEligible(PDO $transaction, string $accountId, string $doctorId): void
    {
        if($this->projection===null)throw new \RuntimeException('ownership_accreditation_account_binding_contract_unavailable');
        if(!$this->projection->usesConnection($transaction))throw new \RuntimeException('professional_binding_connection_mismatch');
        $this->projection->assertEligible($accountId,$doctorId);
    }
}
