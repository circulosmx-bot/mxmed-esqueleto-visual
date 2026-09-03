<?php
declare(strict_types=1);

require_once __DIR__ . '/../http/ProductiveIdentityHttpConfiguration.php';

use Identity\Http\ProductiveIdentityHttpConfiguration;

function eotp02ConfigAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function eotp02ConfigRejects(array $values): void
{
    try {
        ProductiveIdentityHttpConfiguration::fromValues('production', $values);
        throw new RuntimeException('invalid_configuration_accepted');
    } catch (Throwable $exception) {
        eotp02ConfigAssert($exception->getMessage() === 'identity_productive_configuration_unavailable', 'configuration fails closed');
    }
}

$valid = [
    'APP_ENV' => 'production',
    'DB_HOST' => 'db.internal',
    'DB_PORT' => '3306',
    'DB_NAME' => 'mxmed',
    'DB_USERNAME' => 'mxmed_identity',
    'DB_PASSWORD' => 'fixture-database-password',
    'SESSION_SIGNING_KEY' => str_repeat('p', 32),
    'MXMED_IDENTITY_ORIGIN' => 'https://mexicomedico.com',
    'MXMED_EMAIL_PROVIDER' => 'ses',
    'MXMED_SES_REGION' => 'us-east-1',
    'MXMED_EMAIL_FROM_ADDRESS' => 'no-reply@mexicomedico.com',
    'MXMED_EMAIL_FROM_NAME' => 'México Médico',
    'MXMED_EMAIL_REPLY_TO' => '',
];

$configuration = ProductiveIdentityHttpConfiguration::fromValues('production', $valid);
eotp02ConfigAssert($configuration->emailProvider() === 'ses', 'SES provider exact');
eotp02ConfigAssert($configuration->sesRegion() === 'us-east-1', 'SES region exact');
eotp02ConfigAssert($configuration->emailFromAddress() === 'no-reply@mexicomedico.com', 'From address exact');
eotp02ConfigAssert($configuration->emailFromName() === 'México Médico', 'From name exact');
eotp02ConfigAssert($configuration->emailReplyTo() === null, 'Reply-To unset');
eotp02ConfigAssert($configuration->allowedOrigin() === 'https://mexicomedico.com', 'identity origin reused');

foreach ([
    ['MXMED_EMAIL_PROVIDER', 'smtp'],
    ['MXMED_SES_REGION', 'mx-central-1'],
    ['MXMED_EMAIL_FROM_ADDRESS', 'no-reply@other.example'],
    ['MXMED_EMAIL_FROM_NAME', ''],
    ['MXMED_IDENTITY_ORIGIN', 'http://mexicomedico.com'],
    ['AWS_ACCESS_KEY_ID', 'static-access-key'],
    ['AWS_SECRET_ACCESS_KEY', 'static-secret-key'],
    ['AWS_SESSION_TOKEN', 'static-session-token'],
    ['AWS_PROFILE', 'credential-profile'],
    ['AWS_SHARED_CREDENTIALS_FILE', '/tmp/credentials'],
    ['SMTP_USERNAME', 'smtp-user'],
    ['SMTP_PASSWORD', 'smtp-password'],
    ['SES_SMTP_USERNAME', 'ses-smtp-user'],
    ['SES_SMTP_PASSWORD', 'ses-smtp-password'],
] as [$name, $value]) {
    $invalid = $valid;
    $invalid[$name] = $value;
    eotp02ConfigRejects($invalid);
}

$composition = (string)file_get_contents(__DIR__ . '/../http/IdentityHttpComposition.php');
$productiveStart = strpos($composition, 'public static function productive');
$productiveEnd = strpos($composition, 'private static function build', $productiveStart === false ? 0 : $productiveStart);
$productive = $productiveStart === false || $productiveEnd === false ? '' : substr($composition, $productiveStart, $productiveEnd - $productiveStart);
eotp02ConfigAssert(str_contains($productive, 'new \\Aws\\SesV2\\SesV2Client'), 'productive composition creates SES v2 client');
eotp02ConfigAssert(str_contains($productive, 'new SesIdentityNotificationAdapter'), 'productive composition selects SES adapter');
eotp02ConfigAssert(!str_contains($productive, 'PreviewIdentityNotificationAdapter'), 'productive composition excludes preview adapter');
eotp02ConfigAssert(!str_contains($productive, 'InMemoryIdentityNotificationAdapter'), 'productive composition excludes in-memory adapter');
eotp02ConfigAssert(!str_contains($productive, 'RejectingIdentityNotificationAdapter'), 'productive composition has no notification fallback');

echo "EmailOtpConfigurationTest PASS\n";
