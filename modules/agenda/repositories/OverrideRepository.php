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
    private bool $enabled = false;

    private const TIMEZONE = AvailabilityRepository::TIMEZONE;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['overrides_table'] ?? ''));
        $this->enabled = $table !== '';
        if ($this->enabled) {
            $this->table = $table;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getOverridesForDate(string $doctorId, string $consultorioId, string $dateYmd): array
    {
        if (!$this->enabled) {
            return [];
        }

        if (!$this->tableExists($this->table)) {
            throw new RuntimeException('availability overrides not ready');
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id AND (date = :date OR date_ymd = :date)"
        );
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
            $start = $this->resolveDatetime($row, $dateYmd, ['start_at', 'start_time', 'from_time', 'hora_inicio'], '00:00:00');
            $end = $this->resolveDatetime($row, $dateYmd, ['end_at', 'end_time', 'to_time', 'hora_fin'], '23:59:59');
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

    private function loadConfig(): array
    {
        $path = __DIR__ . '/../config/agenda.php';
        if (!is_file($path)) {
            return [];
        }
        $config = require $path;
        return is_array($config) ? $config : [];
    }

    private function tableExists(?string $name): bool
    {
        if (!$name) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
