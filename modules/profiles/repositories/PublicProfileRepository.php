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

        return [
            'exists' => (!empty($consultorios) || !empty($scheduleRows) || $hasAppointments),
            'consultorios' => $consultorios,
            'schedule_rows' => $scheduleRows,
            'has_appointments' => $hasAppointments,
            'identity' => $this->resolveIdentity($doctorId),
            'professional' => $this->resolveProfessional($doctorId),
            'specialties' => $this->resolveSpecialties($doctorId),
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

    private function resolveIdentity(string $doctorId): array
    {
        $resolved = [
            'display_name' => null,
            'prefix' => null,
            'gender_label' => null,
            'photo_url' => null,
            'avatar_url' => null,
            'logo_url' => null,
        ];

        $candidates = [
            [
                'table' => 'users',
                'id' => ['doctor_id', 'user_id', 'id'],
                'display_name' => ['display_name', 'full_name', 'name', 'nombre', 'nombre_completo'],
                'prefix' => ['prefix', 'title_prefix'],
                'gender' => ['gender', 'gender_label', 'sexo'],
                'photo' => ['photo_url', 'avatar_url', 'photo'],
                'avatar' => ['avatar_url'],
                'logo' => ['logo_url'],
            ],
            [
                'table' => 'doctors',
                'id' => ['doctor_id', 'id'],
                'display_name' => ['display_name', 'full_name', 'name', 'nombre', 'nombre_completo'],
                'prefix' => ['prefix', 'title_prefix'],
                'gender' => ['gender', 'gender_label', 'sexo'],
                'photo' => ['photo_url', 'avatar_url', 'photo'],
                'avatar' => ['avatar_url'],
                'logo' => ['logo_url'],
            ],
            [
                'table' => 'medicos',
                'id' => ['doctor_id', 'id', 'medico_id'],
                'display_name' => ['display_name', 'full_name', 'name', 'nombre', 'nombre_completo'],
                'prefix' => ['prefix', 'title_prefix'],
                'gender' => ['gender', 'gender_label', 'sexo'],
                'photo' => ['photo_url', 'avatar_url', 'photo'],
                'avatar' => ['avatar_url'],
                'logo' => ['logo_url'],
            ],
        ];

        foreach ($candidates as $candidate) {
            $table = (string)($candidate['table'] ?? '');
            if ($table === '' || !$this->tableExists($table)) {
                continue;
            }
            $cols = $this->tableColumns($table);
            $idCol = $this->firstExistingColumn($cols, $candidate['id']);
            if ($idCol === null) {
                continue;
            }
            $displayCol = $this->firstExistingColumn($cols, $candidate['display_name']);
            if ($displayCol === null) {
                continue;
            }

            $queryCols = [
                sprintf('`%s` AS display_name', $displayCol),
            ];
            $prefixCol = $this->firstExistingColumn($cols, $candidate['prefix']);
            if ($prefixCol !== null) {
                $queryCols[] = sprintf('`%s` AS prefix', $prefixCol);
            }
            $genderCol = $this->firstExistingColumn($cols, $candidate['gender']);
            if ($genderCol !== null) {
                $queryCols[] = sprintf('`%s` AS gender_label', $genderCol);
            }
            $photoCol = $this->firstExistingColumn($cols, $candidate['photo']);
            if ($photoCol !== null) {
                $queryCols[] = sprintf('`%s` AS photo_url', $photoCol);
            }
            $avatarCol = $this->firstExistingColumn($cols, $candidate['avatar']);
            if ($avatarCol !== null) {
                $queryCols[] = sprintf('`%s` AS avatar_url', $avatarCol);
            }
            $logoCol = $this->firstExistingColumn($cols, $candidate['logo']);
            if ($logoCol !== null) {
                $queryCols[] = sprintf('`%s` AS logo_url', $logoCol);
            }

            $sql = sprintf(
                'SELECT %s FROM `%s` WHERE `%s` = :doctor_id LIMIT 1',
                implode(', ', $queryCols),
                $table,
                $idCol
            );
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['doctor_id' => $doctorId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                continue;
            }
            if (!is_array($row)) {
                continue;
            }

            $displayName = trim((string)($row['display_name'] ?? ''));
            if ($displayName === '') {
                continue;
            }
            $resolved['display_name'] = $displayName;
            $resolved['prefix'] = $this->toNullableText($row['prefix'] ?? null);
            $resolved['gender_label'] = $this->toNullableText($row['gender_label'] ?? null);
            $resolved['photo_url'] = $this->toNullableText($row['photo_url'] ?? null);
            $resolved['avatar_url'] = $this->toNullableText($row['avatar_url'] ?? null);
            $resolved['logo_url'] = $this->toNullableText($row['logo_url'] ?? null);
            return $resolved;
        }

        return $resolved;
    }

    private function resolveProfessional(string $doctorId): array
    {
        $result = [
            'professional_license' => null,
            'specialty_license' => null,
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

        $candidates = [
            [
                'table' => 'doctor_profiles',
                'id' => ['doctor_id', 'profile_id'],
                'professional_license' => ['professional_license', 'cedula_profesional', 'cedula'],
                'specialty_license' => ['specialty_license', 'cedula_especialidad'],
                'bio_short' => ['bio_short', 'bio', 'resena', 'descripcion_corta'],
            ],
            [
                'table' => 'doctors',
                'id' => ['doctor_id', 'id'],
                'professional_license' => ['professional_license', 'cedula_profesional', 'cedula'],
                'specialty_license' => ['specialty_license', 'cedula_especialidad'],
                'bio_short' => ['bio_short', 'bio', 'resena', 'descripcion_corta'],
            ],
            [
                'table' => 'medicos',
                'id' => ['doctor_id', 'id', 'medico_id'],
                'professional_license' => ['professional_license', 'cedula_profesional', 'cedula'],
                'specialty_license' => ['specialty_license', 'cedula_especialidad'],
                'bio_short' => ['bio_short', 'bio', 'resena', 'descripcion_corta'],
            ],
        ];

        foreach ($candidates as $candidate) {
            $table = (string)($candidate['table'] ?? '');
            if ($table === '' || !$this->tableExists($table)) {
                continue;
            }
            $cols = $this->tableColumns($table);
            $idCol = $this->firstExistingColumn($cols, $candidate['id']);
            if ($idCol === null) {
                continue;
            }
            $professionalCol = $this->firstExistingColumn($cols, $candidate['professional_license']);
            $specialtyCol = $this->firstExistingColumn($cols, $candidate['specialty_license']);
            $bioCol = $this->firstExistingColumn($cols, $candidate['bio_short']);
            if ($professionalCol === null && $specialtyCol === null && $bioCol === null) {
                continue;
            }

            $queryCols = [];
            if ($professionalCol !== null) {
                $queryCols[] = sprintf('`%s` AS professional_license', $professionalCol);
            }
            if ($specialtyCol !== null) {
                $queryCols[] = sprintf('`%s` AS specialty_license', $specialtyCol);
            }
            if ($bioCol !== null) {
                $queryCols[] = sprintf('`%s` AS bio_short', $bioCol);
            }

            $sql = sprintf(
                'SELECT %s FROM `%s` WHERE `%s` = :doctor_id LIMIT 1',
                implode(', ', $queryCols),
                $table,
                $idCol
            );
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['doctor_id' => $doctorId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                continue;
            }
            if (!is_array($row)) {
                continue;
            }

            $result['professional_license'] = $this->toNullableText($row['professional_license'] ?? null);
            $result['specialty_license'] = $this->toNullableText($row['specialty_license'] ?? null);
            $result['bio_short'] = $this->toNullableText($row['bio_short'] ?? null);
            return $result;
        }

        return $result;
    }

    private function resolveSpecialties(string $doctorId): array
    {
        $candidates = [
            ['table' => 'doctor_specialties', 'doctor_col' => 'doctor_id', 'name_col' => 'specialty_name'],
            ['table' => 'doctor_specialties', 'doctor_col' => 'doctor_id', 'name_col' => 'name'],
            ['table' => 'medico_especialidades', 'doctor_col' => 'doctor_id', 'name_col' => 'especialidad'],
        ];

        foreach ($candidates as $candidate) {
            $table = (string)$candidate['table'];
            if (!$this->tableExists($table)) {
                continue;
            }
            $cols = $this->tableColumns($table);
            $doctorCol = trim((string)$candidate['doctor_col']);
            $nameCol = trim((string)$candidate['name_col']);
            if (!in_array($doctorCol, $cols, true) || !in_array($nameCol, $cols, true)) {
                continue;
            }
            $idCol = in_array('specialty_id', $cols, true) ? 'specialty_id' : null;
            $isPrimaryCol = in_array('is_primary', $cols, true) ? 'is_primary' : null;
            $parts = [];
            if ($idCol !== null) {
                $parts[] = sprintf('`%s` AS specialty_id', $idCol);
            }
            $parts[] = sprintf('`%s` AS name_es', $nameCol);
            if ($isPrimaryCol !== null) {
                $parts[] = sprintf('`%s` AS is_primary', $isPrimaryCol);
            }
            $sql = sprintf(
                'SELECT %s FROM `%s` WHERE `%s` = :doctor_id',
                implode(', ', $parts),
                $table,
                $doctorCol
            );
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['doctor_id' => $doctorId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                continue;
            }
            if (!is_array($rows) || empty($rows)) {
                continue;
            }

            $out = [];
            foreach ($rows as $row) {
                $name = trim((string)($row['name_es'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $slug = $this->slugify($name);
                $out[] = [
                    'specialty_id' => $this->toNullableText($row['specialty_id'] ?? null),
                    'name_es' => $name,
                    'name_plural_es' => null,
                    'slug' => ($slug !== '' ? $slug : null),
                    'schema_medical_specialty' => null,
                    'is_primary' => ((int)($row['is_primary'] ?? 0) === 1),
                ];
            }
            return $out;
        }

        return [];
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
