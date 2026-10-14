<?php
declare(strict_types=1);

namespace Identity\Contracts;

/** Immutable, server-built command for one exact profile membership transition. */
final readonly class ProfileMembershipRoleChangeRequest
{
    public function __construct(
        public string $operationId,
        public string $membershipId,
        public string $targetAccountId,
        public string $profileDoctorId,
        public string $expectedRole,
        public string $expectedScope,
        public string $expectedStatus,
        public string $expectedUpdatedAt,
        public string $newRole,
        public string $approvalReference,
        public string $stepUpReference,
    ) {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $operationId) !== 1) {
            throw new \InvalidArgumentException('profile_role_change_invalid_operation_id');
        }
        foreach ([$membershipId, $targetAccountId, $profileDoctorId] as $identifier) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D', $identifier) !== 1) {
                throw new \InvalidArgumentException('profile_role_change_invalid_identifier');
            }
        }
        foreach ([$approvalReference, $stepUpReference] as $reference) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,127}$/D', $reference) !== 1) {
                throw new \InvalidArgumentException('profile_role_change_invalid_evidence_reference');
            }
        }
        MembershipRole::assertValid($expectedRole);
        MembershipRole::assertValid($newRole);
        MembershipStatus::assertValid($expectedStatus);
        if (!in_array($expectedScope, ['profile', 'profile_doctor'], true)) {
            throw new \InvalidArgumentException('profile_role_change_invalid_scope');
        }
        if ($expectedStatus !== MembershipStatus::ACTIVE || $newRole !== MembershipRole::OWNER
            || !in_array($expectedRole, [MembershipRole::ADMINISTRATOR, MembershipRole::COLLABORATOR], true)) {
            throw new \InvalidArgumentException('profile_role_change_transition_not_permitted');
        }
        $version = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expectedUpdatedAt, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$version || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $version->format('Y-m-d H:i:s') !== $expectedUpdatedAt) {
            throw new \InvalidArgumentException('profile_role_change_invalid_expected_version');
        }
    }
}
