<?php
declare(strict_types=1);

use Profiles\Controllers\PublicProfileController;
use Profiles\Repositories\PublicProfileRepository;
use Profiles\Services\PublicProfilePlanCapabilities;

require_once __DIR__ . '/../repositories/PublicProfileRepository.php';
require_once __DIR__ . '/../controllers/PublicProfileController.php';

function pdb04eAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$repository = (new ReflectionClass(PublicProfileRepository::class))->newInstanceWithoutConstructor();
$controller = new PublicProfileController($repository);
$extractContact = new ReflectionMethod(PublicProfileController::class, 'extractPublicConsultorioContact');
$mapConsultorios = new ReflectionMethod(PublicProfileController::class, 'mapConsultorios');

$starRow = [
    'consultorio_id' => '2',
    'titulo' => 'Star Médica',
    'grupo_nombre' => 'Hospitales Star Médica',
    'telefonos_json' => '["(449) 123 4567", "bad"]',
    'whatsapp' => '+52 449 765 4321',
    'logo_url' => 'https://cdn.example.test/star.webp',
    'calle' => 'Avenida Pública',
    'num_ext' => '103',
    'cp' => '20020',
    'municipio' => 'Aguascalientes',
    'estado' => 'Aguascalientes',
];
$contact = $extractContact->invoke($controller, $starRow);
pdb04eAssert($contact === ['phone' => '(449) 123 4567', 'whatsapp' => '+52 449 765 4321'], 'consultorio phone and WhatsApp are bounded and preserve readable formatting');

$paidVisibility = ['show_phone' => true, 'show_whatsapp' => true, 'show_map_gps' => false];
$paidPanels = $mapConsultorios->invoke($controller, [$starRow], [], $paidVisibility);
pdb04eAssert(($paidPanels[0]['phone_public'] ?? null) === '(449) 123 4567', 'paid consultorio explicitly projects public phone');
pdb04eAssert(($paidPanels[0]['whatsapp_public'] ?? null) === '+52 449 765 4321', 'paid consultorio explicitly projects public WhatsApp');
pdb04eAssert(!array_key_exists('telefonos_json', $paidPanels[0]) && !array_key_exists('whatsapp', $paidPanels[0]), 'raw contact columns are absent from public DTO');

$freePanels = $mapConsultorios->invoke($controller, [$starRow], [], ['show_phone' => false, 'show_whatsapp' => false]);
pdb04eAssert(array_key_exists('phone_public', $freePanels[0]) && $freePanels[0]['phone_public'] === null, 'free plan receives no consultorio phone');
pdb04eAssert(array_key_exists('whatsapp_public', $freePanels[0]) && $freePanels[0]['whatsapp_public'] === null, 'free plan receives no consultorio WhatsApp');

$unsafe = $extractContact->invoke($controller, [
    'telefonos_json' => '["javascript:alert(1)", "123"]',
    'whatsapp' => 'https://evil.example/path',
]);
pdb04eAssert($unsafe === ['phone' => null, 'whatsapp' => null], 'unsafe arbitrary contact values are rejected');

foreach (['basic', 'standard', 'optimum', 'professional'] as $paidPlan) {
    $contract = PublicProfilePlanCapabilities::build($paidPlan, ['has_public_profile' => true]);
    pdb04eAssert(($contract['plan']['is_paid'] ?? false) === true && ($contract['plan']['is_active'] ?? false) === true, $paidPlan . ' is an active paid plan');
}
$freeContract = PublicProfilePlanCapabilities::build('free', ['has_public_profile' => true]);
pdb04eAssert(($freeContract['plan']['is_paid'] ?? true) === false, 'free plan is not paid');

$pageSource = (string)file_get_contents(__DIR__ . '/../../../profiles/doctor.php');
$cssSource = (string)file_get_contents(__DIR__ . '/../../../assets/css/public-profile.css');
$controllerSource = (string)file_get_contents(__DIR__ . '/../controllers/PublicProfileController.php');

pdb04eAssert(str_contains($pageSource, '$showPaidProfileCheck') && str_contains($pageSource, 'mxpp-paid-profile-check'), 'paid inline check has an explicit render guard');
pdb04eAssert(str_contains($pageSource, "toBool(\$plan['is_paid'] ?? false)") && str_contains($pageSource, "toBool(\$plan['is_active'] ?? false)"), 'inline check uses actual paid and active plan authority');
pdb04eAssert(!str_contains($pageSource, '>Verificado<'), 'distant verified pill is removed');
pdb04eAssert(str_contains($cssSource, 'grid-template-columns: minmax(260px, 1.35fr) minmax(310px, 1.6fr);'), 'logo and actions have deliberate desktop distribution');
pdb04eAssert(str_contains($cssSource, 'max-width: min(256px, 100%);') && str_contains($cssSource, 'max-height: 100px;') && str_contains($cssSource, 'justify-content: center;'), 'personal logo preserves centered alignment with its requested 20 percent reduction');
pdb04eAssert(str_contains($cssSource, 'width: 64px;') && str_contains($cssSource, 'font-size: 1.35rem;'), 'profile actions have enlarged icons, labels, and hit areas');
pdb04eAssert(str_contains($cssSource, 'max-width: 220px;') && str_contains($cssSource, 'height: 66px;'), 'consultorio group logo has increased bounded prominence');
pdb04eAssert(str_contains($controllerSource, "'phone_public' => \$showPhone ? \$publicContact['phone'] : null"), 'phone DTO projection is explicit and capability-gated');
pdb04eAssert(str_contains($controllerSource, "'whatsapp_public' => \$showWhatsapp ? \$publicContact['whatsapp'] : null"), 'WhatsApp DTO projection is explicit and capability-gated');
pdb04eAssert(str_contains($pageSource, '$consultorioPhoneHref = telHref($consultorioPhone);'), 'active consultorio call target is normalized per panel');
pdb04eAssert(str_contains($pageSource, '$consultorioWhatsappHref = whatsappHref($consultorioWhatsapp);'), 'active consultorio WhatsApp target is normalized per panel');
pdb04eAssert(str_contains($pageSource, 'panel.hidden = panel.id !== panelId;'), 'switching replaces the complete contact/action/map panel without stale targets');
pdb04eAssert(str_contains($pageSource, '<iframe src="<?= h($consultorioMapUrl) ?>"'), 'map remains synchronized per consultorio');
pdb04eAssert(str_contains($cssSource, 'grid-template-columns: minmax(210px, 32%) minmax(0, 68%);'), 'desktop map remains in the right column');
pdb04eAssert(str_contains($pageSource, 'mxpp-consultorio-map-col--with-title') && str_contains($cssSource, 'padding-top: 0.82rem;'), 'desktop map clears the turquoise bar and aligns with the consultorio title');
pdb04eAssert(str_contains($cssSource, 'height: 100%;') && strpos($pageSource, 'class="mxpp-map-link"') < strpos($pageSource, 'class="mxpp-consultorio-contact-actions"'), 'map fills through the action-button baseline and buttons remain the final contact element');

echo "PublicProfileVisualFidelityContactTest PASS\n";
