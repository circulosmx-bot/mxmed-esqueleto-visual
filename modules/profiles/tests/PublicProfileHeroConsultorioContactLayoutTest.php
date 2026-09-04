<?php
declare(strict_types=1);

use Profiles\Controllers\PublicProfileController;
use Profiles\Repositories\PublicProfileRepository;

require_once __DIR__ . '/../repositories/PublicProfileRepository.php';
require_once __DIR__ . '/../controllers/PublicProfileController.php';

function pdb04dAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$repository = (new ReflectionClass(PublicProfileRepository::class))->newInstanceWithoutConstructor();
$controller = new PublicProfileController($repository);
$isPublicContactRow = new ReflectionMethod(PublicProfileController::class, 'isPublicContactRow');

$eligibleContact = [
    'is_public' => 1,
    'use_for_public_profile' => 1,
    'use_for_security' => 0,
    'use_for_platform_admin' => 0,
    'status' => 'active',
    'scope' => 'public_profile',
];
pdb04dAssert($isPublicContactRow->invoke($controller, $eligibleContact) === true, 'eligible public profile contact is accepted');
foreach (['is_public', 'use_for_public_profile', 'use_for_security', 'use_for_platform_admin', 'status', 'scope'] as $field) {
    $privateContact = $eligibleContact;
    $privateContact[$field] = match ($field) {
        'is_public', 'use_for_public_profile' => 0,
        'use_for_security', 'use_for_platform_admin' => 1,
        'status' => 'inactive',
        default => 'private',
    };
    pdb04dAssert($isPublicContactRow->invoke($controller, $privateContact) === false, 'private contact is excluded when ' . $field . ' is unsafe');
}

$pageSource = (string)file_get_contents(__DIR__ . '/../../../profiles/doctor.php');
$cssSource = (string)file_get_contents(__DIR__ . '/../../../assets/css/public-profile.css');
$controllerSource = (string)file_get_contents(__DIR__ . '/../controllers/PublicProfileController.php');

// Hero: accepted PDB-04C composition plus the two bounded navigation actions.
pdb04dAssert(str_contains($pageSource, 'mxpp-license-inline--professional'), 'professional license remains beside the name');
pdb04dAssert(str_contains($pageSource, 'mxpp-license-inline--specialty'), 'specialty license remains beside the specialty');
pdb04dAssert(!str_contains($pageSource, 'mxpp-licenses-inline') && !str_contains($pageSource, 'mxpp-license-divider'), 'standalone license row remains absent');
pdb04dAssert(str_contains($pageSource, 'class="mxpp-rating-pill"'), 'compact review bar remains in use');
pdb04dAssert(str_contains($pageSource, 'href="#sobre-mi"') && str_contains($pageSource, '<span>Sobre mí</span>'), 'about action targets a stable real profile anchor');
pdb04dAssert(str_contains($pageSource, '<?php if ($showAgendaSlot): ?>') && str_contains($pageSource, 'href="#proximas-citas"'), 'consultation action is plan-safe and targets the existing agenda');
pdb04dAssert(!preg_match('/href="#proximas-citas"[^>]*data-mxpp-booking-trigger/', $pageSource), 'consultation action does not invoke booking');
pdb04dAssert(str_contains($pageSource, '<?php if ($physicianLogoUrl !== null): ?>') && str_contains($pageSource, 'mxpp-physician-logo'), 'conditional personal logo remains preserved');

// Consultorio branding and inline switching.
pdb04dAssert(str_contains($pageSource, 'data-mxpp-consultorio-brand-logo') && str_contains($pageSource, 'Logotipo de '), 'consultorio logo and alt authority remain present');
pdb04dAssert(str_contains($pageSource, '$primaryBrandName = toText($primaryConsultorio[\'brand_name\'] ?? null) ?? $primaryName;'), 'initial no-logo fallback uses persisted brand or consultorio name');
pdb04dAssert(str_contains($pageSource, 'brandName.textContent = name;'), 'active no-logo fallback uses persisted active name');
pdb04dAssert(str_contains($pageSource, 'mxpp-consultorio-tab__name') && str_contains($pageSource, '$consultorio[\'name\']'), 'tabs retain persisted consultorio labels');
pdb04dAssert(str_contains($pageSource, 'syncBranding(panels.find(function (panel)'), 'tab switch synchronizes active branding');
pdb04dAssert(str_contains($pageSource, 'panel.hidden = panel.id !== panelId;'), 'tab switch atomically swaps active contact and map panel');
pdb04dAssert(str_contains($cssSource, 'min-height: 76px;') && str_contains($cssSource, '.mxpp-consultorio-tab__eyebrow'), 'taller branded bar and light card controls are styled');

// Contact actions use only the controller-gated DTO values and safe local normalizers.
pdb04dAssert(str_contains($controllerSource, "\$base['source'] = 'doctor_contact_points';"), 'contact authority remains doctor_contact_points');
pdb04dAssert(str_contains($pageSource, 'href="<?= h($contactPhoneHref) ?>"') && str_contains($pageSource, '<span>Llamar</span>'), 'conditional click-to-call action uses sanitized href');
pdb04dAssert(str_contains($pageSource, 'href="<?= h($contactWhatsappHref) ?>"') && str_contains($pageSource, '<span>WhatsApp</span>'), 'conditional WhatsApp action uses sanitized href');
pdb04dAssert(str_contains($pageSource, '<?php if ($contactPhoneHref !== null): ?>'), 'call button is absent without a safe phone href');
pdb04dAssert(str_contains($pageSource, '<?php if ($contactWhatsappHref !== null): ?>'), 'WhatsApp button is absent without a safe WhatsApp href');
pdb04dAssert(str_contains($pageSource, "return 'https://wa.me/' . \$digits;") && !str_contains($pageSource, 'api.whatsapp.com/send?phone='), 'no arbitrary WhatsApp destination is introduced');

// Map and regression boundaries.
pdb04dAssert(str_contains($pageSource, '<iframe src="<?= h($consultorioMapUrl) ?>"'), 'map remains tied to each active consultorio panel');
pdb04dAssert(str_contains($pageSource, '<a class="mxpp-map-link" href="<?= h($consultorioMapUrl) ?>"'), 'allowed Google Maps action is a real safe-authority link');
pdb04dAssert(str_contains($pageSource, 'id="proximas-citas"') && str_contains($pageSource, 'data-mxpp-agenda-compact'), 'existing agenda block receives only a navigation anchor');
pdb04dAssert(str_contains($cssSource, 'overflow-x: auto;') && str_contains($cssSource, '@media (max-width: 640px)'), 'mobile selector remains usable without page overflow');

echo "PublicProfileHeroConsultorioContactLayoutTest PASS\n";
