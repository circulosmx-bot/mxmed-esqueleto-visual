<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
require_once __DIR__.'/../../media/services/GdPublicLogoProcessor.php';
/** Raster-only intake. No PDF parser, external tools, public media writer or user paths. */
final class Images
{
    public function __construct(private Policy $policy,private string $stagingRoot)
    {new Storage($stagingRoot,$policy->maxBytes+128);}
    public function process(string $bytes,string $purpose,string $declaredMime):array
    {
        Policy::purpose($purpose);$size=strlen($bytes);
        if($size<1||$size>$this->policy->maxBytes)throw new \RuntimeException('staff_media_bytes_invalid');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if(!in_array($mime,['image/png','image/jpeg'],true)||$declaredMime!==$mime)throw new \RuntimeException('staff_media_format_unsupported');
        $info=@getimagesizefromstring($bytes);
        if(!$info||$info['mime']!==$mime||$info[0]<1||$info[1]<1||max($info[0],$info[1])>$this->policy->maxSide||$info[0]*$info[1]>$this->policy->maxPixels)throw new \RuntimeException('staff_media_image_bounds');
        $this->structure($bytes,$mime,$purpose);
        if($mime==='image/jpeg'&&!function_exists('exif_read_data'))throw new \RuntimeException('staff_media_orientation_unavailable');
        $path=tempnam($this->stagingRoot,'raster-');$result=null;
        if(!$path)throw new \RuntimeException('staff_media_staging_unavailable');
        try{
            if(!chmod($path,0600)||file_put_contents($path,$bytes)!==$size)throw new \RuntimeException('staff_media_staging_unavailable');
            // Reuse the existing actual MIME/dimensions/full GD decode/orientation and derivative path.
            // Its implementation ceilings are not approved staff policy; explicit staff bounds also apply.
            $result=(new \Media\Services\GdPublicLogoProcessor($this->policy->maxBytes,$this->policy->maxSide,$this->policy->maxPixels))->process(['tmp_name'=>$path,'type'=>$mime,'error'=>UPLOAD_ERR_OK],false);
            if($purpose===Policy::EVIDENCE)return ['bytes'=>$bytes,'mime_type'=>$mime,'source_mime'=>$mime,'width'=>$info[0],'height'=>$info[1]];
            if(max($result['width'],$result['height'])>$this->policy->portraitMaxEdge||$result['byte_size']>$this->policy->portraitMaxBytes)throw new \RuntimeException('staff_media_derivative_bounds');
            $out=file_get_contents($result['path']);if(!is_string($out)||strlen($out)!==$result['byte_size'])throw new \RuntimeException('staff_media_derivative_invalid');
            return ['bytes'=>$out,'mime_type'=>$result['mime_type'],'source_mime'=>$mime,'width'=>$result['width'],'height'=>$result['height']];
        }finally{
            if(is_file($path))unlink($path);
            if(is_array($result)&&is_file($result['path']))unlink($result['path']);
        }
    }
    private function structure(string $b,string $mime,string $purpose):void
    {
        $portrait=$purpose===Policy::PORTRAIT;$n=strlen($b);
        if($mime==='image/png'){
            if(substr($b,0,8)!=="\x89PNG\r\n\x1a\n")throw new \RuntimeException('staff_media_malformed');
            $i=8;$first=true;$data=false;$ended=false;
            while($i<$n){
                if($i+12>$n)throw new \RuntimeException('staff_media_malformed');
                $len=unpack('N',substr($b,$i,4))[1];$type=substr($b,$i+4,4);
                if($len>$n-$i-12||($first&&($type!=='IHDR'||$len!==13))||(!$first&&$type==='IHDR'))throw new \RuntimeException('staff_media_malformed');
                $allowed=['IHDR','PLTE','IDAT','IEND','tRNS'];
                if($portrait)$allowed=[...$allowed,'tEXt','pHYs','sRGB','gAMA','cHRM','eXIf'];
                // Reject animation, compressed metadata, unknown chunks and trailing/polyglot payloads.
                if(!in_array($type,$allowed,true)||!hash_equals(hash('crc32b',substr($b,$i+4,$len+4),true),substr($b,$i+8+$len,4)))throw new \RuntimeException('staff_media_malformed');
                $i+=$len+12;$first=false;if($type==='IDAT')$data=true;
                if($type==='IEND'){if($len!==0||$i!==$n||!$data)throw new \RuntimeException('staff_media_malformed');$ended=true;break;}
            }
            if(!$ended)throw new \RuntimeException('staff_media_malformed');return;
        }
        if(substr($b,0,2)!=="\xff\xd8")throw new \RuntimeException('staff_media_malformed');
        $i=2;$scan=false;
        while($i<$n){
            if(ord($b[$i++])!==255)throw new \RuntimeException('staff_media_malformed');
            while($i<$n&&ord($b[$i])===255)$i++;
            if($i>=$n)throw new \RuntimeException('staff_media_malformed');$marker=ord($b[$i++]);
            if($marker===217){if(!$scan||$i!==$n)throw new \RuntimeException('staff_media_malformed');return;}
            $allowed=[192,194,196,219,221,218,224];if($portrait)$allowed=[...$allowed,225,254];
            if(!in_array($marker,$allowed,true)||$i+2>$n)throw new \RuntimeException('staff_media_malformed');
            $len=unpack('n',substr($b,$i,2))[1];if($len<2||$len>$n-$i)throw new \RuntimeException('staff_media_malformed');
            if($marker===224&&!in_array(substr($b,$i+2,5),["JFIF\0","JFXX\0"],true))throw new \RuntimeException('staff_media_malformed');
            if($marker===225&&substr($b,$i+2,6)!=="Exif\0\0")throw new \RuntimeException('staff_media_malformed');
            $i+=$len;
            if($marker===218){$scan=true;while($i<$n){if(ord($b[$i])!==255){$i++;continue;}if($i+1>=$n)break;$next=ord($b[$i+1]);if($next===0||($next>=208&&$next<=215)){$i+=2;continue;}break;}}
        }
        throw new \RuntimeException('staff_media_malformed');
    }
}
