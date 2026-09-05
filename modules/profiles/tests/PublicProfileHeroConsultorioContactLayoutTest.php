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
pdb04dAssert(str_contains($pageSource, 'data-mxpp-profile-view-trigger="about"') && str_contains($pageSource, '<span>Sobre mí</span>'), 'about action switches the shared panel in place');
pdb04dAssert(str_contains($pageSource, '<?php if ($showConsultaAction): ?>') && str_contains($pageSource, 'data-mxpp-profile-view-trigger="consultation"'), 'consultation action uses its plan-safe target');
pdb04dAssert(str_contains($pageSource, 'icon_names=badge,call,event,event_available,groups,health_and_safety,monitor_heart,payments,person,person_text,school,stethoscope,translate,work_history,workspace_premium') && str_contains($pageSource, '>person_text</span>') && str_contains($pageSource, '>event</span>'), 'hero actions use the requested Material Symbols glyphs');
pdb04dAssert(str_contains($cssSource, '.mxpp-hero-action {') && str_contains($cssSource, 'color: #40a8b0;'), 'hero action labels use #40a8b0');
pdb04dAssert(str_contains($cssSource, '.mxpp-hero-action__icon {') && str_contains($cssSource, 'height: 70px;') && str_contains($cssSource, 'width: auto;') && str_contains($cssSource, 'color: #505052;') && str_contains($cssSource, 'font-size: 70px;'), 'hero action icon sizing is fixed-height 70 and color is #505052');
pdb04dAssert(!str_contains($cssSource, 'mxpp-hero-action__icon--about::') && !str_contains($cssSource, 'mxpp-hero-action__icon--consult::'), 'legacy CSS-drawn hero icons are absent');
pdb04dAssert(str_contains($cssSource, '"wght" 400') && str_contains($cssSource, '"opsz" 48') && str_contains($cssSource, 'text-transform: none;'), 'Material Symbols retain the requested variation settings and ligature names');
pdb04dAssert(str_contains($pageSource, 'data-mxpp-profile-view-trigger="consultation"') && !str_contains($pageSource, '$consultaTarget'), 'consultation changes panel state independently of Agenda');
pdb04dAssert(!preg_match('/<button[^>]*data-mxpp-profile-view-trigger="consultation"[^>]*data-mxpp-booking-trigger/', $pageSource), 'consultation action does not invoke booking');
pdb04dAssert(str_contains($pageSource, '<?php if ($physicianLogoUrl !== null): ?>') && str_contains($pageSource, 'mxpp-physician-logo'), 'conditional personal logo remains preserved');

