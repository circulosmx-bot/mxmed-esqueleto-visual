<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class CanonicalProfileReference
{
    private string $targetType;
    private string $targetId;

    private function __construct(string $targetType, string $targetId)
    {
        $targetId = trim($targetId);
        if ($targetId === '' || strlen($targetId) > 64 || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $targetId) !== 1) {
            throw new \InvalidArgumentException('invalid_canonical_profile_reference');
        }
        $this->targetType = $targetType;
        $this->targetId = $targetId;
    }

    public static function profile(string $doctorId): self
    {
        return new self('profile_doctor', $doctorId);
    }

    public static function organization(string $groupId): self
    {
        return new self('medical_group', $groupId);
    }

    public function targetType(): string { return $this->targetType; }
    public function targetId(): string { return $this->targetId; }
    public function isProfile(): bool { return $this->targetType === 'profile_doctor'; }
    public function isOrganization(): bool { return $this->targetType === 'medical_group'; }
}
