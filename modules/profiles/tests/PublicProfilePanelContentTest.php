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
    foreach ($empty as $key => $view) {
        panelAssert(($key === 'consultation' ? count($view['groups']) === 6 : $view['groups'] === []) && $view['empty_message'] !== '', 'missing public data has a bounded subsection empty state');
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
panelAssert(!isset($consultation['Servicios de consulta']), 'generic services replaced by consultation modality');
panelAssert($consultation['Costo de la consulta'] === [] && $consultation['Medios de pago'] === [] && $consultation['Aseguradoras aceptadas'] === [], 'commercial values are withheld when visibility is denied');
$public['public_visibility']['show_consultation_fee'] = true;
$public['public_visibility']['show_accepted_insurances'] = true;
$consultation = array_column(PublicProfilePanelContent::build($public)['consultation']['groups'], 'items', 'title');
panelAssert($consultation['Costo de la consulta'] === ['750'] && $consultation['Medios de pago'] === ['Tarjeta'] && $consultation['Aseguradoras aceptadas'] === ['Seguro de prueba'], 'eligible commercial values use only their public source');
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
$contacts = $full['consultation']['contacts'];
panelAssert($contacts[0]['phone'] === 'tel:4491234567' && $contacts[1]['phone'] === null, 'main public phone only; emergency and raw columns cannot become the main CTA');
$public['consultorios'][0]['whatsapp_public'] = '+52 449 123 4567';
$public['consultorios'][0]['modalities'] = ['in_person', 'online', 'private_unknown'];
$public['professional']['target_audience'] = ['Niños y Adultos'];
$public['public_visibility']['show_public_agenda'] = true;
$public['agenda_public']['availability_endpoint'] = '/api/agenda/index.php/public/availability';
$public['consultorios'][0]['schedule_summary'] = [['weekday' => 1, 'windows' => [['start_time' => '09:00', 'end_time' => '12:00']]]];
$public['commercial_visibility']['accepted_insurances'] = [
    ['name' => 'Seguro publicado', 'logo_url' => '/assets/insurers/published.svg'],
    ['name' => 'Otro seguro', 'logo_url' => 'javascript:alert(1)'],
    ['name' => 'Seguro HTTPS', 'logo_url' => 'https://example.com/logo.png'],
    ['name' => 'URL externa relativa', 'logo_url' => '//example.com/logo.png'],
];
$ready = PublicProfilePanelContent::build($public)['consultation'];
$groups = array_column($ready['groups'], null, 'title');
panelAssert(array_column($ready['columns']['left'], 'title') === ['Atención a', 'Horarios', 'Costo de la consulta', 'Medios de pago'], 'left product hierarchy');
panelAssert(array_column($ready['columns']['right'], 'title') === ['Aseguradoras aceptadas', 'Modalidad de consulta'], 'right product hierarchy');
panelAssert($groups['Atención a']['items'] === ['Niños y Adultos'], 'published audience supported');
panelAssert($groups['Atención a']['icon'] === 'group' && $groups['Atención a']['icon_style'] === 'outlined', 'audience uses the requested outlined group icon');
panelAssert($groups['Modalidad de consulta']['items'] === ['Consulta presencial', 'Consulta en línea'], 'only recognized published modalities appear');
panelAssert($groups['Horarios']['items'] === ['Sede A'] && isset($groups['Horarios']['schedule_actions'][0]), 'structured public schedule produces an agenda reference');
panelAssert($groups['Horarios']['icon'] === 'alarm' && $groups['Horarios']['icon_style'] === 'outlined', 'schedules use the requested outlined alarm icon');
panelAssert($groups['Medios de pago']['icon'] === 'credit_card' && $groups['Medios de pago']['icon_style'] === 'outlined', 'payment methods use the requested outlined credit card icon');
panelAssert($groups['Aseguradoras aceptadas']['logos'] === ['/assets/insurers/published.svg', null, 'https://example.com/logo.png', null], 'logo contract rejects unsafe destinations and retains insurer names');
panelAssert($ready['contacts'][0]['whatsapp'] === 'https://wa.me/524491234567' && $ready['agenda'], 'public WhatsApp and gated reservation destination');
$public['public_visibility']['show_public_agenda'] = false;
$basic = PublicProfilePanelContent::build($public)['consultation'];
$hours = array_column($basic['groups'], null, 'title')['Horarios'];
panelAssert(!$basic['agenda'] && $hours['schedule_actions'][0] === null && $hours['items'] === ['Sede A: Lunes 09:00–12:00'], 'without agenda show real hours and no dead jump link');
foreach ($full as $view) {
    foreach ($view['groups'] as $group) panelAssert($group['icon'] !== '' && in_array($group['column'], ['left', 'right'], true), 'every section has coherent icon and column metadata');
}
echo "PublicProfilePanelContentTest PASS\n";
