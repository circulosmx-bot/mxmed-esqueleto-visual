<?php
declare(strict_types=1);

require_once __DIR__ . '/../http/IdentityHttpComposition.php';

use Identity\Adapters\RejectingIdentityNotificationAdapter;
use Identity\Adapters\RejectingSessionStoreAdapter;
use Identity\Contracts\NotificationMessage;
use Identity\Contracts\OneTimeTokenPurpose;
use Identity\Http\IdentityHttpComposition;
use Identity\Http\IdentityHttpCompositionSelector;
use Identity\Http\ProductiveIdentityHttpConfiguration;

IdentityHttpComposition::registerAutoloader();

$checks = [];
$check = static function (string $name, bool $condition) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($name);
    }
    $checks[] = $name;
};
$throws = static function (callable $operation, string $message = ''): bool {
    try {
        $operation();
        return false;
    } catch (Throwable $exception) {
        return $message === '' || $exception->getMessage() === $message;
    }
};

$previewCalls = 0;
$productiveCalls = 0;
$production = IdentityHttpCompositionSelector::fromValues('production', '0');
$selected = $production->select(
    static function () use (&$previewCalls): string { $previewCalls++; return 'preview'; },
    static function (string $environment) use (&$productiveCalls): string { $productiveCalls++; return $environment; }
);
$check('productive_environment_never_calls_preview', $selected === 'production' && $previewCalls === 0 && $productiveCalls === 1);

foreach (['local', 'development'] as $environment) {
    $localPreviewCalls = 0;
    $selected = IdentityHttpCompositionSelector::fromValues($environment, '1')->select(
        static function () use (&$localPreviewCalls): string { $localPreviewCalls++; return 'preview'; },
        static fn(string $value): string => $value
    );
    $check($environment . '_explicit_preview_allowed', $selected === 'preview' && $localPreviewCalls === 1);
}

$check('local_without_explicit_preview_fails_closed', $throws(
    static fn() => IdentityHttpCompositionSelector::fromValues('local', '0'),
    'identity_composition_unavailable'
));
$check('unknown_environment_fails_closed', $throws(
    static fn() => IdentityHttpCompositionSelector::fromValues('unexpected', '1'),
    'identity_composition_unavailable'
));
$check('absent_environment_fails_closed', $throws(
    static fn() => IdentityHttpCompositionSelector::fromValues('', '1'),
    'identity_composition_unavailable'
));
$check('productive_preview_flag_fails_closed', $throws(
    static fn() => IdentityHttpCompositionSelector::fromValues('production', '1'),
    'identity_composition_unavailable'
));

$validProductiveValues = [
    'MXMED_ENVIRONMENT' => 'production',
    'MXMED_DB_HOST' => 'db.internal',
    'MXMED_DB_PORT' => '3306',
    'MXMED_DB_NAME' => 'mxmed_productive',
    'MXMED_DB_USER' => 'mxmed_identity',
    'MXMED_DB_PASS' => 'fixture-password-never-used',
    'MXMED_IDENTITY_PEPPER' => str_repeat('p', 32),
    'MXMED_IDENTITY_ORIGIN' => 'https://mxmed.example.test',
    'MXMED_EMAIL_PROVIDER' => 'ses',
    'MXMED_SES_REGION' => 'us-east-1',
    'MXMED_EMAIL_FROM_ADDRESS' => 'no-reply@mexicomedico.com',
    'MXMED_EMAIL_FROM_NAME' => 'México Médico',
];
$configuration = ProductiveIdentityHttpConfiguration::fromValues('production', $validProductiveValues);
$check('productive_configuration_is_deterministic',
    $configuration->environment() === 'production'
    && $configuration->allowedOrigin() === 'https://mxmed.example.test'
    && $configuration->databaseDsn() === 'mysql:host=db.internal;port=3306;dbname=mxmed_productive;charset=utf8mb4'
);

$missingConfiguration = $validProductiveValues;
unset($missingConfiguration['MXMED_DB_HOST']);
$check('missing_productive_configuration_fails_closed', $throws(
    static fn() => ProductiveIdentityHttpConfiguration::fromValues('production', $missingConfiguration),
    'identity_productive_configuration_unavailable'
));
$previewDatabaseConfiguration = $validProductiveValues;
$previewDatabaseConfiguration['MXMED_DB_NAME'] = 'mxmed_gate4d_preview_forbidden';
$check('preview_database_rejected_in_productive_mode', $throws(
    static fn() => ProductiveIdentityHttpConfiguration::fromValues('production', $previewDatabaseConfiguration),
    'identity_productive_configuration_unavailable'
));

