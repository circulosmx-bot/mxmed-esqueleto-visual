<?php
declare(strict_types=1);
namespace Media\Storage;
require_once __DIR__.'/../contracts/HistoricalArchivePort.php';
/** Explicit disposable contract adapter; no application bootstrap or HTTP wiring. */
final class LocalDisposableHistoricalArchive implements \Media\Contracts\HistoricalArchivePort
{
    private string $root;
    public function __construct(string $root)
    {
        $real=realpath($root);$tmp=realpath(sys_get_temp_dir());
        if(PHP_SAPI!=='cli'||!$real||!$tmp||!str_starts_with($real,$tmp.'/mxmed-archive-')||!is_dir($real)||is_link($root))throw new \RuntimeException('disposable_archive_required');
        $this->root=$real;chmod($real,0700);
    }
    private function path(string $key):string
    {
        $uuid='[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';
        if(!preg_match('~^media-archive/v1/physicians/[0-9]{4}/(?:0[1-9]|1[0-2])/[A-Za-z0-9_-]{1,100}/'.$uuid.'/(?:manifest.json|sources/'.$uuid.'/original\.(?:jpg|png|webp))$~D',$key))throw new \RuntimeException('archive_invalid_key');
        $cursor=$this->root;foreach(explode('/',$key) as $part){$cursor.='/'.$part;if(is_link($cursor))throw new \RuntimeException('archive_symlink');}return $cursor;
    }
    public function put(string $key,string $bytes,array $identity):void
    {
        $path=$this->path($key);
        if(($identity['sha256']??null)!==hash('sha256',$bytes)||($identity['byte_size']??null)!==strlen($bytes))throw new \RuntimeException('archive_integrity');
        if(is_file($path)){if(!$this->verify($key,$identity))throw new \RuntimeException('archive_immutable_conflict');return;}
        if(!is_dir(dirname($path))&&!mkdir(dirname($path),0700,true))throw new \RuntimeException('archive_directory');
        $temp=tempnam(dirname($path),'.write-');if(!$temp)throw new \RuntimeException('archive_write');
        try{chmod($temp,0600);if(file_put_contents($temp,$bytes)!==strlen($bytes)||!link($temp,$path))throw new \RuntimeException('archive_write');}finally{unlink($temp);}
        $meta=fopen($path.'.identity.json','x');if(!$meta)throw new \RuntimeException('archive_metadata');try{chmod($path.'.identity.json',0600);$json=json_encode($identity,JSON_THROW_ON_ERROR);if(fwrite($meta,$json)!==strlen($json))throw new \RuntimeException('archive_metadata');}finally{fclose($meta);}
    }
    public function verify(string $key,array $identity):bool
    {
        try{$path=$this->path($key);if(is_link($path.'.identity.json')||!is_file($path)||!is_file($path.'.identity.json'))return false;
            $stored=json_decode(file_get_contents($path.'.identity.json'),true,512,JSON_THROW_ON_ERROR);
            return $stored===$identity&&filesize($path)===($identity['byte_size']??null)&&hash_file('sha256',$path)===($identity['sha256']??null);
        }catch(\Throwable){return false;}
    }
}
