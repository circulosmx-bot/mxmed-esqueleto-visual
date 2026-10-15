<?php
declare(strict_types=1);

/**
 * Fail-closed boundary for acts that require the authenticated account to be
 * independently bound to the verified professional represented by a profile.
 *
 * MXMED currently verifies physician-profile identity and credentials, but it
 * has no canonical record that binds either proof to an Identity account. A
 * profile membership, including owner, is deliberately insufficient here.
 */
final class ClinicalProfessionalAuthorityException extends RuntimeException
{
    public const ERROR_CODE = 'PROFESSIONAL_AUTHORITY_REQUIRED';

    public function __construct(public readonly string $operation)
    {
        parent::__construct(self::ERROR_CODE);
    }

    public function httpStatus(): int
    {
        return 403;
    }
}

/** @return array{allowed:bool,reason:string,source:string,operation:string} */
function clinical_professional_authority_decision(array $doctor, string $operation): array
{
    $doctorId = trim((string)($doctor['doctor_id'] ?? ''));
    $accountId = trim((string)($doctor['user_id'] ?? $doctor['account_id'] ?? ''));
    $operation = strtoupper(trim($operation));

    if ($doctorId === '' || $accountId === '' || $operation === '') {
        return ['allowed'=>false, 'reason'=>'invalid_professional_context',
            'source'=>'none', 'operation'=>$operation];
    }

    // profiles_verified_identities and profiles_doctor_credentials prove facts
    // about the profile. Neither table binds the authenticated account to those
    // facts, so no current record can authorize a professional-only operation.
    return ['allowed'=>false, 'reason'=>'account_professional_binding_unavailable',
        'source'=>'no_canonical_account_professional_binding', 'operation'=>$operation];
}

function clinical_professional_authority_assert(array $doctor, string $operation): void
{
    if (clinical_professional_authority_decision($doctor, $operation)['allowed'] !== true) {
        throw new ClinicalProfessionalAuthorityException($operation);
    }
}

function clinical_professional_authority_doctor_signature_present(array $payload): bool
{
    $signature = is_array($payload['signatures']['doctor'] ?? null)
        ? $payload['signatures']['doctor'] : [];
    return trim((string)($signature['image_data'] ?? '')) !== '';
}

/** Preserve drafts, but require professional proof before physician signature or issuance. */
function clinical_professional_authority_assert_document_transition(
    array $doctor,
    array $payload,
    string $intent,
    string $operation
): void {
    if ($intent !== 'draft' || clinical_professional_authority_doctor_signature_present($payload)) {
        clinical_professional_authority_assert($doctor, $operation);
    }
}