$_SERVER['HTTP_X_MXMED_ENVIRONMENT'] = 'local';
$_SERVER['HTTP_X_PREVIEW'] = '1';
$_GET['environment'] = 'local';
$_POST['environment'] = 'local';
$_COOKIE['MXMED_ENVIRONMENT'] = 'local';
$requestControlledPreviewCalls = 0;
$selected = IdentityHttpCompositionSelector::fromValues('production', '0')->select(
    static function () use (&$requestControlledPreviewCalls): string { $requestControlledPreviewCalls++; return 'preview'; },
    static fn(string $environment): string => $environment
);
$check('request_values_cannot_select_preview', $selected === 'production' && $requestControlledPreviewCalls === 0);

$selectorSource = (string)file_get_contents(__DIR__ . '/../http/IdentityHttpCompositionSelector.php');
$check('selector_has_no_request_controlled_inputs', preg_match('/\$_(?:SERVER|GET|POST|REQUEST|COOKIE)/', $selectorSource) !== 1);

$notification = new RejectingIdentityNotificationAdapter();
$check('missing_productive_notification_never_reports_success', $throws(static fn() => $notification->send(
    new NotificationMessage(OneTimeTokenPurpose::EMAIL_VERIFICATION, 'recipient@example.test', 'fixture-token', '2099-01-01 00:00:00')
), 'notification_unavailable'));

$sessionStore = new RejectingSessionStoreAdapter();
$check('missing_productive_session_store_is_unavailable', !$sessionStore->healthCheck()->healthy());

$compositionSource = (string)file_get_contents(__DIR__ . '/../http/IdentityHttpComposition.php');
$productiveStart = strpos($compositionSource, 'public static function productive');
$productiveEnd = strpos($compositionSource, 'private static function build', $productiveStart === false ? 0 : $productiveStart);
$productiveSource = $productiveStart === false || $productiveEnd === false ? '' : substr($compositionSource, $productiveStart, $productiveEnd - $productiveStart);
$check('productive_factory_exists', $productiveSource !== '');
$check('productive_factory_has_no_preview_dependency',
    !str_contains($productiveSource, 'preview()')
    && !str_contains($productiveSource, 'PreviewIdentityNotificationAdapter')
    && !str_contains($productiveSource, 'PreviewValkeyClient')
    && !str_contains($productiveSource, 'InMemorySessionStoreAdapter')
);
$check('productive_factory_uses_ses_and_fail_closed_session_boundaries',
    str_contains($productiveSource, 'SesIdentityNotificationAdapter')
    && !str_contains($productiveSource, 'RejectingIdentityNotificationAdapter')
    && str_contains($productiveSource, 'RejectingSessionStoreAdapter')
);

$entrypointSource = (string)file_get_contents(dirname(__DIR__, 3) . '/api/identity/index.php');
$check('http_entrypoint_uses_environment_selector', str_contains($entrypointSource, 'IdentityHttpCompositionSelector::fromProcessEnvironment()'));
$check('http_failure_contract_is_sanitized',
    str_contains($entrypointSource, "catch (\\Throwable) {\n    identityHttpJson(['ok' => false, 'error' => 'TEMPORARILY_UNAVAILABLE'], 503);")
    && !str_contains($entrypointSource, 'getMessage()')
    && !str_contains($entrypointSource, 'getTrace')
);

$entrypointPath = dirname(__DIR__, 3) . '/api/identity/index.php';
$subprocessSource = <<<'PHP'
putenv('MXMED_ENVIRONMENT=unknown');
putenv('MXMED_PREVIEW_EXPLICIT=');
$_SERVER['REQUEST_URI'] = '/api/identity/index.php/current-session?environment=local&token=request-secret';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_X_MXMED_ENVIRONMENT'] = 'local';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer request-secret';
$_GET['environment'] = 'local';
$_POST['environment'] = 'local';
$_COOKIE['MXMED_ENVIRONMENT'] = 'local';
require $argv[1];
PHP;
$process = proc_open(
    [PHP_BINARY, '-r', $subprocessSource, $entrypointPath],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
if (!is_resource($process)) {
    throw new RuntimeException('sanitized_http_subprocess_unavailable');
}
$httpOutput = stream_get_contents($pipes[1]);
$httpError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$httpExit = proc_close($process);
$check('productive_failure_response_is_generic',
    $httpExit === 0
    && $httpError === ''
    && $httpOutput === '{"ok":false,"error":"TEMPORARILY_UNAVAILABLE"}'
    && !str_contains($httpOutput, 'request-secret')
    && !str_contains($httpOutput, 'identity_composition_unavailable')
);

$secretValue = 'fixture-secret-must-not-escape';
try {
    $invalid = $validProductiveValues;
    $invalid['MXMED_DB_HOST'] = $secretValue . ';invalid';
    ProductiveIdentityHttpConfiguration::fromValues('production', $invalid);
    $check('productive_configuration_error_does_not_expose_secret', false);
} catch (Throwable $exception) {
    $check('productive_configuration_error_does_not_expose_secret', !str_contains($exception->getMessage(), $secretValue));
}

echo json_encode(['ok' => true, 'checks' => $checks], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
