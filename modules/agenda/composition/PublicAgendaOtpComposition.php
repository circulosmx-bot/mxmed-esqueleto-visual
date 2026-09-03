<?php
declare(strict_types=1);

namespace Agenda\Composition;

use Agenda\Adapters\SesPublicAgendaOtpAdapter;
use Agenda\Contracts\OtpProviderPort;

require_once __DIR__ . '/../adapters/SesPublicAgendaOtpAdapter.php';

final class PublicAgendaOtpComposition
{
    public static function productive(): OtpProviderPort
    {
        $provider = strtolower(trim((string)getenv('MXMED_EMAIL_PROVIDER')));
        $region = trim((string)getenv('MXMED_SES_REGION'));
        $fromAddress = strtolower(trim((string)getenv('MXMED_EMAIL_FROM_ADDRESS')));
        $fromName = trim((string)getenv('MXMED_EMAIL_FROM_NAME'));
        if (
            $provider !== 'ses'
            || $region !== SesPublicAgendaOtpAdapter::REGION
            || $fromAddress !== SesPublicAgendaOtpAdapter::FROM_ADDRESS
            || $fromName !== SesPublicAgendaOtpAdapter::FROM_NAME
        ) {
            throw new \RuntimeException('agenda_otp_configuration_unavailable');
        }

        if (!class_exists(\Aws\SesV2\SesV2Client::class)) {
            $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
            if (is_file($autoload)) require_once $autoload;
        }
        if (!class_exists(\Aws\SesV2\SesV2Client::class)) {
            throw new \RuntimeException('agenda_otp_configuration_unavailable');
        }

        $client = new \Aws\SesV2\SesV2Client([
            'version' => 'latest',
            'region' => $region,
        ]);
        return new SesPublicAgendaOtpAdapter($client, $region, $fromAddress, $fromName);
    }
}
