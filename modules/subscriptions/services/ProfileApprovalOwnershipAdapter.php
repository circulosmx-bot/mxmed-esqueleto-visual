<?php
declare(strict_types=1);

namespace Subscriptions\Services;

final class ProfileApprovalOwnershipAdapter
{
    private const APPROVAL_STATES = ['pending_review', 'approved', 'rejected', 'suspended'];
    private const OWNERSHIP_STATES = ['unclaimed', 'claim_pending', 'claimed', 'disputed', 'suspended', 'revoked'];

    public function adapt(array $profile, array $actorContext = []): array
    {
        $approval = $this->approvalState($profile);
        $ownership = $this->ownershipState($profile, $actorContext);
        $profileType = strtolower($this->text($profile['profile_type'] ?? 'doctor') ?? 'doctor');
        $actorAuthorized = $this->actorAuthorized($actorContext);
        $policyGateSatisfied = $approval['state'] === 'approved' && $ownership['state'] === 'claimed';

        return [
            'profile_type' => $profileType,
            'approval_state' => $approval['state'],
            'approval_source' => $approval['source'],
            'ownership_state' => $ownership['state'],
            'ownership_source' => $ownership['source'],
            'actor_scope_allowed' => $actorAuthorized,
            'admin_allowed' => $policyGateSatisfied && $actorAuthorized,
            'purchase_allowed' => $policyGateSatisfied && $actorAuthorized,
            'denial_reason' => $this->denialReason($approval['state'], $ownership['state'], $actorAuthorized),
        ];
    }

    private function approvalState(array $profile): array
    {
        $explicit = strtolower($this->text($profile['approval_status'] ?? null) ?? '');
        $aliases = [
            'pending' => 'pending_review',
            'in_review' => 'pending_review',
            'verified' => 'approved',
            'active_approved' => 'approved',
            'denied' => 'rejected',
            'blocked' => 'suspended',
        ];
        $explicit = $aliases[$explicit] ?? $explicit;
        if (in_array($explicit, self::APPROVAL_STATES, true)) {
            return ['state' => $explicit, 'source' => 'profiles_doctors.approval_status'];
        }

        $legacyStatus = strtolower($this->text($profile['profile_status'] ?? null) ?? '');
        $isPublicCandidate = $this->boolean($profile['is_public_candidate'] ?? false);
        if ($legacyStatus === 'active' && $isPublicCandidate) {
            return ['state' => 'approved', 'source' => 'legacy.active_public_candidate'];
        }
        if (in_array($legacyStatus, ['suspended', 'blocked'], true)) {
            return ['state' => 'suspended', 'source' => 'legacy.profile_status'];
        }
        if (in_array($legacyStatus, ['rejected', 'denied'], true)) {
            return ['state' => 'rejected', 'source' => 'legacy.profile_status'];
        }

        return ['state' => 'pending_review', 'source' => 'fail_closed.no_approval_evidence'];
    }

    private function ownershipState(array $profile, array $actorContext): array
    {
        $explicit = strtolower($this->text($profile['ownership_status'] ?? null) ?? '');
        $aliases = [
            'pending' => 'claim_pending',
            'owned' => 'claimed',
            'verified' => 'claimed',
            'blocked' => 'suspended',
            'released' => 'revoked',
        ];
        $explicit = $aliases[$explicit] ?? $explicit;
        if (in_array($explicit, self::OWNERSHIP_STATES, true)) {
            return ['state' => $explicit, 'source' => 'profiles_doctors.ownership_status'];
        }

        $ownerUserId = $this->text($profile['owner_user_id'] ?? null);
        $actorUserId = $this->text($actorContext['actor_user_id'] ?? null);
        if (
            $this->boolean($actorContext['authenticated'] ?? false)
            && $ownerUserId !== null
            && $actorUserId !== null
            && hash_equals($ownerUserId, $actorUserId)
        ) {
            return ['state' => 'claimed', 'source' => 'legacy.owner_user_id_match'];
        }

        $scopeMatches = $this->boolean($actorContext['authenticated'] ?? false)
            && $this->boolean($actorContext['doctor_scope_matches'] ?? false);
        if ($scopeMatches) {
            return ['state' => 'claimed', 'source' => 'legacy.authenticated_doctor_scope'];
        }

        if (
            $this->boolean($actorContext['is_dev_fixture'] ?? false)
            && $this->boolean($actorContext['fixture_claimed'] ?? false)
        ) {
            return ['state' => 'claimed', 'source' => 'protected_dev_fixture'];
        }

        return ['state' => 'unclaimed', 'source' => 'fail_closed.no_ownership_evidence'];
    }

    private function denialReason(string $approvalState, string $ownershipState, bool $actorAuthorized): ?string
    {
        if ($approvalState !== 'approved') {
            return 'profile_not_approved';
        }
        if ($ownershipState === 'disputed') {
            return 'ownership_disputed';
        }
        if (in_array($ownershipState, ['suspended', 'revoked'], true)) {
            return 'ownership_suspended';
        }
        if ($ownershipState !== 'claimed') {
            return 'ownership_required';
        }
        if (!$actorAuthorized) {
            return 'actor_scope_not_allowed';
        }
        return null;
    }

    private function actorAuthorized(array $context): bool
    {
        if (
            $this->boolean($context['is_dev_fixture'] ?? false)
            && $this->boolean($context['fixture_claimed'] ?? false)
        ) {
            return true;
        }
        $role = strtolower($this->text($context['actor_role'] ?? null) ?? '');
        return in_array($role, ['doctor', 'medico', 'principal', 'owner'], true)
            && $this->boolean($context['authenticated'] ?? false)
            && $this->boolean($context['doctor_scope_matches'] ?? ($context['actor_scope_allowed'] ?? false));
    }

    private function text($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function boolean($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
