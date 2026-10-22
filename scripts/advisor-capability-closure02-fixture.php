<?php
declare(strict_types=1);
// New synthetic records, only the existing owned CLI fixture principal.
$phase=$argv[1]??'prepare';$argv[1]='library';require __DIR__.'/ase-advisor-workspace02-fixture.php';
$file=$owned.'/closure-fixture.json';$data=is_file($file)?json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR):[];
if($phase==='prepare'){
 if(!$data){
  $rec=account('closure_sources');$actor=loginActor($rec['email']);$doctor='closure_sources';
  $p->prepare("INSERT INTO profiles_doctors(doctor_id,display_name,professional_license,specialty_primary,profile_status,is_public_candidate,preloaded_free_plan,preloaded_free_reference,preloaded_free_recorded_at) VALUES(?,?,?,'Medicina sintética','active',1,'free','closure02-synthetic-only',UTC_TIMESTAMP())")->execute([$doctor,'Profesional Sintético Closure','SYNTHETIC-CLOSURE02']);
  $p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$doctor,'closure_source_office','Consultorio sintético','Ciudad sintética','Estado sintético']);
  $browser=Identity\Accreditation\PublicEnrollmentService::opaque();$ticket=$enrollment->ticket('CLAIM_EXISTING_PROFILE',$doctor,'/profiles/doctor.php?doctor_id='.$doctor,$browser);$origin=$enrollment->start($ticket,$browser);$enrollment->bind($actor,$origin['application_id'],$origin['handle'],$browser);
  $enrollment->save($actor,$origin['application_id'],0,$a->uuid(),['given_names'=>'Profesional','first_surname'=>'Sintético','second_surname'=>'Closure','professional_license'=>'SYNTHETIC-CLOSURE02','specialty'=>'Medicina sintética','state'=>'Estado sintético','city'=>'Ciudad sintética'],true);
  require_once __DIR__.'/../modules/agenda/services/HealthcareOrganizationClaimService.php';$provider=new Agenda\Services\HealthcareOrganizationClaimService($p);$claims=[];
  foreach(['approve','reject','outside','demo'] as $key){$group='closure_org_'.$key;$p->prepare("INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source) VALUES(?,'LABORATORY',?,?,'pending','operator_created')")->execute([$group,$group,'Laboratorio Sintético '.$key]);$claims[$key]=$provider->submitExisting($actor,$group,'closure-'.$key,'REPRESENTATIVE_DOCUMENT')['claim_uuid'];}
  $data=['source'=>$rec+['doctor_id'=>$doctor,'application_id'=>$origin['application_id']],'claims'=>$claims];file_put_contents($file,json_encode($data,JSON_THROW_ON_ERROR));
 }
 $cohort=json_decode(file_get_contents($owned.'/closure-scope.json'),true);$cohort['accounts']=array_values(array_unique([...$cohort['accounts'],$data['source']['account_id']]));$cohort['profiles']=array_values(array_unique([...$cohort['profiles'],$data['source']['doctor_id']]));$cohort['provider_claims']=array_values(array_diff($data['claims'],[$data['claims']['outside']]));file_put_contents($owned.'/closure-scope.json',json_encode($cohort,JSON_THROW_ON_ERROR));
}elseif($phase==='upload'){
 $source=new Identity\ProfessionalSources\SourceProvisioningService($enrollment,$cipher,$storage,$mfa);$actor=loginActor($data['source']['email']);$st=$source->status($actor,$data['source']['application_id'],false);
 foreach(['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'] as $kind)if(!in_array($kind,array_column($st['files'],'kind'),true)){$source->upload($actor,$data['source']['application_id'],$st['version'],$kind,file_get_contents($owned.'/synthetic.png'),$data['source']['email'],trim(file_get_contents($owned.'/applicant-password')));$st=$source->status($actor,$data['source']['application_id'],false);}
}else throw new RuntimeException('unknown_fixture_action');
echo json_encode(['ok'=>true],JSON_THROW_ON_ERROR);
