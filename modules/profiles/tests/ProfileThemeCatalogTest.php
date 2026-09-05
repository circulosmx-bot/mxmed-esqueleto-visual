<?php
declare(strict_types=1);

use Profiles\Services\ProfileThemeCatalog;

require_once __DIR__ . '/../services/ProfileThemeCatalog.php';

function theme01aAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function theme01aLuminance(string $hex): float
{
    $values = [substr($hex, 1, 2), substr($hex, 3, 2), substr($hex, 5, 2)];
    $channels = array_map(static function (string $part): float {
        $value = hexdec($part) / 255;
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, $values);
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

$expected = [
    'mxmed_teal' => '#10ADBA', 'medical_blue' => '#1E88E5', 'navy_medical' => '#0D1B3D',
    'soft_lavender' => '#B7A6E6', 'emerald_green' => '#009E73', 'clinical_pink' => '#E91E63',
    'dusty_pink' => '#F4B6C2', 'soft_coral' => '#FF6F61', 'terracotta' => '#C25A3C',
    'soft_gold' => '#C9A227', 'warm_ivory' => '#F2E8D5', 'plum' => '#6A1B9A',
    'clinical_sky' => '#5DADE2', 'steel_blue' => '#5C7C9D', 'petroleum_blue' => '#2C7A7B',
    'cobalt_blue' => '#2F5FB3', 'clinical_light_sky' => '#7EC2FF', 'royal_blue' => '#1A4DFF',
    'deep_ocean_blue' => '#0A3D62', 'ice_blue' => '#CFE2F3',
];

$catalog = ProfileThemeCatalog::all();
theme01aAssert(count($catalog) === 20, 'catalog contains exactly 20 themes');
theme01aAssert(ProfileThemeCatalog::keys() === array_keys($expected), 'catalog keys and order are exact');
foreach ($catalog as $theme) {
    $key = $theme['key'];
    theme01aAssert($theme['accent'] === $expected[$key], 'accent matches contract for ' . $key);
    foreach (['label', 'accent_soft', 'accent_soft_2', 'accent_hover', 'accent_border', 'accent_contrast', 'accent_strong', 'on_accent_strong', 'strong_foreground', 'consultorio_card_active_border'] as $field) {
        theme01aAssert(trim((string)($theme[$field] ?? '')) !== '', $key . ' has ' . $field);
    }
    $l1 = theme01aLuminance($theme['accent']);
    $l2 = theme01aLuminance($theme['accent_contrast']);
    $ratio = (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    theme01aAssert($ratio >= 4.5, $key . ' meets AA text contrast');
}
theme01aAssert(ProfileThemeCatalog::normalize('not-approved') === null, 'invalid key rejected');
theme01aAssert(ProfileThemeCatalog::resolve(null)['key'] === 'mxmed_teal', 'null falls back safely');
theme01aAssert(ProfileThemeCatalog::resolve('not-approved')['key'] === 'mxmed_teal', 'invalid key falls back safely');
theme01aAssert(ProfileThemeCatalog::resolve(null)['accent'] === '#10ADBA', 'null resolves historical native turquoise');
theme01aAssert(ProfileThemeCatalog::resolve('not-approved')['accent'] === '#10ADBA', 'invalid key resolves historical native turquoise');
theme01aAssert(ProfileThemeCatalog::resolve('mxmed_teal')['accent_hover'] === '#0A99A6', 'native hover preserves historical secondary gradient stop');

$whiteStrongKeys = [];
$darkStrongKeys = [];
foreach ($catalog as $theme) {
    if ($theme['strong_foreground'] === 'WHITE') {
        $whiteStrongKeys[] = $theme['key'];
        theme01aAssert($theme['on_accent_strong'] === '#FFFFFF', $theme['key'] . ' strong foreground is white');
    } else {
        $darkStrongKeys[] = $theme['key'];
        theme01aAssert($theme['on_accent_strong'] === '#000000', $theme['key'] . ' light strong surface uses dark foreground');
    }
    $strongLuminance = theme01aLuminance($theme['accent_strong']);
    $strongForegroundLuminance = theme01aLuminance($theme['on_accent_strong']);
    $strongRatio = (max($strongLuminance, $strongForegroundLuminance) + 0.05) / (min($strongLuminance, $strongForegroundLuminance) + 0.05);
    $minimumStrongRatio = $theme['key'] === 'mxmed_teal' ? 3.0 : 4.5;
    theme01aAssert($strongRatio >= $minimumStrongRatio, $theme['key'] . ' strong surface foreground remains readable');
    theme01aAssert($theme['consultorio_card_active_border'] !== $theme['accent_strong'], $theme['key'] . ' active consultorio frame is visibly distinct from its fill');
}
theme01aAssert(count($whiteStrongKeys) === 15, '15 medium/dark/intense themes use white strong foreground');
theme01aAssert($darkStrongKeys === ['soft_lavender', 'dusty_pink', 'warm_ivory', 'clinical_light_sky', 'ice_blue'], 'only the five reviewed light themes use dark strong foreground');

echo "ProfileThemeCatalogTest PASS\n";
