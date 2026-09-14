<?php
declare(strict_types=1);
namespace Signatures;
final class SignatureImage
{
    public const MAX_INPUT_BYTES=2097152;
    public const MAX_OUTPUT_BYTES=153600;
    /** Strict transparent PNG contract; metadata is discarded by re-encoding. */
    public static function normalize(string $data): array
    {
        if(strlen($data)>self::MAX_INPUT_BYTES*4/3+64 || !preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#D',$data,$m)) throw new \RuntimeException('signature_invalid_payload');
        $raw=base64_decode($m[1],true);
        if($raw===false || strlen($raw)>self::MAX_INPUT_BYTES) throw new \RuntimeException('signature_input_too_large');
        $info=@getimagesizefromstring($raw);
        if(!$info || $info[2]!==IMAGETYPE_PNG || $info[0]>4096 || $info[1]>4096 || $info[0]*$info[1]>4000000) throw new \RuntimeException('signature_invalid_image');
        $image=@imagecreatefromstring($raw);
        if(!$image) throw new \RuntimeException('signature_decode_failed');
        try {
            $left=$info[0];$top=$info[1];$right=-1;$bottom=-1;
            for($y=0;$y<$info[1];$y++)for($x=0;$x<$info[0];$x++){
                $rgba=imagecolorsforindex($image,imagecolorat($image,$x,$y));
                if($rgba['alpha']<120 && min($rgba['red'],$rgba['green'],$rgba['blue'])<245){$left=min($left,$x);$right=max($right,$x);$top=min($top,$y);$bottom=max($bottom,$y);}
            }
            if($right-$left<4 || $bottom-$top<1) throw new \RuntimeException('signature_empty');
            $left=max(0,$left-4);$top=max(0,$top-4);$right=min($info[0]-1,$right+4);$bottom=min($info[1]-1,$bottom+4);
            $w=$right-$left+1;$h=$bottom-$top+1;$scale=min(1,1000/$w,400/$h);
            do {
                $ow=max(1,(int)floor($w*$scale));$oh=max(1,(int)floor($h*$scale));
                $out=imagecreatetruecolor($ow,$oh);imagealphablending($out,false);imagesavealpha($out,true);
                imagefill($out,0,0,imagecolorallocatealpha($out,255,255,255,127));
                imagecopyresampled($out,$image,0,0,$left,$top,$ow,$oh,$w,$h);
                ob_start();imagepng($out,null,9);$bytes=ob_get_clean();unset($out);
                if(strlen($bytes)<=self::MAX_OUTPUT_BYTES)return ['bytes'=>$bytes,'width'=>$ow,'height'=>$oh];
                $scale*=.8;
            }while($ow>100 && $oh>30);
            throw new \RuntimeException('signature_output_too_large');
        }finally{unset($image);}
    }
}
