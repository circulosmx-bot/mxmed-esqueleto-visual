<?php
declare(strict_types=1);

namespace Agenda\Adapters;

use Agenda\Contracts\OtpProviderPort;
use Agenda\Contracts\OtpRateLimitPolicy;

require_once __DIR__ . '/../contracts/OtpProviderPort.php';
require_once __DIR__ . '/../contracts/OtpRateLimitPolicy.php';

final class CanonicalPublicAgendaAdapter
{
    private const ROUTES = [
        'public_otp_request',
        'public_otp_verify',
        'public_reserve',
        'public_confirm',
        'public_cancel',
        'public_expire',
    ];

    public static function canonicalPublicAgendaEnabled(array $config): bool
    {
        return ($config['feature_flags']['canonical_public_agenda'] ?? false) === true;
    }

    public function homogeneousError(string $route, string $correlationReference = ''): array
    {
        $safeRoute = in_array($route, self::ROUTES, true) ? $route : 'public_verification';
        $safeCorrelation = preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}\z/D', $correlationReference) === 1
            ? $correlationReference
            : '';
        return [
            'ok' => false,
            'error' => 'verification_unavailable',
            'message' => 'verification could not be completed',
            'data' => null,
            'http_status' => 409,
            'meta' => [
                'route' => $safeRoute,
                'correlation_reference' => $safeCorrelation,
            ],
        ];
    }

    public function readiness(OtpProviderPort $provider, array $approvedRateLimitParameters): array
    {
        $rateLimitConfigured = (new OtpRateLimitPolicy())->approvedParametersPresent($approvedRateLimitParameters);
        $providerConfigured = $provider->configured();
        return [
            'mode' => 'dormant_readiness_only',
            'provider_id' => $provider->providerId(),
            'provider_configured' => $providerConfigured,
            'rate_limit_configured' => $rateLimitConfigured,
            'activation_authorized' => false,
            'ready' => $providerConfigured && $rateLimitConfigured,
        ];
    }
}
