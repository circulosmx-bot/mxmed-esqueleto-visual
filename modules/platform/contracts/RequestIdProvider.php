<?php
declare(strict_types=1);
namespace Platform\Contracts;
interface RequestIdProvider
{
    public function serverGeneratedRequestId(): string;
}
