<?php
declare(strict_types=1);

namespace Agenda\Adapters;

use Agenda\Availability\CanonicalAvailabilityException;
use Agenda\Availability\CanonicalScheduleVersion;
use Agenda\Availability\WeeklyScheduleWindow;

final class CanonicalScheduleReadAdapter
{
    private const SOURCE_TABLES = [
        'consultorio_schedule',
        'consultorio_schedules',
        'consultorio_horarios',
        'consultorio_horarios_base',
        'agenda_consultorio_schedule',
    ];

    public static function canonicalScheduleReadEnabled(array $config): bool
    {
        return ($config['feature_flags']['canonical_schedule_read'] ?? false) === true;
    }

    public static function isRealConsultorioId(string $consultorioId): bool
    {
        $value = trim($consultorioId);
        return $value !== '' && $value !== '__all__';
    }

    public function adapt(array $legacySnapshot, array $canonicalParameters): CanonicalScheduleVersion
    {
        foreach (['source_table', 'legacy_doctor_id', 'consultorio_id', 'rows'] as $key) {
            if (!array_key_exists($key, $legacySnapshot)) {
                throw new CanonicalAvailabilityException('canonical_schedule_missing', 'legacy schedule snapshot is incomplete');
            }
        }
        if (!is_string($legacySnapshot['source_table'])
            || !in_array($legacySnapshot['source_table'], self::SOURCE_TABLES, true)) {
            throw new CanonicalAvailabilityException('canonical_schedule_missing', 'legacy schedule source is invalid');
        }
        if (!is_string($legacySnapshot['legacy_doctor_id'])
            || trim($legacySnapshot['legacy_doctor_id']) === '') {
            throw new CanonicalAvailabilityException('canonical_schedule_missing', 'legacy doctor reference is required');
        }
        if (!is_string($legacySnapshot['consultorio_id'])
            || !self::isRealConsultorioId($legacySnapshot['consultorio_id'])) {
            throw new CanonicalAvailabilityException('consultorio_mismatch', 'a real consultorio is required');
        }
        if (!is_array($legacySnapshot['rows'])) {
            throw new CanonicalAvailabilityException('invalid_window', 'legacy schedule rows must be an array');
        }

        $types = [
            'version_id' => 'string',
            'version' => 'integer',
            'profile_id' => 'string',
            'timezone' => 'string',
            'effective_from' => 'string',
            'effective_until' => ['string', 'NULL'],
            'duration_minutes' => 'integer',
            'gap_minutes' => 'integer',
        ];
        foreach ($types as $key => $expected) {
            if (!array_key_exists($key, $canonicalParameters)) {
                throw new CanonicalAvailabilityException('canonical_schedule_missing', 'canonical schedule parameters are incomplete');
            }
            $actual = gettype($canonicalParameters[$key]);
            $valid = is_array($expected)
                ? in_array($actual, $expected, true)
                : $actual === $expected;
            if (!$valid) {
                throw new CanonicalAvailabilityException('canonical_schedule_missing', 'canonical schedule parameter type is invalid');
            }
        }

        $windows = [];
        foreach ($legacySnapshot['rows'] as $row) {
            if (!is_array($row)
                || !array_key_exists('is_active', $row)
                || !is_bool($row['is_active'])) {
                throw new CanonicalAvailabilityException('invalid_window', 'legacy schedule row is invalid');
            }
            if ($row['is_active'] === false) {
                continue;
            }
            foreach (['weekday', 'start_time', 'end_time'] as $key) {
                if (!array_key_exists($key, $row)) {
                    throw new CanonicalAvailabilityException('invalid_window', 'active legacy schedule row is incomplete');
                }
            }
            if (!is_int($row['weekday']) || $row['weekday'] < 1 || $row['weekday'] > 7
                || !is_string($row['start_time']) || !is_string($row['end_time'])) {
                throw new CanonicalAvailabilityException('invalid_window', 'active legacy schedule row is malformed');
            }
            $start = self::normalizeTime($row['start_time']);
            $end = self::normalizeTime($row['end_time']);
            $windows[] = new WeeklyScheduleWindow($row['weekday'], $start, $end);
        }

        return new CanonicalScheduleVersion(
            $canonicalParameters['version_id'],
            $canonicalParameters['version'],
            $canonicalParameters['profile_id'],
            trim($legacySnapshot['consultorio_id']),
            $canonicalParameters['timezone'],
            $canonicalParameters['effective_from'],
            $canonicalParameters['effective_until'],
            $canonicalParameters['duration_minutes'],
            $canonicalParameters['gap_minutes'],
            $windows
        );
    }

    private static function normalizeTime(string $value): string
    {
        if (preg_match('/\A([01][0-9]|2[0-3]):[0-5][0-9](?::([0-5][0-9]))?\z/D', $value, $match) !== 1) {
            throw new CanonicalAvailabilityException('invalid_window', 'legacy schedule time is malformed');
        }
        if (isset($match[2]) && $match[2] !== '00') {
            throw new CanonicalAvailabilityException('invalid_window', 'legacy schedule seconds must be zero');
        }
        return substr($value, 0, 5);
    }
}
