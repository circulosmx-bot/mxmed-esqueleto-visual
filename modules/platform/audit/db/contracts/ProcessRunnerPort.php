<?php
declare(strict_types=1);

namespace Platform\Audit\Db\Contracts;

interface ProcessRunnerPort
{
    /**
     * @param list<string> $argv Argument vector passed without shell interpolation.
     * @return array{exitCode:int,stdout:string,stderr:string}
     */
    public function run(array $argv): array;
}
