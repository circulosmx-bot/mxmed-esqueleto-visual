<?php
declare(strict_types=1);
namespace Platform\Contracts;
interface AuditUuidProvider { public function generateCanonicalUuid(): string; }
