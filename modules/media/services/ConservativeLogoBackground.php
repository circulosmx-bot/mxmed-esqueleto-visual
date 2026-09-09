<?php
declare(strict_types=1);
namespace Media\Services;

/** Deterministic policy on the oriented, bounded (<=800px) working raster. */
final class ConservativeLogoBackground
{
    public function apply(\GdImage $image): string
    {
        if(!imageistruecolor($image)&&!imagepalettetotruecolor($image))return 'NO_SAFE_IMPROVEMENT';
        $w=imagesx($image);$h=imagesy($image);$n=$w*$h;
        if(min($w,$h)<16 || max($w,$h)>800)return 'NO_SAFE_IMPROVEMENT';
        $border=[];$channels=[[],[],[]];
        for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++){
            $c=imagecolorat($image,$x,$y);
            // Never flatten or reinterpret existing alpha, even when sparse.
            if(($c>>24)&127)return 'ALREADY_TRANSPARENT';
            if($x===0||$y===0||$x===$w-1||$y===$h-1){$border[]=$c;for($k=0;$k<3;$k++)$channels[$k][]=($c>>(16-8*$k))&255;}
        }
        $bg=[];foreach($channels as $channel){sort($channel,SORT_NUMERIC);$bg[]=$channel[intdiv(count($channel),2)];}
        $distance=static fn(int $c):int=>max(abs((($c>>16)&255)-$bg[0]),abs((($c>>8)&255)-$bg[1]),abs(($c&255)-$bg[2]));
        $variance=0;foreach($border as $c){$d=$distance($c);if($d>2)return 'NO_SAFE_IMPROVEMENT';$variance+=$d*$d;}
        if($variance/count($border)>1)return 'NO_SAFE_IMPROVEMENT';
        // All corners and 100% of border pass the same uniformity gate above.
        $color=($bg[0]<<16)|($bg[1]<<8)|$bg[2];
        $seen=str_repeat("\0",$n);$queue='';$head=0;$removed=0;$foreground=0;
        $add=static function(int $x,int $y)use($image,$w,$h,$color,&$seen,&$queue):void{
            if($x<0||$y<0||$x>=$w||$y>=$h)return;
            $i=$y*$w+$x;if($seen[$i]!=="\0")return;$seen[$i]="\1";
            // Exact dominant color only. Near-background lines and anti-alias edges stay opaque.
            if(imagecolorat($image,$x,$y)===$color){$seen[$i]="\2";$queue.=pack('V',$i);}
        };
        for($x=0;$x<$w;$x++){$add($x,0);$add($x,$h-1);}for($y=1;$y<$h-1;$y++){$add(0,$y);$add($w-1,$y);}
        while($head<strlen($queue)){$i=unpack('V',substr($queue,$head,4))[1];$head+=4;$removed++;$x=$i%$w;$y=intdiv($i,$w);$add($x-1,$y);$add($x+1,$y);$add($x,$y-1);$add($x,$y+1);}
        for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++)if($distance(imagecolorat($image,$x,$y))>=24)$foreground++;
        if($removed/$n<0.05 || $removed/$n>0.95 || $foreground/$n<0.01)return 'NO_SAFE_IMPROVEMENT';
        imagealphablending($image,false);imagesavealpha($image,true);$transparent=(127<<24)|$color;
        for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++)if($seen[$y*$w+$x]==="\2")imagesetpixel($image,$x,$y,$transparent);
        return 'PROPOSAL_CREATED';
    }
}
