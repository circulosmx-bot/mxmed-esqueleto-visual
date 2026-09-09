<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/db.php';
require_once __DIR__.'/../private-bootstrap.php';
$p=mxmed_pdo();$public=mxmed_public_media_storage();$private=mxmed_private_media_storage();
$tables=[];
foreach(['media_assets'=>'media_id','profiles_doctors'=>'doctor_id','media_review_submissions'=>'submission_id','media_review_files'=>'file_id'] as $table=>$order){
 $rows=$p->query("SELECT * FROM $table ORDER BY $order")->fetchAll();
 $tables[$table]=hash('sha256',json_encode($rows));
}
$hashes=[];
foreach($p->query("SELECT media_id,storage_key FROM media_assets WHERE status='READY'") as $a){$f=$public->openReadStream($a['storage_key']);$h=hash_init('sha256');hash_update_stream($h,$f['stream']);fclose($f['stream']);$hashes[$a['media_id']]=hash_final($h);}
$s=$p->prepare('SELECT role,storage_key FROM media_review_files WHERE submission_id=? ORDER BY role');$s->execute(['42adffaa-3344-47cf-9bf5-6831c969cbd5']);
foreach($s as $f){$o=$private->openReadStream($f['storage_key']);$h=hash_init('sha256');hash_update_stream($h,$o['stream']);fclose($o['stream']);$hashes[$f['role']]=hash_final($h);}
ksort($hashes);
echo json_encode(['tables'=>$tables,'files'=>$hashes,'photo'=>$p->query("SELECT photo_url FROM profiles_doctors WHERE doctor_id='1'")->fetchColumn(),'counts'=>$p->query("SELECT purpose,COUNT(*) n FROM media_assets WHERE owner_type='PHYSICIAN' AND owner_id='1' AND status='READY' AND classification='PUBLIC' GROUP BY purpose ORDER BY purpose")->fetchAll()],JSON_UNESCAPED_SLASHES);
