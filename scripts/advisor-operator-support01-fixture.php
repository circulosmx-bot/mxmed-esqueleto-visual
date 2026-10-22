<?php
declare(strict_types=1);
// Canonical operator service creates all invitations, memberships, grants and events
// in a fresh OP01 rehearsal schema on the same owned server. Transfer exact synthetic
// rows (including event hashes) to the review schema; no mutation factory is widened.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/ase-advisor-workspace02-runtime.php';
$s=workspace02OwnedServices();$owned=realpath(getenv('ASE_INT02A_OWNED'));$db=getenv('MXMED_DB_NAME');$fixture=getenv('SUPPORT_FIXTURE_DB');
if(!preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',$fixture)||!preg_match('/^mxmed_gate4d_preview_aseadm02imp01_[a-f0-9]{10}$/D',$db))throw new RuntimeException('owned_schema_required');
$p=new PDO('mysql:unix_socket='.$owned.'/mysql.sock;dbname='.$fixture.';charset=utf8mb4','ase_fixture',getenv('ASE_INT02A_FIXTURE_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if(realpath($p->query('SELECT @@datadir')->fetchColumn())!==$owned.'/mysql')throw new RuntimeException('owned_datadir_required');
$tables=['auth_accounts','auth_account_credentials','auth_rate_limit_buckets','internal_staff','internal_operator_grants','profiles_doctors','auth_account_memberships','profile_subscriptions','subscription_plans','auth_profile_operator_invitations','auth_profile_operator_grant_revisions','auth_profile_operator_grants','auth_profile_operator_commands','auth_profile_operator_presentations','platform_audit_events','platform_audit_stream_heads'];
foreach($tables as $t)$p->exec("CREATE TABLE `$t` LIKE `$db`.`$t`");
require_once __DIR__.'/../modules/identity/tests/support/Op01Harness.php';
$hash=password_hash('Only-Synthetic-Op01!',PASSWORD_ARGON2ID);
foreach(['owner_a','invitee_a','invitee_b','invitee_c','outsider'] as $id){
 $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
 $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$id,$hash]);
}
$p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES('outsider','ADVISOR','ACTIVE')");
$stack=op01stack($p);$owner=op01login($stack,'owner_a');$a=op01login($stack,'invitee_a');$b=op01login($stack,'invitee_b');$outsider=op01login($stack,'outsider');
$profile=op01fresh($p,'support');
$first=op01invite($stack,$owner,$profile,'invitee_a');$accepted=op01accept($stack,$a,$profile,$first);
op01command($stack,$owner,$profile,'rename',['membership_id'=>$accepted['membership_id'],'expected_presentation_revision'=>2,'alias'=>'Lucía']);
$cancel=op01invite($stack,$owner,$profile,'invitee_b');op01command($stack,$owner,$profile,'cancel',['invitation_id'=>$cancel['invitation_id']]);
$pending=op01invite($stack,$owner,$profile,'invitee_c');
$denied=[];
foreach(['invite','grants','suspend','revoke'] as $action){try{op01command($stack,$outsider,$profile,$action,[]);$denied[$action]=false;}catch(RuntimeException $e){$denied[$action]=$e->getMessage()==='operator_owner_required';}}
$quota=false;try{op01invite($stack,$owner,$profile,'invitee_b');}catch(RuntimeException $e){$quota=$e->getMessage()==='operator_quota_exceeded';}
// Additional profile exercises accepted/suspended/revoked relationships.
$second=op01fresh($p,'support_states');$x=op01accept($stack,$a,$second,op01invite($stack,$owner,$second,'invitee_a'));$y=op01accept($stack,$b,$second,op01invite($stack,$owner,$second,'invitee_b',[], 'administrator'));
op01command($stack,$owner,$second,'suspend',['membership_id'=>$x['membership_id'],'expected_revision'=>1]);
op01command($stack,$owner,$second,'revoke',['membership_id'=>$y['membership_id'],'expected_revision'=>1]);
// Preserve true accepted states even after their original seven-day expiry.
// A separate historical clock fixture uses the same canonical service, not SQL state edits.
$past=op01stack($p,new Op01Clock(new DateTimeImmutable('-10 days',new DateTimeZone('UTC'))));$po=op01login($past,'owner_a');$pa=op01login($past,'invitee_a');$historical=op01fresh($p,'support_history');
$hi=op01invite($past,$po,$historical,'invitee_a');op01accept($past,$pa,$historical,$hi);op01invite($past,$po,$historical,'invitee_b');
// Exact columns, omit generated values. No credentials/tokens are printed.
$copy=['auth_accounts','auth_account_credentials','profiles_doctors','auth_account_memberships','profile_subscriptions','auth_profile_operator_invitations','auth_profile_operator_grant_revisions','auth_profile_operator_grants','auth_profile_operator_presentations','auth_profile_operator_commands','platform_audit_stream_heads','platform_audit_events'];
$p->beginTransaction();
foreach($copy as $t){$cols=[];foreach($p->query("SHOW COLUMNS FROM `$t`") as $col)if(!str_contains($col['Extra'],'GENERATED')&&!str_contains($col['Extra'],'auto_increment'))$cols[]='`'.$col['Field'].'`';$list=implode(',',$cols);$p->exec("INSERT INTO `$db`.`$t` ($list) SELECT $list FROM `$fixture`.`$t`");}
$p->commit();
// Use the existing Media writer to place the synthetic profiles in authorized scope.
$target=new PDO('mysql:unix_socket='.$owned.'/mysql.sock;dbname='.$db.';charset=utf8mb4','ase_fixture',getenv('ASE_INT02A_FIXTURE_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require_once __DIR__.'/../modules/media/private-bootstrap.php';require_once __DIR__.'/../modules/media/tests/ReviewCandidateFixture.php';require_once __DIR__.'/../modules/media/services/MediaReviewBatchService.php';
foreach([$profile,$second,$historical] as $id){$up=candidateFixture('png',240,160);try{(new Media\Services\ProfilePhotoReviewCandidateService($target,mxmed_private_media_storage()))->upload($id,$up);}finally{unlink($up['tmp_name']);}$target->prepare('UPDATE media_review_batches SET last_activity_at=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL 31 MINUTE) WHERE owner_id=?')->execute([$id]);}
(new Media\Services\MediaReviewBatchService($target))->submitInactive();
$result=['profiles'=>[$profile,$second,$historical],'owner_only_denials'=>$denied,'third_position_denied'=>$quota,'fixture_source'=>'canonical OP01 service; exact synthetic rows and audit hashes transferred within owned server','owner_overview'=>$stack['service']->overview($owner['token'],$profile)];
file_put_contents($owned.'/support-fixture.json',json_encode($result,JSON_THROW_ON_ERROR));echo json_encode(['ok'=>true,'profiles'=>$result['profiles'],'owner_only_denials'=>$denied,'third_position_denied'=>$quota]);
