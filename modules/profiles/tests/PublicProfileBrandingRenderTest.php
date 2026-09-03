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
pdb04bAssert(str_contains($pageSource, "'Logotipo de ' . \$displayName"), 'physician logo receives descriptive alt text');
pdb04bAssert(str_contains($pageSource, "brandName.textContent = 'Consultorio';"), 'consultorio fallback text is exact');
pdb04bAssert(str_contains($pageSource, "brandLogo.alt = 'Logotipo de ' + name;"), 'consultorio logo alt text follows active brand');
pdb04bAssert(str_contains($pageSource, 'syncBranding(panels.find(function (panel)'), 'tab activation synchronizes consultorio branding');
pdb04bAssert(str_contains($pageSource, "brandLogo.removeAttribute('src');"), 'missing active logo collapses without stale image');

pdb04bAssert(str_contains($cssSource, '.mxpp-physician-logo img'), 'physician logo has bounded layout styling');
pdb04bAssert(str_contains($cssSource, '.mxpp-consultorio-brand__logo[hidden]'), 'missing consultorio logo is removed from layout');
pdb04bAssert(str_contains($cssSource, '@media (max-width: 640px)'), 'responsive profile breakpoint remains present');

echo "PublicProfileBrandingRenderTest PASS\n";
