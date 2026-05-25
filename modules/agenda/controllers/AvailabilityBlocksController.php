<?php
namespace Agenda\Controllers;

use Agenda\Repositories\OverrideRepository;
use Agenda\Repositories\AppointmentCollisionsRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/OverrideRepository.php';
require_once __DIR__ . '/../repositories/AppointmentCollisionsRepository.php';
require_once __DIR__ . '/../config/agenda.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class AvailabilityBlocksController
{
    private const TIMEZONE = 'America/Mexico_City';

    private ?OverrideRepository $repository = null;
    private ?AppointmentCollisionsRepository $collisionsRepository = null;
    private ?string $dbError = null;
    private array $agendaConfig = [];
    private array $actorContext = [];
    private array $contextWarnings = [];

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new OverrideRepository($pdo);
            $config = require __DIR__ . '/../config/agenda.php';
            $this->agendaConfig = is_array($config) ? $config : [];
            $this->collisionsRepository = new AppointmentCollisionsRepository($pdo, $this->agendaConfig);
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
        } catch (PDOException $e) {
            $this->dbError = 'database error';
        } catch (\Throwable $e) {
            $this->dbError = 'database error';
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function index(array $query = []): array
    {
        $this->contextWarnings = [];
        if ($this->dbError || !$this->repository) {
            return $this->error('db_not_ready', 'availability overrides not ready');
        }

        $doctorIdRequested = trim((string)($query['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];

        $consultorioId = trim((string)($query['consultorio_id'] ?? ''));
        if ($consultorioId !== '' && !$this->isValidNumeric($consultorioId)) {
            return $this->error('invalid_params', 'consultorio_id must be numeric', ['consultorio_id' => $consultorioId]);
        }

        $date = trim((string)($query['date'] ?? ''));
        if ($date !== '' && !$this->isValidDate($date)) {
            return $this->error('invalid_params', 'date must be in YYYY-MM-DD format', ['date' => $date]);
        }

        $from = trim((string)($query['from'] ?? ''));
        if ($from !== '' && !$this->isValidDateTime($from)) {
            return $this->error('invalid_params', 'from must be in YYYY-MM-DD HH:MM:SS format', ['from' => $from]);
        }

        $to = trim((string)($query['to'] ?? ''));
        if ($to !== '' && !$this->isValidDateTime($to)) {
            return $this->error('invalid_params', 'to must be in YYYY-MM-DD HH:MM:SS format', ['to' => $to]);
        }

        if ($from !== '' && $to !== '' && $from >= $to) {
            return $this->error('invalid_params', 'invalid datetime range', ['from' => $from, 'to' => $to]);
        }

        $activeOnly = array_key_exists('active_only', $query)
            ? $this->normalizeBoolean($query['active_only'], true)
            : true;

        try {
            $rows = $this->repository->listBlocks([
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'date' => $date,
                'from' => $from,
                'to' => $to,
                'active_only' => $activeOnly,
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'availability overrides not ready') {
                return $this->error('db_not_ready', 'availability overrides not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error');
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($rows, [
            'count' => count($rows),
            'active_only' => $activeOnly,
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
            'action' => 'availability_blocks_list',
        ]);
    }

    public function store(array $payload = []): array
    {
        $this->contextWarnings = [];
        if ($this->dbError || !$this->repository) {
            return $this->error('db_not_ready', 'availability overrides not ready');
        }

        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];

        $scope = strtolower(trim((string)($payload['scope'] ?? 'partial')));
        if (!in_array($scope, ['partial', 'full_day'], true)) {
            return $this->error('invalid_params', 'scope must be partial or full_day', ['scope' => $scope]);
        }

        $type = strtolower(trim((string)($payload['type'] ?? 'close')));
        if ($type !== 'close') {
            return $this->error('invalid_params', 'type must be close', ['type' => $type]);
        }

        $consultorioIds = $this->resolveConsultorioIds($payload);
        if (empty($consultorioIds)) {
            return $this->error('invalid_params', 'consultorio_id or consultorio_ids are required', []);
        }

        $date = trim((string)($payload['date'] ?? ''));
        $startAt = trim((string)($payload['start_at'] ?? ''));
        $endAt = trim((string)($payload['end_at'] ?? ''));

        if ($scope === 'full_day') {
            if (!$this->isValidDate($date)) {
                return $this->error('invalid_params', 'date must be in YYYY-MM-DD format', ['date' => $date]);
            }
            $startAt = $date . ' 00:00:00';
            $endAt = $date . ' 23:59:59';
        } else {
            if (!$this->isValidDateTime($startAt) || !$this->isValidDateTime($endAt)) {
                return $this->error('invalid_params', 'start_at and end_at must be YYYY-MM-DD HH:MM:SS', [
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                ]);
            }
            $date = substr($startAt, 0, 10);
            if ($date !== substr($endAt, 0, 10)) {
                return $this->error('invalid_params', 'start_at and end_at must be in same date', [
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                ]);
            }
        }

        if ($startAt >= $endAt) {
            return $this->error('invalid_params', 'invalid datetime range', [
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]);
        }

        $start = $this->parseDateTime($startAt);
        $now = new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
        if (!$start || $start <= $now) {
            return $this->error('invalid_transition', 'cannot block in past', [
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]);
        }

        if (!$this->collisionsRepository) {
            return $this->error('db_not_ready', 'availability appointments not ready');
        }

        $reason = isset($payload['reason']) ? trim((string)$payload['reason']) : null;
        $created = [];

        try {
            foreach ($consultorioIds as $consultorioId) {
                $busyIntervals = $this->collisionsRepository->getBusyIntervalsForDate($doctorId, $consultorioId, $date, null);
                if ($this->rangeOverlaps($startAt, $endAt, $busyIntervals)) {
                    return $this->error('block_conflict_with_appointments', 'block conflicts with existing appointments', [
                        'doctor_id' => $doctorId,
                        'consultorio_id' => $consultorioId,
                        'start_at' => $startAt,
                        'end_at' => $endAt,
                    ]);
                }

                $created[] = $this->repository->createBlock([
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                    'date_ymd' => $date,
                    'type' => 'close',
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'reason' => $reason,
                ]);
            }
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'availability overrides not ready' || $msg === 'availability appointments not ready') {
                return $this->error('db_not_ready', $msg);
            }
            return $this->error('db_error', 'database error');
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error');
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success([
            'blocks' => $created,
        ], [
            'count' => count($created),
            'scope' => $scope,
            'type' => 'close',
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
            'action' => ($scope === 'full_day' ? 'full_day_blocked' : 'availability_blocked'),
        ]);
    }

    public function deactivate(string $blockId, array $payload = []): array
    {
        $this->contextWarnings = [];
        if ($this->dbError || !$this->repository) {
            return $this->error('db_not_ready', 'availability overrides not ready');
        }

        $id = (int)$blockId;
        if ($id <= 0) {
            return $this->error('invalid_params', 'invalid block id', ['id' => $blockId]);
        }

        $reason = isset($payload['reason']) ? trim((string)$payload['reason']) : null;

        try {
            $current = $this->repository->findBlockById($id);
            if (!$current) {
                return $this->error('not_found', 'availability block not found', ['override_id' => $id]);
            }

            $doctorScope = $this->resolveDoctorScope(trim((string)($current['doctor_id'] ?? '')), true);
            if (!$doctorScope['ok']) {
                return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
            }

            $result = $this->repository->deactivateBlock($id, ['reason' => $reason]);
            $alreadyInactive = (bool)($result['already_inactive'] ?? false);

            if ($alreadyInactive) {
                return $this->success([
                    'block' => $result,
                ], [
                    'override_id' => $id,
                    'action' => 'already_inactive',
                    'message' => 'already_inactive',
                ]);
            }

            return $this->success([
                'block' => $result,
            ], [
                'override_id' => $id,
                'action' => 'availability_unblocked',
            ]);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'block not found') {
                return $this->error('not_found', 'availability block not found', ['override_id' => $id]);
            }
            if ($msg === 'availability overrides not ready') {
                return $this->error('db_not_ready', 'availability overrides not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error');
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error');
        }
    }

    private function resolveConsultorioIds(array $payload): array
    {
        $ids = [];

        $single = trim((string)($payload['consultorio_id'] ?? ''));
        if ($single !== '') {
            $ids[] = $single;
        }

        $many = $payload['consultorio_ids'] ?? null;
        if (is_array($many)) {
            foreach ($many as $value) {
                $candidate = trim((string)$value);
                if ($candidate !== '') {
                    $ids[] = $candidate;
                }
            }
        }

        $ids = array_values(array_unique($ids));
        return array_values(array_filter($ids, fn(string $id): bool => $this->isValidNumeric($id)));
    }

    private function resolveDoctorScope(string $doctorIdRequested, bool $doctorIsRequired): array
    {
        $doctorIdContext = trim((string)($this->actorContext['doctor_id'] ?? ''));
        $strictMode = ($this->actorContext['strict'] ?? false) === true;
        if ($doctorIdContext !== '') {
            if ($doctorIdRequested !== '' && $doctorIdRequested !== $doctorIdContext) {
                if ($strictMode) {
                    return [
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor scope mismatch',
                        'meta' => [
                            'doctor_id_requested' => $doctorIdRequested,
                            'doctor_id_context' => $doctorIdContext,
                        ],
                    ];
                }
                $this->contextWarnings[] = [
                    'type' => 'doctor_scope_mismatch',
                    'doctor_id_requested' => $doctorIdRequested,
                    'doctor_id_context' => $doctorIdContext,
                ];
            }
            return ['ok' => true, 'doctor_id' => $doctorIdContext];
        }
        if ($doctorIsRequired && $doctorIdRequested === '') {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'doctor_id is required',
                'meta' => [],
            ];
        }
        return ['ok' => true, 'doctor_id' => $doctorIdRequested];
    }

    private function rangeOverlaps(string $startAt, string $endAt, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            $busyStart = trim((string)($interval['start_at'] ?? ''));
            $busyEnd = trim((string)($interval['end_at'] ?? ''));
            if ($busyStart === '' || $busyEnd === '') {
                continue;
            }
            if ($startAt < $busyEnd && $endAt > $busyStart) {
                return true;
            }
        }
        return false;
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone(self::TIMEZONE)) ?: null;
    }

    private function isValidDate(string $value): bool
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone(self::TIMEZONE));
        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $value;
    }

    private function isValidDateTime(string $value): bool
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone(self::TIMEZONE));
        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d H:i:s') === $value;
    }

    private function isValidNumeric($value): bool
    {
        return is_scalar($value) && preg_match('/^\\d+$/', trim((string)$value)) === 1;
    }

    private function normalizeBoolean($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
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

    private function success($data, array $meta = []): array
    {
        $meta = $this->appendAuthMeta($meta);
        $meta['actor_role'] = trim((string)($this->actorContext['actor_role'] ?? ''));
        $meta['actor_id'] = trim((string)($this->actorContext['actor_id'] ?? ''));
        $meta['channel_origin'] = trim((string)($this->actorContext['channel_origin'] ?? ''));
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }

    private function error(string $code, string $message, array $meta = []): array
    {
        $meta = $this->appendAuthMeta($meta);
        $meta['actor_role'] = trim((string)($this->actorContext['actor_role'] ?? ''));
        $meta['actor_id'] = trim((string)($this->actorContext['actor_id'] ?? ''));
        $meta['channel_origin'] = trim((string)($this->actorContext['channel_origin'] ?? ''));
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }

    private function appendAuthMeta(array $meta): array
    {
        $meta['auth_mode'] = trim((string)($this->actorContext['mode'] ?? ''));
        $meta['auth_warnings'] = $this->contextWarnings;
        return $meta;
    }
}
