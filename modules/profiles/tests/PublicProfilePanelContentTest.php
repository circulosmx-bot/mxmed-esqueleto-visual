<?php
declare(strict_types=1);

use Profiles\Services\PublicProfilePanelContent;
use Profiles\Services\PublicProfilePlanCapabilities;
require_once __DIR__ . '/../services/PublicProfilePanelContent.php';
require_once __DIR__ . '/../services/PublicProfilePlanCapabilities.php';

function panelAssert(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

foreach (['free', 'basic', 'standard', 'optimum', 'professional'] as $plan) {
    $contract = PublicProfilePlanCapabilities::build($plan, ['has_public_profile' => true]);
    $empty = PublicProfilePanelContent::build(['public_visibility' => $contract['public_visibility']]);
    panelAssert(array_keys($empty) === ($plan === 'free' ? [] : ['about', 'consultation']), $plan . ' preserves action gating');
    foreach ($empty as $view) {
        panelAssert($view['groups'] === [] && $view['empty_message'] !== '', 'missing public data has a bounded empty state');
    }
}

$public = [
    'public_visibility' => ['show_about_action' => true, 'show_consulta_action' => true],
    'professional' => ['bio_short' => 'Descripción publicada', 'professional_license' => '001234', 'education' => [], 'languages' => ['Español'], 'services' => ['Consulta de seguimiento']],
    'specialties' => [['name_es' => 'Endocrinología']],
    'consultorios' => [['public_name' => 'Sede A', 'schedule_summary' => ['Lunes 9:00–12:00']], ['public_name' => 'Sede B', 'schedule_summary' => null]],
    'commercial_visibility' => ['consultation_fee' => '750', 'payment_methods' => ['Tarjeta'], 'accepted_insurances' => [['name' => 'Seguro de prueba']]],
];
$views = PublicProfilePanelContent::build($public);
$about = array_column($views['about']['groups'], 'items', 'title');
$consultation = array_column($views['consultation']['groups'], 'items', 'title');
panelAssert($about['Perfil profesional'] === ['Descripción publicada'] && $about['Cédulas'] === ['Cédula profesional: 001234'], 'description and license preserve their public values');
panelAssert($about['Especialidades'] === ['Endocrinología'] && !isset($about['Formación profesional']), 'only populated professional groups are shown');
panelAssert($consultation['Horarios por consultorio'] === ['Sede A: Lunes 9:00–12:00'], 'only published office schedules appear');
panelAssert($consultation['Servicios de consulta'] === ['Consulta de seguimiento'], 'consultation uses public services');
panelAssert(!isset($consultation['Costo de consulta'], $consultation['Medios de pago'], $consultation['Aseguradoras aceptadas']), 'commercial fields are omitted when visibility is denied');
$public['public_visibility']['show_consultation_fee'] = true;
$public['public_visibility']['show_accepted_insurances'] = true;
$consultation = array_column(PublicProfilePanelContent::build($public)['consultation']['groups'], 'items', 'title');
panelAssert($consultation['Costo de consulta'] === ['750'] && $consultation['Medios de pago'] === ['Tarjeta'] && $consultation['Aseguradoras aceptadas'] === ['Seguro de prueba'], 'eligible commercial values use only their public source');
$public['professional'] = ['education' => [['private_note' => 'Do not expose']], 'languages' => [null, [], '']];
$about = array_column(PublicProfilePanelContent::build($public)['about']['groups'], 'items', 'title');
panelAssert(!isset($about['Formación profesional']) && !isset($about['Idiomas']), 'unrecognized records and empty groups are not serialized into public content');
echo "PublicProfilePanelContentTest PASS\n";
