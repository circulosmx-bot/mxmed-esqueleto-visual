<?php
declare(strict_types=1);
require __DIR__.'/LogoImprovementFixture.php';
function check(bool $v,string $name):void{if(!$v)throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
$engine=new Media\Services\ConservativeLogoBackground();
foreach(['white','offwhite','thin','gradient','photo','variance','edge','transparent'] as $kind){
 $im=mr8Image($kind);$interior=imagecolorat($im,300,150);$line=imagecolorat($im,20,5);$foreground=imagecolorat($im,140,80);$result=$engine->apply($im);
 if(in_array($kind,['white','offwhite','thin'],true)){check($result==='PROPOSAL_CREATED',$kind.'_proposal');check(((imagecolorat($im,0,0)>>24)&127)===127,'exterior_transparent');check(imagecolorat($im,300,150)===$interior,'enclosed_white_exact_opaque');check(imagecolorat($im,140,80)===$foreground,'foreground_exact');if($kind==='thin')check(imagecolorat($im,20,5)===$line,'thin_near_white_exact_opaque');}
 else check($result===($kind==='transparent'?'ALREADY_TRANSPARENT':'NO_SAFE_IMPROVEMENT'),$kind.'_abstention');$im=null;
}
$palette=imagecreate(200,100);$white=imagecolorallocate($palette,255,255,255);$blue=imagecolorallocate($palette,20,60,140);imagefilledrectangle($palette,40,20,160,80,$blue);imagefilledrectangle($palette,80,40,120,60,$white);
check($engine->apply($palette)==='PROPOSAL_CREATED'&&((imagecolorat($palette,0,0)>>24)&127)===127&&imagecolorat($palette,100,50)===0xffffff,'indexed_png_palette_white_protected');$palette=null;
$jpeg=mr6File('jpeg',640,360);$im=mr8Image('white',640,360);imagejpeg($im,$jpeg['tmp_name'],100);$im=null;
$tiff="II".pack('vV',42,8).pack('v',1).pack('vvVv',0x112,3,1,6)."\0\0".pack('V',0);$exif="Exif\0\0".$tiff;$comment='GPS=TEST;DEVICE=TEST;';$raw=file_get_contents($jpeg['tmp_name']);file_put_contents($jpeg['tmp_name'],substr($raw,0,2)."\xff\xe1".pack('n',strlen($exif)+2).$exif."\xff\xfe".pack('n',strlen($comment)+2).$comment.substr($raw,2));$state=null;
try{$oriented=(new Media\Services\GdPublicLogoProcessor(10485760,8192,25000000))->process($jpeg,false,null,function($im)use($engine,&$state){$state=$engine->apply($im);});$binary=file_get_contents($oriented['path']);check($state==='PROPOSAL_CREATED'&&$oriented['width']===360&&$oriented['height']===640&&!str_contains($binary,'Exif')&&!str_contains($binary,'GPS=TEST')&&!str_contains($binary,'DEVICE=TEST'),'jpeg_orientation_metadata_stripped');unlink($oriented['path']);}finally{unlink($jpeg['tmp_name']);}
echo 'PHP_PEAK_BYTES='.memory_get_peak_usage(true).PHP_EOL;
