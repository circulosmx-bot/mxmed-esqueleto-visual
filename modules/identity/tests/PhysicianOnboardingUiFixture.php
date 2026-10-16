<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';
require_once __DIR__.'/../../profiles/services/VerifiedPhysicianIdentityService.php';
require_once __DIR__.'/../../profiles/services/VerifiedDoctorCredentialService.php';
require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';
require_once __DIR__.'/support/C4EvidenceHarness.php';
$p=evConnect();$db=(string)$p->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/^mxmed_gate4d_preview_c4ui_[a-f0-9]{10}$/D',$db))throw new RuntimeException('ui_disposable_only');
$clock=new EvClock(new DateTimeImmutable('-2 minutes',new DateTimeZone('UTC')));$s=evStack($p,$clock);
$hash=password_hash(EV_PASSWORD,PASSWORD_ARGON2ID);
foreach(['ui_director'=>['DIRECTOR',true],'ui_no_grant'=>['MASTER_ADMIN',false],...array_combine(array_map(fn($i)=>'ui_reviewer_'.$i,range(0,7)),array_fill(0,8,['MASTER_ADMIN',true]))] as $id=>[$class,$grant]){
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
    $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,$hash]);
    $p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,?,'ACTIVE')")->execute([$id,$class]);
    if($grant&&$id!=='ui_director')$p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,'physician_accreditation_review','ACTIVE')")->execute([$id]);
}
$director=evLogin($s,'ui_director@example.invalid');$enroll=new Identity\Services\GovernanceStaffMfaCeremony($p,$s['mfa'],$s['security'],new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['ui_director']);
$profiles=[];
for($i=0;$i<8;$i++){
    $id='ui_reviewer_'.$i;$reviewer=evLogin($s,$id.'@example.invalid');
    $factor=$enroll->begin($reviewer['context'],$director['context'],$reviewer['email'],EV_PASSWORD,$director['email'],EV_PASSWORD);
    $enroll->activate($reviewer['context'],$factor['factor_id'],Identity\Services\StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
    $profile=evProfessional($p,'ui_'.$i,$id);$profiles[]=$profile[0];
}
file_put_contents((string)getenv('GOV03B_QA_EVIDENCE').'/ui-fixture.json',json_encode(['profiles'=>$profiles,'password'=>EV_PASSWORD],JSON_THROW_ON_ERROR));
evCheck((int)$p->query('SELECT COUNT(*) FROM profiles_physician_admissions')->fetchColumn()===0,'original_ui_fixture_no_preissued_authorities');
echo "C4_UI01_SYNTHETIC_FIXTURE=PASS\n";
