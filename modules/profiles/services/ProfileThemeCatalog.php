<?php
declare(strict_types=1);

namespace Profiles\Services;

final class ProfileThemeCatalog
{
    public const DEFAULT_KEY = 'mxmed_teal';

    private const LIGHT_STRONG_SURFACE_KEYS = [
        'soft_lavender',
        'dusty_pink',
        'warm_ivory',
        'clinical_light_sky',
        'ice_blue',
    ];

    private const THEMES = [
        'mxmed_teal' => ['México Médico turquesa', '#10ADBA', '#0A99A6'],
        'medical_blue' => ['Azul médico', '#1E88E5'],
        'navy_medical' => ['Azul marino médico', '#0D1B3D'],
        'soft_lavender' => ['Lavanda suave', '#B7A6E6'],
        'emerald_green' => ['Verde esmeralda', '#009E73'],
        'clinical_pink' => ['Rosa clínico', '#E91E63'],
        'dusty_pink' => ['Rosa empolvado', '#F4B6C2'],
        'soft_coral' => ['Coral suave', '#FF6F61'],
        'terracotta' => ['Terracota', '#C25A3C'],
        'soft_gold' => ['Dorado suave', '#C9A227'],
        'warm_ivory' => ['Marfil cálido', '#F2E8D5'],
        'plum' => ['Ciruela', '#6A1B9A'],
        'clinical_sky' => ['Cielo clínico', '#5DADE2'],
        'steel_blue' => ['Azul acero', '#5C7C9D'],
        'petroleum_blue' => ['Azul petróleo', '#2C7A7B'],
        'cobalt_blue' => ['Azul cobalto', '#2F5FB3'],
        'clinical_light_sky' => ['Cielo claro clínico', '#7EC2FF'],
        'royal_blue' => ['Azul rey', '#1A4DFF'],
        'deep_ocean_blue' => ['Azul océano profundo', '#0A3D62'],
        'ice_blue' => ['Azul hielo', '#CFE2F3'],
    ];

    public static function keys(): array
    {
        return array_keys(self::THEMES);
    }

    public static function isApproved(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::THEMES);
    }

    public static function normalize(?string $key): ?string
    {
        $value = trim((string)$key);
        return self::isApproved($value) ? $value : null;
    }

    public static function resolve(?string $key): array
    {
        $resolvedKey = self::normalize($key) ?? self::DEFAULT_KEY;
        $definition = self::THEMES[$resolvedKey];
        [$label, $accent] = $definition;
        [$red, $green, $blue] = self::rgb($accent);
        $contrast = self::contrastColor($red, $green, $blue);
        $accentHover = $definition[2] ?? self::shade($red, $green, $blue, 0.82);
        $onAccentStrong = in_array($resolvedKey, self::LIGHT_STRONG_SURFACE_KEYS, true)
            ? '#000000'
            : '#FFFFFF';
        $accentStrong = ($onAccentStrong === '#FFFFFF' && $resolvedKey !== self::DEFAULT_KEY)
            ? self::ensureWhiteContrast($accentHover)
            : $accentHover;
        $consultorioCardActiveBorder = self::strongFrameColor($accentStrong);

        return [
            'key' => $resolvedKey,
            'label' => $label,
            'accent' => $accent,
            'accent_soft' => sprintf('rgba(%d, %d, %d, 0.14)', $red, $green, $blue),
            'accent_soft_2' => sprintf('rgba(%d, %d, %d, 0.24)', $red, $green, $blue),
            'accent_hover' => $accentHover,
            'accent_border' => sprintf('rgba(%d, %d, %d, 0.42)', $red, $green, $blue),
            'accent_contrast' => $contrast,
            'accent_strong' => $accentStrong,
            'on_accent_strong' => $onAccentStrong,
            'strong_foreground' => $onAccentStrong === '#FFFFFF' ? 'WHITE' : 'DARK',
            'consultorio_card_active_border' => $consultorioCardActiveBorder,
        ];
    }

    public static function all(): array
    {
        return array_map(static fn(string $key): array => self::resolve($key), self::keys());
    }

    public static function cssVariables(array $theme): string
    {
        $pairs = [
            '--profile-accent' => $theme['accent'] ?? '',
            '--profile-accent-soft' => $theme['accent_soft'] ?? '',
            '--profile-accent-soft-2' => $theme['accent_soft_2'] ?? '',
            '--profile-accent-hover' => $theme['accent_hover'] ?? '',
            '--profile-accent-border' => $theme['accent_border'] ?? '',
            '--profile-accent-contrast' => $theme['accent_contrast'] ?? '',
            '--profile-accent-strong' => $theme['accent_strong'] ?? '',
            '--profile-on-accent-strong' => $theme['on_accent_strong'] ?? '',
            '--profile-consultorio-card-active-border' => $theme['consultorio_card_active_border'] ?? '',
        ];
        $out = [];
        foreach ($pairs as $name => $value) {
            $out[] = $name . ':' . (string)$value;
        }
        return implode(';', $out);
    }

    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function shade(int $red, int $green, int $blue, float $factor): string
    {
        return sprintf('#%02X%02X%02X', (int)round($red * $factor), (int)round($green * $factor), (int)round($blue * $factor));
    }

    private static function contrastColor(int $red, int $green, int $blue): string
    {
        $luminance = self::relativeLuminance($red, $green, $blue);
        $whiteRatio = 1.05 / ($luminance + 0.05);
        return $whiteRatio >= 4.5 ? '#FFFFFF' : '#000000';
    }

    private static function ensureWhiteContrast(string $hex): string
    {
        [$red, $green, $blue] = self::rgb($hex);
        while ((1.05 / (self::relativeLuminance($red, $green, $blue) + 0.05)) < 4.5) {
            $red = (int)round($red * 0.94);
            $green = (int)round($green * 0.94);
            $blue = (int)round($blue * 0.94);
        }
        return sprintf('#%02X%02X%02X', $red, $green, $blue);
    }

    private static function strongFrameColor(string $hex): string
    {
        [$red, $green, $blue] = self::rgb($hex);
        if (self::relativeLuminance($red, $green, $blue) < 0.18) {
            $red = (int)round($red + (255 - $red) * 0.28);
            $green = (int)round($green + (255 - $green) * 0.28);
            $blue = (int)round($blue + (255 - $blue) * 0.28);
            return sprintf('#%02X%02X%02X', $red, $green, $blue);
        }
        return self::shade($red, $green, $blue, 0.68);
    }

    private static function relativeLuminance(int $red, int $green, int $blue): float
    {
        $channels = array_map(static function (int $channel): float {
            $value = $channel / 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, [$red, $green, $blue]);
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
