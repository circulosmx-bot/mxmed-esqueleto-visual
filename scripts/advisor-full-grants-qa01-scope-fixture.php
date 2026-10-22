<?php
declare(strict_types=1);
// CLI-only owned fixture: real canonical applicant origins for self-review denial.
if(PHP_SAPI!=='cli')exit(1);
$argv[1]='library';require __DIR__.'/ase-advisor-workspace02-fixture.php';
$record=json_decode(file_get_contents($owned.'/full-demo.json'),true,512,JSON_THROW_ON_ERROR);
if($record['email']!=='full_grants_demo@example.test')throw new RuntimeException('synthetic_subject_required');
$path=$owned.'/full-self-origins.json';
if(is_file($path)){echo '{"prepared":true}';exit;}
$actor=loginActor($record['email']);$origins=[];
foreach(['REQUEST_NEW_PROFILE'=>null,'CLAIM_EXISTING_PROFILE'=>'workspace_aurora'] as $type=>$profile){
 $browser=Identity\Accreditation\PublicEnrollmentService::opaque();
 $ticket=$enrollment->ticket($type,$profile,'/profiles/listing.php',$browser);$o=$enrollment->start($ticket,$browser);
 $enrollment->bind($actor,$o['application_id'],$o['handle'],$browser);
 $enrollment->save($actor,$o['application_id'],0,$a->uuid(),['given_names'=>'Solicitante Sintético','first_surname'=>'Autorrevisión','second_surname'=>'QA01','professional_license'=>'SYNTHETIC-QA01-SELF','specialty'=>'Medicina general','state'=>'Estado Ficticio','city'=>'Ciudad Sintética'],true);
 $origins[$type]=$o['application_id'];
}
file_put_contents($path,json_encode($origins,JSON_THROW_ON_ERROR));chmod($path,0600);echo '{"prepared":true}';