// Consultorio branding and inline switching.
pdb04dAssert(str_contains($pageSource, 'data-mxpp-consultorio-brand-logo') && str_contains($pageSource, 'Logotipo de '), 'consultorio logo and alt authority remain present');
pdb04dAssert(str_contains($pageSource, '$primaryBrandName = toText($primaryConsultorio[\'brand_name\'] ?? null) ?? $primaryName;'), 'initial no-logo fallback uses persisted brand or consultorio name');
pdb04dAssert(str_contains($pageSource, 'brandName.textContent = name;'), 'active no-logo fallback uses persisted active name');
pdb04dAssert(str_contains($pageSource, 'brandName.hidden = true;') && str_contains($pageSource, 'brandName.hidden = false;'), 'active branding shows either logo or fallback name, never both');
pdb04dAssert(str_contains($pageSource, 'mxpp-consultorio-tab__name') && str_contains($pageSource, '$consultorio[\'name\']'), 'tabs retain persisted consultorio labels');
pdb04dAssert(str_contains($pageSource, 'syncBranding(panels.find(function (panel)'), 'tab switch synchronizes active branding');
pdb04dAssert(str_contains($pageSource, 'panel.hidden = panel.id !== panelId;'), 'tab switch atomically swaps active contact and map panel');
pdb04dAssert(str_contains($cssSource, 'height: 60px;') && str_contains($cssSource, 'min-height: 60px;') && str_contains($cssSource, 'align-items: center;') && str_contains($cssSource, 'min-height: 42px;'), '60px branded bar vertically centers the unchanged 42px consultorio cards');
pdb04dAssert(str_contains($cssSource, '.mxpp-consultorio-tab__eyebrow') && str_contains($cssSource, 'font-size: 0.86rem;'), 'consultorio eyebrow label is enlarged by approximately 30 percent');
pdb04dAssert(str_contains($cssSource, '.mxpp-consultorio-tab--active .mxpp-consultorio-tab__eyebrow') && str_contains($cssSource, 'background: var(--mxpp-surface);') && str_contains($cssSource, 'background: rgba(255, 255, 255, 0.5);'), 'consultorio selection uses opaque versus translucent white backgrounds');
pdb04dAssert(substr_count($cssSource, 'color: var(--profile-consultorio-fg-selected);') >= 2, 'selected consultorio foreground uses the selected consultorio semantic token');
pdb04dAssert(str_contains($cssSource, '--profile-consultorio-card-foreground: #ffffff;') && substr_count($cssSource, 'color: var(--profile-consultorio-card-foreground);') >= 4, 'unselected consultorio foreground uses resolved semantic foreground token and preserves white in non-exception themes');
pdb04dAssert(str_contains($cssSource, 'border-color: var(--mxpp-border);') && str_contains($cssSource, 'outline: none;'), 'selected consultorio keeps only its neutral structural boundary');
pdb04dAssert(!str_contains($cssSource, 'inset 0 0 0 1px var(--profile-consultorio-card-active-border)'), 'selected consultorio does not use an inset ring');

// Contact actions use only the controller-gated DTO values and safe local normalizers.
pdb04dAssert(str_contains($controllerSource, "\$base['source'] = 'doctor_contact_points';"), 'contact authority remains doctor_contact_points');
pdb04dAssert(str_contains($pageSource, 'href="<?= h($consultorioPhoneHref) ?>"') && str_contains($pageSource, '<span>Llamar</span>'), 'conditional click-to-call action uses sanitized href');
pdb04dAssert(str_contains($pageSource, 'href="<?= h($consultorioWhatsappHref) ?>"') && str_contains($pageSource, '<span>WhatsApp</span>'), 'conditional WhatsApp action uses sanitized href');
pdb04dAssert(str_contains($pageSource, '<?php if ($consultorioPhoneHref !== null): ?>'), 'call button is absent without a safe phone href');
pdb04dAssert(str_contains($pageSource, '<?php if ($consultorioWhatsappHref !== null): ?>'), 'WhatsApp button is absent without a safe WhatsApp href');
pdb04dAssert(str_contains($pageSource, "return 'https://wa.me/' . \$digits;") && !str_contains($pageSource, 'api.whatsapp.com/send?phone='), 'no arbitrary WhatsApp destination is introduced');

// Map and regression boundaries.
pdb04dAssert(str_contains($pageSource, '<iframe src="<?= h($consultorioMapUrl) ?>"'), 'map remains tied to each active consultorio panel');
pdb04dAssert(str_contains($pageSource, '<a class="mxpp-map-directions" href="<?= h($consultorioDirectionsUrl) ?>"'), 'allowed Google Maps action is a real safe-authority link');
pdb04dAssert(str_contains($pageSource, '<section id="consultorios"'), 'basic consultation anchor has a real consultorio target');
pdb04dAssert(str_contains($pageSource, 'id="proximas-citas"') && str_contains($pageSource, 'data-mxpp-agenda-compact'), 'existing agenda block receives only a navigation anchor');
pdb04dAssert(str_contains($cssSource, 'overflow-x: auto;') && str_contains($cssSource, '@media (max-width: 640px)'), 'mobile selector remains usable without page overflow');

echo "PublicProfileHeroConsultorioContactLayoutTest PASS\n";
