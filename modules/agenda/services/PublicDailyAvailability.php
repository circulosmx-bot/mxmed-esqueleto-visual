<?php
declare(strict_types=1);
namespace Agenda\Services;

use DateTimeImmutable;
use DateTimeZone;
use Agenda\Repositories\AvailabilityRepository;
require_once __DIR__ . '/../repositories/AvailabilityRepository.php';

/** Public result composition only; all scheduling and collision decisions stay in the existing day calculator. */
final class PublicDailyAvailability
{
    public function build(array $profile, string $doctorId, array $params, callable $calculateDay, ?DateTimeImmutable $now = null): array
    {
        $fail = static fn(string $error, string $message): array => ['ok'=>false, 'error'=>$error, 'message'=>$message, 'data'=>null, 'meta'=>(object)[]];
        $enabled = ($profile['public_visibility']['show_public_agenda'] ?? false)
            || ($profile['agenda_public']['enabled'] ?? false) || ($profile['feature_flags']['has_public_agenda'] ?? false);
        if (!($profile['profile']['is_public'] ?? false) || !$enabled) return $fail('forbidden', 'public agenda unavailable');
        $zone = new DateTimeZone(AvailabilityRepository::TIMEZONE);
        $now = ($now ?? new DateTimeImmutable('now', $zone))->setTimezone($zone);
        $today = $now->setTime(0, 0);
        $end = $today->modify('+90 days');
        $mode = $params['mode'] ?? 'day';
        $raw = $params['date'] ?? ($mode === 'global_days' ? $today->format('Y-m-d') : '');
        $date = is_string($raw) ? DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $zone) : false;
        if (!$date || $date->format('Y-m-d') !== $raw || $date < $today || $date >= $end) {
            return $fail('invalid_params', 'date must be valid and within the public 90-day horizon');
        }
        $offices = [];
        foreach (($profile['consultorios'] ?? []) as $office) {
            if (!($office['is_public'] ?? false) || !($office['is_active'] ?? false)) continue;
            $id = (string)($office['consultorio_id'] ?? '');
            if ($id !== '') $offices[$id] = (string)($office['public_name'] ?? 'Consultorio');
        }
        $days = [];
        $cursor = $date;
        do {
            $ymd = $cursor->format('Y-m-d');
            $slots = [];
            foreach ($offices as $id => $name) {
                $result = $calculateDay($doctorId, (string)$id, $ymd);
                if (($result['ok'] ?? false) !== true) return $result['response'] ?? $fail('db_error', 'availability unavailable');
                foreach (($result['slots'] ?? []) as $slot) {
                    $start = (string)($slot['start_at'] ?? '');
                    $finish = (string)($slot['end_at'] ?? '');
                    if ($start <= $now->format('Y-m-d H:i:s') || substr($start, 0, 10) !== $ymd || $finish <= $start) continue;
                    $key = $doctorId . '|' . $id . '|' . $start;
                    $candidate = ['date'=>$ymd, 'doctor_id'=>$doctorId, 'consultorio_id'=>(string)$id,
                        'consultorio_name'=>$name, 'start_at'=>$start, 'end_at'=>$finish];
                    if (!isset($slots[$key]) || $finish < $slots[$key]['end_at']) $slots[$key] = $candidate;
                }
            }
            $slots = array_values($slots);
            usort($slots, static fn(array $a, array $b): int => strcmp($a['start_at'], $b['start_at'])
                ?: strnatcmp($a['consultorio_id'], $b['consultorio_id']) ?: strcmp($a['end_at'], $b['end_at']));
            if ($slots !== [] || $mode === 'day') $days[] = ['date'=>$ymd, 'slots'=>$slots, 'total'=>count($slots)];
            $cursor = $cursor->modify('+1 day');
        } while ($mode === 'global_days' && count($days) < 3 && $cursor < $end && $offices !== []);
        return ['ok'=>true, 'error'=>null, 'message'=>'', 'data'=>['mode'=>$mode, 'days'=>$days], 'meta'=>[
            'timezone'=>AvailabilityRepository::TIMEZONE, 'min_date'=>$today->format('Y-m-d'),
            'max_date'=>$end->modify('-1 day')->format('Y-m-d'), 'horizon_days'=>90,
            'has_more'=>$cursor < $end && $offices !== [], 'scope'=>'public_global',
        ]];
    }
}
