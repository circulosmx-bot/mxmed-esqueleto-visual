<?php
declare(strict_types=1);
namespace Platform\Contracts;
final readonly class CanonicalAuditEventInput
{
    public function __construct(
        public string $eventType,
        public string $result,
        public string $reasonCode,
        public ?string $effectiveEntityType,
        public ?string $effectiveEntityId,
        public string $targetType,
        public string $targetId,
        public array $metadata = [],
    ) {
        CanonicalAuditEventType::assertKnown($eventType);
        CanonicalAuditResult::assertKnown($result);
        CanonicalAuditReasonCode::assertKnown($reasonCode);
        if ($targetType === '' || $targetId === '') throw new \InvalidArgumentException('missing_audit_target');
        if (($effectiveEntityType === null) !== ($effectiveEntityId === null)) throw new \InvalidArgumentException('incomplete_effective_entity');
    }
}
