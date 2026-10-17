<?php
declare(strict_types=1);
// CLI fixture setup only; never an HTTP route or an enrollment-origin authority.
if (PHP_SAPI !== 'cli' || getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT') !== '0') exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';
require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';
require_once __DIR__.'/support/C4EvidenceHarness.php';
$p=evConnect();
$db=(string)$p->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/^mxmed_gate4d_preview_c4ui_([a-f0-9]{10})$/D', $db, $match)
    || $p->query('SELECT CURRENT_USER()')->fetchColumn() !== 'gov03b_'.$match[1].'@127.0.0.1') {
    throw new RuntimeException('ui_resume_owned_disposable_only');
}
$index=$argv[1]??'';
if (!preg_match('/\A[0-7]\z/D', $index)) throw new RuntimeException('ui_resume_fixture_index_required');
$fixture=json_decode(file_get_contents((string)getenv('GOV03B_QA_EVIDENCE').'/ui-fixture.json'),true,32,JSON_THROW_ON_ERROR);
$profile=$fixture['profiles'][(int)$index];
if ($profile!=='c4ev_ui_'.$index) throw new RuntimeException('ui_resume_fixture_profile_mismatch');
$q=$p->prepare("SELECT i.verified_identity_id,c.credential_id FROM profiles_verified_identities i
    JOIN profiles_doctor_credentials c ON c.doctor_id=i.doctor_id
    WHERE i.doctor_id=? AND c.credential_type='PROFESSIONAL' AND c.verification_status='VERIFIED' AND c.lifecycle_status='ACTIVE'");
$q->execute([$profile]);$sources=$q->fetchAll(PDO::FETCH_ASSOC);
if(count($sources)!==1) throw new RuntimeException('ui_resume_fixture_sources_required');
$s=evStack($p,new EvClock(new DateTimeImmutable('now',new DateTimeZone('UTC'))));
$actor=evLogin($s,'c4ui_physician_'.$index.'@example.invalid');
// Existing service revalidates the real browser-verified phone, account and exact sources.
$request=$s['evidence']->create($actor['context'],'CLAIM_EXISTING_PROFILE',$profile,
    (int)$sources[0]['verified_identity_id'],(int)$sources[0]['credential_id']);
echo json_encode(['request_id'=>$request['request_id'],'fixture_setup_only'=>true],JSON_THROW_ON_ERROR).PHP_EOL;
