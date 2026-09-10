<?php
declare(strict_types=1);
namespace Media\Services;
final class TemporaryOriginalsZip
{
    private string $path;
    private \ZipArchive $zip;
    private bool $open=false;
    private array $files=[];
    public function __construct()
    {
        $path=tempnam(sys_get_temp_dir(),'mxmed-originals-');if($path===false)throw new \RuntimeException('zip_unavailable');$this->path=$path;chmod($path,0600);
        $this->zip=new \ZipArchive();if($this->zip->open($path,\ZipArchive::OVERWRITE)!==true){$this->remove();throw new \RuntimeException('zip_unavailable');}$this->open=true;
    }
    public function add(string $name,string $bytes):void
    {
        if(!preg_match('~^[A-Za-z0-9_/-]+\.(jpg|png|webp|json)$~D',$name)||str_starts_with($name,'/')||str_contains($name,'..'))throw new \RuntimeException('zip_unavailable');
        $temp=tempnam(sys_get_temp_dir(),'mxmed-originals-');if(!$temp)throw new \RuntimeException('zip_unavailable');$this->files[]=$temp;chmod($temp,0600);
        if(file_put_contents($temp,$bytes)!==strlen($bytes)||!$this->zip->addFile($temp,$name))throw new \RuntimeException('zip_unavailable');
        $this->zip->setCompressionName($name,\ZipArchive::CM_STORE);
    }
    public function finish():void{if(!$this->zip->close())throw new \RuntimeException('zip_unavailable');$this->open=false;}
    public function path():string{return $this->path;}
    public function remove():void{if($this->open){$this->zip->close();$this->open=false;}foreach($this->files as $file)if(is_file($file))unlink($file);$this->files=[];if(isset($this->path)&&is_file($this->path))unlink($this->path);}
    public function __destruct(){$this->remove();}
}
