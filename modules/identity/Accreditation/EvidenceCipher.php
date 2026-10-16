<?php
declare(strict_types=1);
namespace Identity\Accreditation;

/** Injected private keyring. No environment default, generated runtime key or public configuration.
 * Operational custody/rotation and retention approval remain deployment prerequisites.
 */
final class EvidenceCipher
{
    public function __construct(private string $currentId, private array $keys,
        public readonly string $retentionPolicyReference)
    {
        if (!extension_loaded('sodium') || !preg_match('/^[A-Za-z0-9_.:-]{3,64}$/D', $currentId)
            || !preg_match('/^[A-Za-z0-9_.:-]{8,128}$/D', $retentionPolicyReference))
            throw new \RuntimeException('c4_private_policy_required');
        foreach ($keys as $id=>$key) {
            if (!is_string($id) || !is_string($key) || strlen($key)!==SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)
                throw new \RuntimeException('c4_private_key_required');
        }
        $this->key($currentId);
    }
    private function key(string $id): string { return $this->keys[$id] ?? throw new \RuntimeException('c4_private_key_unavailable'); }
    public function seal(string $bytes, string $scope): array
    {
        $nonce=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        return ['key_id'=>$this->currentId,'bytes'=>$nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($bytes,'C4-EV01:'.$scope,$nonce,$this->key($this->currentId))];
    }
    public function open(string $bytes, string $scope, string $keyId): string
    {
        $n=SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($bytes)<=$n) throw new \RuntimeException('c4_private_integrity_failed');
        $plain=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($bytes,$n),'C4-EV01:'.$scope,substr($bytes,0,$n),$this->key($keyId));
        if (!is_string($plain)) throw new \RuntimeException('c4_private_integrity_failed');
        return $plain;
    }
    public function digest(string $scope, string $value, ?string $keyId=null): string
    {
        return hash_hmac('sha256','C4-EV01:'.$scope.':'.$value,$this->key($keyId??$this->currentId));
    }
}
