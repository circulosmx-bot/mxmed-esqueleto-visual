<?php
declare(strict_types=1);
namespace Identity\Team;
final class ReadFailure extends \RuntimeException
{
    public function __construct(public readonly int $status, string $reason) { parent::__construct($reason); }
}
