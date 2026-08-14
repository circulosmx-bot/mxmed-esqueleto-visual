<?php
declare(strict_types=1);
namespace Platform\Services;
use DateTimeImmutable; use DateTimeZone; use Platform\Contracts\AuditUtcClock;
final class SystemAuditUtcClock implements AuditUtcClock { public function nowUtc(): string { return (new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'); } }
