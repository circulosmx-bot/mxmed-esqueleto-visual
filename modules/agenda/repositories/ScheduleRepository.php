<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class ScheduleRepository
{
    private PDO $pdo;
    private ?string $table = null;
    private ?array $writeColumns = null;

    private array $tableCandidates = [
        'consultorio_schedule',
        'consultorio_schedules',
        'consultorio_horarios',
        'consultorio_horarios_base',
        'agenda_consultorio_schedule',
    ];

    private const WEEKDAY_KEYS = ['weekday', 'day_of_week', 'dia_semana', 'day'];
    private const START_KEYS = ['start_time', 'start_at', 'hora_inicio', 'time_from', 'inicio'];
    private const END_KEYS = ['end_time', 'end_at', 'hora_fin', 'time_to', 'fin'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->table = $this->locateTable();
    }

    public function listByDoctorConsultorio(string $doctorId, string $consultorioId): array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT * FROM %s WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id',
            $this->table
        ));
        $stmt->execute([
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $weekday = $this->extractWeekday($row);
            $start = $this->extractTime($row, self::START_KEYS);
            $end = $this->extractTime($row, self::END_KEYS);
            if ($weekday === null || !$start || !$end) {
                continue;
            }
            $result[] = [
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
                'is_active' => isset($row['is_active']) ? ((int)$row['is_active'] === 1) : true,
            ];
        }
        return $result;
    }

    public function listByDoctor(string $doctorId): array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT * FROM %s WHERE doctor_id = :doctor_id',
            $this->table
        ));
        $stmt->execute([
            'doctor_id' => $doctorId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $weekday = $this->extractWeekday($row);
            $start = $this->extractTime($row, self::START_KEYS);
            $end = $this->extractTime($row, self::END_KEYS);
            $consultorioId = trim((string)($row['consultorio_id'] ?? ''));
            if ($weekday === null || !$start || !$end || $consultorioId === '') {
                continue;
            }
            $result[] = [
                'consultorio_id' => $consultorioId,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
                'is_active' => isset($row['is_active']) ? ((int)$row['is_active'] === 1) : true,
            ];
        }
        return $result;
    }

    public function replaceWeeklySchedule(string $doctorId, string $consultorioId, array $segments): void
    {
        $this->ensureTable();
        $cols = $this->resolveWriteColumns();
        if (!$cols) {
            throw new RuntimeException('availability base schedule not ready');
        }

        $this->pdo->beginTransaction();
        try {
            $deleteSql = sprintf(
                'DELETE FROM %s WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id',
                $this->table
            );
            $delete = $this->pdo->prepare($deleteSql);
            $delete->execute([
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
            ]);

            if (!empty($segments)) {
                $fields = ['doctor_id', 'consultorio_id', $cols['weekday'], $cols['start'], $cols['end']];
                $params = [':doctor_id', ':consultorio_id', ':weekday', ':start_time', ':end_time'];
                if ($cols['is_active']) {
                    $fields[] = 'is_active';
                    $params[] = ':is_active';
                }
                $insertSql = sprintf(
                    'INSERT INTO %s (%s) VALUES (%s)',
                    $this->table,
                    implode(', ', $fields),
                    implode(', ', $params)
                );
                $insert = $this->pdo->prepare($insertSql);
                foreach ($segments as $segment) {
                    $bind = [
                        'doctor_id' => $doctorId,
                        'consultorio_id' => $consultorioId,
                        'weekday' => (int)$segment['weekday'],
                        'start_time' => (string)$segment['start_time'],
                        'end_time' => (string)$segment['end_time'],
                    ];
                    if ($cols['is_active']) {
                        $bind['is_active'] = 1;
                    }
                    $insert->execute($bind);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function ensureTable(): void
    {
        if (!$this->table) {
            throw new RuntimeException('availability base schedule not ready');
        }
    }

    private function locateTable(): ?string
    {
        foreach ($this->tableCandidates as $candidate) {
            if ($this->tableExists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function resolveWriteColumns(): ?array
    {
        if ($this->writeColumns !== null) {
            return $this->writeColumns;
        }
        if (!$this->table) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $this->table]);
        $names = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $find = function (array $candidates) use ($names): ?string {
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $names, true)) {
                    return $candidate;
                }
            }
            return null;
        };

        $weekday = $find(self::WEEKDAY_KEYS);
        $start = $find(self::START_KEYS);
        $end = $find(self::END_KEYS);
        if (!$weekday || !$start || !$end) {
            return null;
        }
        $this->writeColumns = [
            'weekday' => $weekday,
            'start' => $start,
            'end' => $end,
            'is_active' => in_array('is_active', $names, true),
        ];
        return $this->writeColumns;
    }

    private function extractWeekday(array $row): ?int
    {
        foreach (self::WEEKDAY_KEYS as $key) {
            if (!isset($row[$key])) {
                continue;
            }
            $normalized = (int)$row[$key];
            if ($normalized >= 1 && $normalized <= 7) {
                return $normalized;
            }
            if ($normalized >= 0 && $normalized <= 6) {
                return $normalized === 0 ? 7 : $normalized;
            }
        }
        return null;
    }

    private function extractTime(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!isset($row[$key])) {
                continue;
            }
            $normalized = $this->normalizeTime((string)$row[$key]);
            if ($normalized) {
                return $normalized;
            }
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
}
