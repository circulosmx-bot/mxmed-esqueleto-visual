<?php
declare(strict_types=1);
namespace Platform\Contracts;
interface AuditUtcClock { public function nowUtc(): string; }
