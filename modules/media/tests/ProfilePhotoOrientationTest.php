<?php
declare(strict_types=1);
require __DIR__.'/../services/GdPublicLogoProcessor.php';
use Media\Services\GdPublicLogoProcessor;
if(!function_exists('exif_read_data'))throw new RuntimeException('EXIF support required for JPEG orientation');
$path=tempnam(sys_get_temp_dir(),'profile-orientation-');$im=imagecreatetruecolor(240,120);imagejpeg($im,$path,90);
// A real TIFF Orientation=6 tag inside a JPEG APP1 segment, not a text-only fixture.
$tiff="II".pack('vV',42,8).pack('v',1).pack('vvVv',0x112,3,1,6)."\0\0".pack('V',0);
$exif="Exif\0\0".$tiff;$jpeg=file_get_contents($path);file_put_contents($path,substr($jpeg,0,2)."\xff\xe1".pack('n',strlen($exif)+2).$exif.substr($jpeg,2));
$out=(new GdPublicLogoProcessor())->process(['tmp_name'=>$path,'type'=>'image/jpeg','name'=>'portrait.jpg']);
try{
 if($out['width']!==120 || $out['height']!==240)throw new RuntimeException('orientation not normalized');
 $data=file_get_contents($out['path']);
 if(str_contains($data,'EXIF') || str_contains($data,'Exif') || str_contains($data,'XMP '))throw new RuntimeException('source metadata preserved');
 echo "PASS: JPEG EXIF orientation normalized; output WebP excludes EXIF/XMP\n";
}finally{unlink($out['path']);}
