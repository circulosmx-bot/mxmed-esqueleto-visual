<?php
namespace Agenda\Controllers;

use Agenda\Repositories\ScheduleRepository;

require_once __DIR__ . '/../repositories/ScheduleRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class ScheduleController
{
    private ?ScheduleRepository $repository = null;
    private ?string $dbError = null;
    private array $actorContext = [];
    private array $contextWarnings = [];

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new ScheduleRepository($pdo);
        } catch (\RuntimeException $e) {
            $this->dbError = $e->getMessage();
        } catch (\Throwable $e) {
            $this->dbError = 'database error';
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function index(array $params = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'availability base schedule not ready');
        }
        $doctorIdRequested = trim((string)($params['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $consultorioId = trim((string)($params['consultorio_id'] ?? ''));
        if ($doctorId === '' || $consultorioId === '') {
            return $this->error('invalid_params', 'doctor_id and consultorio_id are required', [
                'doctor_id' => $doctorId ?: null,
                'consultorio_id' => $consultorioId ?: null,
            ]);
        }

        try {
            $rows = $this->repository->listByDoctorConsultorio($doctorId, $consultorioId);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'availability base schedule not ready') {
                return $this->error('db_not_ready', 'availability base schedule not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'days' => $this->groupRowsByDay($rows),
            ],
            'meta' => (object)[
                'count' => count($rows),
                'doctor_id_effective' => $doctorId,
                'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
                'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
                'auth_warnings' => $this->contextWarnings,
            ],
        ];
    }

    public function update(array $payload = [])
    {
        $this->contextWarnings = [];
        if ($this->dbError) {
            return $this->error('db_not_ready', 'availability base schedule not ready');
        }
        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $consultorioId = trim((string)($payload['consultorio_id'] ?? ''));
        $days = $payload['days'] ?? null;

        if ($doctorId === '' || $consultorioId === '') {
            return $this->error('invalid_params', 'doctor_id and consultorio_id are required');
        }
        if (!is_array($days)) {
            return $this->error('invalid_params', 'days must be an array');
        }

        [$segments, $errors] = $this->normalizeSegments($days);
        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid schedule payload', ['errors' => $errors]);
        }

        try {
            $this->repository->replaceWeeklySchedule($doctorId, $consultorioId, $segments);
            $rows = $this->repository->listByDoctorConsultorio($doctorId, $consultorioId);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'availability base schedule not ready') {
                return $this->error('db_not_ready', 'availability base schedule not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => 'schedule updated',
            'data' => [
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'days' => $this->groupRowsByDay($rows),
            ],
            'meta' => (object)[
                'segments_saved' => count($segments),
                'doctor_id_effective' => $doctorId,
                'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
                'auth_mode' => trim((string)($this->actorContext['mode'] ?? '')),
                'auth_warnings' => $this->contextWarnings,
            ],
        ];
    }

    private function resolveDoctorScope(string $doctorIdRequested): array
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
        if ($doctorIdRequested === '') {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'doctor_id and consultorio_id are required',
                'meta' => [],
            ];
        }
        return ['ok' => true, 'doctor_id' => $doctorIdRequested];
    }

    private function normalizeSegments(array $days): array
    {
        $segments = [];
        $errors = [];
        foreach ($days as $idx => $day) {
            if (!is_array($day)) {
                $errors[] = "days[$idx] must be object";
                continue;
            }
            $weekday = (int)($day['weekday'] ?? 0);
            if ($weekday < 1 || $weekday > 7) {
                $errors[] = "days[$idx].weekday must be 1..7";
                continue;
            }
            $active = isset($day['active']) ? (bool)$day['active'] : true;
            $windows = isset($day['windows']) && is_array($day['windows']) ? $day['windows'] : [];
            if (!$active) {
                continue;
            }
            foreach ($windows as $wIdx => $window) {
                if (!is_array($window)) {
                    $errors[] = "days[$idx].windows[$wIdx] must be object";
                    continue;
                }
                $start = $this->normalizeTime((string)($window['start_time'] ?? ''));
                $end = $this->normalizeTime((string)($window['end_time'] ?? ''));
                if (!$start || !$end) {
                    $errors[] = "days[$idx].windows[$wIdx] invalid time";
                    continue;
                }
                if ($start >= $end) {
                    $errors[] = "days[$idx].windows[$wIdx] start_time must be before end_time";
                    continue;
                }
                $segments[] = [
                    'weekday' => $weekday,
                    'start_time' => $start,
                    'end_time' => $end,
                ];
            }
        }
        usort($segments, function (array $a, array $b) {
            if ((int)$a['weekday'] === (int)$b['weekday']) {
                return strcmp((string)$a['start_time'], (string)$b['start_time']);
            }
            return ((int)$a['weekday'] <=> (int)$b['weekday']);
        });
        return [$segments, $errors];
    }

    private function groupRowsByDay(array $rows): array
    {
        $days = [];
        for ($weekday = 1; $weekday <= 7; $weekday += 1) {
            $days[$weekday] = [
                'weekday' => $weekday,
                'active' => false,
                'windows' => [],
            ];
        }
        foreach ($rows as $row) {
            $weekday = (int)($row['weekday'] ?? 0);
            if ($weekday < 1 || $weekday > 7) {
                continue;
            }
            $start = $this->normalizeTime((string)($row['start_time'] ?? ''));
            $end = $this->normalizeTime((string)($row['end_time'] ?? ''));
            if (!$start || !$end) {
                continue;
            }
            $days[$weekday]['active'] = true;
            $days[$weekday]['windows'][] = [
                'start_time' => $start,
                'end_time' => $end,
            ];
        }
        return array_values($days);
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

    private function error(string $code, string $message, array $meta = [])
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }
}
