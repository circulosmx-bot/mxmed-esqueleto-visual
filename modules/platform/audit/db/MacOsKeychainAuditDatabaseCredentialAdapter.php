<?php
declare(strict_types=1);

namespace Platform\Audit\Db;

use Platform\Audit\Db\Contracts\AuditDatabaseCredentialProvider;
use Platform\Audit\Db\Contracts\ProcessRunnerPort;

final class MacOsKeychainAuditDatabaseCredentialAdapter implements AuditDatabaseCredentialProvider
{
    private const SECURITY_EXECUTABLE = '/usr/bin/security';

    public function __construct(private readonly ProcessRunnerPort $runner)
    {
    }

    public function credentialFor(AuditDatabaseCredentialRole $role): AuditDatabaseCredential
    {
        $argv = [
            self::SECURITY_EXECUTABLE,
            'find-generic-password',
            '-s',
            $role->service(),
            '-a',
            $role->account(),
            '-w',
        ];

        $result = $this->runner->run($argv);
        if (
            !array_key_exists('exitCode', $result)
            || !array_key_exists('stdout', $result)
            || !array_key_exists('stderr', $result)
            || !is_int($result['exitCode'])
            || !is_string($result['stdout'])
            || !is_string($result['stderr'])
        ) {
            throw new \RuntimeException('keychain_process_contract_invalid');
        }
        if ($result['exitCode'] !== 0) {
            throw new \RuntimeException('keychain_credential_read_failed');
        }

        $secret = rtrim($result['stdout'], "\r\n");
        if ($secret === '') {
            throw new \RuntimeException('keychain_credential_empty');
        }

        return new AuditDatabaseCredential(
            $role,
            $role->account(),
            $role->host(),
            $secret,
        );
    }
}
