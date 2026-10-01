<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class HealthcareOrganizationType
{
    public const MEDICAL_GROUP = 'MEDICAL_GROUP';
    public const LABORATORY = 'LABORATORY';
    public const DIAGNOSTIC_CENTER = 'DIAGNOSTIC_CENTER';
    public const CLINIC = 'CLINIC';
    public const HOSPITAL = 'HOSPITAL';
    public const DENTAL_ORGANIZATION = 'DENTAL_ORGANIZATION';
    public const OTHER_HEALTHCARE_ORGANIZATION = 'OTHER_HEALTHCARE_ORGANIZATION';

    public static function all(): array
    {
        return [
            self::MEDICAL_GROUP,
            self::LABORATORY,
            self::DIAGNOSTIC_CENTER,
            self::CLINIC,
            self::HOSPITAL,
            self::DENTAL_ORGANIZATION,
            self::OTHER_HEALTHCARE_ORGANIZATION,
        ];
    }

    public static function requireValid(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, self::all(), true)) {
            throw new \InvalidArgumentException('invalid_organization_type_key');
        }
        return $value;
    }
}
