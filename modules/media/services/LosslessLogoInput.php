<?php
declare(strict_types=1);
namespace Media\Services;
use GdImage;
use RuntimeException;

/** Internal bounded derivative; callers own and must unlink the returned temporary file. */
final class LosslessLogoInput
{
    public const MAX_SIDE=800;
    public const MAX_PIXELS=640000;
    public const MAX_BYTES=4194304;

    public static function export(GdImage $working):array
    {
        $width=imagesx($working);$height=imagesy($working);
        if(min($width,$height)<1||max($width,$height)>self::MAX_SIDE||$width*$height>self::MAX_PIXELS)throw new RuntimeException('lossless_input_dimensions_exceeded');
        $path=tempnam(sys_get_temp_dir(),'mxmed-lossless-logo-');
        if($path===false)throw new RuntimeException('lossless_input_temp_failed');
        try {
            if(!chmod($path,0600))throw new RuntimeException('lossless_input_temp_failed');
            imagesavealpha($working,true);
            if(!imagepng($working,$path,6))throw new RuntimeException('lossless_input_encode_failed');
            clearstatcache(true,$path);$bytes=filesize($path);
            if(!$bytes||$bytes>self::MAX_BYTES)throw new RuntimeException('lossless_input_bytes_exceeded');
            $info=@getimagesize($path);
            if((new \finfo(FILEINFO_MIME_TYPE))->file($path)!=='image/png'||!$info||$info[0]!==$width||$info[1]!==$height||$info['mime']!=='image/png')throw new RuntimeException('lossless_input_invalid');
            $decoded=@imagecreatefrompng($path);
            if(!$decoded)throw new RuntimeException('lossless_input_decode_failed');
            $decoded=null;
            return ['path'=>$path,'mime_type'=>'image/png','format'=>'png','width'=>$width,'height'=>$height,'byte_size'=>$bytes,'checksum_sha256'=>hash_file('sha256',$path)];
        }catch(\Throwable $e){unlink($path);throw $e;}
    }
}
