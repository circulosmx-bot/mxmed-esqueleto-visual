<?php
declare(strict_types=1);

use Profiles\Services\PublicProfilePlanCapabilities;

require_once __DIR__ . '/../services/PublicProfilePlanCapabilities.php';

function pdb04eConsultaAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pdb04eConsultaContract(string $plan): array
{
    return PublicProfilePlanCapabilities::build($plan, [
        'has_public_profile' => true,
        'ownership_source_ready' => true,
        'profile_is_administered' => false,
    ]);
}

$free = pdb04eConsultaContract('free');
pdb04eConsultaAssert(!(bool)($free['public_visibility']['show_consulta_action'] ?? false), 'Gratuito hides Consulta');
pdb04eConsultaAssert(!(bool)($free['public_visibility']['show_public_agenda'] ?? false), 'Gratuito has no public Agenda');

$basic = pdb04eConsultaContract('basic');
pdb04eConsultaAssert((bool)($basic['public_visibility']['show_consulta_action'] ?? false), 'Básico shows Consulta');
pdb04eConsultaAssert(!(bool)($basic['public_visibility']['show_public_agenda'] ?? false), 'Básico Consulta does not open Agenda');

foreach (['standard', 'optimum', 'professional'] as $agendaPlan) {
    $contract = pdb04eConsultaContract($agendaPlan);
    pdb04eConsultaAssert((bool)($contract['public_visibility']['show_consulta_action'] ?? false), $agendaPlan . ' shows Consulta');
    pdb04eConsultaAssert((bool)($contract['public_visibility']['show_public_agenda'] ?? false), $agendaPlan . ' retains public Agenda');
}

$pageSource = (string)file_get_contents(__DIR__ . '/../../../profiles/doctor.php');
pdb04eConsultaAssert(str_contains($pageSource, 'data-mxpp-profile-view-trigger="consultation"') && !str_contains($pageSource, '$consultaTarget'), 'Consulta uses an in-place panel trigger for every eligible plan');
pdb04eConsultaAssert(str_contains($pageSource, '<section id="consultorios"'), 'consultorio target exists');
pdb04eConsultaAssert(str_contains($pageSource, 'id="proximas-citas"'), 'Agenda target exists when rendered');
pdb04eConsultaAssert(str_contains($pageSource, '<?php if ($showConsultaAction): ?>'), 'Consulta has no free placeholder');
pdb04eConsultaAssert(!preg_match('/<button[^>]*data-mxpp-profile-view-trigger="consultation"[^>]*data-mxpp-booking-trigger/', $pageSource), 'Consulta never creates an appointment directly');

echo "PublicProfileConsultaPlanBehaviorTest PASS\n";
