<?php
declare(strict_types=1);
// CLI-only, fixed disposable MySQL port. Never write through the real application's PDO.
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require_once __DIR__.'/../../../api/_lib/db.php';
require_once __DIR__.'/../private-bootstrap.php';
require_once __DIR__.'/../services/DoctorProfilePhotoService.php';
require_once __DIR__.'/../services/ProfilePhotoApprovalService.php';
function mr5Pdo(): PDO {
    return new PDO('mysql:host=127.0.0.1;port=3309;dbname=mxmed;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}
function mr5Storage(): array {
    $base=(string)getenv('MR5_FIXTURE_ROOT');
    if (!preg_match('#^/private/tmp/mxmed-mr5-[a-zA-Z0-9_-]+$#D',$base) && !preg_match('#^/tmp/mxmed-mr5-[a-zA-Z0-9_-]+$#D',$base)) throw new RuntimeException('isolated_storage_required');
    return [new Media\Storage\LocalPersistentPrivateMediaStorage($base.'/private',[$base.'/public']),new Media\Storage\LocalPersistentPublicMediaStorage($base.'/public')];
}
function mr5Candidate(PDO $p,string $label='Prueba sintética MR5'): array {
    [$private,$public]=mr5Storage();$doctor='mr5_'.bin2hex(random_bytes(6));
    $p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute([$doctor,$label]);
    $path=tempnam(sys_get_temp_dir(),'mr5-source-');$image=imagecreatetruecolor(420,560);
    imagefill($image,0,0,imagecolorallocate($image,34,126,142));
    imagefilledellipse($image,210,170,135,150,imagecolorallocate($image,242,212,180));
    imagefilledrectangle($image,85,265,335,530,imagecolorallocate($image,245,245,245));
    imagestring($image,5,65,480,'FOTO SINTETICA MR5',imagecolorallocate($image,20,60,80));
    imagepng($image,$path);
    try {
        (new Media\Services\DoctorProfilePhotoService($p,$public))->upload($doctor,['tmp_name'=>$path,'name'=>'synthetic.png','error'=>0]);
        $old=$p->query("SELECT * FROM media_assets WHERE owner_id='$doctor'")->fetch();
        imagepng($image,$path);
        $service=new Media\Services\ProfilePhotoReviewCandidateService($p,$private);
        $service->upload($doctor,['tmp_name'=>$path,'name'=>'synthetic.png','error'=>0]);
        $id=$service->current($doctor)['submission_id'];
        return ['doctor'=>$doctor,'id'=>$id,'old'=>$old];
    } finally { if(is_file($path)) unlink($path); }
}
function mr5Context(array $caps=['media_review_read','media_review_approve'],string $source='canonical_internal_operator'): Platform\Contracts\TrustedAuthorizationContext {
    $actor=new Platform\Contracts\ActorReference('account','mr5_synthetic_operator');
    return Platform\Contracts\TrustedAuthorizationContext::fromBackend(new Platform\Contracts\AuthorizationContext(
        realActor:$actor,effectiveActor:$actor,sessionReference:new Platform\Contracts\SessionReference('mr5_synthetic_session'),
        accountId:'mr5_synthetic_operator',credentialVersion:1,capabilities:new Platform\Contracts\CapabilitySet($caps),
        action:'approve',resource:'media_review_submission',authorizationPlane:Platform\Contracts\AuthorizationPlane::INTERNAL_OPERATOR,riskLevel:Platform\Contracts\RiskLevel::R1),$source,'active',true,false);
}
if (realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__) return;
if (($argv[1]??'')==='setup') {
    $admin=new PDO('mysql:host=127.0.0.1;port=3309;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE mxmed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$p=mr5Pdo();
    $source=mxmed_pdo();
    foreach (['profiles_doctors','media_assets','media_review_batches','media_review_submissions','media_review_files','media_review_batch_ready_events','platform_audit_events','platform_audit_stream_heads'] as $table) {
        // Schema only: no Director rows, keys, files, or audit history copied.
        if(in_array($table,['media_review_batches','media_review_batch_ready_events'],true)&&!$source->query("SHOW TABLES LIKE '$table'")->fetchColumn())continue;
        $p->exec($source->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1]);
    }
    // Test-only implementations of the existing controlled lock/CAS procedure contract.
    $p->exec('CREATE PROCEDURE audit_mp01c_lock_stream_head_v1(IN k VARCHAR(255)) SELECT last_sequence_number,last_event_hash,hash_version,updated_at FROM platform_audit_stream_heads WHERE stream_key=k FOR UPDATE');
    $p->exec("CREATE PROCEDURE audit_mp01c_advance_stream_head_cas_v1(IN k VARCHAR(255),IN seq BIGINT,IN h VARCHAR(64),IN v VARCHAR(64),IN t VARCHAR(32),IN ns BIGINT,IN nh VARCHAR(64),IN nv VARCHAR(64),IN nt VARCHAR(32)) BEGIN
        UPDATE platform_audit_stream_heads SET last_sequence_number=ns,last_event_hash=nh,hash_version=nv,updated_at=STR_TO_DATE(nt,'%Y-%m-%dT%H:%i:%s.%fZ')
        WHERE stream_key=k AND last_sequence_number=seq AND last_event_hash=h AND hash_version<=>v AND updated_at<=>STR_TO_DATE(t,'%Y-%m-%dT%H:%i:%s.%fZ');
        IF ROW_COUNT()<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='head_update_failed'; END IF;
        END");
    echo json_encode(['ok'=>true]);
} elseif (($argv[1]??'')==='candidate') echo json_encode(mr5Candidate(mr5Pdo()));
elseif (($argv[1]??'')==='approve') {
    [$private,$public]=mr5Storage();
    try { echo json_encode((new Media\Services\ProfilePhotoApprovalService(mr5Pdo(),$private,$public))->approve(mr5Context(),$argv[2])); }
    catch (Throwable $e) { echo json_encode(['error'=>$e->getMessage()]);exit(2); }
}
