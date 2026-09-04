<?php
declare(strict_types=1);

use Profiles\Controllers\PrivateProfileController;

require_once __DIR__ . '/../controllers/PrivateProfileController.php';

function theme01aIntegrationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$controller = (new ReflectionClass(PrivateProfileController::class))->newInstanceWithoutConstructor();
$prepare = new ReflectionMethod(PrivateProfileController::class, 'prepareEditablePayload');
$valid = $prepare->invoke($controller, ['profile_theme_key' => 'plum']);
$invalid = $prepare->invoke($controller, ['profile_theme_key' => '#ff00ff']);
$reset = $prepare->invoke($controller, ['profile_theme_key' => null]);
theme01aIntegrationAssert(($valid['editable']['profile_theme_key'] ?? null) === 'plum', 'approved key accepted');
theme01aIntegrationAssert($invalid['editable'] === [] && $invalid['unknown_fields'] === ['profile_theme_key:invalid_catalog_key'], 'arbitrary color rejected');
theme01aIntegrationAssert(array_key_exists('profile_theme_key', $reset['editable']) && $reset['editable']['profile_theme_key'] === null, 'reset persists null');

$root = dirname(__DIR__, 3);
$privateRepo = (string)file_get_contents(__DIR__ . '/../repositories/PrivateProfileRepository.php');
$publicRepo = (string)file_get_contents(__DIR__ . '/../repositories/PublicProfileRepository.php');
$page = (string)file_get_contents($root . '/profiles/doctor.php');
$css = (string)file_get_contents($root . '/assets/css/public-profile.css');
$admin = (string)file_get_contents($root . '/index.html');
$adminJs = (string)file_get_contents($root . '/assets/js/app.js');

theme01aIntegrationAssert(str_contains($privateRepo, "'profile_theme_key' => 'profile_theme_key'"), 'theme key is repository writable');
theme01aIntegrationAssert(str_contains($publicRepo, "'profile_theme_key',"), 'theme key is read from canonical profile');
theme01aIntegrationAssert(str_contains($page, "mxmed_theme_preview") && str_contains($page, 'isLocalDevRequest()'), 'local-only preview query is bounded');
theme01aIntegrationAssert(str_contains($page, 'MXMED_PROFILE_THEME_PUBLIC_ENABLED') === false, 'view does not independently widen production rollout');
theme01aIntegrationAssert(str_contains($page, 'data-profile-theme='), 'resolved theme is observable on profile root');
theme01aIntegrationAssert(substr_count($admin, 'mx-theme-admin__swatches') === 1, 'single admin selector exists');
theme01aIntegrationAssert(str_contains($adminJs, 'catalog.length !== 20'), 'admin requires exact catalog size');
theme01aIntegrationAssert(!str_contains($admin, 'type="color"'), 'no free color picker exists');
theme01aIntegrationAssert(str_contains($adminJs, "profile_theme_key: state.themeSelectedKey"), 'admin persists key only');
theme01aIntegrationAssert(str_contains($css, 'var(--profile-accent)') && str_contains($css, 'var(--profile-accent-soft)'), 'public profile consumes controlled variables');
theme01aIntegrationAssert(str_contains($css, '.mxpp-consultorio-bar') && str_contains($css, '.mxpp-agenda-compact__header') && str_contains($css, '.mxpp-gallery-bar'), 'candidate component hooks exist');
theme01aIntegrationAssert(str_contains($css, '.mxpp-contact-cta--whatsapp') && str_contains($css, 'background: #29ac63;'), 'WhatsApp semantic green remains fixed');

echo "ProfileThemeIntegrationContractTest PASS\n";
