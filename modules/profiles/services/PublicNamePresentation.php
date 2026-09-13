<?php
declare(strict_types=1);

namespace Profiles\Services;

// Presentation only: never changes verified-name authority or persisted data.
final class PublicNamePresentation
{
    public const KNOWN_PREFIXES = ['Dr.', 'Dra.', 'Psic.', 'Psicóloga', 'Lic.', 'Mtro.', 'Mtra.', 'C.D.', 'Dent.', 'QFB', 'Nut.', 'Fisio.'];

    public static function withoutLegacyPrefix(mixed $displayName): ?string
    {
        $name = self::text($displayName);
        if ($name === null) return null;
        $prefixes = implode('|', array_map(static fn(string $prefix): string => preg_quote($prefix, '/'), self::KNOWN_PREFIXES));
        // Strip exactly one catalog prefix followed by whitespace, never an arbitrary first word.
        return self::text(preg_replace('/^(?:' . $prefixes . ')\s+/iu', '', $name, 1));
    }

    public static function compose(mixed $prefix, mixed $displayName): ?string
    {
        $name = self::withoutLegacyPrefix($displayName);
        if ($name === null) return null;
        return implode(' ', array_filter([self::text($prefix), $name], static fn(?string $value): bool => $value !== null));
    }

    private static function text(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) return null;
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }
}
