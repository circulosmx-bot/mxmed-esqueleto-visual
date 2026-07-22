<?php
declare(strict_types=1);

namespace Patients\Identity;

function patientIdentityResolutionIsclinicalEncounter(): bool { return false; }

final readonly class PatientIdentityPolicy
{
    public const CONTRACT_ID = 'pg03-patient-identity-duplicates';
    public const VERSION = 1;
    public const CANONICAL_SOURCE = 'patients_patients.patient_id';
    public const OWNER_DOMAIN = 'modules/patients';
    public const ACTOR_AUTHORITY_DEPENDENCY = 'pg03-server-authoritative-actors';
    public const PUBLIC_IDENTITY_DEPENDENCY = 'pg03-public-agenda-otp-privacy';
    public const PERSISTENCE_DEFERRED = 'IDENTITY_PERSISTENCE_MIGRATION_RETENTION_ROLLOUT_DEFERRED_TO_GATE_8G';

    private const INPUT_TYPES = ['canonical_patient_id', 'legacy_patient_key_hash'];
    private const SOURCES = ['public_verified', 'private_authenticated', 'legacy_bridge'];
    private const RESULTS = ['already_canonical', 'mapped_from_legacy', 'create_minimal_required', 'review_required', 'ambiguous', 'not_found', 'invalid_candidate_set'];
    private const MODES = ['already_canonical', 'mapped_from_legacy', 'created_minimal_patient', 'unresolved'];
    private const TIERS = ['contact_birthdate_exact', 'contact_name_exact', 'name_birthdate_sex_exact', 'name_birthdate_exact', 'contact_only', 'name_only', 'no_match'];
    private const DECISION_REASONS = ['already_canonical', 'canonical_patient_not_found', 'candidate_not_eligible', 'unique_strong_identity_match', 'multiple_strong_candidates', 'identity_evidence_conflict', 'weak_identity_evidence', 'no_identity_candidate', 'invalid_candidate_set'];
    private const STATUS_REASONS = [
        'already_canonical' => ['already_canonical'],
        'mapped_from_legacy' => ['unique_strong_identity_match'],
        'create_minimal_required' => ['no_identity_candidate'],
        'review_required' => ['candidate_not_eligible', 'identity_evidence_conflict', 'weak_identity_evidence'],
        'ambiguous' => ['multiple_strong_candidates'],
        'not_found' => ['canonical_patient_not_found'],
        'invalid_candidate_set' => ['invalid_candidate_set'],
    ];

    public function contractId(): string { return self::CONTRACT_ID; }
    public function version(): int { return self::VERSION; }
    public function canonicalSource(): string { return self::CANONICAL_SOURCE; }
    public function ownerDomain(): string { return self::OWNER_DOMAIN; }
    public function actorAuthorityDependency(): string { return self::ACTOR_AUTHORITY_DEPENDENCY; }
    public function publicIdentityDependency(): string { return self::PUBLIC_IDENTITY_DEPENDENCY; }
    public function inputTypes(): array { return self::INPUT_TYPES; }
    public function resolutionSources(): array { return self::SOURCES; }
    public function resultStates(): array { return self::RESULTS; }
    public function eventualResolutionModes(): array { return self::MODES; }
    public function matchTiers(): array { return self::TIERS; }
    public function automaticMergeAllowed(): bool { return false; }
    public function manualMergeImplemented(): bool { return false; }
    public function probabilisticMatchingImplemented(): bool { return false; }
    public function rawLegacyKeyAccepted(): bool { return false; }
    public function rawContactAccepted(): bool { return false; }
    public function rawPatientNameAccepted(): bool { return false; }
    public function clinicalEncounter(): bool { return false; }
    public function persistenceDeferred(): string { return self::PERSISTENCE_DEFERRED; }

    public static function reference(string $value, string $reason = 'invalid_identity_reference'): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) throw new PatientIdentityDomainException($reason);
        return $value;
    }

    public static function identifier(string $value, string $reason): string
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_.:-]{0,127}\z/D', $value) !== 1 || preg_match('/\d{8,}/', $value) === 1) {
            throw new PatientIdentityDomainException($reason);
        }
        return $value;
    }

    public static function operationId(string $value): string
    {
        return self::namespacedMetadata($value, ['operation', 'op'], 'invalid_operation_id');
    }

    public static function correlationId(string $value): string
    {
        return self::namespacedMetadata($value, ['correlation', 'corr', 'request', 'req'], 'invalid_correlation_id');
    }

    public static function actorReference(string $value): string
    {
        return self::namespacedMetadata($value, ['account', 'acct', 'operator', 'doctor', 'system', 'support', 'user', 'profile'], 'invalid_actor');
    }

    public static function resultState(string $value): string
    {
        if (!in_array($value, self::RESULTS, true)) throw new PatientIdentityDomainException('invalid_identity_outcome');
        return $value;
    }

    public static function decisionReason(string $value): string
    {
        if (!in_array($value, self::DECISION_REASONS, true)) throw new PatientIdentityDomainException('invalid_decision_reason');
        return $value;
    }

    public static function assertStatusReasonCoherence(string $status, string $reason): void
    {
        $status = self::resultState($status);
        $reason = self::decisionReason($reason);
        if (!in_array($reason, self::STATUS_REASONS[$status], true)) throw new PatientIdentityDomainException('identity_status_reason_mismatch');
    }

    private static function namespacedMetadata(string $value, array $namespaces, string $reason): string
    {
        $namespacePattern = implode('|', array_map(static fn(string $namespace): string => preg_quote($namespace, '/'), $namespaces));
        $matches = [];
        if (
            $value === ''
            || strlen($value) > 128
            || str_contains($value, '@')
            || str_contains($value, '+')
            || preg_match('/\d{4}-\d{2}-\d{2}/', $value) === 1
            || preg_match('/\d{8,}/', $value) === 1
            || preg_match('/\A(?:' . $namespacePattern . ')[_:-]([A-Za-z0-9_.:-]+)\z/D', $value, $matches) !== 1
            || preg_match('/\d/', $matches[1] ?? '') !== 1
        ) {
            throw new PatientIdentityDomainException($reason);
        }
        return $value;
    }

    public static function timestamp(string $value): \DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/D', $value) !== 1) throw new PatientIdentityDomainException('invalid_timestamp');
        try { return new \DateTimeImmutable($value); }
        catch (\Exception) { throw new PatientIdentityDomainException('invalid_timestamp'); }
    }

    public static function canonical(array $value): string
    {
        try { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new PatientIdentityDomainException('invalid_identity_reference'); }
    }

    public static function digest(array $value): string { return hash('sha256', self::canonical($value)); }
    public static function isSource(string $value): bool { return in_array($value, self::SOURCES, true); }
    public static function isInputType(string $value): bool { return in_array($value, self::INPUT_TYPES, true); }
    public static function tierRank(string $tier): int
    {
        $rank = array_search($tier, self::TIERS, true);
        if ($rank === false) throw new PatientIdentityDomainException('invalid_identity_evidence');
        return $rank + 1;
    }
    public function toArray(): array
    {
        return ['contract_id' => self::CONTRACT_ID, 'version' => self::VERSION, 'canonical_source' => self::CANONICAL_SOURCE, 'owner_domain' => self::OWNER_DOMAIN, 'actor_authority_dependency' => self::ACTOR_AUTHORITY_DEPENDENCY, 'public_identity_dependency' => self::PUBLIC_IDENTITY_DEPENDENCY, 'input_types' => self::INPUT_TYPES, 'resolution_sources' => self::SOURCES, 'result_states' => self::RESULTS, 'eventual_resolution_modes' => self::MODES, 'match_tiers' => self::TIERS, 'automatic_merge_allowed' => false, 'manual_merge_implemented' => false, 'probabilistic_matching_implemented' => false, 'raw_legacy_key_accepted' => false, 'raw_contact_accepted' => false, 'raw_patient_name_accepted' => false, 'clinical_encounter' => false, 'persistence_deferred' => self::PERSISTENCE_DEFERRED];
    }
}
