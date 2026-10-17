<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('ACT02_QA')!=='1'||getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')!=='0')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../profiles/controllers/PublicProfileController.php';
require_once __DIR__.'/../../profiles/Corrections/bootstrap.php';
$identity=Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$p=$identity->pdo();$db=$p->query('SELECT DATABASE()')->fetchColumn();
if(!preg_match('/^mxmed_gate4d_preview_(cor02|c4ui)_[a-f0-9]{10}$/D',$db))throw new RuntimeException('new_synthetic_target_required');
function commercialCheck(bool $v,string $name):void{if(!$v)throw new RuntimeException($name);echo 'PASS '.$name.PHP_EOL;}
// This is a new synthetic mapping only. No normal-local backfill command exists.
$p->exec("UPDATE profiles_doctors SET preloaded_free_plan='free',preloaded_free_reference='act02-synthetic-reviewed-mapping',preloaded_free_recorded_at=UTC_TIMESTAMP(),plan_code=NULL WHERE plan_code='free'");
$resolver=new Subscriptions\Services\PublicCommercialPlanSource($p);
foreach(['free','basic','standard','optimum','professional','unknown',null] as $n=>$plan){
    $id='act02_plan_'.$n;
    $p->prepare("INSERT INTO profiles_doctors(doctor_id,display_name,professional_license,specialty_primary,profile_status,is_public_candidate) VALUES(?,?,?,?,'active',1)")->execute([$id,'Profesional comercial sintético '.$n,'ACT02-SYNTHETIC-'.$n,'Especialidad sintética']);
    $p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$id,'commercial-synthetic-office','Consultorio sintético','Ciudad sintética','Estado sintético']);
    $profile=['doctor_id'=>$id,'preloaded_free_plan'=>'free','preloaded_free_reference'=>'synthetic-map-01','preloaded_free_recorded_at'=>'2026-10-16 00:00:00'];
    if($plan!==null){$p->prepare("INSERT INTO profile_subscriptions(subscription_id,entity_type,entity_id,doctor_id,plan_code,contracted_plan_code,effective_plan_code,billing_period,status,starts_at,expires_at,source) VALUES(UUID(),'doctor',?,?,?,?,?,?, 'active',UTC_TIMESTAMP()-INTERVAL 1 DAY,UTC_TIMESTAMP()+INTERVAL 1 YEAR,'act02_synthetic_fixture')")->execute([$id,$id,$plan,$plan,$plan,$plan==='free'?'lifetime':'annual']);}
    $r=$resolver->resolve($profile);
    commercialCheck($r['plan_code']===($plan==='unknown'?null:($plan??'free')),'canonical_commercial_explicit_state_'.($plan??'mapped_free_without_subscription'));
    if(in_array($plan,['free','basic','standard','optimum','professional'],true))commercialCheck($r['source']==='profile_subscriptions','subscription_precedes_preloaded_mapping_'.$plan);
    $dto=(new Profiles\Controllers\PublicProfileController(new Profiles\Repositories\PublicProfileRepository($p)))->showByDoctorId($id)['data'];
    commercialCheck($dto['claim']['claim_allowed']===($plan==='free'),'claim_never_treats_missing_unknown_or_paid_as_FREE_'.($plan??'missing'));
    commercialCheck($dto['public_visibility']['show_suggest_correction']===($plan==='free'),'correction_visibility_requires_explicit_FREE_'.($plan??'missing'));
    if($plan==='free')commercialCheck((new Profiles\Corrections\ReportEligibility($p))->resolve($id)['profile']===$id,'canonical_FREE_subscription_allows_actual_correction_intake');
    if($plan==='free')commercialCheck(($dto['public_visibility']['show_phone']??true)===false,'FREE_telephone_remains_hidden');
}
$id='act02_plan_0';
foreach(range(1,6) as $mask){
    // CHECK must reject every partial mapping, including SQL NULL/UNKNOWN.
    $values=[($mask&1)?'free':null,($mask&2)?'synthetic-reviewed-mapping':null,($mask&4)?'2026-10-16 00:00:00':null,$id];
    try{$p->prepare('UPDATE profiles_doctors SET preloaded_free_plan=?,preloaded_free_reference=?,preloaded_free_recorded_at=? WHERE doctor_id=?')->execute($values);throw new LogicException('partial FREE mapping accepted');}
    catch(PDOException $e){commercialCheck((int)($e->errorInfo[1]??0)===3819,'native_FREE_mapping_rejects_partial_fields_'.$mask);}
}
$p->prepare("UPDATE profiles_doctors SET preloaded_free_plan='free',preloaded_free_reference='synthetic-reviewed-mapping',preloaded_free_recorded_at=UTC_TIMESTAMP() WHERE doctor_id=?")->execute([$id]);
commercialCheck($p->query("SELECT preloaded_free_plan FROM profiles_doctors WHERE doctor_id='act02_plan_0'")->fetchColumn()==='free','native_FREE_mapping_accepts_complete_explicit_source');
$p->prepare('UPDATE profiles_doctors SET preloaded_free_plan=NULL,preloaded_free_reference=NULL,preloaded_free_recorded_at=NULL WHERE doctor_id=?')->execute([$id]);
$unavailable=new PDO('sqlite::memory:',options:[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$unavailable->exec('CREATE TABLE profile_subscriptions(subscription_id TEXT)');
$unknown=(new Subscriptions\Services\PublicCommercialPlanSource($unavailable))->resolve(['doctor_id'=>'unreadable-commercial-source','preloaded_free_plan'=>'free','preloaded_free_reference'=>'synthetic-reviewed-mapping','preloaded_free_recorded_at'=>'2026-10-16 00:00:00']);
commercialCheck($unknown['plan_code']===null&&$unknown['source']==='commercial_unavailable','unreadable_canonical_subscription_never_falls_through_to_preloaded_FREE');
echo "ACT02_COMMERCIAL=PASS\n";
