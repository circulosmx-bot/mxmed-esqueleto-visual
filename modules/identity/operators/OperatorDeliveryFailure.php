<?php
declare(strict_types=1);
namespace Identity\Operators;
/** Retry only a provider-confirmed non-acceptance, never an ambiguous send. */
final class OperatorDeliveryFailure extends \RuntimeException
{
    public function __construct(public readonly string $outcome)
    {if(!in_array($outcome,['retryable_not_accepted','permanent_not_accepted','uncertain'],true))throw new \InvalidArgumentException('invalid_delivery_outcome');parent::__construct('operator_delivery_unavailable');}
}
