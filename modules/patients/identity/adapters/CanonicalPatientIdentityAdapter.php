<?php
declare(strict_types=1);

namespace Patients\Identity\Adapters;

use Patients\Identity\PatientIdentityCandidateSet;
use Patients\Identity\PatientIdentityMutationPlan;
use Patients\Identity\PatientIdentityResolutionDecision;
use Patients\Identity\PatientIdentityResolutionRequest;
use Patients\Identity\PatientIdentityResolver;
use Platform\Contracts\Pg03CutoverFeatureFlagPort;

require_once __DIR__ . '/../PatientIdentityResolver.php';
require_once __DIR__ . '/../PatientIdentityResolutionRequest.php';
require_once __DIR__ . '/../PatientIdentityCandidateSet.php';
require_once __DIR__ . '/../PatientIdentityResolutionDecision.php';
require_once __DIR__ . '/../PatientIdentityMutationPlan.php';
require_once __DIR__ . '/../../../platform/contracts/Pg03CutoverFeatureFlagPort.php';

final class CanonicalPatientIdentityAdapter
{
    public static function canonicalPatientIdentityEnabled(array $config): bool
    {
        return ($config['feature_flags']['canonical_patient_identity'] ?? null) === true;
    }

    public function resolvePreview(
        PatientIdentityResolutionRequest $request,
        PatientIdentityCandidateSet $candidateSet
    ): PatientIdentityResolutionDecision {
        return (new PatientIdentityResolver())->resolve($request, $candidateSet);
    }

    public function mutationPlan(): array
    {
        return (new PatientIdentityMutationPlan())->toArray();
    }

    public function readiness(Pg03CutoverFeatureFlagPort $flags): array
    {
        return [
            'mode' => 'dormant_preview_only',
            'feature_configured' => $flags->configuredValue('canonical_patient_identity'),
            'feature_effective' => false,
            'persistence_configured' => false,
            'writes_enabled' => false,
            'backfill_enabled' => false,
            'activation_authorized' => false,
            'runtime_wiring' => false,
            'ready' => false,
        ];
    }
}
