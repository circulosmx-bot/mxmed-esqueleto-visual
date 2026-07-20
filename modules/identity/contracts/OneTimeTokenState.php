<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class OneTimeTokenState
{
    public const ISSUED = 'issued';
    public const CONSUMED = 'consumed';
    public const EXPIRED = 'expired';
    public const INVALIDATED = 'invalidated';
}
