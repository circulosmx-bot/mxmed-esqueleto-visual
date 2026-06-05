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
            'deleted_at' => $this->toNullableText($row['deleted_at'] ?? null),
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
