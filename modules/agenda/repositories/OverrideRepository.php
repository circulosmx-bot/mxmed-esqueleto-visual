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
        $table = $this->sanitizeIdentifier($config['overrides_table'] ?? '');
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

        // IMPORTANTE:
        // Tu tabla real usa date_ymd (DATE). NO usamos "date" porque no existe.
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM {$this->table}
             WHERE doctor_id = :doctor_id
               AND consultorio_id = :consultorio_id
               AND date_ymd = :date
               AND is_active = 1"
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
            $end   = $this->resolveDatetime($row, $dateYmd, ['end_at', 'end_time', 'to_time', 'hora_fin'], '23:59:59');

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

    public function listBlocks(array $filters = []): array
    {
        $this->ensureReady();
        $columns = $this->getColumns();
        $builder = [];
        $params = [];

        $doctorId = trim((string)($filters['doctor_id'] ?? ''));
        if ($doctorId !== '') {
            $builder[] = 'doctor_id = :doctor_id';
            $params['doctor_id'] = $doctorId;
        }

        $consultorioId = trim((string)($filters['consultorio_id'] ?? ''));
        if ($consultorioId !== '') {
            $builder[] = 'consultorio_id = :consultorio_id';
            $params['consultorio_id'] = $consultorioId;
        }

        $dateYmd = trim((string)($filters['date'] ?? ''));
        if ($dateYmd !== '' && $this->hasColumn($columns, 'date_ymd')) {
            $builder[] = 'date_ymd = :date_ymd';
            $params['date_ymd'] = $dateYmd;
        }

        $from = trim((string)($filters['from'] ?? ''));
        $to = trim((string)($filters['to'] ?? ''));
        if ($from !== '' && $to !== '' && $this->hasColumn($columns, 'start_at') && $this->hasColumn($columns, 'end_at')) {
            $builder[] = '(start_at < :to_dt AND end_at > :from_dt)';
            $params['to_dt'] = $to;
            $params['from_dt'] = $from;
        } elseif ($from !== '' && $this->hasColumn($columns, 'end_at')) {
            $builder[] = 'end_at > :from_dt';
            $params['from_dt'] = $from;
        } elseif ($to !== '' && $this->hasColumn($columns, 'start_at')) {
            $builder[] = 'start_at < :to_dt';
            $params['to_dt'] = $to;
        }

        $activeOnly = array_key_exists('active_only', $filters)
            ? $this->normalizeBoolean($filters['active_only'], true)
            : true;
        if ($activeOnly && $this->hasColumn($columns, 'is_active')) {
            $builder[] = 'is_active = 1';
        }

        $sql = sprintf('SELECT * FROM %s', $this->table);
        if (!empty($builder)) {
            $sql .= ' WHERE ' . implode(' AND ', $builder);
        }
        $orderColumn = $this->hasColumn($columns, 'override_id') ? 'override_id' : 'created_at';
        $sql .= sprintf(' ORDER BY %s DESC LIMIT 1000', $orderColumn);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'normalizeBlockRow'], $rows);
    }

    public function findBlockById($id): ?array
    {
        $this->ensureReady();
        $blockId = (int)$id;
        if ($blockId <= 0) {
            return null;
        }
        $columns = $this->getColumns();
        if (!$this->hasColumn($columns, 'override_id')) {
            throw new RuntimeException('availability overrides not ready');
        }

        $sql = sprintf('SELECT * FROM %s WHERE override_id = :override_id LIMIT 1', $this->table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['override_id' => $blockId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->normalizeBlockRow($row);
    }

    public function createBlock(array $payload): array
    {
        $this->ensureReady();
        $columns = $this->getColumns();
        $now = (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');

        $insert = [
            'doctor_id' => trim((string)($payload['doctor_id'] ?? '')),
            'consultorio_id' => trim((string)($payload['consultorio_id'] ?? '')),
            'date_ymd' => trim((string)($payload['date_ymd'] ?? '')),
            'type' => trim((string)($payload['type'] ?? 'close')),
            'start_at' => trim((string)($payload['start_at'] ?? '')),
            'end_at' => trim((string)($payload['end_at'] ?? '')),
            'is_active' => 1,
            'created_at' => $now,
            'reason' => isset($payload['reason']) ? trim((string)$payload['reason']) : null,
        ];

        $data = [];
        foreach ($insert as $column => $value) {
            if ($this->hasColumn($columns, $column)) {
                $data[$column] = $value;
            }
        }

        if (empty($data)) {
            throw new RuntimeException('availability overrides not ready');
        }

        $columnsSql = implode(', ', array_keys($data));
        $valuesSql = implode(', ', array_map(static fn($key) => ':' . $key, array_keys($data)));
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->table, $columnsSql, $valuesSql);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        $insertedId = (int)$this->pdo->lastInsertId();
        if ($insertedId > 0) {
            $row = $this->findBlockById($insertedId);
            if ($row) {
                return $row;
            }
        }

        return $this->normalizeBlockRow($data);
    }

    public function deactivateBlock($id, array $payload = []): array
    {
        $this->ensureReady();
        $blockId = (int)$id;
        if ($blockId <= 0) {
            throw new RuntimeException('block not found');
        }
        $current = $this->findBlockById($blockId);
        if (!$current) {
            throw new RuntimeException('block not found');
        }

        $isActive = (int)($current['is_active'] ?? 0);
        if ($isActive === 0) {
            $current['already_inactive'] = true;
            return $current;
        }

        $columns = $this->getColumns();
        $set = ['is_active = :is_active'];
        $params = [
            'is_active' => 0,
            'override_id' => $blockId,
        ];
        if ($this->hasColumn($columns, 'reason') && isset($payload['reason'])) {
            $set[] = 'reason = :reason';
            $params['reason'] = trim((string)$payload['reason']);
        }

        $sql = sprintf('UPDATE %s SET %s WHERE override_id = :override_id', $this->table, implode(', ', $set));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $next = $this->findBlockById($blockId);
        if (!$next) {
            throw new RuntimeException('block not found');
        }
        $next['already_inactive'] = false;
        return $next;
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

    private function ensureReady(): void
    {
        if (!$this->enabled || !$this->table || !$this->tableExists($this->table)) {
            throw new RuntimeException('availability overrides not ready');
        }
    }

    private function getColumns(): array
    {
        $sql = 'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['table' => $this->table]);
        return array_map(static fn($value) => strtolower(trim((string)$value)), $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function hasColumn(array $columns, string $name): bool
    {
        return in_array(strtolower($name), $columns, true);
    }

    private function normalizeBoolean($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return ((int)$value) === 1;
        }
        $raw = strtolower(trim((string)$value));
        if (in_array($raw, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($raw, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
        return $default;
    }

    private function normalizeBlockRow(array $row): array
    {
        return [
            'override_id' => isset($row['override_id']) ? (int)$row['override_id'] : null,
            'doctor_id' => isset($row['doctor_id']) ? trim((string)$row['doctor_id']) : '',
            'consultorio_id' => isset($row['consultorio_id']) ? trim((string)$row['consultorio_id']) : '',
            'date_ymd' => isset($row['date_ymd']) ? trim((string)$row['date_ymd']) : '',
            'type' => isset($row['type']) ? strtolower(trim((string)$row['type'])) : '',
            'start_at' => isset($row['start_at']) ? trim((string)$row['start_at']) : '',
            'end_at' => isset($row['end_at']) ? trim((string)$row['end_at']) : '',
            'is_active' => isset($row['is_active']) ? (int)$row['is_active'] : 0,
            'created_at' => isset($row['created_at']) ? trim((string)$row['created_at']) : '',
            'reason' => array_key_exists('reason', $row) ? (($row['reason'] === null || trim((string)$row['reason']) === '') ? null : trim((string)$row['reason'])) : null,
        ];
    }

    private function sanitizeIdentifier($value): string
    {
        $candidate = trim((string)$value);
        if ($candidate === '') {
            return '';
        }
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $candidate) ? $candidate : '';
    }
}
