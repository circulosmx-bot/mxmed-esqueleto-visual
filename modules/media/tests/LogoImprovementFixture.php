<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/PhysicianLogoReviewFixture.php';
require_once __DIR__.'/../services/LogoImprovementService.php';
function mr8Image(string $kind='white',int $w=600,int $h=300):GdImage{
 $im=imagecreatetruecolor($w,$h);imagealphablending($im,false);imagesavealpha($im,true);
 $bg=$kind==='offwhite'?imagecolorallocate($im,248,248,245):imagecolorallocate($im,255,255,255);imagefill($im,0,0,$bg);
 if($kind==='transparent')imagefill($im,0,0,imagecolorallocatealpha($im,255,255,255,127));
 if(in_array($kind,['gradient','photo','variance'],true))for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++){if($kind==='gradient')$c=180+(int)(75*$x/$w);else $c=($x*17+$y*31)%200;imagesetpixel($im,$x,$y,imagecolorallocate($im,$c,($c+30)%256,($c+70)%256));}
 $blue=imagecolorallocate($im,20,60,140);imagefilledrectangle($im,(int)($w*.2),(int)($h*.2),(int)($w*.8),(int)($h*.8),$blue);
 imagefilledrectangle($im,(int)($w*.35),(int)($h*.4),(int)($w*.65),(int)($h*.6),imagecolorallocate($im,255,255,255));
 if($kind==='thin')imageline($im,5,5,$w-6,5,imagecolorallocate($im,254,254,254));
 if($kind==='edge'){imageline($im,0,0,$w-1,$h-1,$blue);imageline($im,$w-1,0,0,$h-1,$blue);}
 return $im;
}
function mr8Upload(string $kind='white',int $w=600,int $h=300):array{$p=tempnam(sys_get_temp_dir(),'mr8-synthetic-');rename($p,$p.'.png');$p.='.png';$im=mr8Image($kind,$w,$h);imagepng($im,$p);$im=null;return ['tmp_name'=>$p,'name'=>'logo-sintetico.png','type'=>'image/png','error'=>0];}
function mr8Candidate(string $kind='white',int $w=600,int $h=300):array{$p=mr5Pdo();$f=mr7Candidate($p);[$private]=$st=mr5Storage();$u=mr8Upload($kind,$w,$h);$service=new Media\Services\PhysicianLogoReviewCandidateService($p,$private);try{$service->upload($f['doctor'],$u);}finally{unlink($u['tmp_name']);}$f['id']=$service->current($f['doctor'])['submission_id'];return $f;}
function mr8Context(string $action,array $caps=['media_review_improve']):Platform\Contracts\TrustedAuthorizationContext{return mr6Context('improvement_'.$action,$caps);}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){if(($argv[1]??'')==='candidate')echo json_encode(mr8Candidate($argv[2]??'white',(int)($argv[3]??600),(int)($argv[4]??300)));}
