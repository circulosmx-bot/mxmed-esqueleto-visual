<?php
declare(strict_types=1);
namespace Billing\Services;
require_once __DIR__.'/CfdiCertificationProviderPort.php';

final class UnconfiguredPacAdapter implements CfdiCertificationProviderPort
{
    public function code():string { return 'UNCONFIGURED'; }
    public function certify(array $preparedInvoice,string $idempotencyKey):array { throw new \DomainException('pac_provider_selection_required'); }
    public function reconcile(string $idempotencyKey,?string $providerRequestId):array { throw new \DomainException('pac_provider_selection_required'); }
}
