<?php
declare(strict_types=1);
namespace Platform\Contracts;
interface AuditIpHasher { /** @return array{ip_hmac:string,ip_hmac_key_version:string} */ public function hashTrustedNetworkAddress(string $trustedNetworkAddress): array; }
