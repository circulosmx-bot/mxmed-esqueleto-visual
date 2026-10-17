<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\{Clock,SystemClock};
use Identity\Services\{GovernanceSecurityContext,StaffMfaService};
use Identity\Repositories\ProfessionalAccountBindingProjection;
use Identity\Adapters\CanonicalInitialOwnerEligibilityAdapter;
use Media\Contracts\PrivateMediaStoragePort;
use PDO;

/** Explicit source composition only; never selected by an env flag or HTTP bootstrap.
 * Real provider/key/retention/staff/schema/UI deployment requires separate approval.
 */
final class PhysicianAdmissionComposition
{
    public readonly PhysicianEvidenceService $evidence;
    public readonly ProfessionalAuthorityIssuer $issuer;
    public readonly ProfessionalAccountBindingProjection $projection;
    public readonly CanonicalInitialOwnerEligibilityAdapter $eligibility;
    public function __construct(PDO $identityPdo,GovernanceSecurityContext $security,EvidenceCipher $cipher,
        PrivateMediaStoragePort $storage,private StaffMfaService $mfa,Clock $clock=new SystemClock(),?\Identity\Operational\WorkflowScope $scope=null)
    {
        $authority=new EvidenceAuthority($identityPdo,$security,$clock,$scope);
        $this->evidence=new PhysicianEvidenceService($authority,$cipher,$storage,$mfa,new ProfessionalIssuanceWorkflow($identityPdo));
        $this->issuer=new ProfessionalAuthorityIssuer($authority,$this->evidence,$mfa);
        $this->projection=new ProfessionalAccountBindingProjection($identityPdo,$clock,$this->evidence);
        $this->eligibility=new CanonicalInitialOwnerEligibilityAdapter($this->projection);
    }
    public function mfa():StaffMfaService {return $this->mfa;}
}
