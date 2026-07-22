<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientIdentityResolver
{
    public function resolve(PatientIdentityResolutionRequest $request, PatientIdentityCandidateSet $candidateSet): PatientIdentityResolutionDecision
    {
        if ($request->inputType() === 'canonical_patient_id') return $this->resolveCanonical($request, $candidateSet);
        return $this->resolveLegacy($request, $candidateSet);
    }

    private function resolveCanonical(PatientIdentityResolutionRequest $request, PatientIdentityCandidateSet $candidateSet): PatientIdentityResolutionDecision
    {
        $patientId = $request->canonicalPatientId();
        if ($patientId === null) throw new PatientIdentityDomainException('invalid_resolution_request');
        $candidate = $candidateSet->find($patientId);
        if ($candidate === null) return PatientIdentityResolutionDecision::create('not_found', 'canonical_patient_not_found', null, 'no_match', null, $candidateSet, $request);
        if (!$candidate->identityEligible()) {
            $review = new PatientDuplicateReview('candidate_not_eligible', [$patientId], 'no_match', $request->fingerprint());
            return PatientIdentityResolutionDecision::create('review_required', 'candidate_not_eligible', null, 'no_match', $review, $candidateSet, $request);
        }
        return PatientIdentityResolutionDecision::create('already_canonical', 'already_canonical', $patientId, 'no_match', null, $candidateSet, $request);
    }

    private function resolveLegacy(PatientIdentityResolutionRequest $request, PatientIdentityCandidateSet $candidateSet): PatientIdentityResolutionDecision
    {
        $input = $request->evidence();
        if ($input === null) throw new PatientIdentityDomainException('invalid_resolution_request');
        $evaluations = [];
        foreach ($candidateSet->candidates() as $candidate) $evaluations[] = $this->evaluate($input, $candidate);
        usort($evaluations, static fn(array $left, array $right): int => [$left['rank'], $left['id']] <=> [$right['rank'], $right['id']]);

        $strong = array_values(array_filter($evaluations, static fn(array $row): bool => $row['strong'] && !$row['contradiction'] && $row['eligible']));
        if ($strong !== []) {
            $topRank = $strong[0]['rank'];
            $top = array_values(array_filter($strong, static fn(array $row): bool => $row['rank'] === $topRank));
            if (count($top) > 1) {
                $ids = array_column($top, 'id');
                $review = new PatientDuplicateReview('multiple_strong_candidates', $ids, $top[0]['tier'], $request->fingerprint());
                return PatientIdentityResolutionDecision::create('ambiguous', 'multiple_strong_candidates', null, $top[0]['tier'], $review, $candidateSet, $request);
            }
        }

        $conflicts = array_values(array_filter($evaluations, static fn(array $row): bool => $row['contradiction']));
        if ($conflicts !== []) {
            $ids = array_column($conflicts, 'id');
            $highest = $conflicts[0]['tier'];
            $review = new PatientDuplicateReview(count($ids) > 1 ? 'multiple_strong_candidates' : 'identity_evidence_conflict', $ids, $highest, $request->fingerprint());
            return PatientIdentityResolutionDecision::create(count($ids) > 1 ? 'ambiguous' : 'review_required', count($ids) > 1 ? 'multiple_strong_candidates' : 'identity_evidence_conflict', null, $highest, $review, $candidateSet, $request);
        }

        if ($strong !== []) {
            $winner = $strong[0];
            return PatientIdentityResolutionDecision::create('mapped_from_legacy', 'unique_strong_identity_match', $winner['candidate']->patientId(), $winner['tier'], null, $candidateSet, $request);
        }

        $weak = array_values(array_filter($evaluations, static fn(array $row): bool => $row['weak']));
        if ($weak !== []) {
            $ids = array_column($weak, 'id');
            $review = new PatientDuplicateReview('weak_identity_evidence', $ids, $weak[0]['tier'], $request->fingerprint());
            return PatientIdentityResolutionDecision::create('review_required', 'weak_identity_evidence', null, $weak[0]['tier'], $review, $candidateSet, $request);
        }

        return PatientIdentityResolutionDecision::create('create_minimal_required', 'no_identity_candidate', null, 'no_match', null, $candidateSet, $request);
    }

    private function evaluate(PatientIdentityEvidence $input, PatientIdentityCandidate $candidate): array
    {
        $existing = $candidate->evidence();
        $name = hash_equals($input->nameReference(), $existing->nameReference());
        $birth = $input->birthdateReference() !== null && $existing->birthdateReference() !== null && hash_equals($input->birthdateReference(), $existing->birthdateReference());
        $phone = $input->phoneReference() !== null && $existing->phoneReference() !== null && hash_equals($input->phoneReference(), $existing->phoneReference());
        $email = $input->emailReference() !== null && $existing->emailReference() !== null && hash_equals($input->emailReference(), $existing->emailReference());
        $contact = $phone || $email;
        $sex = $input->sex() !== null && $existing->sex() !== null && $input->sex() === $existing->sex();
        $birthConflict = $input->birthdateReference() !== null && $existing->birthdateReference() !== null && !$birth && ($contact || $name);

        if ($contact && $birth) $tier = 'contact_birthdate_exact';
        elseif ($contact && $name) $tier = 'contact_name_exact';
        elseif ($name && $birth && $sex) $tier = 'name_birthdate_sex_exact';
        elseif ($name && $birth) $tier = 'name_birthdate_exact';
        elseif ($contact) $tier = 'contact_only';
        elseif ($name) $tier = 'name_only';
        else $tier = 'no_match';

        $strong = in_array($tier, ['contact_birthdate_exact', 'contact_name_exact', 'name_birthdate_sex_exact'], true);
        $contradiction = $birthConflict || ($strong && !$candidate->identityEligible());
        return ['id' => $candidate->patientId()->value(), 'candidate' => $candidate, 'tier' => $tier, 'rank' => PatientIdentityPolicy::tierRank($tier), 'strong' => $strong, 'weak' => in_array($tier, ['name_birthdate_exact', 'contact_only', 'name_only'], true), 'eligible' => $candidate->identityEligible(), 'contradiction' => $contradiction];
    }
}
