<?php
declare(strict_types=1);
require __DIR__.'/../services/DoctorProfilePhotoService.php';
use Media\Services\DoctorProfilePhotoService as Photo;
use Media\Services\GdPublicLogoProcessor as Processor;
function checkPhoto(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function makePhoto(int $width,int $height):string{
 $p=tempnam(sys_get_temp_dir(),'photo-source-');$im=imagecreatetruecolor($width,$height);imagejpeg($im,$p,85);$im=null;return $p;
}
function photoUpload(string $p):array{return ['tmp_name'=>$p,'type'=>'image/jpeg','name'=>'camera.jpg','error'=>0];}
$processor=new Processor(Photo::MAX_UPLOAD_BYTES,Photo::MAX_SOURCE_SIDE,Photo::MAX_DECODED_PIXELS);
$source=makePhoto(4000,3000);$jpeg=file_get_contents($source);
// Valid JPEG comment segments make a near-10 MiB original, including synthetic metadata.
$comments='';while(strlen($comments)+strlen($jpeg)+60004 < Photo::MAX_UPLOAD_BYTES-1024){$payload=str_pad('GPSLatitude=TEST;Device=QA;',60000,'x');$comments.="\xff\xfe".pack('n',strlen($payload)+2).$payload;}
file_put_contents($source,substr($jpeg,0,2).$comments.substr($jpeg,2));unset($comments,$jpeg);
try{
 checkPhoto(filesize($source)>10000000 && filesize($source)<Photo::MAX_UPLOAD_BYTES,'near-10 MiB fixture');
 $out=$processor->process(photoUpload($source),false);
 try{
  checkPhoto($out['source_width']*$out['source_height']===12000000,'12 MP accepted');
  checkPhoto(max($out['width'],$out['height'])<=800 && $out['byte_size']<=153600,'output limits unchanged');
  $data=file_get_contents($out['path']);checkPhoto(!str_contains($data,'GPSLatitude')&&!str_contains($data,'Device=QA'),'metadata stripped');
 }finally{unlink($out['path']);}
 try{(new Processor())->process(photoUpload($source),false);throw new RuntimeException('default limits changed');}catch(RuntimeException $e){checkPhoto($e->getMessage()==='logo_upload_bytes_exceeded','gallery/logo defaults retained');}
 if(getenv('PHOTO_MODERN_FIXTURE'))copy($source,getenv('PHOTO_MODERN_FIXTURE'));
}finally{unlink($source);}
foreach([[5001,5000,'logo_upload_pixel_count_exceeded'],[8193,10,'logo_upload_dimensions_exceeded']] as [$w,$h,$error]){
 $p=makePhoto($w,$h);try{$processor->process(photoUpload($p));throw new RuntimeException('unsafe image accepted');}catch(RuntimeException $e){checkPhoto($e->getMessage()===$error,$error);}finally{if(is_file($p))unlink($p);}
}
$p=makePhoto(10,10);$f=fopen($p,'ab');ftruncate($f,Photo::MAX_UPLOAD_BYTES+1);fclose($f);
try{$processor->process(photoUpload($p));throw new RuntimeException('oversized bytes accepted');}catch(RuntimeException $e){checkPhoto($e->getMessage()==='logo_upload_bytes_exceeded','10 MiB enforced');}finally{if(is_file($p))unlink($p);}
echo "PASS: 12 MP / near-10 MiB JPEG accepted; >25 MP, >8192 side, >10 MiB rejected; output/metadata and gallery/logo limits preserved\n";
