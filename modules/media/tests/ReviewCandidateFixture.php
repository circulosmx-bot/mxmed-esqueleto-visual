<?php
declare(strict_types=1);
function candidateFixture(string $format='png', int $width=240, int $height=120): array {
    $path=tempnam(sys_get_temp_dir(),'mr1-synthetic-');
    $im=imagecreatetruecolor($width,$height);
    imagealphablending($im,false); imagesavealpha($im,true);
    imagefill($im,0,0,imagecolorallocatealpha($im,40,140,180,50));
    match($format) {'jpeg'=>imagejpeg($im,$path,90),'webp'=>imagewebp($im,$path,85),default=>imagepng($im,$path)};
    if($format==='jpeg'){
        $tiff="II".pack('vV',42,8).pack('v',1).pack('vvVv',0x112,3,1,6)."\0\0".pack('V',0);
        $exif="Exif\0\0".$tiff;$jpeg=file_get_contents($path);
        file_put_contents($path,substr($jpeg,0,2)."\xff\xe1".pack('n',strlen($exif)+2).$exif.substr($jpeg,2));
    }
    return ['tmp_name'=>$path,'name'=>'synthetic.'.$format,'type'=>'image/'.$format,'error'=>UPLOAD_ERR_OK];
}
