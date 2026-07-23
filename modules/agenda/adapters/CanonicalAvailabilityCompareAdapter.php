<?php
declare(strict_types=1);

namespace Agenda\Adapters;

use Agenda\Availability\CanonicalAvailabilityResult;

final class CanonicalAvailabilityCompareAdapter
{
    public static function canonicalAvailabilityCompareEnabled(array $config): bool
    {
        return ($config['feature_flags']['canonical_availability_compare'] ?? false) === true;
    }

    public function compare(array $legacyResponse, CanonicalAvailabilityResult $canonicalResult): array
    {
        $canonicalArray = $canonicalResult->toArray();
        $canonical = [
            'profile_id' => $canonicalResult->profileId(),
            'consultorio_id' => $canonicalResult->consultorioId(),
            'date' => (string)($canonicalArray['calculation_date'] ?? ''),
            'timezone' => $canonicalResult->timezone(),
            'windows' => self::canonicalWindows($canonicalResult->windows()),
            'slots' => self::canonicalSlots($canonicalResult->slots()),
        ];
        $canonicalDigest = self::digest($canonical);
        $base = [
            'mode' => 'diagnostic_read_only',
            'comparable' => false,
            'equal' => null,
            'reason' => 'legacy_shape_invalid',
            'profile_id' => $canonical['profile_id'],
            'consultorio_id' => $canonical['consultorio_id'],
            'date' => $canonical['date'],
            'timezone' => $canonical['timezone'],
            'legacy_digest' => null,
            'canonical_digest' => $canonicalDigest,
            'differences' => [],
        ];

        if (($legacyResponse['ok'] ?? null) !== true) {
            $base['reason'] = 'legacy_not_ok';
            return $base;
        }
        if (!isset($legacyResponse['data']) || !is_array($legacyResponse['data'])) {
            return $base;
        }
        $data = $legacyResponse['data'];
        foreach (['date', 'timezone', 'doctor_id', 'consultorio_id'] as $key) {
            if (!array_key_exists($key, $data) || !is_string($data[$key])) {
                return $base;
            }
        }
        if (!array_key_exists('windows', $data) || !is_array($data['windows'])
            || !array_key_exists('slots', $data) || !is_array($data['slots'])
            || !self::validDate($data['date'])) {
            return $base;
        }

        $legacyWindows = self::legacyIntervals($data['windows'], $data['date']);
        $legacySlots = self::legacyIntervals($data['slots'], $data['date']);
        if ($legacyWindows === null || $legacySlots === null) {
            return $base;
        }
        $legacy = [
            'profile_id' => $data['doctor_id'],
            'consultorio_id' => $data['consultorio_id'],
            'date' => $data['date'],
            'timezone' => $data['timezone'],
            'windows' => $legacyWindows,
            'slots' => $legacySlots,
        ];
        $base['legacy_digest'] = self::digest($legacy);

        $identityDifferences = [];
        foreach (['profile_id', 'consultorio_id', 'date', 'timezone'] as $dimension) {
            if ($legacy[$dimension] !== $canonical[$dimension]) {
                $identityDifferences[] = $dimension;
            }
        }
        if ($legacy['consultorio_id'] === '__all__'
            && !in_array('consultorio_id', $identityDifferences, true)) {
            $identityDifferences[] = 'consultorio_id';
        }
        if ($identityDifferences !== []) {
            $base['reason'] = self::identityReason($identityDifferences);
            $base['differences'] = $identityDifferences;
            return $base;
        }

        $contentDifferences = [];
        if ($legacy['windows'] !== $canonical['windows']) {
            $contentDifferences[] = 'windows';
        }
        if ($legacy['slots'] !== $canonical['slots']) {
            $contentDifferences[] = 'slots';
        }
        $base['comparable'] = true;
        $base['equal'] = $contentDifferences === [];
        $base['differences'] = $contentDifferences;
        $base['reason'] = match ($contentDifferences) {
            [] => 'equivalent',
            ['windows'] => 'windows_mismatch',
            ['slots'] => 'slots_mismatch',
            default => 'windows_and_slots_mismatch',
        };
        return $base;
    }

    private static function canonicalWindows(array $windows): array
    {
        $normalized = array_map(static fn(array $window): array => [
            'start' => (string)$window['start'],
            'end' => (string)$window['end'],
        ], $windows);
        usort($normalized, self::intervalOrder(...));
        return $normalized;
    }

    private static function canonicalSlots(array $slots): array
    {
        $normalized = array_map(static fn(array $slot): array => [
            'start' => (string)$slot['start'],
            'end' => (string)$slot['end'],
        ], $slots);
        usort($normalized, self::intervalOrder(...));
        return $normalized;
    }

    private static function legacyIntervals(array $intervals, string $date): ?array
    {
        $normalized = [];
        foreach ($intervals as $interval) {
            if (!is_array($interval)
                || !isset($interval['start_at'], $interval['end_at'])
                || !is_string($interval['start_at'])
                || !is_string($interval['end_at'])) {
                return null;
            }
            $start = self::legacyDateTime($interval['start_at'], $date);
            $end = self::legacyDateTime($interval['end_at'], $date);
            if ($start === null || $end === null || $start >= $end) {
                return null;
            }
            $normalized[] = ['start' => $start, 'end' => $end];
        }
        usort($normalized, self::intervalOrder(...));
        return $normalized;
    }

    private static function legacyDateTime(string $value, string $date): ?string
    {
        if (preg_match('/\A([0-9]{4}-[0-9]{2}-[0-9]{2}) ([0-2][0-9]:[0-5][0-9]):([0-5][0-9])\z/D', $value, $match) !== 1
            || $match[1] !== $date
            || $match[3] !== '00') {
            return null;
        }
        $hour = (int)substr($match[2], 0, 2);
        return $hour <= 23 ? $match[2] : null;
    }

    private static function validDate(string $value): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $value;
    }

    private static function intervalOrder(array $left, array $right): int
    {
        return [$left['start'], $left['end']] <=> [$right['start'], $right['end']];
    }

    private static function identityReason(array $differences): string
    {
        foreach (['profile_id', 'consultorio_id', 'date', 'timezone'] as $dimension) {
            if (in_array($dimension, $differences, true)) {
                return match ($dimension) {
                    'profile_id' => 'profile_mismatch',
                    'consultorio_id' => 'consultorio_mismatch',
                    'date' => 'date_mismatch',
                    'timezone' => 'timezone_mismatch',
                };
            }
        }
        return 'legacy_shape_invalid';
    }

    private static function digest(array $value): string
    {
        return hash('sha256', (string)json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
