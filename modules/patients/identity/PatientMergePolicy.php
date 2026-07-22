<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientMergePolicy
{
    public const REASON = 'MERGE_DISABLED_PENDING_SEPARATE_APPROVAL_AND_IMPLEMENTATION';
    public function automaticMergeAllowed(): bool { return false; }
    public function manualMergeImplemented(): bool { return false; }
    public function survivorSelectionAllowed(): bool { return false; }
    public function sourcePatientDeletionAllowed(): bool { return false; }
    public function careRecordReassignmentAllowed(): bool { return false; }
    public function contactConsolidationAllowed(): bool { return false; }
    public function consentConsolidationAllowed(): bool { return false; }
    public function mergeEndpointAvailable(): bool { return false; }
    public function reason(): string { return self::REASON; }
    public function requestMerge(): never { throw new PatientIdentityDomainException('patient_merge_disabled'); }
    public function requestSurvivorSelection(): never { throw new PatientIdentityDomainException('patient_merge_disabled'); }
    public function requestCareRecordReassignment(): never { throw new PatientIdentityDomainException('patient_merge_disabled'); }
    public function toArray(): array { return ['automatic_merge_allowed' => false, 'manual_merge_implemented' => false, 'survivor_selection_allowed' => false, 'source_patient_deletion_allowed' => false, 'clinical_record_reassignment_allowed' => false, 'contact_consolidation_allowed' => false, 'consent_consolidation_allowed' => false, 'merge_endpoint_available' => false, 'reason' => self::REASON]; }
}
