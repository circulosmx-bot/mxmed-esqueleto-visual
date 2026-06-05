<?php
declare(strict_types=1);

namespace Profiles\Repositories;

use PDO;
use PDOException;
use RuntimeException;

final class DoctorContactPointsRepository
{
    private PDO $pdo;

    private const TABLE = 'doctor_contact_points';

    private const READ_COLUMNS = [
        'contact_point_id',
        'doctor_id',
        'consultorio_id',
        'type',
        'value',
        'normalized_value',
        'label',
        'scope',
        'is_public',
        'is_verified',
        'verification_status',
        'use_for_security',
        'use_for_platform_admin',
        'use_for_public_profile',
        'use_for_appointments',
        'visibility_plan_min',
        'status',
        'sort_order',
        'source',
        'metadata_json',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listByDoctor(string $doctorId): array
    {
        $columns = $this->requireTableColumns();
        $selected = $this->existingColumns(self::READ_COLUMNS, $columns);
        if (empty($selected)) {
            throw new RuntimeException('doctor_contact_points columns unavailable');
        }

        $sql = sprintf(
            'SELECT %s
               FROM `%s`
              WHERE `doctor_id` = :doctor_id
                AND `deleted_at` IS NULL
              ORDER BY `scope` ASC, `sort_order` ASC, `contact_point_id` ASC',
            implode(', ', array_map(static fn(string $col): string => sprintf('`%s`', $col), $selected)),
            self::TABLE
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['doctor_id' => $doctorId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('doctor_contact_points query failed', 0, $e);
        }

        if (!is_array($rows)) {
            return [];
        }
        return array_map([$this, 'mapContactPointRow'], $rows);
    }

    public function findById(string $doctorId, string $contactPointId): ?array
    {
        $columns = $this->requireTableColumns();
        $selected = $this->existingColumns(self::READ_COLUMNS, $columns);

        $sql = sprintf(
            'SELECT %s
               FROM `%s`
              WHERE `doctor_id` = :doctor_id
                AND `contact_point_id` = :contact_point_id
                AND `deleted_at` IS NULL
              LIMIT 1',
            implode(', ', array_map(static fn(string $col): string => sprintf('`%s`', $col), $selected)),
            self::TABLE
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'doctor_id' => $doctorId,
                'contact_point_id' => $contactPointId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('doctor_contact_points query failed', 0, $e);
        }

        return is_array($row) ? $this->mapContactPointRow($row) : null;
    }

    public function findByNormalizedValue(string $doctorId, string $type, string $normalizedValue): ?array
    {
        $columns = $this->requireTableColumns();
        $selected = $this->existingColumns(self::READ_COLUMNS, $columns);

        $sql = sprintf(
            'SELECT %s
               FROM `%s`
              WHERE `doctor_id` = :doctor_id
                AND `type` = :type
                AND `normalized_value` = :normalized_value
                AND `deleted_at` IS NULL
              LIMIT 1',
            implode(', ', array_map(static fn(string $col): string => sprintf('`%s`', $col), $selected)),
            self::TABLE
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'doctor_id' => $doctorId,
                'type' => $type,
                'normalized_value' => $normalizedValue,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('doctor_contact_points query failed', 0, $e);
        }

        return is_array($row) ? $this->mapContactPointRow($row) : null;
    }

    public function findDuplicateActive(
        string $doctorId,
        string $type,
        string $normalizedValue,
        string $excludeContactPointId
    ): ?array {
        $columns = $this->requireTableColumns();
        $selected = $this->existingColumns(self::READ_COLUMNS, $columns);

        $sql = sprintf(
            'SELECT %s
               FROM `%s`
              WHERE `doctor_id` = :doctor_id
                AND `type` = :type
                AND `normalized_value` = :normalized_value
                AND `contact_point_id` <> :exclude_contact_point_id
                AND `deleted_at` IS NULL
              LIMIT 1',
            implode(', ', array_map(static fn(string $col): string => sprintf('`%s`', $col), $selected)),
            self::TABLE
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'doctor_id' => $doctorId,
                'type' => $type,
                'normalized_value' => $normalizedValue,
                'exclude_contact_point_id' => $excludeContactPointId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('doctor_contact_points query failed', 0, $e);
        }

        return is_array($row) ? $this->mapContactPointRow($row) : null;
    }

    public function createForDoctor(string $doctorId, array $payload): array
    {
        $this->requireTableColumns();

        $sql = sprintf(
            'INSERT INTO `%s` (
                `doctor_id`,
                `type`,
                `value`,
                `normalized_value`,
                `label`,
                `scope`,
                `is_public`,
                `is_verified`,
                `verification_status`,
                `use_for_security`,
                `use_for_platform_admin`,
                `use_for_public_profile`,
                `use_for_appointments`,
                `status`,
                `sort_order`,
                `source`
            ) VALUES (
                :doctor_id,
                :type,
                :value,
                :normalized_value,
                :label,
                :scope,
                0,
                0,
                \'unverified\',
                :use_for_security,
                :use_for_platform_admin,
                0,
                :use_for_appointments,
                :status,
                :sort_order,
                \'manual\'
            )',
            self::TABLE
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'doctor_id' => $doctorId,
                'type' => $payload['type'],
                'value' => $payload['value'],
                'normalized_value' => $payload['normalized_value'],
                'label' => $payload['label'],
                'scope' => $payload['scope'],
                'use_for_security' => (int)((bool)($payload['use_for_security'] ?? false)),
                'use_for_platform_admin' => (int)((bool)($payload['use_for_platform_admin'] ?? false)),
                'use_for_appointments' => (int)((bool)($payload['use_for_appointments'] ?? false)),
                'status' => $payload['status'],
                'sort_order' => (int)$payload['sort_order'],
            ]);
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                throw new RuntimeException('duplicate_active_contact', 0, $e);
            }
            throw new RuntimeException('doctor_contact_points create failed', 0, $e);
        }

        $created = $this->findById($doctorId, (string)$this->pdo->lastInsertId());
        if (!is_array($created)) {
            throw new RuntimeException('doctor_contact_points create failed');
        }
        return $created;
    }

    public function updateForDoctor(string $doctorId, string $contactPointId, array $payload): array
    {
        $this->requireTableColumns();

        $allowed = [
            'type',
            'value',
            'normalized_value',
            'label',
            'scope',
            'use_for_security',
            'use_for_platform_admin',
            'use_for_appointments',
            'status',
            'sort_order',
        ];
        $assignments = [];
        $params = [
            'doctor_id' => $doctorId,
            'contact_point_id' => $contactPointId,
        ];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $assignments[] = sprintf('`%s` = :%s', $field, $field);
            if (in_array($field, ['use_for_security', 'use_for_platform_admin', 'use_for_appointments'], true)) {
                $params[$field] = (int)((bool)$payload[$field]);
            } elseif ($field === 'sort_order') {
                $params[$field] = (int)$payload[$field];
            } else {
                $params[$field] = $payload[$field];
            }
        }

        if (empty($assignments)) {
            $current = $this->findById($doctorId, $contactPointId);
            if (!is_array($current)) {
                throw new RuntimeException('contact_point_not_found');
            }
            return $current;
        }

        $assignments[] = '`updated_at` = NOW()';
        $sql = sprintf(
            'UPDATE `%s`
                SET %s
              WHERE `doctor_id` = :doctor_id
                AND `contact_point_id` = :contact_point_id
                AND `deleted_at` IS NULL',
            self::TABLE,
            implode(', ', $assignments)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                throw new RuntimeException('duplicate_active_contact', 0, $e);
            }
            throw new RuntimeException('doctor_contact_points update failed', 0, $e);
        }

        $updated = $this->findById($doctorId, $contactPointId);
        if (!is_array($updated)) {
            throw new RuntimeException('contact_point_not_found');
        }
        return $updated;
    }

    public function softDeleteForDoctor(string $doctorId, string $contactPointId): bool
    {
        $this->requireTableColumns();

        $sql = sprintf(
            'UPDATE `%s`
                SET `deleted_at` = NOW(),
                    `updated_at` = NOW()
              WHERE `doctor_id` = :doctor_id
                AND `contact_point_id` = :contact_point_id
                AND `deleted_at` IS NULL',
            self::TABLE
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'doctor_id' => $doctorId,
                'contact_point_id' => $contactPointId,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('doctor_contact_points delete failed', 0, $e);
        }

        return $stmt->rowCount() === 1;
    }

    public function normalizeValue(string $type, string $value): string
    {
        $type = strtolower(trim($type));
        $value = trim($value);

        if ($type === 'email') {
            return strtolower($value);
        }

        if ($type === 'phone' || $type === 'whatsapp') {
            $compact = preg_replace('/[\s\-\(\)]/', '', $value);
            $compact = is_string($compact) ? $compact : '';
            if (str_starts_with($compact, '+')) {
                $digits = preg_replace('/\D/', '', substr($compact, 1));
                return '+' . (is_string($digits) ? $digits : '');
            }
            $digits = preg_replace('/\D/', '', $compact);
            return is_string($digits) ? $digits : '';
        }

        return $value;
    }

    private function requireTableColumns(): array
    {
        if (!$this->tableExists(self::TABLE)) {
            throw new RuntimeException('doctor_contact_points table not ready');
        }
        $columns = $this->tableColumns(self::TABLE);
        foreach (['doctor_id', 'contact_point_id', 'deleted_at'] as $required) {
            if (!in_array($required, $columns, true)) {
                throw new RuntimeException('doctor_contact_points table not ready');
            }
        }
        return $columns;
    }

    private function existingColumns(array $allowlist, array $columns): array
    {
        $selected = [];
        foreach ($allowlist as $column) {
            if (in_array($column, $columns, true)) {
                $selected[] = $column;
            }
        }
        return $selected;
    }

    private function mapContactPointRow(array $row): array
    {
        return [
            'contact_point_id' => (int)($row['contact_point_id'] ?? 0),
            'doctor_id' => $this->toText($row['doctor_id'] ?? null),
            'consultorio_id' => $this->toNullableText($row['consultorio_id'] ?? null),
            'type' => $this->toText($row['type'] ?? null),
            'value' => $this->toText($row['value'] ?? null),
            'normalized_value' => $this->toText($row['normalized_value'] ?? null),
            'label' => $this->toNullableText($row['label'] ?? null),
            'scope' => $this->toText($row['scope'] ?? null),
            'is_public' => ((int)($row['is_public'] ?? 0) === 1),
            'is_verified' => ((int)($row['is_verified'] ?? 0) === 1),
            'verification_status' => $this->toText($row['verification_status'] ?? null),
            'use_for_security' => ((int)($row['use_for_security'] ?? 0) === 1),
            'use_for_platform_admin' => ((int)($row['use_for_platform_admin'] ?? 0) === 1),
            'use_for_public_profile' => ((int)($row['use_for_public_profile'] ?? 0) === 1),
            'use_for_appointments' => ((int)($row['use_for_appointments'] ?? 0) === 1),
            'visibility_plan_min' => $this->toNullableText($row['visibility_plan_min'] ?? null),
            'status' => $this->toText($row['status'] ?? null),
            'sort_order' => (int)($row['sort_order'] ?? 100),
            'source' => $this->toText($row['source'] ?? null),
            'metadata_json' => $this->decodeJsonObject($row['metadata_json'] ?? null),
            'created_at' => $this->toNullableText($row['created_at'] ?? null),
            'updated_at' => $this->toNullableText($row['updated_at'] ?? null),
        ];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    private function tableColumns(string $table): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($rows)) {
            return [];
        }
        return array_values(array_unique(array_map(static fn($v): string => (string)$v, $rows)));
    }

    private function toText($value): string
    {
        return trim((string)($value ?? ''));
    }

    private function toNullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function decodeJsonObject($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
