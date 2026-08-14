<?php
declare(strict_types=1);
namespace Platform\Contracts;
final readonly class TrustedActorContext
{
    private function __construct(
        public ?string $authenticatedIdentityId,
        public ?string $realActorType,
        public ?string $realActorId,
        public ?string $effectiveEntityType,
        public ?string $effectiveEntityId,
        public string $actorRole,
        public string $actorScope,
        public ?string $ownershipRelation,
        public bool $delegation,
        public bool $impersonation,
        public bool $breakGlass,
        public string $targetType,
        public string $targetId,
        public ?string $reason,
        public string $authorizationProvenance,
        public bool $authenticationFailure,
        public string $trustSource,
    ) {}

    public static function fromTrustedBackend(array $data): self
    {
        $source = (string)($data['trust_source'] ?? '');
        if (!in_array($source, ['backend_trusted', 'system'], true)) {
            throw new \InvalidArgumentException('untrusted_actor_source');
        }
        $auth = self::optional($data, 'authenticated_identity_id');
        $realType = self::optional($data, 'real_actor_type');
        $realId = self::optional($data, 'real_actor_id');
        $effectiveType = self::optional($data, 'effective_entity_type');
        $effectiveId = self::optional($data, 'effective_entity_id');
        $delegation = (bool)($data['delegation'] ?? false);
        $impersonation = (bool)($data['impersonation'] ?? false);
        $breakGlass = (bool)($data['break_glass'] ?? false);
        $authFailure = (bool)($data['authentication_failure'] ?? false);
        $reason = self::optional($data, 'reason');
        if (($realType === null) !== ($realId === null) || ($effectiveType === null) !== ($effectiveId === null)) {
            throw new \InvalidArgumentException('incomplete_actor_reference');
        }
        if ($source !== 'system' && !$authFailure && ($auth === null || $realId === null)) {
            throw new \InvalidArgumentException('unknown_actor_for_normal_event');
        }
        if (($delegation || $impersonation) && ($realId === null || $effectiveId === null)) {
            throw new \InvalidArgumentException('real_and_effective_actor_required');
        }
        if (($delegation || $impersonation || $breakGlass) && $reason === null) {
            throw new \InvalidArgumentException('actor_context_reason_required');
        }
        foreach (['actor_role', 'actor_scope', 'target_type', 'target_id', 'authorization_provenance'] as $field) {
            if (trim((string)($data[$field] ?? '')) === '') {
                throw new \InvalidArgumentException('missing_actor_context:' . $field);
            }
        }
        return new self($auth, $realType, $realId, $effectiveType, $effectiveId,
            (string)$data['actor_role'], (string)$data['actor_scope'], self::optional($data, 'ownership_relation'),
            $delegation, $impersonation, $breakGlass, (string)$data['target_type'], (string)$data['target_id'],
            $reason, (string)$data['authorization_provenance'], $authFailure, $source);
    }

    public function auditIdentityId(): string { return $this->authenticatedIdentityId ?? 'UNKNOWN'; }
    public function auditActorType(): string { return $this->realActorType ?? ($this->trustSource === 'system' ? 'SYSTEM' : 'UNKNOWN'); }

    private static function optional(array $data, string $field): ?string
    {
        $value = trim((string)($data[$field] ?? ''));
        return $value === '' ? null : $value;
    }
}
