<?php
declare(strict_types=1);

namespace Platform\Repositories;

use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditEventEnvelope;

interface AuditEventRepository
{
    public function availability(): string;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollBack(): void;
    public function inTransaction(): bool;
    public function findByEventId(string $eventId): ?AuditEventEnvelope;
    public function latest(string $streamKey): ?AuditEventEnvelope;
    public function insert(AuditEventEnvelope $event): void;
}
