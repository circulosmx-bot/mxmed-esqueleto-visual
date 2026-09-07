<?php
declare(strict_types=1);

namespace Profiles\Services;

final class PublicBookingDoctorReference
{
    public static function question(array $identity): string
    {
        $title = self::titleFromExplicitGender($identity['gender_label'] ?? $identity['gender'] ?? null);
        $surname = self::firstSurname($identity);
        if ($title === null || $surname === null) {
            return '¿Es su primera consulta con este especialista?';
        }
        $article = $title === 'Dra.' ? 'la' : 'el';
        return sprintf('¿Es su primera consulta con %s %s %s?', $article, $title, $surname);
    }

    private static function titleFromExplicitGender(mixed $value): ?string
    {
        $gender = self::normalize((string)$value);
        if (in_array($gender, ['f', 'femenino', 'femenina', 'mujer', 'female', 'feminine'], true)) {
            return 'Dra.';
        }
        if (in_array($gender, ['m', 'masculino', 'masculina', 'hombre', 'male', 'masculine'], true)) {
            return 'Dr.';
        }
        return null;
    }

    private static function firstSurname(array $identity): ?string
    {
        foreach (['first_surname', 'paternal_last_name', 'last_name'] as $field) {
            $value = self::cleanNamePart($identity[$field] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        $displayName = trim((string)($identity['display_name'] ?? ''));
        if ($displayName === '') {
            return null;
        }
        $displayName = preg_replace('/^(?:dra?|doctor(?:a)?)\.?\s+/iu', '', $displayName) ?? '';
        $parts = preg_split('/\s+/u', trim($displayName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return null;
        }
        return self::cleanNamePart($parts[count($parts) >= 3 ? count($parts) - 2 : count($parts) - 1] ?? null);
    }

    private static function cleanNamePart(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text !== '' && preg_match('/^[\p{L}\p{M}\'’.-]+$/u', $text) === 1 ? $text : null;
    }

    private static function normalize(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        return strtr($value, ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u']);
    }
}
