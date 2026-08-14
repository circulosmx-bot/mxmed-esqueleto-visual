<?php
declare(strict_types=1);
namespace Platform\Contracts;
interface AuditSecretProvider { /** @return array{version:string,secret:string} */ public function currentAuditIpKey(): array; }
