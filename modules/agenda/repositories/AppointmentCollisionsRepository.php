<?php
declare(strict_types=1);

namespace Agenda\Repositories;

use PDO;
use RuntimeException;
use DateTimeImmutable;
use DateTimeZone;

class AppointmentCollisionsRepository
{
    private PDO $pdo;
    private array $config;
    private string $appointmentPk;

    private const START_KEYS = ['start_at', 'starts_at', 'inicio_at'];
    private const END_KEYS = ['end_at', 'ends_at', 'fin_at'];
    private const STATUS_KEYS = ['status', 'appointment_status', 'state'];
    private const CANCEL_KEYS = ['is_cancelled', 'is_canceled', 'is_no_show'];
    private const TIMEZONE = 'America/Mexico_City';

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->appointmentPk = trim((string)($config['appointment_pk'] ?? 'appointment_id')) ?: 'appointment_id';
    }

    public function getBusyIntervalsForDate(string $doctorId, string $consultorioId, string $dateYmd, ?string $excludeAppointmentId = null): array
    {
        $table = trim((string)($this->config['appointments_table'] ?? ''));
        if ($table === '') {
            throw new RuntimeException('availability appointments not ready');
        }
        if (!$this->tableExists($table)) {
            throw new RuntimeException('availability appointments not ready');
        }

        $columns = $this->getColumns($table);
        $startCol = $this->findColumn(self::START_KEYS, $columns);
        $endCol = $this->findColumn(self::END_KEYS, $columns);
        if (!$startCol || !$endCol) {
            throw new RuntimeException('availability appointments not ready');
        }
        $statusCol = $this->findColumn(self::STATUS_KEYS, $columns);
        $cancelCols = array_values(array_intersect(self::CANCEL_KEYS, $columns));

        $sql = "SELECT * FROM {$table} WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id";
        $sql .= " AND DATE({$startCol}) = :date";
        $params = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'date' => $dateYmd,
        ];
        if ($excludeAppointmentId && in_array($this->appointmentPk, $columns, true)) {
            $sql .= " AND {$this->appointmentPk} <> :exclude_id";
            $params['exclude_id'] = $excludeAppointmentId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tz = new DateTimeZone(self::TIMEZONE);
        $busy = [];
        foreach ($rows as $row) {
            if ($this->isCancelled($row, $statusCol, $cancelCols)) {
                continue;
            }
            $start = $this->parseDatetime($row[$startCol] ?? '', $tz);
            $end = $this->parseDatetime($row[$endCol] ?? '', $tz);
            if (!$start || !$end || $start >= $end) {
                continue;
            }
            $busy[] = [
                'start_at' => $start->format('Y-m-d H:i:s'),
                'end_at' => $end->format('Y-m-d H:i:s'),
                'source' => 'collision',
            ];
        }
        return $busy;
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function getColumns(string $table): array
    {
        $stmt = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function findColumn(array $candidates, array $columns): ?string
    {
        foreach ($candidates as $c) {
            if (in_array(strtolower($c), $columns, true)) {
                return $c;
            }
        }
        return null;
    }

    private function isCancelled(array $row, ?string $statusCol, array $cancelCols): bool
    {
        if ($statusCol && isset($row[$statusCol])) {
            $status = strtolower((string)$row[$statusCol]);
            if (in_array($status, ['cancelled', 'canceled', 'no_show'], true)) {
                return true;
            }
        }
        foreach ($cancelCols as $col) {
            if (!isset($row[$col])) {
                continue;
            }
            $val = strtolower((string)$row[$col]);
            if ($val === '1' || $val === 'true' || $val === 'yes') {
                return true;
            }
        }
        return false;
    }

    private function parseDatetime(string $value, DateTimeZone $tz): ?DateTimeImmutable
    {
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', DateTimeImmutable::ATOM];
        foreach ($formats as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $value, $tz);
            if ($dt) {
                return $dt;
            }
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
    }
}
