<?php
declare(strict_types=1);
require __DIR__.'/../services/MediaReviewInboxService.php';
use Media\Services\{MediaReviewInboxService as Inbox,MediaReviewAuthority};
use Platform\Contracts\{AuthorizationContext,TrustedAuthorizationContext,AuthorizationPlane,SessionReference,CapabilitySet};
function ok(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$context=TrustedAuthorizationContext::fromBackend(new AuthorizationContext(sessionReference:new SessionReference('mr4_session'),accountId:'mr4_operator',credentialVersion:1,capabilities:new CapabilitySet(['media_review_read']),action:'read',resource:'media_review_submission',authorizationPlane:AuthorizationPlane::INTERNAL_OPERATOR,riskLevel:'R0'));
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec('CREATE TABLE profiles_doctors(doctor_id TEXT PRIMARY KEY,display_name TEXT,email TEXT)');
$p->exec('CREATE TABLE media_review_submissions(submission_id TEXT PRIMARY KEY,owner_type TEXT,owner_id TEXT,purpose TEXT,technical_status TEXT,review_status TEXT,created_at TEXT,updated_at TEXT,batch_id TEXT)');
$p->exec('CREATE TABLE media_review_files(submission_id TEXT,role TEXT,mime_type TEXT,width INT,height INT,byte_size INT,storage_key TEXT,UNIQUE(submission_id,role))');
$p->exec("INSERT INTO profiles_doctors VALUES('1','Dra. Nombre canónico','private@example.invalid')");
$service=new Inbox($p);ok($service->pending($context)['items']===[],'valid empty');
for($i=60;$i>=1;$i--){$id=sprintf('00000000-0000-4000-8000-%012d',$i);$q=$p->prepare("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status,created_at,updated_at) VALUES(?,'PHYSICIAN','1','DOCTOR_PROFILE_PHOTO','READY','PENDING_REVIEW',?,?)");$date=$i<=30?'2026-09-01 10:00:00':'2026-09-02 10:00:00';$q->execute([$id,$date,$date]);$q=$p->prepare("INSERT INTO media_review_files VALUES(?,'REVIEW','image/webp',800,600,10000,'private/review/secret')");$q->execute([$id]);$q=$p->prepare("INSERT INTO media_review_files VALUES(?,'SOURCE','image/jpeg',4000,3000,999999,'private/source/secret')");$q->execute([$id]);}
foreach(['WITHDRAWN','APPROVED','REJECTED','NEEDS_WORK'] as $status)$p->exec("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status,created_at,updated_at) VALUES('$status','PHYSICIAN','1','DOCTOR_PROFILE_PHOTO','READY','$status','2020-01-01','2020-01-01')");
$p->exec("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status,created_at,updated_at) VALUES('failed','PHYSICIAN','1','DOCTOR_PROFILE_PHOTO','FAILED','PENDING_REVIEW','2020-01-01','2020-01-01')");
$p->exec("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status,created_at,updated_at) VALUES('other','PHYSICIAN','1','UNSUPPORTED','READY','PENDING_REVIEW','2020-01-01','2020-01-01')");
foreach(['WITHDRAWN','APPROVED','REJECTED','NEEDS_WORK','failed','other'] as $id)$p->exec("INSERT INTO media_review_files VALUES('$id','REVIEW','image/webp',800,600,10000,'private/secret')");
$a=$service->pending($context);ok(count($a['items'])===25,'default limit');ok($a['pagination']['next_offset']===25,'next offset');
$b=$service->pending($context,999);ok(count($b['items'])===50,'max limit');$c=$service->pending($context,50,50);ok(count($c['items'])===10&&!$c['pagination']['has_more'],'last page');
$all=array_merge($b['items'],$c['items']);$ids=array_column($all,'submission_id');$sorted=$ids;sort($sorted);ok($ids===$sorted&&count(array_unique($ids))===60,'ordering and no duplicates');
foreach($all as $item){ok($item['owner_display_name']==='Dra. Nombre canónico','canonical display');ok($item['technical_status']==='READY'&&$item['review_status']==='PENDING_REVIEW','filter');}
$json=json_encode($all);foreach(['storage_key','SOURCE','private/','999999','email','public_url'] as $secret)ok(!str_contains($json,$secret),'privacy '.$secret);
$unavailable=new class extends PDO{public function __construct(){}public function prepare(string $q,array $o=[]):PDOStatement|false{throw new RuntimeException('query executed');}};
try{(new Inbox($unavailable))->pending(null);throw new RuntimeException('allowed');}catch(RuntimeException $e){ok($e->getMessage()==='review_access_denied','auth before query');}
echo "PASS: pending filters, excluded states/purposes, canonical name, single appearance, stable order, 25/50 pagination, empty list, metadata privacy, authorization before query\n";

$p->exec("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status,created_at,updated_at) VALUES('00000000-0000-4000-8000-000000000061','PHYSICIAN','1','DOCTOR_GALLERY','READY','PENDING_REVIEW','2026-09-03','2026-09-03')");
$p->exec("INSERT INTO media_review_files VALUES('00000000-0000-4000-8000-000000000061','REVIEW','image/webp',800,600,10000,'private/secret')");
ok(in_array('DOCTOR_GALLERY',array_column($service->pending($context,50,50)['items'],'purpose'),true),'gallery_inbox_supported');
