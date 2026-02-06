<?php
namespace Agenda\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

class AvailabilityRepository
{
    private PDO $pdo;
    private ?string $table = null;

    private array $tableCandidates = [
        'consultorio_schedule',
        'consultorio_schedules',
        'consultorio_horarios',
        'consultorio_horarios_base',
        'agenda_consultorio_schedule',
    ];

    private const WEEKDAY_KEYS = ['weekday', 'day_of_week', 'dia_semana', 'day'];
    private const START_KEYS = ['start_at', 'start_time', 'hora_inicio', 'time_from', 'inicio'];
    private const END_KEYS = ['end_at', 'end_time', 'hora_fin', 'time_to', 'fin'];
    public const TIMEZONE = 'America/Mexico_City';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->table = $this->locateTable();
    }

    public function getBaseWindowsForDate(string $doctorId, string $consultorioId, string $dateYmd): array
    {
        $this->ensureTable();
        $date = $this->createDate($dateYmd);
        $desiredWeekday = (int)$date->format('N');

        $stmt = $this->pdo->prepare(sprintf(
            'SELECT * FROM %s WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id',
            $this->table
        ));
        $stmt->execute([
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $windows = [];
        foreach ($rows as $row) {
            $rowWeekday = $this->extractWeekday($row);
            if ($rowWeekday !== null && $rowWeekday !== $desiredWeekday) {
                continue;
            }
            $start = $this->buildDatetime($row, $date, self::START_KEYS);
            $end = $this->buildDatetime($row, $date, self::END_KEYS);
            if (!$start || !$end) {
                continue;
            }
            if ($start >= $end) {
                continue;
            }
            $windows[] = [
                'start_at' => $start->format('Y-m-d H:i:s'),
                'end_at' => $end->format('Y-m-d H:i:s'),
                'source' => 'A',
            ];
        }

        return $windows;
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

    private function ensureTable(): void
    {
        if (!$this->table) {
            throw new RuntimeException('availability base schedule not ready');
        }
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
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

    private function buildDatetime(array $row, DateTimeImmutable $date, array $fields): ?DateTimeImmutable
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        foreach ($fields as $field) {
            if (empty($row[$field])) {
                continue;
            }
            $value = trim((string)$row[$field]);
            if ($value === '') {
                continue;
            }
            $dt = $this->parseValueToDateTime($value, $date, $timezone);
            if ($dt) {
                return $dt;
            }
        }
        return null;
    }

    private function parseValueToDateTime(string $value, DateTimeImmutable $date, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'H:i:s', 'H:i'];
        foreach ($formats as $format) {
            $needsDate = strpos($format, 'Y-m-d') === false;
            $effectiveFormat = $needsDate ? 'Y-m-d ' . $format : $format;
            $template = $needsDate
                ? $date->format('Y-m-d') . ' ' . $value
                : $value;
            $dt = DateTimeImmutable::createFromFormat($effectiveFormat, $template, $timezone);
            if ($dt) {
                return $dt;
            }
        }
        return null;
    }

    private function createDate(string $value): DateTimeImmutable
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value, $timezone);
        if (!$date) {
            throw new RuntimeException('availability base schedule not ready');
        }
        return $date;
    }
}
