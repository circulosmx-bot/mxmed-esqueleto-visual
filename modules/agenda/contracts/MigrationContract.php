<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class MigrationContract
{
    public function __construct(private string $version, private string $checksum, private string $preflight, private string $apply, private string $verify, private string $rollback, private string $ledger)
    {
        foreach ([$version, $checksum, $preflight, $apply, $verify, $rollback, $ledger] as $value) if (trim($value) === '') throw new \InvalidArgumentException('migration contract incomplete');
    }
    public function version(): string { return $this->version; }
    public function checksum(): string { return $this->checksum; }
    public function executionAllowed(): bool { return false; }
    public function toArray(): array { return ['version' => $this->version, 'checksum' => $this->checksum, 'preflight' => $this->preflight, 'apply' => $this->apply, 'verify' => $this->verify, 'rollback' => $this->rollback, 'ledger' => $this->ledger, 'execution_allowed' => false]; }
}
