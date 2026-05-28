<?php
declare(strict_types=1);

namespace Profiles\Repositories;

use PDO;
use PDOException;
use RuntimeException;

final class PrivateProfileRepository
{
    private PDO $pdo;

    private const TABLE = 'profiles_doctors';

    private const READ_COLUMNS = [
        'doctor_id',
        'display_name',
        'prefix',
        'gender',
        'gender_label',
        'professional_license',
        'specialty_license',
        'specialty_primary',
        'specialty_secondary_json',
        'bio_short',
        'photo_url',
        'avatar_url',
        'logo_url',
        'profile_status',
        'is_public_candidate',
        'updated_at',
    ];

    private const EDITABLE_TO_COLUMN = [
        'display_name' => 'display_name',
        'prefix' => 'prefix',
        'gender' => 'gender',
        'gender_label' => 'gender_label',
        'professional_license' => 'professional_license',
        'specialty_license' => 'specialty_license',
        'specialty_primary' => 'specialty_primary',
        'specialty_secondary_json' => 'specialty_secondary_json',
        'bio_short' => 'bio_short',
        'photo_url' => 'photo_url',
        'avatar_url' => 'avatar_url',
        'logo_url' => 'logo_url',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function fetchIdentity(string $doctorId): ?array
    {
        $columns = $this->requireTableColumns();
        $selected = $this->existingColumns(self::READ_COLUMNS, $columns);
        if (empty($selected)) {
            throw new RuntimeException('profiles_doctors columns unavailable');
        }

        $sql = sprintf(
            'SELECT %s FROM `%s` WHERE `doctor_id` = :doctor_id LIMIT 1',
            implode(', ', array_map(static fn(string $col): string => sprintf('`%s`', $col), $selected)),
            self::TABLE
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['doctor_id' => $doctorId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('profiles_doctors query failed', 0, $e);
        }

        if (!is_array($row)) {
            return null;
        }
        return $this->mapIdentityRow($doctorId, $row);
    }

    public function upsertIdentity(string $doctorId, array $editable): array
    {
        $columns = $this->requireTableColumns();
        $mutable = $this->existingEditableColumns($columns);
        if (empty($mutable)) {
            throw new RuntimeException('profiles_doctors editable columns unavailable');
        }

        $mapped = [];
        foreach ($editable as $field => $value) {
            $fieldName = (string)$field;
            if (!isset(self::EDITABLE_TO_COLUMN[$fieldName])) {
                continue;
            }
            $column = self::EDITABLE_TO_COLUMN[$fieldName];
            if (!in_array($column, $mutable, true)) {
                continue;
            }
            $mapped[$column] = $value;
        }

        if (empty($mapped)) {
            throw new RuntimeException('no editable columns provided');
        }

        $existing = $this->fetchIdentity($doctorId);
        if ($existing === null) {
            $insertColumns = array_merge(['doctor_id'], array_keys($mapped));
            $insertValues = array_merge(['doctor_id' => $doctorId], $mapped);
            $placeholders = implode(', ', array_map(static fn(string $col): string => ':' . $col, $insertColumns));
            $sql = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                self::TABLE,
                implode(', ', array_map(static fn(string $col): string => sprintf('`%s`', $col), $insertColumns)),
                $placeholders
            );
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($insertValues);
            } catch (PDOException $e) {
                throw new RuntimeException('profiles_doctors insert failed', 0, $e);
            }
        } else {
            $setFragments = [];
            $params = ['doctor_id' => $doctorId];
            foreach ($mapped as $column => $value) {
                $paramName = 'f_' . $column;
                $setFragments[] = sprintf('`%s` = :%s', $column, $paramName);
                $params[$paramName] = $value;
            }
            $setFragments[] = '`updated_at` = CURRENT_TIMESTAMP';
            $sql = sprintf(
                'UPDATE `%s` SET %s WHERE `doctor_id` = :doctor_id LIMIT 1',
                self::TABLE,
                implode(', ', $setFragments)
            );
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } catch (PDOException $e) {
                throw new RuntimeException('profiles_doctors update failed', 0, $e);
            }
        }

        $fresh = $this->fetchIdentity($doctorId);
        if ($fresh === null) {
            throw new RuntimeException('profiles_doctors read-after-write failed');
        }
        return $fresh;
    }

    private function requireTableColumns(): array
    {
        if (!$this->tableExists(self::TABLE)) {
            throw new RuntimeException('profiles_doctors table not found');
        }
        $columns = $this->tableColumns(self::TABLE);
        if (empty($columns) || !in_array('doctor_id', $columns, true)) {
            throw new RuntimeException('profiles_doctors doctor_id column missing');
        }
        return $columns;
    }

    private function existingEditableColumns(array $columns): array
    {
        $out = [];
        foreach (self::EDITABLE_TO_COLUMN as $column) {
            if (in_array($column, $columns, true)) {
                $out[] = $column;
            }
        }
        return array_values(array_unique($out));
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

    private function mapIdentityRow(string $doctorId, array $row): array
    {
        return [
            'doctor_id' => $doctorId,
            'display_name' => $this->toNullableText($row['display_name'] ?? null),
            'prefix' => $this->toNullableText($row['prefix'] ?? null),
            'gender' => $this->toNullableText($row['gender'] ?? null),
            'gender_label' => $this->toNullableText($row['gender_label'] ?? null),
            'professional_license' => $this->toNullableText($row['professional_license'] ?? null),
            'specialty_license' => $this->toNullableText($row['specialty_license'] ?? null),
            'specialty_primary' => $this->toNullableText($row['specialty_primary'] ?? null),
            'specialty_secondary' => $this->decodeJsonTextArray($row['specialty_secondary_json'] ?? null),
            'bio_short' => $this->toNullableText($row['bio_short'] ?? null),
            'photo_url' => $this->toNullableText($row['photo_url'] ?? null),
            'avatar_url' => $this->toNullableText($row['avatar_url'] ?? null),
            'logo_url' => $this->toNullableText($row['logo_url'] ?? null),
            'profile_status' => $this->toNullableText($row['profile_status'] ?? null) ?? 'hidden',
            'is_public_candidate' => ((int)($row['is_public_candidate'] ?? 0) === 1),
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
        return array_values(array_unique(array_map(static fn($v) => (string)$v, $rows)));
    }

    private function toNullableText($value): ?string
    {
        $v = trim((string)($value ?? ''));
        return ($v === '') ? null : $v;
    }

    private function decodeJsonTextArray($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $raw = trim((string)($value ?? ''));
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }
            $items = $decoded;
        }

        $clean = [];
        foreach ($items as $item) {
            $text = trim((string)$item);
            if ($text !== '') {
                $clean[] = $text;
            }
        }
        return array_values(array_unique($clean));
    }
}
