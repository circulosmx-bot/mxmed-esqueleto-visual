<?php
declare(strict_types=1);

namespace Profiles\Services;

final class PublicProfileEligibility
{
    public static function hasMinimumPublicData(
        array $identity,
        array $professional,
        array $specialties,
        array $consultorios
    ): bool {
        if (self::text($identity['display_name'] ?? null) === null) {
            return false;
        }
        if (self::text($professional['professional_license'] ?? null) === null) {
            return false;
        }
        if (!self::hasPrimarySpecialty($professional, $specialties)) {
            return false;
        }

        foreach ($consultorios as $consultorio) {
            if (!is_array($consultorio)) {
                continue;
            }
            if (
                self::text($consultorio['consultorio_id'] ?? null) !== null
                && (bool)($consultorio['is_public'] ?? false)
                && (bool)($consultorio['is_active'] ?? false)
            ) {
                return true;
            }
        }
        return false;
    }

    private static function hasPrimarySpecialty(array $professional, array $specialties): bool
    {
        if (self::text($professional['specialty_primary'] ?? null) !== null) {
            return true;
        }
        foreach ($specialties as $specialty) {
            if (is_array($specialty) && self::text($specialty['name_es'] ?? null) !== null) {
                return true;
            }
        }
        return false;
    }

    private static function text($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }
}
