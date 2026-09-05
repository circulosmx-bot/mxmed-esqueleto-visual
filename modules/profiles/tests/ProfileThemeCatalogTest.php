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
    'mxmed_teal' => '#10ADBA', 'medical_blue' => '#1E88E5', 'navy_medical' => '#1569C7',
    'soft_lavender' => '#B7A6E6', 'emerald_green' => '#009E73', 'clinical_pink' => '#E91E63',
    'dusty_pink' => '#F4B6C2', 'soft_coral' => '#FF6F61', 'terracotta' => '#C25A3C',
    'soft_gold' => '#E0BB58', 'warm_ivory' => '#F2E8D5', 'plum' => '#6A1B9A',
    'clinical_sky' => '#5DADE2', 'steel_blue' => '#5C7C9D', 'petroleum_blue' => '#2C7A7B',
    'cobalt_blue' => '#2F5FB3', 'clinical_light_sky' => '#7EC2FF', 'royal_blue' => '#00BFFF',
    'deep_ocean_blue' => '#0A3D62', 'ice_blue' => '#CFE2F3',
];

$catalog = ProfileThemeCatalog::all();
theme01aAssert(count($catalog) === 20, 'catalog contains exactly 20 themes');
theme01aAssert(ProfileThemeCatalog::keys() === array_keys($expected), 'catalog keys and order are exact');
foreach ($catalog as $theme) {
    $key = $theme['key'];
    theme01aAssert($theme['accent'] === $expected[$key], 'accent matches contract for ' . $key);
    foreach (['label', 'accent_soft', 'accent_soft_2', 'accent_hover', 'accent_border', 'accent_contrast', 'on_theme_surface', 'accent_strong', 'on_accent_strong', 'strong_foreground', 'consultorio_card_active_border', 'consultorio_card_foreground', 'consultorio_fg_selected', 'consultorio_fg_unselected'] as $field) {
        theme01aAssert(trim((string)($theme[$field] ?? '')) !== '', $key . ' has ' . $field);
    }
    [$expectedRed, $expectedGreen, $expectedBlue] = array_map('hexdec', [substr($expected[$key], 1, 2), substr($expected[$key], 3, 2), substr($expected[$key], 5, 2)]);
    if (in_array($key, ['warm_ivory', 'ice_blue'], true)) {
        theme01aAssert($theme['consultorio_fg_selected'] === '#113D59', $key . ' selected consultorio foreground uses the Director dark foreground override');
        theme01aAssert($theme['consultorio_fg_selected'] === $theme['consultorio_card_foreground'], $key . ' selected and unselected consultorio foreground are unified for the light theme exception');
    } else {
        theme01aAssert($theme['consultorio_fg_selected'] === $expected[$key], $key . ' selected consultorio foreground uses accent at 100 percent');
    }
    if (in_array($key, ['warm_ivory', 'ice_blue'], true)) {
        theme01aAssert($theme['consultorio_fg_unselected'] === '#113D59', $key . ' unselected consultorio foreground remains the Director dark foreground for light themes');
    } else {
        theme01aAssert($theme['consultorio_fg_unselected'] === sprintf('rgba(%d, %d, %d, 0.50)', $expectedRed, $expectedGreen, $expectedBlue), $key . ' unselected consultorio foreground uses accent at 50 percent');
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
theme01aAssert(ProfileThemeCatalog::resolve('soft_gold')['label'] === 'Dorado Metálico', 'soft gold selector label uses the approved metallic name');
theme01aAssert(ProfileThemeCatalog::resolve('navy_medical')['label'] === 'Azul Profundo', 'persisted navy key exposes the approved deep-blue label');
$deepBlue = ProfileThemeCatalog::resolve('navy_medical');
theme01aAssert($deepBlue['accent_soft'] === 'rgba(21, 105, 199, 0.14)', 'deep-blue soft token derives from the new base');
theme01aAssert($deepBlue['accent_soft_2'] === 'rgba(21, 105, 199, 0.24)', 'deep-blue second soft token derives from the new base');
theme01aAssert($deepBlue['accent_hover'] === '#1156A3', 'deep-blue hover token derives from the new base');
theme01aAssert($deepBlue['accent_border'] === 'rgba(21, 105, 199, 0.42)', 'deep-blue border token derives from the new base');
theme01aAssert($deepBlue['accent_strong'] === '#1156A3', 'deep-blue strong surface derives from the new base');

theme01aAssert(ProfileThemeCatalog::resolve('royal_blue')['label'] === 'Azul Cielo Profundo', 'persisted royal_blue key exposes the approved deep-sky label');

$whiteStrongKeys = [];
$directorDarkForegroundKeys = [];
foreach ($catalog as $theme) {
    if ($theme['strong_foreground'] === 'WHITE') {
        $whiteStrongKeys[] = $theme['key'];
        theme01aAssert($theme['on_accent_strong'] === '#FFFFFF', $theme['key'] . ' strong foreground is white');
        theme01aAssert($theme['on_theme_surface'] === '#FFFFFF', $theme['key'] . ' section-surface foreground is white');
    } else {
        $directorDarkForegroundKeys[] = $theme['key'];
        theme01aAssert($theme['on_accent_strong'] === '#113D59', $theme['key'] . ' uses the Director dark foreground on strong surfaces');
        theme01aAssert($theme['on_theme_surface'] === '#113D59', $theme['key'] . ' uses the Director dark foreground on section surfaces');
    }
    $strongLuminance = theme01aLuminance($theme['accent_strong']);
    $strongForegroundLuminance = theme01aLuminance($theme['on_accent_strong']);
    $strongRatio = (max($strongLuminance, $strongForegroundLuminance) + 0.05) / (min($strongLuminance, $strongForegroundLuminance) + 0.05);
    $minimumStrongRatio = $theme['on_theme_surface'] === '#113D59' || $theme['key'] === 'mxmed_teal' ? 3.0 : 4.5;
    theme01aAssert($strongRatio >= $minimumStrongRatio, $theme['key'] . ' strong surface foreground remains readable');
    theme01aAssert($theme['consultorio_card_active_border'] !== $theme['accent_strong'], $theme['key'] . ' active consultorio frame is visibly distinct from its fill');
}

$exceptionCount = 0;
foreach ($catalog as $theme) {
    if ($theme['consultorio_card_foreground'] === '#113D59') {
        $exceptionCount++;
        theme01aAssert(in_array($theme['key'], ['warm_ivory', 'ice_blue'], true), 'exception foreground token only applies to exception themes');
        theme01aAssert($theme['on_theme_surface'] === '#113D59', $theme['key'] . ' exception token aligns with director dark theme surface');
    } else {
        theme01aAssert($theme['consultorio_card_foreground'] === '#FFFFFF', $theme['key'] . ' consultorio card foreground remains white');
        theme01aAssert($theme['on_theme_surface'] === '#FFFFFF', $theme['key'] . ' non-exception theme surface foreground remains white');
    }
}
theme01aAssert($exceptionCount === 2, 'consultorio-card dark foreground exception count is exactly 2');
theme01aAssert(count($whiteStrongKeys) === 18, 'exactly 18 themes use the Director white foreground');
theme01aAssert($directorDarkForegroundKeys === ['warm_ivory', 'ice_blue'], 'only the two Director exceptions use #113D59');

echo "ProfileThemeCatalogTest PASS\n";
