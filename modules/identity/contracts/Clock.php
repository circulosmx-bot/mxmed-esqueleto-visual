<?php
declare(strict_types=1);

namespace Identity\Contracts;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
