<?php
namespace Agenda\Services;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;

class HolidayMxProvider
{
    private const FIXED_HOLIDAYS = [
        '01-01' => 'Año Nuevo',
        '05-01' => 'Día del Trabajo',
        '09-16' => 'Día de la Independencia',
        '12-25' => 'Navidad',
    ];

    private const TIMEZONE = 'America/Mexico_City';

    public static function isHoliday(string $dateYmd): array
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd, new DateTimeZone(self::TIMEZONE));
        if (!$date) {
            return ['is_holiday' => false, 'name' => null];
        }

        $monthDay = $date->format('m-d');
        if (isset(self::FIXED_HOLIDAYS[$monthDay])) {
            return ['is_holiday' => true, 'name' => self::FIXED_HOLIDAYS[$monthDay]];
        }

        $year = (int)$date->format('Y');
        $dynamic = [
            'first_monday_february' => ['month' => 2, 'nth' => 1, 'name' => 'Día de la Constitución'],
            'third_monday_march' => ['month' => 3, 'nth' => 3, 'name' => 'Natalicio de Benito Juárez'],
            'third_monday_november' => ['month' => 11, 'nth' => 3, 'name' => 'Revolución Mexicana'],
        ];

        foreach ($dynamic as $rule) {
            $candidate = self::nthWeekdayOfMonth($year, $rule['month'], 1, $rule['nth']);
            if ($candidate && $candidate->format('Y-m-d') === $date->format('Y-m-d')) {
                return ['is_holiday' => true, 'name' => $rule['name']];
            }
        }

        return ['is_holiday' => false, 'name' => null];
    }

    private static function nthWeekdayOfMonth(int $year, int $month, int $weekday, int $nth): ?DateTimeImmutable
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-01', $year, $month), $timezone);
        if (!$date) {
            return null;
        }
        $currentWeekday = (int)$date->format('N');
        $offset = ($weekday - $currentWeekday + 7) % 7;
        $firstMatching = $date->modify("+{$offset} days");
        if ($nth > 1) {
            $firstMatching = $firstMatching->modify('+' . ($nth - 1) . ' weeks');
        }
        return $firstMatching;
    }
}
