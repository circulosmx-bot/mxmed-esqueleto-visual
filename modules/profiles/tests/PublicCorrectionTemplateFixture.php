<?php
declare(strict_types=1);

// CLI-only synthetic renderer of the ORIGINAL public profile; no SQL or session.
if (PHP_SAPI !== 'cli' || getenv('MXMED_PUBLIC_CORRECTION_SYNTHETIC_QA') !== '1') exit(1);
$root = dirname(__DIR__, 3);
require_once $root . '/modules/profiles/services/PublicProfilePlanCapabilities.php';
require_once $root . '/modules/profiles/services/PublicProfileClaimEligibility.php';

function publicCorrectionFixture(string $id): array
{
    if (!in_array($id, ['synthetic-free', 'synthetic-administered', 'synthetic-paid', 'synthetic-unknown-source'], true)) {
        return ['ok' => false, 'error' => 'profile_not_found', 'data' => null];
    }
    $plan = $id === 'synthetic-paid' ? 'optimum' : 'free';
    $ready = $id !== 'synthetic-unknown-source';
    $administered = $id === 'synthetic-administered';
    $claim = \Profiles\Services\PublicProfileClaimEligibility::build($id, ['source' => 'profiles_doctors', 'plan_code' => $plan], true,
        ['source_ready' => $ready, 'has_active_owner' => $administered]);
    $data = \Profiles\Services\PublicProfilePlanCapabilities::build($plan, [
        'has_public_profile' => true, 'ownership_source_ready' => $ready,
        'profile_is_administered' => $administered, 'claim_source_ready' => $claim['eligible'],
    ]);
    $data['claim']['claim_url'] = $claim['url'];
    $data += [
        'profile' => ['doctor_id' => $id, 'is_public' => true, 'status' => 'active'],
        'identity' => ['display_name' => 'Profesional Sintética <QA>', 'prefix' => 'Dra.'],
        'professional' => ['professional_license' => 'SYNTHETIC-NOT-A-LICENSE'],
        'specialties' => [['name_es' => 'Especialidad sintética']],
        'consultorios' => [['consultorio_id' => 'synthetic-office', 'name' => 'Consultorio sintético', 'is_public' => true, 'is_active' => true]],
        'seo' => [],
    ];
    return ['ok' => true, 'data' => $data];
}

$path = $argv[1] ?? '';
parse_str($argv[2] ?? '', $_GET);
if ($path === '/fixture-dto') {
    echo json_encode(publicCorrectionFixture((string)($_GET['profile'] ?? '')), JSON_THROW_ON_ERROR);
    exit;
}
if ($path === '/profiles/doctor.php') {
    $_SERVER['HTTP_HOST'] = (string)parse_url((string)getenv('MXMED_PROFILES_API_BASE'), PHP_URL_HOST);
    require $root . $path;
    exit;
}
exit(1);
