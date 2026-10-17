<?php
declare(strict_types=1);
namespace Profiles\Corrections;

/** Purpose-separated sodium AEAD and HMAC. No default key or real retention policy. */
final class ReportCipher
{
    public function __construct(public readonly string $keyId,private array $keys,public readonly string $retentionPolicy)
    {
        if(!extension_loaded('sodium')||!isset($keys[$keyId])||!preg_match('/^[A-Za-z0-9_.:-]{3,64}$/D',$keyId)
            ||!preg_match('/^[A-Za-z0-9_.:-]{8,128}$/D',$retentionPolicy))throw new \RuntimeException('correction_custody_required');
        foreach($keys as $key)if(!is_string($key)||strlen($key)!==32)throw new \RuntimeException('correction_key_invalid');
    }
    public function digest(string $purpose,string $value):string
    {return hash_hmac('sha256','PUB-COR01:'.$purpose.':'.$value,$this->keys[$this->keyId]);}
    public function seal(string $scope,array $payload):string
    {
        $nonce=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        return $nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            'PUB-COR01:'.$scope,$nonce,$this->keys[$this->keyId]);
    }
    public function open(string $scope,string $keyId,string $ciphertext):array
    {
        if(!isset($this->keys[$keyId])||strlen($ciphertext)<41)throw new \RuntimeException('correction_key_unavailable');
        $nonce=substr($ciphertext,0,24);$plain=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($ciphertext,24),'PUB-COR01:'.$scope,$nonce,$this->keys[$keyId]);
        if(!is_string($plain))throw new \RuntimeException('correction_decryption_failed');
        return json_decode($plain,true,16,JSON_THROW_ON_ERROR);
    }
}
