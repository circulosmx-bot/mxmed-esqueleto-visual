<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\SessionAccountStatePort;
use Identity\Repositories\AccountCredentialRepository;
use Identity\Repositories\IdentityAccountRepository;

final class PdoSessionAccountStateAdapter implements SessionAccountStatePort
{
    public function __construct(private IdentityAccountRepository $accounts, private AccountCredentialRepository $credentials) {}
    public function current(string $accountId): ?array
    {
        $account = $this->accounts->findById($accountId); $credential = $this->credentials->findByAccountId($accountId);
        if (!is_array($account) || !is_array($credential)) return null;
        return ['status' => (string)$account['status'], 'credential_version' => (int)$credential['credential_version']];
    }
}
