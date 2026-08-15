<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

use Platform\Audit\Read\Contracts\AuditReadRepositoryPort;

/** Dormant application service: bounded reads, defense-in-depth validation and no emission/wiring. */
final class AuditReadService
{
    public function __construct(
        private AuditReadRepositoryPort $repository,
        private AuditReadAuthorizer $authorizer,
        private SelfSecurityTimelinePolicy $selfTimeline,
        private AuditReadCursorCodec $cursorCodec,
    ) {}

    public function read(AuditReadQuery $query, TrustedAuditReadAuthority $authority): AuditReadPage
    {
        $authorized = $this->authorizer->authorize($query, $authority);
        $after = $query->cursor === null ? null : $this->cursorCodec->decode($query->cursor);
        $limit = $query->pageSize + 1;
        $rows = $this->repository->fetch($authorized, $after, $limit);
        if (!array_is_list($rows) || count($rows) > $limit) {
            throw new \UnexpectedValueException('audit_read_repository_bound_violation');
        }
        $previousKey = null;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \UnexpectedValueException('invalid_audit_read_row');
            }
            $key = $this->assertCanonicalOrderKey($row);
            if ($previousKey !== null && strcmp($previousKey, $key) <= 0) {
                throw new \UnexpectedValueException('non_deterministic_audit_read_order');
            }
            $previousKey = $key;
            $this->assertFilterMatch($row, $query->filter);
            $this->assertScopeMatch($row, $authorized);
            if ($authorized->forcedSelfIdentityId !== null) {
                $this->selfTimeline->assertEligibleRow($row, $authorized->forcedSelfIdentityId);
            }
        }

        $hasMore = count($rows) > $query->pageSize;
        $visibleRows = array_slice($rows, 0, $query->pageSize);
        $items = array_map(fn(array $row): array => $query->projection->project($row), $visibleRows);
        $nextCursor = null;
        if ($hasMore && $visibleRows !== []) {
            $last = $visibleRows[array_key_last($visibleRows)];
            $nextCursor = $this->cursorCodec->encode(new AuditReadCursor($last['created_at'], $last['event_id']));
        }
        $filterJson = json_encode($query->filter->values, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $intent = [
            'auditable' => true,
            'emission_active' => false,
            'requester_identity_id' => $authorized->requesterIdentityId,
            'capability' => $query->capability,
            'read_scope' => $query->scope,
            'scope_value' => $authorized->forcedSelfIdentityId ?? $query->scopeValue,
            'access_reason' => $query->accessReason,
            'filter_fingerprint' => hash('sha256', $filterJson),
            'returned_count' => count($items),
            'authority_provenance' => $authorized->authorityProvenance,
        ];
        return new AuditReadPage($items, $nextCursor, $intent, $items === [] ? 'RETENTION_BOUNDARY_OR_UNAVAILABLE' : 'AVAILABLE');
    }

    /** @param array<string,mixed> $row */
    private function assertCanonicalOrderKey(array $row): string
    {
        $createdAt = $row['created_at'] ?? null;
        $eventId = $row['event_id'] ?? null;
        if (!is_string($createdAt) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/D', $createdAt) !== 1
            || !is_string($eventId) || preg_match('/^[a-f0-9]{64}$/D', $eventId) !== 1) {
            throw new \UnexpectedValueException('invalid_audit_read_order_key');
        }
        return $createdAt . '|' . $eventId;
    }

    /** @param array<string,mixed> $row */
    private function assertFilterMatch(array $row, AuditReadFilter $filter): void
    {
        foreach ($filter->values as $key => $expected) {
            if ($key === 'created_at_from') {
                if (!isset($row['created_at']) || $row['created_at'] < $expected) throw new \UnexpectedValueException('audit_read_filter_mismatch');
                continue;
            }
            if ($key === 'created_at_to') {
                if (!isset($row['created_at']) || $row['created_at'] > $expected) throw new \UnexpectedValueException('audit_read_filter_mismatch');
                continue;
            }
            if (!array_key_exists($key, $row) || $row[$key] !== $expected) {
                throw new \UnexpectedValueException('audit_read_filter_mismatch');
            }
        }
    }

    /** @param array<string,mixed> $row */
    private function assertScopeMatch(array $row, AuthorizedAuditRead $read): void
    {
        $query = $read->query;
        if ($read->forcedSelfIdentityId !== null) return;
        $match = match ($query->scope) {
            AuditReadAccess::SCOPE_ACCOUNT, AuditReadAccess::SCOPE_PROFILE => ($row['target_id'] ?? null) === $query->scopeValue,
            AuditReadAccess::SCOPE_CORRELATION => ($row['correlation_id'] ?? null) === $query->scopeValue,
            AuditReadAccess::SCOPE_REQUEST => ($row['request_id'] ?? null) === $query->scopeValue,
            AuditReadAccess::SCOPE_EVENT_TYPE => ($row['event_type'] ?? null) === $query->scopeValue,
            AuditReadAccess::SCOPE_TIME_RANGE => $query->scopeValue === 'FILTER_RANGE'
                && isset($query->filter->values['created_at_from'], $query->filter->values['created_at_to']),
            default => false,
        };
        if (!$match) {
            throw new \DomainException('audit_read_row_outside_authorized_scope');
        }
    }
}
