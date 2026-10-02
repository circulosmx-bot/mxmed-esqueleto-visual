<?php
declare(strict_types=1);

namespace Agenda\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;

require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';

final class ProviderMatchRequestException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $httpStatus)
    {
        parent::__construct($reason);
    }
}

/** Read-only, exact-order matching. No recommendation, referral, or provider disclosure. */
final class HealthcareStudyProviderMatchingService
{
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;
    private const MAX_PAGE = 1000;
    private const MAX_REGIONS = 20;

    public function __construct(private PDO $pdo)
    {
    }

    public function matchOrder(string $doctorId, string $patientId, string $documentUuid, int $version,
        array $regionKeys, string $serviceMode, int $page = 1, int $pageSize = self::DEFAULT_PAGE_SIZE): array
    {
        $regions = $this->regions($regionKeys);
        if (!in_array($serviceMode, ['ON_SITE','HOME_SERVICE','MOBILE'], true)) {
            throw new ProviderMatchRequestException('invalid_service_mode', 400);
        }
        if ($page < 1 || $page > self::MAX_PAGE || $pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw new ProviderMatchRequestException('invalid_pagination', 400);
        }
        if ($version < 1 || $doctorId === '' || $patientId === ''
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $documentUuid) !== 1) {
            throw new ProviderMatchRequestException('invalid_order_identity', 400);
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $order = $this->authorizedCurrentOrder($doctorId, $patientId, $documentUuid, $version);
            $items = $this->orderItems($order['payload']);
            $catalog = $this->catalogStatus($items);
            $activeItems = [];
            $unmatchable = [];
            foreach ($items as $item) {
                $typeId = $item['study_type_id'];
                if ($typeId !== null && ($catalog[$typeId] ?? false)) {
                    $activeItems[] = $item;
                } else {
                    $item['reason'] = $typeId === null ? $item['reason'] : 'INACTIVE_OR_MISSING_STUDY_TYPE';
                    $unmatchable[] = $item;
                }
            }
            $candidatePage = $activeItems === [] ? [] : $this->candidatePage(
                array_values(array_unique(array_column($activeItems, 'study_type_id'))),
                $regions, $serviceMode, $page, $pageSize
            );
            $hasMore = count($candidatePage) > $pageSize;
            if ($hasMore) {
                array_pop($candidatePage);
            }
            $coverage = $candidatePage === [] ? [] : $this->coverageRows(
                array_column($candidatePage, 'location_id'),
                array_values(array_unique(array_column($activeItems, 'study_type_id'))),
                $regions, $serviceMode
            );
            $candidates = [];
            foreach ($candidatePage as $row) {
                $matched = [];
                $unmatched = [];
                foreach ($activeItems as $item) {
                    if (isset($coverage[$row['location_id']][$item['study_type_id']])) {
                        $matched[] = $item;
                    } else {
                        $unmatched[] = $item;
                    }
                }
                $matchedCount = count($matched);
                $classification = $this->classification($matchedCount, count($items));
                if ($classification === 'NO_VERIFIED_COVERAGE') {
                    continue; // A concurrent change cannot fill the product list with zero-coverage rows.
                }
                $total = count($items);
                $address = trim(implode(', ', array_filter([
                    trim(implode(' ', array_filter([$row['street'], $row['exterior_number'], $row['interior_number']]))),
                    $row['colonia'], $row['municipality'], $row['state_name'], $row['postal_code'],
                ], static fn ($part) => is_string($part) && trim($part) !== '')));
                $candidates[] = [
                    'organization' => ['group_id' => $row['group_id'], 'display_name' => $row['organization_name'],
                        'organization_type_key' => $row['organization_type_key']],
                    'location' => ['location_uuid' => $row['location_uuid'], 'branch_name' => $row['branch_name'],
                        'address_label' => $address ?: null, 'postal_code' => $row['postal_code'],
                        'municipality' => $row['municipality'], 'state_name' => $row['state_name'],
                        'phone' => $row['phone']],
                    'service_mode' => $serviceMode,
                    'coverage' => [
                        'classification' => $classification,
                        'total_order_item_count' => $total,
                        'cataloged_item_count' => count($activeItems),
                        'matched_item_count' => $matchedCount,
                        'unmatched_cataloged_item_count' => count($unmatched),
                        'unmatchable_item_count' => count($unmatchable),
                        'matched_order_item_ids' => array_column($matched, 'order_item_id'),
                        'unmatched_cataloged_order_item_ids' => array_column($unmatched, 'order_item_id'),
                        'unmatchable_custom_order_item_ids' => array_values(array_map(
                            static fn ($item) => $item['order_item_id'],
                            array_filter($unmatchable, static fn ($item) => $item['reason'] === 'UNMATCHABLE_CUSTOM')
                        )),
                        'matched_items' => $this->publicItems($matched),
                        'unmatched_cataloged_items' => $this->publicItems($unmatched),
                    ],
                ];
            }
            $result = [
                'order' => ['document_uuid' => $documentUuid, 'document_version' => $version],
                'query' => ['region_keys' => $regions, 'service_mode' => $serviceMode,
                    'page' => $page, 'page_size' => $pageSize, 'has_more' => $hasMore],
                'summary' => ['total_order_item_count' => count($items),
                    'cataloged_item_count' => count($activeItems),
                    'unmatchable_item_count' => count($unmatchable),
                    'unmatchable_items' => $this->publicItems($unmatchable, true)],
                'candidates' => $candidates,
            ];
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function authorizedCurrentOrder(string $doctorId, string $patientId, string $uuid, int $version): array
    {
        $scope = $this->pdo->prepare("SELECT 1 FROM patients_doctor_links
            WHERE doctor_id=:doctor_id AND patient_id=:patient_id AND status='active' LIMIT 1");
        $scope->execute(['doctor_id' => $doctorId, 'patient_id' => $patientId]);
        if (!$scope->fetchColumn()) {
            throw new ProviderMatchRequestException('order_not_found', 404);
        }
        $stmt = $this->pdo->prepare('SELECT id,document_uuid,document_type,version,status,patient_id,generated_at,payload_json
            FROM clinical_documents WHERE document_uuid=:uuid AND patient_id=:patient_id AND version=:version LIMIT 1');
        $stmt->execute(['uuid' => $uuid, 'patient_id' => $patientId, 'version' => $version]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !\clinical_study_order_type((string)$row['document_type'])) {
            throw new ProviderMatchRequestException('order_not_found', 404);
        }
        $payload = json_decode((string)$row['payload_json'], true);
        if (!is_array($payload)) {
            throw new ProviderMatchRequestException('order_payload_invalid', 409);
        }
        $revision = $this->pdo->prepare('SELECT 1 FROM clinical_document_revisions
            WHERE supersedes_document_id=:supersedes OR (original_document_id=:original AND supersedes_document_id IS NULL) LIMIT 1');
        $revision->execute(['supersedes' => $row['id'], 'original' => $row['id']]);
        if ($revision->fetchColumn() || strtolower(trim((string)($payload['status'] ?? ''))) === 'replaced'
            || trim((string)($payload['replaced_by_document_uuid'] ?? '')) !== ''
            || trim((string)($payload['replaced_by_document_id'] ?? '')) !== '') {
            throw new ProviderMatchRequestException('order_replaced', 409);
        }
        if ($row['status'] === 'voided' || strtolower(trim((string)($payload['status'] ?? ''))) === 'voided') {
            throw new ProviderMatchRequestException('order_voided', 409);
        }
        if (!in_array($row['status'], ['generated','signed'], true)
            || !is_string($row['generated_at'])
            || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $row['generated_at']) !== 1
            || strtotime($row['generated_at'].' UTC') === false
            || str_starts_with($row['generated_at'], '0000-')) {
            throw new ProviderMatchRequestException('order_not_issued', 409);
        }
        $row['payload'] = $payload;
        unset($row['payload_json']);
        return $row;
    }

    private function orderItems(array $payload): array
    {
        $v2 = (int)($payload['order_payload_version'] ?? 0) === 2;
        $raw = $v2 ? ($payload['order_items'] ?? null) : ($payload['requested_studies'] ?? null);
        if (!is_array($raw) || !array_is_list($raw) || $raw === [] || count($raw) > 100) {
            throw new ProviderMatchRequestException('order_items_invalid', 409);
        }
        $items = [];
        $seen = [];
        foreach ($raw as $index => $entry) {
            if (!$v2) {
                if (!is_string($entry) || trim($entry) === '') {
                    throw new ProviderMatchRequestException('order_items_invalid', 409);
                }
                $items[] = ['order_item_id' => null, 'item_key' => 'legacy:'.($index + 1),
                    'sequence' => $index + 1, 'study_type_id' => null, 'study_display_name' => trim($entry),
                    'reason' => 'UNMATCHABLE_LEGACY'];
                continue;
            }
            $sequence = is_array($entry) ? ($entry['sequence'] ?? null) : null;
            if (!is_array($entry)
                || !(is_int($sequence) || (is_string($sequence) && ctype_digit($sequence)))
                || (int)$sequence !== $index + 1
                || !is_string($entry['order_item_id'] ?? null)
                || !\clinical_study_valid_uuid($entry['order_item_id'])
                || isset($seen[$entry['order_item_id']])
                || !is_string($entry['study_display_name'] ?? null)
                || trim($entry['study_display_name']) === '') {
                throw new ProviderMatchRequestException('order_items_invalid', 409);
            }
            $seen[$entry['order_item_id']] = true;
            $id = $entry['study_type_id'] ?? null;
            $canonicalId = (is_int($id) || (is_string($id) && ctype_digit($id))) && (int)$id > 0
                ? (int)$id : null;
            $items[] = ['order_item_id' => $entry['order_item_id'], 'item_key' => $entry['order_item_id'],
                'sequence' => $index + 1, 'study_type_id' => $canonicalId,
                'study_display_name' => trim($entry['study_display_name']),
                'reason' => $canonicalId === null ? 'UNMATCHABLE_CUSTOM' : null];
        }
        return $items;
    }

    private function catalogStatus(array $items): array
    {
        $ids = array_values(array_unique(array_filter(array_column($items, 'study_type_id'), static fn ($id) => $id !== null)));
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare('SELECT study_type_id,is_active FROM clinical_study_types WHERE study_type_id IN ('.$marks.')');
        $stmt->execute($ids);
        $status = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status[(int)$row['study_type_id']] = (int)$row['is_active'] === 1;
        }
        return $status;
    }

    private function candidatePage(array $studyIds, array $regions, string $mode, int $page, int $pageSize): array
    {
        [$idsSql, $ids] = $this->inParams('study', $studyIds);
        [$regionSql, $regionParams] = $this->inParams('region', $mode === 'ON_SITE'
            ? array_map(static fn ($key) => substr($key, 6), $regions) : $regions);
        $geo = $mode === 'ON_SITE' ? 'l.postal_code IN ('.$regionSql.')'
            : "EXISTS (SELECT 1 FROM healthcare_organization_location_study_service_areas a
                WHERE a.offering_id=o.offering_id AND a.scope_type='POSTAL_CODE'
                AND a.operational_state='ACTIVE' AND a.verification_state='VERIFIED'
                AND a.region_key IN (".$regionSql.'))';
        $sql = "SELECT mg.group_id,mg.display_name AS organization_name,mg.organization_type_key,
                l.location_id,l.location_uuid,l.branch_name,l.street,l.exterior_number,l.interior_number,
                l.colonia,l.postal_code,l.municipality,l.state_name,l.phone
            FROM healthcare_organization_locations l
            JOIN medical_groups mg ON mg.group_id=l.group_id
            JOIN healthcare_organization_provider_status ps ON ps.group_id=mg.group_id
            WHERE ps.operational_state='ACTIVE' AND ps.verification_state='VERIFIED'
              AND EXISTS (SELECT 1 FROM healthcare_provider_commercial_active_capabilities commercial
                WHERE commercial.group_id=mg.group_id AND commercial.capability='provider_matching_participation')
              AND l.operational_state='ACTIVE' AND l.verification_state='VERIFIED'
              AND EXISTS (SELECT 1 FROM healthcare_organization_location_study_offerings o
                JOIN clinical_study_types s ON s.study_type_id=o.study_type_id
                JOIN healthcare_organization_master_services master ON master.master_service_id=o.master_service_id AND master.group_id=l.group_id AND master.study_type_id=o.study_type_id
                WHERE o.location_id=l.location_id AND o.study_type_id IN (".$idsSql.")
                  AND o.operational_state='ACTIVE' AND o.verification_state='VERIFIED'
                  AND master.operational_state='ACTIVE' AND s.is_active=1 AND o.service_mode=:mode AND ".$geo.")
            ORDER BY mg.display_name,mg.group_id,l.branch_name,l.location_uuid
            LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($ids + $regionParams + ['mode' => $mode] as $key => $value) {
            $stmt->bindValue(':'.$key, $value);
        }
        $stmt->bindValue(':limit', $pageSize + 1, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function coverageRows(array $locationIds, array $studyIds, array $regions, string $mode): array
    {
        [$locationSql, $locationParams] = $this->inParams('location', $locationIds);
        [$studySql, $studyParams] = $this->inParams('study', $studyIds);
        [$regionSql, $regionParams] = $this->inParams('region', $mode === 'ON_SITE'
            ? array_map(static fn ($key) => substr($key, 6), $regions) : $regions);
        $geo = $mode === 'ON_SITE' ? 'l.postal_code IN ('.$regionSql.')'
            : "EXISTS (SELECT 1 FROM healthcare_organization_location_study_service_areas a
                WHERE a.offering_id=o.offering_id AND a.scope_type='POSTAL_CODE'
                AND a.operational_state='ACTIVE' AND a.verification_state='VERIFIED'
                AND a.region_key IN (".$regionSql.'))';
        $sql = "SELECT o.location_id,o.study_type_id FROM healthcare_organization_location_study_offerings o
            JOIN healthcare_organization_locations l ON l.location_id=o.location_id
            JOIN clinical_study_types s ON s.study_type_id=o.study_type_id
            JOIN healthcare_organization_master_services master ON master.master_service_id=o.master_service_id AND master.group_id=l.group_id AND master.study_type_id=o.study_type_id
            WHERE o.location_id IN (".$locationSql.") AND o.study_type_id IN (".$studySql.")
              AND o.operational_state='ACTIVE' AND o.verification_state='VERIFIED'
              AND master.operational_state='ACTIVE' AND s.is_active=1 AND o.service_mode=:mode AND ".$geo;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($locationParams + $studyParams + $regionParams + ['mode' => $mode]);
        $covered = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $covered[$row['location_id']][(int)$row['study_type_id']] = true;
        }
        return $covered;
    }

    private function regions(array $raw): array
    {
        if ($raw === [] || !array_is_list($raw) || count($raw) > 50) {
            throw new ProviderMatchRequestException('invalid_region_keys', 400);
        }
        $regions = [];
        foreach ($raw as $key) {
            if (!is_string($key) || preg_match('/^MX\|CP\|[0-9]{5}$/D', $key) !== 1) {
                throw new ProviderMatchRequestException('invalid_region_keys', 400);
            }
            $regions[$key] = true;
        }
        $regions = array_keys($regions);
        if (count($regions) > self::MAX_REGIONS) {
            throw new ProviderMatchRequestException('too_many_region_keys', 400);
        }
        sort($regions, SORT_STRING);
        return $regions;
    }

    private function inParams(string $prefix, array $values): array
    {
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $params[$prefix.$index] = $value;
        }
        return [implode(',', array_map(static fn ($key) => ':'.$key, array_keys($params))), $params];
    }

    private function publicItems(array $items, bool $includeReason = false): array
    {
        return array_map(static function (array $item) use ($includeReason): array {
            $visible = ['order_item_id' => $item['order_item_id'], 'item_key' => $item['item_key'],
                'sequence' => $item['sequence'], 'study_display_name' => $item['study_display_name'],
                'study_type_id' => $item['study_type_id']];
            if ($includeReason) {
                $visible['reason'] = $item['reason'];
            }
            return $visible;
        }, $items);
    }

    private function classification(int $matchedCount, int $totalCount): string
    {
        if ($matchedCount === 0) {
            return 'NO_VERIFIED_COVERAGE';
        }
        return $matchedCount === $totalCount ? 'FULL_VERIFIED_COVERAGE' : 'PARTIAL_VERIFIED_COVERAGE';
    }
}
