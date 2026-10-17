<?php
declare(strict_types=1);
namespace Identity\Operational;
require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';
/** D01 is open. Only a separately reviewed real adapter may enter this registry. */
final class SmsIntegration
{
    public const APPROVED_ADAPTERS=[];
    public static function configured():?\Agenda\Contracts\OtpProviderPort{return null;}
}
