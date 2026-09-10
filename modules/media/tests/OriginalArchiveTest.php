<?php
declare(strict_types=1);
require_once __DIR__.'/ReviewBatchFixture.php';
require_once __DIR__.'/../services/HistoricalOriginalArchive.php';
require_once __DIR__.'/../storage/LocalDisposableHistoricalArchive.php';
use Media\Services\{MediaReviewInterventionService as Download,BatchOriginals,HistoricalOriginalArchive as Archive,MediaReviewBatchService,ProfilePhotoApprovalService,PhysicianLogoApprovalService,GalleryApprovalService};
function a12(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function denied12(Closure $run,string $label):void{try{$run();}catch(RuntimeException){a12(true,$label);return;}throw new LogicException($label);}
$p=mr5Pdo();[$private,$public]=mr5Storage();$service=new Download($p,$private);$context=mr6Context('download_source',['media_review_read',Download::DOWNLOAD]);
$f=mr11Batch(2);$id=$f['batch_id'];(new MediaReviewBatchService($p))->submit($f['doctor']);$data=BatchOriginals::load($p,$id);
$root=sys_get_temp_dir().'/mxmed-archive-'.bin2hex(random_bytes(8));mkdir($root,0700);$adapter=new Media\Storage\LocalDisposableHistoricalArchive($root);$archive=new Archive($p,$service,$adapter);
try{
 denied12(fn()=>$archive->archive($id),'submitted_not_archive_eligible');
 foreach([null,mr6Context('download_source',['media_review_read'])] as $bad)denied12(fn()=>$service->downloadBatch($bad,$id),'download_authority_required');
 denied12(fn()=>$service->downloadBatch($context,'../../etc/passwd'),'traversal_denied');
 denied12(fn()=>$service->downloadBatch($context,(new Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid()),'foreign_missing_batch_denied');
 $legacy=mr11Legacy();denied12(fn()=>$service->downloadBatch($context,$legacy['id']),'legacy_submission_not_batch');
 $open=mr11Candidate(mr11Doctor());denied12(fn()=>$service->downloadBatch($context,$open['batch_id']),'open_batch_denied');
 $before=glob(sys_get_temp_dir().'/mxmed-originals-*');$result=$service->downloadBatch($context,$id);$zip=new ZipArchive();a12($zip->open($result['zip']->path())===true,'zip_readable');$manifest=json_decode($zip->getFromName('manifest.json'),true,512,JSON_THROW_ON_ERROR);a12(count($manifest['sources'])===4&&$zip->numFiles===5,'zip_sources_only_manifest');
 $byId=array_column($data['items'],null,'submission_id');$names=[];
 foreach($manifest['sources'] as $entry){$bytes=$zip->getFromName($entry['archive_filename']);a12($bytes===$service->sourceBytes($byId[$entry['submission_id']],$byId[$entry['submission_id']]['source']),'zip_source_bytes_match');a12(hash('sha256',$bytes)===$entry['sha256']&&strlen($bytes)===$entry['byte_size'],'manifest_integrity');a12(!str_contains($entry['archive_filename'],'..')&&!str_starts_with($entry['archive_filename'],'/'),'safe_zip_name');$names[]=$entry['archive_filename'];a12(array_key_exists('original_filename',$entry)&&$entry['original_filename']===null,'unretained_original_name_explicit');}
 a12(count(array_unique($names))===4,'duplicate_names_no_overwrite');$raw=$zip->getFromName('manifest.json');foreach(['storage_key','private/','token','session','email','phone','address'] as $forbidden)a12(!str_contains($raw,$forbidden),'no_'.$forbidden);$zip->close();$result['zip']->remove();a12(glob(sys_get_temp_dir().'/mxmed-originals-*')===$before,'zip_cleanup_success');
 $first=$data['items'][0];$p->prepare('UPDATE media_review_files SET checksum_sha256=? WHERE file_id=?')->execute([str_repeat('0',64),$first['source']['file_id']]);denied12(fn()=>$service->downloadBatch($context,$id),'corrupt_source_fails_closed');a12(glob(sys_get_temp_dir().'/mxmed-originals-*')===$before,'zip_cleanup_failure');$p->prepare('UPDATE media_review_files SET checksum_sha256=? WHERE file_id=?')->execute([$first['source']['checksum_sha256'],$first['source']['file_id']]);
 foreach($data['items'] as $i=>$item){if($i===0){(new Media\Services\MediaReplacementService($p,$private))->request(mr9Context(),$item['submission_id'],'OTHER','Nueva imagen necesaria');}else{$class=match($item['purpose']){'DOCTOR_PROFILE_PHOTO'=>ProfilePhotoApprovalService::class,'PHYSICIAN_PERSONAL_LOGO'=>PhysicianLogoApprovalService::class,default=>GalleryApprovalService::class};(new $class($p,$private,$public))->approve(mr5Context(),$item['submission_id']);}if($i===0)denied12(fn()=>$archive->archive($id),'partial_batch_not_resolved');}
 $resolvedZip=$service->downloadBatch($context,$id);$reader=new ZipArchive();$reader->open($resolvedZip['zip']->path());a12(count(json_decode($reader->getFromName('manifest.json'),true)['sources'])===4,'resolved_zip_preserves_all_originals');$reader->close();$resolvedZip['zip']->remove();
 $replacement=mr11Candidate($f['doctor'],$data['items'][0]['purpose']);a12($replacement['batch_id']!==$id,'replacement_separate_open_batch');a12(count(BatchOriginals::load($p,$id)['items'])===4,'submitted_membership_preserved');
 $unverifiable=new class($adapter) implements Media\Contracts\HistoricalArchivePort {public function __construct(private Media\Contracts\HistoricalArchivePort $inner){} public function put(string $key,string $bytes,array $identity):void{$this->inner->put($key,$bytes,$identity);}public function verify(string $key,array $identity):bool{return false;}};
 $unverified=(new Archive($p,$service,$unverifiable))->archive($id);a12($unverified['state']==='ARCHIVED_UNVERIFIED','write_success_not_verification');a12(!$archive->purgeEligible($unverified,new DateTimeImmutable('+31 days')),'unverified_adapter_no_purge');
 $receipt=$archive->archive($id);a12($receipt['state']==='ARCHIVED_VERIFIED','local_archive_verified');$prefix=Archive::prefix($data['batch']);a12($archive->archive($id)['state']==='ARCHIVED_VERIFIED','archive_idempotent');a12(str_starts_with($prefix,'media-archive/v1/physicians/'),'archive_key_schema');
 foreach($receipt['objects'] as $object)a12($adapter->verify($object['key'],$object['identity']),'stored_identity_checksum');
 foreach($data['items'] as $item){$ext=$item['source']['format']==='jpeg'?'jpg':$item['source']['format'];a12(file_get_contents($root.'/'.$prefix.'sources/'.$item['submission_id'].'/original.'.$ext)===$service->sourceBytes($item,$item['source']),'archived_equals_source');}
 a12(!$archive->purgeEligible($receipt,new DateTimeImmutable('now')),'retention_not_elapsed');a12($archive->purgeEligible($receipt,new DateTimeImmutable('+31 days')),'verified_retention_policy');
 foreach(['NOT_ARCHIVED','ARCHIVED_UNVERIFIED','FAILED','OPERATIONAL_SOURCE_PURGED'] as $state){$bad=$receipt;$bad['state']=$state;a12(!$archive->purgeEligible($bad,new DateTimeImmutable('+31 days')),'unverified_never_purge');}
 $object=$receipt['objects'][0];file_put_contents($root.'/'.$object['key'],'corrupt');a12(!$archive->purgeEligible($receipt,new DateTimeImmutable('+31 days')),'reverify_before_purge');a12($archive->archive($id)['state']==='FAILED','archive_failure_reported');
 foreach($data['items'] as $item)a12($private->exists($item['source']['storage_key']),'archive_failure_preserves_source');
 echo "ORIGINAL_ARCHIVE_E2E=PASS\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);}
