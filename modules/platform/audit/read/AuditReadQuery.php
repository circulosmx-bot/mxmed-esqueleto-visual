<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

/** Immutable, bounded and fixed-order read request; it contains no requester identity or role. */
final readonly class AuditReadQuery
{
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;
    public const SORT = 'created_at_desc_event_id_desc';

    private function __construct(
        public AuditReadFilter $filter,
        public ?string $cursor,
        public int $pageSize,
        public string $sort,
        public AuditReadProjection $projection,
        public string $capability,
        public string $scope,
        public string $scopeValue,
        public string $accessReason,
    ) {
        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw new \InvalidArgumentException('audit_read_page_size_out_of_bounds');
        }
        if ($sort !== self::SORT) {
            throw new \InvalidArgumentException('unsupported_audit_read_sort');
        }
        if ($cursor !== null && ($cursor === '' || strlen($cursor) > 1024 || preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $cursor) !== 1)) {
            throw new \InvalidArgumentException('invalid_audit_read_cursor_transport');
        }
        AuditReadAccess::assertCombination($capability, $scope, $accessReason);
        $projection->assertCompatible($capability);
        self::assertScopeValue($scopeValue);
    }

    public static function selfSecurity(
        AuditReadFilter $filter,
        ?string $cursor = null,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
        string $sort = self::SORT,
    ): self {
        $filter->assertSafeForSelfTimeline();
        return new self($filter, $cursor, $pageSize, $sort, AuditReadProjection::named(AuditReadProjection::SELF_SECURITY), AuditReadAccess::SELF_SECURITY, AuditReadAccess::SCOPE_SELF_ACCOUNT, 'TRUSTED_SELF', AuditReadAccess::REASON_SELF_SECURITY);
    }

    public static function internalScoped(
        AuditReadFilter $filter,
        string $capability,
        string $scope,
        string $scopeValue,
        string $accessReason,
        AuditReadProjection $projection,
        ?string $cursor = null,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
        string $sort = self::SORT,
    ): self {
        if ($capability === AuditReadAccess::SELF_SECURITY) {
            throw new \InvalidArgumentException('internal_read_requires_internal_capability');
        }
        return new self($filter, $cursor, $pageSize, $sort, $projection, $capability, $scope, $scopeValue, $accessReason);
    }

    private static function assertScopeValue(string $value): void
    {
        if ($value === '' || $value !== trim($value) || strlen($value) > 160 || $value === '*'
            || preg_match('/^[A-Za-z0-9._:@\/-]+$/D', $value) !== 1
            || preg_match('/(?:password|credential|secret|bearer|otp|raw[_-]?token|magic[_-]?link)/i', $value) === 1) {
            throw new \InvalidArgumentException('invalid_audit_read_scope_value');
        }
    }
}
