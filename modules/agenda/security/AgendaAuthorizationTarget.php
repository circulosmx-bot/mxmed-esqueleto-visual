<?php
declare(strict_types=1);

namespace Agenda\Security;

use Platform\Contracts\SafeIdentifier;

/** A server-normalized target; it does not confer authority by itself. */
final class AgendaAuthorizationTarget
{
    public function __construct(
        private string $resource,
        private string $method,
        private string $profileId,
        private string $requestedScope,
        private string $action,
        private string $correlationId,
        private string $requestId
    ) {
        $this->resource = (new SafeIdentifier($this->resource))->value();
        $this->method = strtoupper(trim($this->method));
        $this->profileId = (new SafeIdentifier($this->profileId))->value();
        $this->requestedScope = (new SafeIdentifier($this->requestedScope))->value();
        $this->action = (new SafeIdentifier($this->action))->value();
        $this->correlationId = (new SafeIdentifier($this->correlationId))->value();
        $this->requestId = (new SafeIdentifier($this->requestId))->value();
        if (!in_array($this->method, ['GET', 'POST', 'PATCH', 'DELETE'], true)) throw new \InvalidArgumentException('unsupported_agenda_method');
    }

    public function resource(): string { return $this->resource; }
    public function method(): string { return $this->method; }
    public function profileId(): string { return $this->profileId; }
    public function requestedScope(): string { return $this->requestedScope; }
    public function action(): string { return $this->action; }
    public function correlationId(): string { return $this->correlationId; }
    public function requestId(): string { return $this->requestId; }
}
