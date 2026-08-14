<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\AuditIpHasher; use Platform\Contracts\AuditSecretProvider;
final class HmacSha256AuditIpHasher implements AuditIpHasher { public function __construct(private AuditSecretProvider $provider){} public function hashTrustedNetworkAddress(string $trustedNetworkAddress): array { $key=$this->provider->currentAuditIpKey(); if(strlen($key['secret'])<32)throw new \RuntimeException('audit_ip_secret_too_short'); return ['ip_hmac'=>hash_hmac('sha256',$trustedNetworkAddress,$key['secret']),'ip_hmac_key_version'=>$key['version']]; } }
