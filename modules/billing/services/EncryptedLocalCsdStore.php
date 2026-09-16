<?php
declare(strict_types=1);
namespace Billing\Services;

require_once __DIR__.'/CsdSecretPort.php';
require_once __DIR__.'/InvoiceDocumentStorage.php';

/** Local adapter for CsdSecretPort; a production secret manager can replace it. */
final class EncryptedLocalCsdStore implements CsdSecretPort
{
    private string $root;
    private string $key;
    public function __construct(string $root,string $base64Key)
    {
        $key=base64_decode($base64Key,true);
        if ($key===false || strlen($key)!==32) throw new \RuntimeException('csd_encryption_key_required');
        $this->key=$key;
        // Reuse the established private-root boundary check, not its media keyspace.
        $this->root=(new InvoiceDocumentStorage($root))->canonicalRoot();
    }
    public static function runtime():self
    {
        $root=trim((string)(getenv('MXMED_PRIVATE_CSD_ROOT')?:''));
        if ($root==='') {
            $home=trim((string)(getenv('HOME')?:''));
            if ($home==='') throw new \RuntimeException('private_csd_root_required');
            $root=$home.'/.local/share/mxmed/private-billing-csd';
        }
        return new self($root,(string)(getenv('MXMED_CSD_ENCRYPTION_KEY')?:''));
    }
    public function store(string $doctorId,string $credentialId,string $kind,string $plaintext):string
    {
        if (!in_array($kind,['cer','key','password'],true) || !preg_match('/^[0-9a-f-]{36}$/D',$credentialId)) throw new \InvalidArgumentException('invalid_csd_storage_key');
        $relative=hash('sha256','PHYSICIAN:'.$doctorId).'/'.$credentialId.'/'.$kind.'.enc';
        $path=$this->path($relative,true);
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($plaintext,'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,$iv,$tag,$relative);
        if ($cipher===false) throw new \RuntimeException('csd_encrypt_failed');
        $tmp=dirname($path).'/.'.bin2hex(random_bytes(16));
        $fh=fopen($tmp,'x+b');
        if ($fh===false) throw new \RuntimeException('csd_store_failed');
        try {
            chmod($tmp,0600);
            if (fwrite($fh,$iv.$tag.$cipher)!==strlen($iv.$tag.$cipher)) throw new \RuntimeException('csd_store_failed');
            fflush($fh); fclose($fh);$fh=null;
            if (!link($tmp,$path)) throw new \RuntimeException('csd_store_failed');
        } finally { if (is_resource($fh)) fclose($fh); if (is_file($tmp)) unlink($tmp); }
        return $relative;
    }
    public function read(string $key):string
    {
        $path=$this->path($key,false);
        if (!is_file($path) || filesize($path)>65536 || filesize($path)<29) throw new \RuntimeException('csd_integrity_failed');
        $blob=file_get_contents($path); if ($blob===false) throw new \RuntimeException('csd_integrity_failed');
        $plain=openssl_decrypt(substr($blob,28),'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,substr($blob,0,12),substr($blob,12,16),$key);
        if ($plain===false) throw new \RuntimeException('csd_integrity_failed');
        return $plain;
    }
    public function delete(string $key):void { $path=$this->path($key,false); if(is_file($path))unlink($path); }
    private function path(string $relative,bool $create):string
    {
        if (!preg_match('#^[a-f0-9]{64}/[0-9a-f-]{36}/(?:cer|key|password)\.enc$#D',$relative)) throw new \RuntimeException('invalid_csd_storage_key');
        $dir=$this->root;
        foreach (explode('/',dirname($relative)) as $segment) {
            $dir.='/'.$segment;
            if (is_link($dir)) throw new \RuntimeException('csd_symlink_forbidden');
            if ($create && !is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new \RuntimeException('csd_store_failed');
            if ($create) chmod($dir,0700);
        }
        $path=$this->root.'/'.$relative;
        if (is_link($path)) throw new \RuntimeException('csd_symlink_forbidden');
        return $path;
    }
}
