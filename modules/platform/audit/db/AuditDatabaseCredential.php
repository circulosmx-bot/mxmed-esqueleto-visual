<?php
declare(strict_types=1);

namespace Platform\Audit\Db;

final readonly class AuditDatabaseCredential
{
    private string $secretMaterial;

    public function __construct(
        public AuditDatabaseCredentialRole $role,
        public string $username,
        public string $host,
        #[\SensitiveParameter] string $secret,
    ) {
        if ($username === '') {
            throw new \InvalidArgumentException('audit_database_username_empty');
        }
        if ($username !== $role->account()) {
            throw new \InvalidArgumentException('audit_database_username_role_mismatch');
        }
        if ($host !== $role->host()) {
            throw new \InvalidArgumentException('audit_database_host_role_mismatch');
        }
        if ($secret === '') {
            throw new \InvalidArgumentException('audit_database_secret_empty');
        }

        $this->secretMaterial = $secret;
    }

    public function secret(): string
    {
        return $this->secretMaterial;
    }

    /** @return array{role:string,username:string,host:string,secret:string} */
    public function __debugInfo(): array
    {
        return [
            'role' => $this->role->name,
            'username' => $this->username,
            'host' => $this->host,
            'secret' => '[redacted]',
        ];
    }

    /** @return array<never,never> */
    public function __serialize(): array
    {
        throw new \LogicException('audit_database_credential_serialization_forbidden');
    }
}
