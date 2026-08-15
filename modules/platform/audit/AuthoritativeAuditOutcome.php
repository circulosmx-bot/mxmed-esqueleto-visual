<?php
declare(strict_types=1);

namespace Platform\Audit;

use Platform\Contracts\CanonicalAuditReasonCode;
use Platform\Contracts\CanonicalAuditResult;

/** Typed handoff available only after the authoritative domain outcome. */
final readonly class AuthoritativeAuditOutcome
{
    /** @param array<string,mixed> $metadata */
    private function __construct(
        public string $eventType,
        public string $result,
        public string $reasonCode,
        public AuthoritativeAuditTarget $target,
        public array $metadata,
        public ?string $sensitiveAdminActionKey,
        public string $outcomeAuthority,
    ) {}

    public static function afterCommitted(
        Mp01fEventScopePolicy $scope,
        string $eventType,
        string $result,
        string $reasonCode,
        AuthoritativeAuditTarget $target,
        bool $authoritativeOutcomeCommitted,
        ?ChangedFieldNames $changedFields = null,
    ): self {
        $scope->assertEvent($eventType);
        if ($eventType === 'SENSITIVE_ADMIN_ACTION') {
            throw new \InvalidArgumentException('sensitive_admin_requires_catalog_key');
        }
        if (!$authoritativeOutcomeCommitted) {
            throw new \LogicException('authoritative_outcome_not_committed');
        }
        CanonicalAuditResult::assertKnown($result);
        CanonicalAuditReasonCode::assertKnown($reasonCode);
        $metadata = $changedFields === null ? [] : ['changed_field_names' => $changedFields->values];
        return new self($eventType, $result, $reasonCode, $target, $metadata, null, 'committed_domain_outcome');
    }

    public static function sensitiveAdminAfterCommitted(
        SensitiveAdminActionKey $action,
        string $result,
        string $reasonCode,
        AuthoritativeAuditTarget $target,
        bool $authoritativeOutcomeCommitted,
        ?ChangedFieldNames $changedFields = null,
    ): self {
        if (!$authoritativeOutcomeCommitted) {
            throw new \LogicException('authoritative_outcome_not_committed');
        }
        if ($target->type !== $action->targetType) {
            throw new \InvalidArgumentException('sensitive_admin_target_type_mismatch');
        }
        CanonicalAuditResult::assertKnown($result);
        CanonicalAuditReasonCode::assertKnown($reasonCode);
        $metadata = ['action_code' => $action->value];
        if ($changedFields !== null) {
            $metadata['changed_field_names'] = $changedFields->values;
        }
        return new self('SENSITIVE_ADMIN_ACTION', $result, $reasonCode, $target, $metadata, $action->value, 'committed_sensitive_admin_outcome');
    }
}
