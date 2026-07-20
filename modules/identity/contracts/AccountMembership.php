<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class AccountMembership
{
    private string $membershipId;
    private string $accountId;
    private CanonicalProfileReference $reference;
    private string $role;
    private string $scope;
    private string $status;
    private string $source;

    public function __construct(
        string $membershipId,
        string $accountId,
        CanonicalProfileReference $reference,
        string $role,
        string $scope,
        string $status = MembershipStatus::PENDING,
        string $source = 'manual_review'
    ) {
        $this->membershipId = self::assertIdentifier($membershipId, 'invalid_membership_id');
        $this->accountId = self::assertIdentifier($accountId, 'invalid_account_id');
        $this->reference = $reference;
        $this->role = MembershipRole::assertValid($role);
        $this->scope = self::assertText($scope, 64, 'invalid_membership_scope');
        $this->status = MembershipStatus::assertValid($status);
        $this->source = self::assertText($source, 64, 'invalid_membership_source');
    }

    public function membershipId(): string { return $this->membershipId; }
    public function accountId(): string { return $this->accountId; }
    public function reference(): CanonicalProfileReference { return $this->reference; }
    public function role(): string { return $this->role; }
    public function scope(): string { return $this->scope; }
    public function status(): string { return $this->status; }
    public function source(): string { return $this->source; }
    public function grantsAuthority(): bool { return $this->status === MembershipStatus::ACTIVE; }

    private static function assertIdentifier(string $value, string $error): string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,63}$/', $value) !== 1) {
            throw new \InvalidArgumentException($error);
        }
        return $value;
    }

    private static function assertText(string $value, int $maxLength, string $error): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException($error);
        }
        return $value;
    }
}
