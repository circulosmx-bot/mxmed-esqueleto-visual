<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
/** Same reviewed sodium AEAD primitive as C4, separate exact staff domain and structured AAD. */
final class Cipher
{
    public const ALGORITHM='XCHACHA20-POLY1305-v1';
    public function __construct(private string $currentId,private array $keys)
    {
        if(!extension_loaded('sodium'))throw new \RuntimeException('staff_media_crypto_unavailable');
        foreach($keys as $id=>$key)if(!is_string($id)||!preg_match('/^[A-Za-z0-9_.:-]{3,64}$/D',$id)||!is_string($key)||strlen($key)!==32)throw new \RuntimeException('staff_media_key_required');
        $this->key($currentId);
    }
    private function key(string $id):string{return $this->keys[$id]??throw new \RuntimeException('staff_media_key_unavailable');}
    private function context(array $a):string
    {
        Policy::purpose($a['purpose']);
        return json_encode(['STAFF-ADMISSION-MEDIA-v1',$a['case_id'],$a['account_id'],$a['asset_id'],$a['purpose'],(int)$a['asset_version'],(int)$a['case_version'],$a['custody_reference'],$a['retention_reference']],JSON_THROW_ON_ERROR);
    }
    public function seal(string $bytes,array $context):array
    {
        $n=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        return ['key_id'=>$this->currentId,'cipher_algorithm'=>self::ALGORITHM,'bytes'=>$n.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($bytes,$this->context($context),$n,$this->key($this->currentId))];
    }
    public function open(string $bytes,array $context,string $keyId):string
    {
        $n=SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if(strlen($bytes)<=$n+SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES)throw new \RuntimeException('staff_media_integrity_failed');
        $plain=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($bytes,$n),$this->context($context),substr($bytes,0,$n),$this->key($keyId));
        if(!is_string($plain))throw new \RuntimeException('staff_media_integrity_failed');return $plain;
    }
}
