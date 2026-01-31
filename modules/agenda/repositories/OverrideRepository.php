<?php
namespace Agenda\Repositories;

use Agenda\Repositories\AvailabilityRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

class OverrideRepository
{
    private PDO $pdo;
    private ?string $table = null;

    private array $tableCandidates = [
        'agenda_overrides',
        'agenda_exceptions',
        'schedule_exceptions',
        'bloqueos_agenda',
        'disponibilidad_excepciones',
        'agenda_overrides_v1',
        'availability_overrides',
    ];

    private const TIMEZONE = AvailabilityRepository::TIMEZONE;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->table = $this->locateTable();
    }

    public function getOverridesForDate(string $doctorId, string $consultorioId, string $dateYmd): array
    {
        $this->ensureTable();

        $stmt = $this->pdo->prepare(sprintf(
            'SELECT * FROM %s WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id AND date = :date',
            $this->table
        ));
        $stmt->execute([
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'date' => $dateYmd,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $overrides = [];
        foreach ($rows as $row) {
            $type = strtolower(trim($row['type'] ?? ''));
            if ($type !== 'close' && $type !== 'open') {
                continue;
            }
            $start = $this->resolveDatetime($row, $dateYmd, ['start_at', 'from_time', 'hora_inicio'], '00:00:00');
            $end = $this->resolveDatetime($row, $dateYmd, ['end_at', 'to_time', 'hora_fin'], '23:59:59');
            if (!$start || !$end || $start >= $end) {
                continue;
            }
            $overrides[] = [
                'type' => $type,
                'start_at' => $start->format('Y-m-d H:i:s'),
                'end_at' => $end->format('Y-m-d H:i:s'),
                'reason' => $row['reason'] ?? null,
            ];
        }

        return $overrides;
    }

    private function resolveDatetime(array $row, string $dateYmd, array $candidates, string $defaultTime): ?DateTimeImmutable
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        foreach ($candidates as $key) {
            if (empty($row[$key])) {
                continue;
            }
            $value = trim((string)$row[$key]);
            if ($value === '') {
                continue;
            }
            $dt = $this->parseValue($value, $dateYmd, $timezone);
            if ($dt) {
                return $dt;
            }
        }
        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', "{$dateYmd} {$defaultTime}", $timezone);
    }

    private function parseValue(string $value, string $dateYmd, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'H:i:s', 'H:i'];
        foreach ($formats as $format) {
            $template = strpos($format, 'Y-m-d') === false
                ? "{$dateYmd} {$value}"
                : $value;
            $dt = DateTimeImmutable::createFromFormat($format, $template, $timezone);
            if ($dt) {
                return $dt;
            }
        }
        return null;
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
            throw new RuntimeException('availability overrides not ready');
        }
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
