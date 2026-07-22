<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final class AppointmentValue
{
    public static function safeIdentifier(string $value, string $reason): string
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}\z/D', $value) !== 1) {
            throw new AppointmentDomainException($reason, 'safe identifier is required');
        }
        return $value;
    }

    public static function scopedIdentity(string $value, string $reason): string
    {
        $normalized = trim($value);
        if ($normalized === '' || strlen($normalized) > 255 || preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1) {
            throw new AppointmentDomainException($reason, 'scoped identity is required');
        }
        return $normalized;
    }

    public static function timezone(string $value): \DateTimeZone
    {
        if (!in_array($value, \DateTimeZone::listIdentifiers(), true)) {
            throw new AppointmentDomainException('invalid_slot', 'timezone must be an IANA identifier');
        }
        return new \DateTimeZone($value);
    }

    public static function timestamp(string $value, string $reason = 'invalid_timestamp'): \DateTimeImmutable
    {
        if (preg_match(
            '/\A(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})\z/D',
            $value,
            $parts
        ) !== 1) {
            throw new AppointmentDomainException($reason, 'RFC3339 timestamp with explicit offset is required');
        }
        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];
        $hour = (int) $parts[4];
        $minute = (int) $parts[5];
        $second = (int) $parts[6];
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new AppointmentDomainException($reason, 'timestamp components are invalid');
        }
        $offset = $parts[8];
        if ($offset !== 'Z') {
            $offsetHour = (int) substr($offset, 1, 2);
            $offsetMinute = (int) substr($offset, 4, 2);
            if ($offsetHour > 14 || $offsetMinute > 59 || ($offsetHour === 14 && $offsetMinute !== 0)) {
                throw new AppointmentDomainException($reason, 'timestamp offset is invalid');
            }
        }
        try { return new \DateTimeImmutable($value); }
        catch (\Exception) { throw new AppointmentDomainException($reason, 'timestamp is invalid'); }
    }

    public static function canonicalUtc(string $value, string $reason = 'invalid_timestamp'): string
    {
        return self::timestamp($value, $reason)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    public static function canonicalJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function digest(array $value): string
    {
        return hash('sha256', self::canonicalJson($value));
    }

    private function __construct() {}
}
