<?php
declare(strict_types=1);
namespace Billing\Services;

interface CfdiCertificationProviderPort
{
    /** Must be an explicitly selected, SAT-authorized PAC sandbox adapter. */
    public function code():string;
    /** Adapter decides signing/submission format from its documented PAC contract. */
    public function certify(array $preparedInvoice,string $idempotencyKey):array;
    public function reconcile(string $idempotencyKey,?string $providerRequestId):array;
}
