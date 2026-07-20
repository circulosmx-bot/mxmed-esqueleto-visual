<?php
declare(strict_types=1);

require_once __DIR__ . '/../http/IdentityHttpComposition.php';

use Identity\Http\IdentityHttpComposition;

IdentityHttpComposition::registerAutoloader();

$checks = [];
$fail = static function (string $name): never {
    fwrite(STDERR, json_encode(['ok' => false, 'failed_check' => $name], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
};
$check = static function (string $name, bool $condition) use (&$checks, $fail): void {
    $checks[$name] = $condition;
    if (!$condition) $fail($name);
};

try {
    $composition = IdentityHttpComposition::preview();
    $check('preview_environment_is_explicit', getenv('MXMED_ENVIRONMENT') === 'local' && getenv('MXMED_PREVIEW_EXPLICIT') === '1');
    $check('preview_database_prefix_isolated', str_starts_with((string)getenv('MXMED_DB_NAME'), 'mxmed_gate4d_preview_'));

    $csrf = $composition->csrf()->issue();
    $check('csrf_token_validates', $composition->csrf()->valid($csrf));
    $check('csrf_invalid_rejected', !$composition->csrf()->valid('invalid-preview-csrf'));

    $email = (string)(getenv('MXMED_PREVIEW_TEST_EMAIL') ?: '');
    $password = (string)(getenv('MXMED_PREVIEW_TEST_PASSWORD') ?: '');
    $profileId = (string)(getenv('MXMED_PREVIEW_TEST_PROFILE_ID') ?: '');
    if ($email === '' || $password === '' || $profileId === '') $fail('preview_fixture_environment');

    $clockDimensions = ['ip' => 'gate4d-test', 'device' => 'gate4d-test'];
    $auth = $composition->authentication()->authenticate($email, $password, $clockDimensions);
    $check('active_fixture_authenticates', $auth->isAllowed() && $auth->candidate() !== null);
    $session = $composition->sessions()->create($auth->candidate(), ['device_label' => 'Gate 4D test', 'user_agent' => 'Gate 4D test', 'ip' => '127.0.0.1']);
    $check('session_created_with_cookie_descriptor', $session->allowed() && $session->token() !== null && $session->cookie() !== null);
    $cookie = $session->cookie();
    $check('cookie_contract', $cookie->name() === '__Host-mxmed_session' && $cookie->secure() && $cookie->httpOnly() && $cookie->sameSite() === 'Lax' && $cookie->path() === '/' && $cookie->domain() === null);
    $rawSessionToken = $session->token()?->value();
    $validated = $composition->sessions()->validate($rawSessionToken);
    $check('session_validates', $validated->allowed() && $validated->context() !== null);

    $context = $validated->context();
    $standard = ['plan_code' => 'standard', 'subscription_status' => 'active', 'is_active' => true];
    $check('standard_agenda_allowed', $composition->authorization()->authorize($context, 'profile_doctor', $profileId, 'agenda_appointments', $standard)->allowed());
    $check('standard_patients_denied', !$composition->authorization()->authorize($context, 'profile_doctor', $profileId, 'patients', $standard)->allowed());
    $check('transitional_open_denied', !$composition->authorization()->authorize($context, 'profile_doctor', $profileId, 'agenda_appointments', $standard, 'transitional_open')->allowed());
    $check('missing_membership_denied', !$composition->authorization()->authorize($context, 'profile_doctor', 'profile_gate4d_missing', 'agenda_appointments', $standard)->allowed());
    $check('profile_mismatch_denied', !$composition->authorization()->authorize($context, 'medical_group', 'group_gate4d_missing', 'agenda_appointments', $standard)->allowed());

    $logout = $composition->sessions()->logout($rawSessionToken);
    $check('logout_revokes_session', $logout->allowed() && !$composition->sessions()->validate($rawSessionToken)->allowed());
    echo json_encode(['ok' => true, 'checks' => $checks], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, json_encode(['ok' => false, 'failed_check' => 'unexpected_preview_failure'], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
