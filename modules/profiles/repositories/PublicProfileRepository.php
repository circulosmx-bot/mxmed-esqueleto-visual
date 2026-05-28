<?php
declare(strict_types=1);

namespace Profiles\Repositories;

use PDO;
use PDOException;

final class PublicProfileRepository
{
    private PDO $pdo;

    private const SCHEDULE_TABLE_CANDIDATES = [
        'consultorio_schedule',
        'consultorio_schedules',
        'consultorio_horarios',
        'consultorio_horarios_base',
        'agenda_consultorio_schedule',
    ];

    private const WEEKDAY_CANDIDATES = ['weekday', 'day_of_week', 'dia_semana', 'day'];
    private const START_CANDIDATES = ['start_time', 'start_at', 'hora_inicio', 'time_from', 'inicio'];
    private const END_CANDIDATES = ['end_time', 'end_at', 'hora_fin', 'time_to', 'fin'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function resolvePublicDoctorProfile(string $doctorId): array
    {
        $consultorios = $this->fetchConsultoriosRows($doctorId);
        $scheduleRows = $this->fetchScheduleRows($doctorId);
        $hasAppointments = $this->doctorHasAppointments($doctorId);
        $canonicalProfile = $this->fetchCanonicalProfileRow($doctorId);

        return [
            'exists' => (!empty($consultorios) || !empty($scheduleRows) || $hasAppointments || $canonicalProfile !== null),
            'consultorios' => $consultorios,
            'schedule_rows' => $scheduleRows,
            'has_appointments' => $hasAppointments,
            'profile_source' => $this->resolveProfileSource($canonicalProfile),
            'identity' => $this->resolveIdentity($canonicalProfile),
            'professional' => $this->resolveProfessional($canonicalProfile),
            'specialties' => $this->resolveSpecialties($canonicalProfile),
        ];
    }

    private function doctorHasAppointments(string $doctorId): bool
    {
        $configPath = __DIR__ . '/../../agenda/config/agenda.php';
        $table = 'agenda_appointments';
        if (is_file($configPath)) {
            $cfg = require $configPath;
            if (is_array($cfg)) {
                $candidate = trim((string)($cfg['appointments_table'] ?? ''));
                if ($candidate !== '') {
                    $table = $candidate;
                }
            }
        }
        if (!$this->tableExists($table)) {
            return false;
        }
        try {
            $sql = sprintf('SELECT COUNT(*) AS c FROM `%s` WHERE doctor_id = :doctor_id', $table);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['doctor_id' => $doctorId]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function fetchConsultoriosRows(string $doctorId): array
    {
        if (!$this->tableExists('consultorios')) {
            return [];
        }

        $columns = $this->tableColumns('consultorios');
        if (empty($columns) || !in_array('doctor_id', $columns, true) || !in_array('consultorio_id', $columns, true)) {
            return [];
        }

        $allowlist = [
            'consultorio_id',
            'titulo',
            'grupo_nombre',
            'calle',
            'num_ext',
            'num_int',
            'cp',
            'colonia',
            'municipio',
            'estado',
            'telefonos_json',
            'whatsapp',
            'lat',
            'lng',
            'geocode_source',
            'geocode_updated_at',
        ];

        $selected = [];
        foreach ($allowlist as $col) {
            if (in_array($col, $columns, true)) {
                $selected[] = sprintf('`%s`', $col);
            }
        }
        if (!in_array('consultorio_id', $columns, true)) {
            return [];
        }
        if (empty($selected)) {
            return [];
        }

        $sql = sprintf(
            'SELECT %s FROM `consultorios` WHERE doctor_id = :doctor_id ORDER BY consultorio_id ASC',
            implode(', ', $selected)
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doctor_id' => $doctorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function fetchScheduleRows(string $doctorId): array
    {
        $tableMeta = $this->resolveScheduleTableMeta();
        if ($tableMeta === null) {
            return [];
        }

        $parts = [
            sprintf('`%s` AS consultorio_id', $tableMeta['consultorio_col']),
            sprintf('`%s` AS weekday_raw', $tableMeta['weekday_col']),
            sprintf('`%s` AS start_raw', $tableMeta['start_col']),
            sprintf('`%s` AS end_raw', $tableMeta['end_col']),
        ];
        if ($tableMeta['is_active_col'] !== null) {
            $parts[] = sprintf('`%s` AS is_active_raw', $tableMeta['is_active_col']);
        }

        $sql = sprintf(
            'SELECT %s FROM `%s` WHERE `%s` = :doctor_id',
            implode(', ', $parts),
            $tableMeta['table'],
            $tableMeta['doctor_col']
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doctor_id' => $doctorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $consultorioId = trim((string)($row['consultorio_id'] ?? ''));
            $weekday = $this->normalizeWeekday($row['weekday_raw'] ?? null);
            $start = $this->normalizeTime((string)($row['start_raw'] ?? ''));
            $end = $this->normalizeTime((string)($row['end_raw'] ?? ''));
            if ($consultorioId === '' || $weekday === null || $start === null || $end === null || $start >= $end) {
                continue;
            }
            $active = true;
            if (array_key_exists('is_active_raw', $row)) {
                $active = ((int)$row['is_active_raw'] === 1);
            }
            if (!$active) {
                continue;
            }
            $normalized[] = [
                'consultorio_id' => $consultorioId,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            if ($a['consultorio_id'] === $b['consultorio_id']) {
                if ($a['weekday'] === $b['weekday']) {
                    return strcmp((string)$a['start_time'], (string)$b['start_time']);
                }
                return ((int)$a['weekday']) <=> ((int)$b['weekday']);
            }
            return strcmp((string)$a['consultorio_id'], (string)$b['consultorio_id']);
        });

        return $normalized;
    }

    private function resolveScheduleTableMeta(): ?array
    {
        foreach (self::SCHEDULE_TABLE_CANDIDATES as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $cols = $this->tableColumns($table);
            if (!in_array('doctor_id', $cols, true) || !in_array('consultorio_id', $cols, true)) {
                continue;
            }

            $weekday = $this->firstExistingColumn($cols, self::WEEKDAY_CANDIDATES);
            $start = $this->firstExistingColumn($cols, self::START_CANDIDATES);
            $end = $this->firstExistingColumn($cols, self::END_CANDIDATES);
            if ($weekday === null || $start === null || $end === null) {
                continue;
            }
            $isActive = in_array('is_active', $cols, true) ? 'is_active' : null;
            return [
                'table' => $table,
                'doctor_col' => 'doctor_id',
                'consultorio_col' => 'consultorio_id',
                'weekday_col' => $weekday,
                'start_col' => $start,
                'end_col' => $end,
                'is_active_col' => $isActive,
            ];
        }
        return null;
    }

    private function fetchCanonicalProfileRow(string $doctorId): ?array
    {
        if (!$this->tableExists('profiles_doctors')) {
            return null;
        }
        $columns = $this->tableColumns('profiles_doctors');
        if (empty($columns) || !in_array('doctor_id', $columns, true)) {
            return null;
        }

        $allowlist = [
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

        $selected = [];
        foreach ($allowlist as $column) {
            if (in_array($column, $columns, true)) {
                $selected[] = sprintf('`%s`', $column);
            }
        }
        if (empty($selected)) {
            return null;
        }

        $sql = sprintf(
            'SELECT %s FROM `profiles_doctors` WHERE `doctor_id` = :doctor_id LIMIT 1',
            implode(', ', $selected)
        );
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['doctor_id' => $doctorId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
        return is_array($row) ? $row : null;
    }

    private function resolveProfileSource(?array $profileRow): array
    {
        $source = [
            'has_canonical_row' => false,
            'profile_status' => 'hidden',
            'is_public_candidate' => false,
            'last_public_update_at' => null,
        ];
        if (!is_array($profileRow)) {
            return $source;
        }

        $source['has_canonical_row'] = true;
        $status = strtolower((string)($this->toNullableText($profileRow['profile_status'] ?? null) ?? 'hidden'));
        $allowed = ['draft', 'pending_review', 'active', 'hidden', 'suspended', 'removed'];
        $source['profile_status'] = in_array($status, $allowed, true) ? $status : 'hidden';
        $source['is_public_candidate'] = ((int)($profileRow['is_public_candidate'] ?? 0) === 1);
        $source['last_public_update_at'] = $this->toNullableText($profileRow['updated_at'] ?? null);
        return $source;
    }

    private function resolveIdentity(?array $profileRow): array
    {
        $resolved = [
            'display_name' => null,
            'prefix' => null,
            'gender_label' => null,
            'photo_url' => null,
            'avatar_url' => null,
            'logo_url' => null,
        ];
        if (!is_array($profileRow)) {
            return $resolved;
        }

        $resolved['display_name'] = $this->toNullableText($profileRow['display_name'] ?? null);
        $resolved['prefix'] = $this->toNullableText($profileRow['prefix'] ?? null);
        $resolved['gender_label'] = $this->toNullableText($profileRow['gender_label'] ?? null)
            ?? $this->toNullableText($profileRow['gender'] ?? null);
        $resolved['photo_url'] = $this->toNullableText($profileRow['photo_url'] ?? null);
        $resolved['avatar_url'] = $this->toNullableText($profileRow['avatar_url'] ?? null);
        $resolved['logo_url'] = $this->toNullableText($profileRow['logo_url'] ?? null);
        return $resolved;
    }

    private function resolveProfessional(?array $profileRow): array
    {
        $result = [
            'professional_license' => null,
            'specialty_license' => null,
            'specialty_primary' => null,
            'bio_short' => null,
            'bio_long' => null,
            'education' => [],
            'certifications' => [],
            'professional_associations' => [],
            'languages' => [],
            'years_experience' => null,
            'services' => [],
            'conditions_treated' => [],
        ];
        if (!is_array($profileRow)) {
            return $result;
        }

        $result['professional_license'] = $this->toNullableText($profileRow['professional_license'] ?? null);
        $result['specialty_license'] = $this->toNullableText($profileRow['specialty_license'] ?? null);
        $result['specialty_primary'] = $this->toNullableText($profileRow['specialty_primary'] ?? null);
        $result['bio_short'] = $this->toNullableText($profileRow['bio_short'] ?? null);
        return $result;
    }

    private function resolveSpecialties(?array $profileRow): array
    {
        if (!is_array($profileRow)) {
            return [];
        }

        $out = [];
        $primary = $this->toNullableText($profileRow['specialty_primary'] ?? null);
        if ($primary !== null) {
            $out[] = [
                'specialty_id' => null,
                'name_es' => $primary,
                'name_plural_es' => null,
                'slug' => $this->slugify($primary),
                'schema_medical_specialty' => null,
                'is_primary' => true,
            ];
        }

        $secondaries = $this->decodeJsonTextArray($profileRow['specialty_secondary_json'] ?? null);
        foreach ($secondaries as $secondary) {
            if ($primary !== null && mb_strtolower($secondary, 'UTF-8') === mb_strtolower($primary, 'UTF-8')) {
                continue;
            }
            $out[] = [
                'specialty_id' => null,
                'name_es' => $secondary,
                'name_plural_es' => null,
                'slug' => $this->slugify($secondary),
                'schema_medical_specialty' => null,
                'is_primary' => false,
            ];
        }

        return $out;
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

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return (string)$candidate;
            }
        }
        return null;
    }

    private function normalizeWeekday($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int)$value;
        if ($n >= 1 && $n <= 7) {
            return $n;
        }
        if ($n >= 0 && $n <= 6) {
            return $n === 0 ? 7 : $n;
        }
        return null;
    }

    private function normalizeTime(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $raw) === 1) {
            [$hh, $mm, $ss] = array_map('intval', explode(':', $raw));
            if ($hh < 0 || $hh > 23 || $mm < 0 || $mm > 59 || $ss < 0 || $ss > 59) {
                return null;
            }
            return sprintf('%02d:%02d:%02d', $hh, $mm, $ss);
        }
        if (preg_match('/^\d{1,2}:\d{2}$/', $raw) === 1) {
            [$hh, $mm] = array_map('intval', explode(':', $raw));
            if ($hh < 0 || $hh > 23 || $mm < 0 || $mm > 59) {
                return null;
            }
            return sprintf('%02d:%02d:00', $hh, $mm);
        }
        return null;
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

    private function slugify(string $value): string
    {
        $v = trim(mb_strtolower($value, 'UTF-8'));
        if ($v === '') {
            return '';
        }
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ];
        $v = strtr($v, $replacements);
        $v = preg_replace('/[^a-z0-9]+/', '-', $v) ?? '';
        return trim($v, '-');
    }
}
