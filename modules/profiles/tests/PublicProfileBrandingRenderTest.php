<?php
declare(strict_types=1);

use Profiles\Controllers\PublicProfileController;
use Profiles\Repositories\PublicProfileRepository;

require_once __DIR__ . '/../repositories/PublicProfileRepository.php';
require_once __DIR__ . '/../controllers/PublicProfileController.php';

function pdb04bAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$repository = (new ReflectionClass(PublicProfileRepository::class))->newInstanceWithoutConstructor();
$controller = new PublicProfileController($repository);
$sanitizeLogo = new ReflectionMethod(PublicProfileController::class, 'sanitizePublicLogoUrl');

$dataLogo = 'data:image/png;base64,aGVsbG8=';
pdb04bAssert($sanitizeLogo->invoke($controller, $dataLogo) === $dataLogo, 'persisted image data URL remains renderable');
pdb04bAssert($sanitizeLogo->invoke($controller, 'https://cdn.example.test/logo.png') === 'https://cdn.example.test/logo.png', 'HTTPS logo remains renderable');
pdb04bAssert($sanitizeLogo->invoke($controller, 'javascript:alert(1)') === null, 'unsafe logo scheme is rejected');
pdb04bAssert($sanitizeLogo->invoke($controller, null) === null, 'absent logo remains absent');

$repositorySource = (string)file_get_contents(__DIR__ . '/../repositories/PublicProfileRepository.php');
$controllerSource = (string)file_get_contents(__DIR__ . '/../controllers/PublicProfileController.php');
$pageSource = (string)file_get_contents(__DIR__ . '/../../../profiles/doctor.php');
$cssSource = (string)file_get_contents(__DIR__ . '/../../../assets/css/public-profile.css');

pdb04bAssert(str_contains($repositorySource, "'logo_url',"), 'consultorio logo field is selected from persisted source');
pdb04bAssert(str_contains($controllerSource, "'brand_logo_url' => \$this->sanitizePublicLogoUrl"), 'consultorio logo is mapped through public sanitizer');
pdb04bAssert(str_contains($controllerSource, "'brand_name' => \$brandName"), 'consultorio or group name is mapped');

pdb04bAssert(str_contains($pageSource, '<?php if ($physicianLogoUrl !== null): ?>'), 'physician logo renders only when available');
pdb04bAssert(!str_contains($pageSource, 'mxpp-physician-logo--placeholder'), 'physician logo has no empty placeholder');
pdb04bAssert(!str_contains($pageSource, 'Consultas recientes de este perfil no disponibles por ahora.'), 'obsolete recent-consultations notice is absent');
pdb04bAssert(str_contains($pageSource, "'Logotipo de ' . \$displayName"), 'physician logo receives descriptive alt text');
pdb04bAssert(str_contains($pageSource, 'brandName.textContent = name;'), 'consultorio fallback uses the active persisted name');
pdb04bAssert(str_contains($pageSource, "brandLogo.alt = 'Logotipo de ' + name;"), 'consultorio logo alt text follows active brand');
pdb04bAssert(str_contains($pageSource, 'syncBranding(panels.find(function (panel)'), 'tab activation synchronizes consultorio branding');
pdb04bAssert(str_contains($pageSource, "brandLogo.removeAttribute('src');"), 'missing active logo collapses without stale image');

$titleLinePosition = strpos($pageSource, 'class="mxpp-title-primary-line"');
$ratingPosition = strpos($pageSource, 'class="mxpp-rating-row"');
$specialtyLinePosition = strpos($pageSource, 'class="mxpp-specialty-line"');
pdb04bAssert($titleLinePosition !== false && $ratingPosition !== false && $specialtyLinePosition !== false, 'hero lines are present');
pdb04bAssert($titleLinePosition < $ratingPosition && $ratingPosition < $specialtyLinePosition, 'review bar remains between title and specialty lines');
pdb04bAssert(str_contains($pageSource, '<?php if ($professionalLicense !== null): ?>') && str_contains($pageSource, 'mxpp-license-inline--professional'), 'professional license is conditional beside name');
pdb04bAssert(str_contains($pageSource, '<?php if ($specialtyLicense !== null): ?>') && str_contains($pageSource, 'mxpp-license-inline--specialty'), 'specialty license is conditional beside specialty');
pdb04bAssert(substr_count($pageSource, '<strong>Cédula profesional:</strong>') === 1, 'professional license label is not duplicated');
pdb04bAssert(substr_count($pageSource, '<strong>Cédula especialidad:</strong>') === 1, 'specialty license label is not duplicated');
pdb04bAssert(!str_contains($pageSource, 'mxpp-licenses-inline') && !str_contains($pageSource, 'mxpp-license-divider'), 'standalone license row is absent');
pdb04bAssert(str_contains($pageSource, 'mxpp-badge <?= $isPublic'), 'verified badge is preserved');

pdb04bAssert(str_contains($cssSource, '.mxpp-physician-logo img'), 'physician logo has bounded layout styling');
pdb04bAssert(str_contains($cssSource, 'max-width: min(280px, 62%);') && str_contains($cssSource, 'max-height: 92px;'), 'physician logo uses enlarged responsive bounds');
pdb04bAssert(str_contains($cssSource, '.mxpp-consultorio-brand__logo[hidden]'), 'missing consultorio logo is removed from layout');
pdb04bAssert(str_contains($cssSource, '@media (max-width: 640px)'), 'responsive profile breakpoint remains present');
pdb04bAssert(str_contains($cssSource, 'flex-wrap: wrap;') && str_contains($cssSource, 'overflow-wrap: anywhere;'), 'long text wraps without horizontal overflow');
pdb04bAssert(str_contains($cssSource, 'min-height: 32px;') && str_contains($cssSource, 'font-size: 1.04rem;') && str_contains($cssSource, 'font-size: 0.86rem;'), 'rating pill dimensions are reduced by approximately 20 percent');
pdb04bAssert(!preg_match('/\\.mxpp-rating-pill\\s*\\{[^}]*transform\\s*:/s', $cssSource), 'rating pill does not use transform scaling');
pdb04bAssert(str_contains($cssSource, '.mxpp-rating-write-link') && str_contains($cssSource, 'font-size: 1rem;'), 'review write action size is preserved');

echo "PublicProfileBrandingRenderTest PASS\n";
