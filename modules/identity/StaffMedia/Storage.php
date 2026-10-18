<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
require_once __DIR__.'/../../media/contracts/PrivateMediaStoragePort.php';
/** Dedicated namespace implementing the existing port; no widening of any older allowlist.
 * Root and descendants must be service-owned, non-symlink, private directories.
 * Assumes the owning OS account is trusted; not a defense against that account replacing files concurrently.
 */
final class Storage implements \Media\Contracts\PrivateMediaStoragePort
{
    private string $root;
    public function __construct(string $root,private int $maxCipherBytes,array $forbiddenRoots=[])
    {
        if($maxCipherBytes<1||$root===''||$root[0]!=='/'||preg_match('~/\.{1,2}(?:/|$)~',$root))throw new \RuntimeException('staff_media_storage_invalid');
        $this->noLinks($root);$real=realpath($root);
        if($real===false||!is_dir($real))throw new \RuntimeException('staff_media_storage_invalid');
        $this->root=$real;
        $forbiddenRoots[]=dirname(__DIR__,3);
        if(!empty($_SERVER['DOCUMENT_ROOT']))$forbiddenRoots[]=$_SERVER['DOCUMENT_ROOT'];
        foreach($forbiddenRoots as $path){$path=realpath($path);if($path===false||$real===$path||str_starts_with($real,$path.'/')||str_starts_with($path,$real.'/'))throw new \RuntimeException('staff_media_public_root_denied');}
        $this->privateDirectory($real);
    }
    private function noLinks(string $path):void
    {
        $cursor='';foreach(explode('/',ltrim($path,'/')) as $part){$cursor.='/'.$part;
            // macOS /tmp is a trusted system alias; callers supply realpath roots, not symlink roots.
            if(is_link($cursor))throw new \RuntimeException('staff_media_symlink_denied');}
    }
    private function privateDirectory(string $path):void
    {if(!is_dir($path)||is_link($path)||(fileperms($path)&0777)!==0700||(function_exists('posix_geteuid')&&fileowner($path)!==posix_geteuid()))throw new \RuntimeException('staff_media_private_permissions_required');}
    public static function key(string $id):string
    {if(!preg_match('/^sma_[a-f0-9]{32}$/D',$id))throw new \RuntimeException('staff_media_key_invalid');return 'private/staff-admission/'.$id.'.enc';}
    private function path(string $key,bool $create=false):string
    {
        if(!preg_match('~^private/staff-admission/sma_[a-f0-9]{32}\.enc$~D',$key))throw new \RuntimeException('staff_media_key_invalid');
        $this->noLinks($this->root);$this->privateDirectory($this->root);$dir=$this->root;
        foreach(['private','staff-admission'] as $part){$dir.='/'.$part;if(is_link($dir))throw new \RuntimeException('staff_media_symlink_denied');
            if(!file_exists($dir)&&$create&&!mkdir($dir,0700))throw new \RuntimeException('staff_media_storage_unavailable');
            if(file_exists($dir))$this->privateDirectory($dir);
        }
        $target=$this->root.'/'.$key;clearstatcache(true,$target);
        if(is_link($target))throw new \RuntimeException('staff_media_symlink_denied');
        if(file_exists($target)&&(!is_file($target)||(fileperms($target)&0777)!==0600||stat($target)['nlink']!==1))throw new \RuntimeException('staff_media_private_permissions_required');
        return $target;
    }
    public function storeImmutable(string $storageKey,string $sourcePath):void
    {
        if(is_link($sourcePath)||!is_file($sourcePath)||filesize($sourcePath)<1||filesize($sourcePath)>$this->maxCipherBytes)throw new \RuntimeException('staff_media_source_invalid');
        $target=$this->path($storageKey,true);$tmp=dirname($target).'/.'.bin2hex(random_bytes(16)).'.tmp';
        try{
            $out=fopen($tmp,'x+b');if(!$out)throw new \RuntimeException('staff_media_storage_unavailable');
            try{if(!chmod($tmp,0600))throw new \RuntimeException('staff_media_storage_unavailable');
                $in=fopen($sourcePath,'rb');if(!$in)throw new \RuntimeException('staff_media_source_invalid');
                try{$count=stream_copy_to_stream($in,$out,$this->maxCipherBytes+1);}finally{fclose($in);}
                if($count!==filesize($sourcePath)||$count>$this->maxCipherBytes||!fflush($out)||(function_exists('fsync')&&!fsync($out)))throw new \RuntimeException('staff_media_storage_unavailable');
            }finally{fclose($out);}
            if(!hash_equals(hash_file('sha256',$sourcePath),hash_file('sha256',$tmp))||!@link($tmp,$target))throw new \RuntimeException('staff_media_immutable_write_denied');
        }finally{if(is_file($tmp))unlink($tmp);}
    }
    public function openReadStream(string $storageKey):array
    {
        $path=$this->path($storageKey);$f=@fopen($path,'rb');if(!$f)throw new \RuntimeException('staff_media_file_unavailable');
        $s=fstat($f);if(($s['mode']&0170000)!==0100000||$s['size']<1||$s['size']>$this->maxCipherBytes||$s['nlink']!==1){fclose($f);throw new \RuntimeException('staff_media_integrity_failed');}
        return ['stream'=>$f,'bytes'=>$s['size']];
    }
    public function exists(string $storageKey):bool{return is_file($this->path($storageKey));}
    public function delete(string $storageKey):void
    {$this->path($storageKey);throw new \RuntimeException('staff_media_disposal_policy_unavailable');}
}
