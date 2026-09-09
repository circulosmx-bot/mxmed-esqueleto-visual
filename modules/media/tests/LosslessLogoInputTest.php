<?php
declare(strict_types=1);
require __DIR__.'/LogoImprovementFixture.php';
use Media\Services\{GdPublicLogoProcessor,LosslessLogoInput};
function pass(bool $ok,string $name):void{if(!$ok)throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
foreach(['thin','transparent'] as $kind){
 $upload=mr8Upload($kind);$input=null;$calls=0;
 try{
  $review=(new GdPublicLogoProcessor(10485760,8192,25000000))->process($upload,false,null,null,function($im)use(&$input,&$calls){$calls++;$input=LosslessLogoInput::export($im);});
  $control=(new GdPublicLogoProcessor(10485760,8192,25000000))->process($upload,false);
  pass($control['checksum_sha256']===$review['checksum_sha256'],'export_does_not_change_review_'.$kind);unlink($control['path']);
  $png=imagecreatefrompng($input['path']);$expected=mr8Image($kind);
  pass($calls===1&&imagesx($png)===600&&imagesy($png)===300,'one_bounded_export_no_upscale_'.$kind);
  for($y=0;$y<300;$y++)for($x=0;$x<600;$x++)if(imagecolorat($png,$x,$y)!==imagecolorat($expected,$x,$y))throw new RuntimeException('lossless_pixel_mismatch');
  pass(true,'every_RGB_alpha_pixel_preserved_'.$kind);
  pass($input['byte_size']<=4194304&&hash_file('sha256',$input['path'])===$input['checksum_sha256']&&(fileperms($input['path'])&0777)===0600,'private_png_contract');
  pass($review['byte_size']<=153600&&$review['mime_type']==='image/webp','review_output_unchanged');
  $png=null;$expected=null;unlink($review['path']);
 }finally{unlink($upload['tmp_name']);if($input)unlink($input['path']);}
}
$upload=mr6File('jpeg',1200,600);$input=null;
try{
 $review=(new GdPublicLogoProcessor(10485760,8192,25000000))->process($upload,false,null,null,function($im)use(&$input){$input=LosslessLogoInput::export($im);});
 pass($input['width']===400&&$input['height']===800,'orientation_before_bounded_export');
 $bytes=file_get_contents($input['path']);$offset=8;$chunks=[];
 while($offset<strlen($bytes)){$n=unpack('N',substr($bytes,$offset,4))[1];$chunks[]=substr($bytes,$offset+4,4);$offset+=12+$n;}
 pass(array_diff($chunks,['IHDR','IDAT','IEND','pHYs','PLTE','tRNS'])===[],'no_EXIF_IPTC_XMP_GPS_device_chunks');
 unlink($review['path']);
}finally{unlink($upload['tmp_name']);if($input)unlink($input['path']);}
$large=imagecreatetruecolor(801,1);try{LosslessLogoInput::export($large);throw new LogicException('unbounded export');}catch(RuntimeException $e){pass($e->getMessage()==='lossless_input_dimensions_exceeded','reject_unbounded_raster');}$large=null;
