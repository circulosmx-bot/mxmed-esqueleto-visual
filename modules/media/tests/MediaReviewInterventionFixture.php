<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/ProfilePhotoApprovalFixture.php';
require_once __DIR__.'/../services/MediaReviewInterventionService.php';
function mr6Context(string $action,array $caps):Platform\Contracts\TrustedAuthorizationContext {
    $actor=new Platform\Contracts\ActorReference('account','mr6_synthetic_operator');
    return Platform\Contracts\TrustedAuthorizationContext::fromBackend(new Platform\Contracts\AuthorizationContext(realActor:$actor,effectiveActor:$actor,
        sessionReference:new Platform\Contracts\SessionReference('mr6_synthetic_session'),accountId:'mr6_synthetic_operator',credentialVersion:1,
        capabilities:new Platform\Contracts\CapabilitySet($caps),action:$action,resource:'media_review_submission',authorizationPlane:Platform\Contracts\AuthorizationPlane::INTERNAL_OPERATOR,riskLevel:Platform\Contracts\RiskLevel::R1),'canonical_internal_operator','active',true,false);
}
function mr6File(string $format='png',int $width=640,int $height=360):array {
    $path=tempnam(sys_get_temp_dir(),'mr6-corrected-');$im=imagecreatetruecolor($width,$height);
    imagealphablending($im,false);imagesavealpha($im,true);imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127));
    imagefilledrectangle($im,10,10,max(11,$width-10),max(11,$height-10),imagecolorallocate($im,145,75,180));
    imagestring($im,5,20,20,'CORRECCION SINTETICA MR6',imagecolorallocate($im,255,255,255));
    match($format){'jpeg'=>imagejpeg($im,$path,85),'webp'=>imagewebp($im,$path,90),default=>imagepng($im,$path)};
    if($format==='jpeg'){
        $tiff="II".pack('vV',42,8).pack('v',1).pack('vvVv',0x112,3,1,6)."\0\0".pack('V',0);$exif="Exif\0\0".$tiff;
        $jpeg=file_get_contents($path);$comment='GPS=TEST;DEVICE=TEST;';
        file_put_contents($path,substr($jpeg,0,2)."\xff\xe1".pack('n',strlen($exif)+2).$exif."\xff\xfe".pack('n',strlen($comment)+2).$comment.substr($jpeg,2));
    }
    rename($path,$path.'.'.$format);$path.='.'. $format;
    return ['tmp_name'=>$path,'name'=>'corregida.'.$format,'type'=>'image/'.$format,'error'=>0];
}
function mr6Rows(PDO $p,string $id):array {
    $s=$p->prepare('SELECT * FROM media_review_files WHERE submission_id=? ORDER BY role');$s->execute([$id]);$rows=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r)$rows[$r['role']]=$r;return $rows;
}
function mr6ModernFile():array {
    $u=mr6File('jpeg',4000,3000);$jpeg=file_get_contents($u['tmp_name']);$comments='';
    while(strlen($jpeg)+strlen($comments)+60004<10485760-1024){$payload=str_pad('GPS=TEST;DEVICE=TEST;',60000,'x');$comments.="\xff\xfe".pack('n',strlen($payload)+2).$payload;}
    file_put_contents($u['tmp_name'],substr($jpeg,0,2).$comments.substr($jpeg,2));return $u;
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__ && ($argv[1]??'')==='modern')echo json_encode(mr6ModernFile());
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__ && ($argv[1]??'')==='file')echo json_encode(mr6File($argv[2]??'png'));
