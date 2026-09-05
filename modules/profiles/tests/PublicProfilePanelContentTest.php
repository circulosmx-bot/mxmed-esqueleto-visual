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
panelAssert($views['about']['intro'] === 'Descripción publicada' && $about['Cédulas profesionales'] === ['Cédula profesional: 001234'], 'description and license preserve their public values');
panelAssert($about['Especialista en'] === ['Endocrinología'] && $about['Formación académica'] === [], 'missing academic data has an empty section alongside real specialties');
panelAssert($consultation['Horarios'] === ['Sede A: Lunes 9:00–12:00'], 'only published office schedules appear');
panelAssert($consultation['Servicios de consulta'] === ['Consulta de seguimiento'], 'consultation uses public services');
panelAssert(!isset($consultation['Costo de consulta']) && !isset($consultation['Medios de pago']) && !isset($consultation['Aseguradoras aceptadas']), 'commercial fields are omitted when visibility is denied');
$public['public_visibility']['show_consultation_fee'] = true;
$public['public_visibility']['show_accepted_insurances'] = true;
$consultation = array_column(PublicProfilePanelContent::build($public)['consultation']['groups'], 'items', 'title');
panelAssert($consultation['Costo de consulta'] === ['750'] && $consultation['Medios de pago'] === ['Tarjeta'] && $consultation['Aseguradoras aceptadas'] === ['Seguro de prueba'], 'eligible commercial values use only their public source');
$public['professional'] = ['education' => [['private_note' => 'Do not expose']], 'languages' => [null, [], '']];
$about = array_column(PublicProfilePanelContent::build($public)['about']['groups'], 'items', 'title');
panelAssert($about['Formación académica'] === [] && !isset($about['Idiomas']), 'unrecognized records and empty groups are not serialized into public content');
$public['professional']['education'] = [['title' => 'Formación publicada']];
$public['professional']['certifications'] = ['Certificación publicada'];
$public['professional']['professional_associations'] = [['name' => 'Asociación publicada']];
$public['professional']['conditions_treated'] = ['Tratamiento publicado'];
$public['consultorios'][0]['phone_public'] = '(449) 123 4567';
$public['consultorios'][0]['emergency_phone_public'] = '+52 449 123 4568';
$public['consultorios'][1]['telefonos_json'] = '["449 999 9999"]';
$full = PublicProfilePanelContent::build($public);
panelAssert(array_keys($full['about']['columns']) === ['left', 'right'], 'two explicit columns support the shared template');
panelAssert(array_column($full['about']['columns']['left'], 'title') === ['Formación académica', 'Certificaciones y asociaciones'], 'academic and certification blocks occupy the left column');
panelAssert(array_column($full['about']['columns']['right'], 'title') === ['Especialista en', 'Principales enfermedades y tratamientos'], 'specialties and treatment blocks occupy the right column');
$about = array_column($full['about']['groups'], 'items', 'title');
panelAssert($about['Certificaciones y asociaciones'] === ['Certificación publicada', 'Asociación publicada'], 'certifications and associations merge without inventing credentials');
$contacts = array_values(array_filter($full['consultation']['groups'], static fn(array $g): bool => $g['title'] === 'Teléfonos y urgencias'))[0];
panelAssert($contacts['column'] === 'right' && $contacts['icon'] === 'call', 'public contacts use the right-column phone block');
panelAssert($contacts['links'] === ['tel:4491234567', 'tel:+524491234568'] && count($contacts['items']) === 2, 'only public office phones produce normalized call links; raw columns are ignored');
foreach ($full as $view) {
    foreach ($view['groups'] as $group) panelAssert($group['icon'] !== '' && in_array($group['column'], ['left', 'right'], true), 'every section has coherent icon and column metadata');
}
echo "PublicProfilePanelContentTest PASS\n";
