<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('ACT03_QA')!=='1')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';require_once __DIR__.'/support/C4EvidenceHarness.php';
require_once __DIR__.'/../../profiles/controllers/PublicProfileController.php';require_once __DIR__.'/../../profiles/controllers/DoctorContactPointsController.php';
require_once __DIR__.'/../../profiles/Corrections/bootstrap.php';
$p=evConnect();$public=new Profiles\Controllers\PublicProfileController(new Profiles\Repositories\PublicProfileRepository($p));$private=new Profiles\Controllers\DoctorContactPointsController(new Profiles\Repositories\DoctorContactPointsRepository($p));
foreach(['act03_profile_0','act03_profile_1','act03_profile_2','act03_contact_free'] as $profile){
 $items=$private->index($profile)['data']['items'];evCheck(count(array_filter($items,fn($i)=>$i['value']==='+15557779999'))===1,'FREE_stored_consultation_phone_remains_private_readable_'.$profile);
 $dto=$public->showByDoctorId($profile);evCheck(($dto['data']['public_visibility']['show_phone']??true)===false&&!str_contains(json_encode($dto),'15557779999'),'FREE_public_DTO_phone_hidden_after_sources_or_explicit_owner_'.$profile);
}
foreach(['basic','standard','optimum','professional'] as $plan){$dto=$public->showByDoctorId('act03_phone_'.$plan);evCheck(($dto['data']['public_visibility']['show_phone']??false)===true&&str_contains(json_encode($dto),'15557779999'),'paid_existing_exact_phone_entitlement_'.$plan);}
// PUB-COR02 target selection uses the original public read model, not all stored contacts.
$source=file_get_contents(__DIR__.'/../../profiles/Corrections/ApplicationService.php');
evCheck(str_contains($source,'PublicPhoneCorrectionAdapter'),'PUB_COR02_reuses_original_published_phone_targets');
$targets=new Profiles\Corrections\PublicPhoneCorrectionAdapter($p);
$p->beginTransaction();$hidden=$targets->targets('act03_profile_0');$p->rollBack();evCheck($hidden===[],'hidden_FREE_consultation_phone_is_not_a_PUB_COR02_target');
$before=$p->query("CHECKSUM TABLE doctor_contact_points")->fetch(PDO::FETCH_NUM)[1];
$clock=new EvClock(new DateTimeImmutable('now',new DateTimeZone('UTC')));$s=evStack($p,$clock);$actor=evLogin($s,'act03_applicant_0@example.invalid');evPhone($s,$actor,'+15556660000');
evCheck($p->query("CHECKSUM TABLE doctor_contact_points")->fetch(PDO::FETCH_NUM)[1]===$before,'C4_OTP_phone_verification_does_not_write_consultation_contacts');
evCheck(!str_contains(json_encode($public->showByDoctorId('act03_profile_0')),'15556660000'),'C4_OTP_phone_not_in_public_profile');
echo "ACT03_FREE_CONTACT=PASS\n";
