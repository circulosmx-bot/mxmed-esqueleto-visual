<?php
declare(strict_types=1);
namespace Billing\Services;

/** Allowed transitions; an ambiguous PAC outcome cannot re-enter pending. */
final class InvoiceIssuanceState
{
    private const NEXT = [
        'DRAFT'=>['VALIDATION_FAILED','READY'],
        'VALIDATION_FAILED'=>['DRAFT','READY'],
        'READY'=>['DRAFT','CERTIFICATION_PENDING'],
        'CERTIFICATION_PENDING'=>['CERTIFIED','CERTIFICATION_FAILED','RECONCILIATION_REQUIRED'],
        'CERTIFICATION_FAILED'=>['DRAFT','RECONCILIATION_REQUIRED'],
        'RECONCILIATION_REQUIRED'=>['CERTIFIED','CERTIFICATION_FAILED'],
        'CERTIFIED'=>[],
    ];
    public static function requireTransition(string $from,string $to):void
    {
        if(!in_array($to,self::NEXT[$from]??[],true))throw new \DomainException('invalid_issuance_transition');
    }
}
