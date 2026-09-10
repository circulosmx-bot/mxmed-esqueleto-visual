<?php
declare(strict_types=1);
// Disposable MR12A database only. No productive configuration is loaded.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Services\{InternalGovernanceService,InternalCapabilityCatalog,SessionService,SessionTokenCodec,InternalOperatorAuthority};
use Identity\Repositories\{InternalOperatorGrantRepository,IdentityAccountRepository,AccountCredentialRepository};
use Identity\Adapters\{InMemorySessionStoreAdapter,PdoSessionAccountStateAdapter};
use Identity\Contracts\{SystemClock,SessionPolicy,AuthenticationPrincipalCandidate};
use Identity\Http\CanonicalHttpSessionResolver;
function iwCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
$p=new PDO('mysql:host=127.0.0.1;port=3309;dbname=mxmed;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach(['2026_07_19_01_create_auth_accounts.sql','2026_07_20_04_create_auth_account_credentials.sql','2026_09_08_07_create_internal_operator_grants.sql'] as $file)$p->exec(file_get_contents(__DIR__.'/../db/migrations/'.$file));
$migration=file_get_contents(__DIR__.'/../db/migrations/2026_09_10_01_internal_governance.sql');$p->exec($migration);iwCheck((int)$p->query('SELECT COUNT(*) FROM internal_staff')->fetchColumn()===0,'fresh_no_real_bootstrap');
$p->exec('DROP TABLE internal_capability_delegations');$p->exec('DROP TABLE internal_staff');
$credentials=new AccountCredentialRepository($p);$password=password_hash('Synthetic-IW01-only',PASSWORD_DEFAULT);
foreach(['director','master','master2','advisor','new','new2','legacy','revoked','orphan'] as $name){$id='iw01_'.$name;$p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',CURRENT_TIMESTAMP)")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);$credentials->create($id,$password,gmdate('Y-m-d H:i:s'));}
foreach(['legacy','revoked'] as $name)$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status,revoked_at) VALUES(UUID(),'iw01_$name','media_review_read','".($name==='legacy'?'ACTIVE':'REVOKED')."',".($name==='legacy'?'NULL':'CURRENT_TIMESTAMP').")");
$originalGrants=$p->query('SELECT * FROM internal_operator_grants ORDER BY grant_id')->fetchAll();$p->exec($migration);iwCheck($originalGrants===$p->query('SELECT * FROM internal_operator_grants ORDER BY grant_id')->fetchAll(),'backfill_grants_unchanged');
$grants=new InternalOperatorGrantRepository($p);iwCheck($grants->activeCapabilities('iw01_legacy')->contains('media_review_read'),'legacy_backfilled_preserved');iwCheck($grants->activeCapabilities('iw01_revoked')->isEmpty(),'revoked_backfill_not_reactivated');
$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'iw01_orphan','media_review_read','ACTIVE')");iwCheck($grants->activeCapabilities('iw01_orphan')->isEmpty(),'missing_staff_fail_closed');
// This assignment exists only in this synthetic fixture. No productive bootstrap service.
$p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES('iw01_director','DIRECTOR','ACTIVE')");
try{$p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES('absent','ADVISOR','ACTIVE')");throw new LogicException('foreign_key');}catch(PDOException){iwCheck(true,'canonical_account_fk');}
$sessions=new SessionService(new InMemorySessionStoreAdapter(),new SessionTokenCodec('iw01-synthetic-session-pepper'),new SystemClock(),new SessionPolicy(),new PdoSessionAccountStateAdapter(new IdentityAccountRepository($p),$credentials));$cookies=[];
foreach(['director','master','master2','advisor','new','new2','legacy','revoked','orphan'] as $name){$created=$sessions->create(new AuthenticationPrincipalCandidate('iw01_'.$name,1,'active',gmdate('Y-m-d H:i:s')));$cookies[$name]=['__Host-mxmed_session'=>$created->token()->value()];}
$resolver=new CanonicalHttpSessionResolver($sessions);$service=new InternalGovernanceService($p,$resolver);
$run=fn($actor,$op,$target,$cap=null)=>$service->execute($cookies[$actor],$op,'iw01_'.$target,$cap);
$state=function()use($p){$result=[];foreach(['internal_staff','internal_operator_grants','internal_capability_delegations','platform_audit_events','platform_audit_stream_heads'] as $table)$result[$table]=$p->query('SELECT * FROM '.$table)->fetchAll();return $result;};
$deny=function(Closure $fn,string $label)use($state){$before=$state();try{$fn();throw new LogicException('unexpected_authorization '.$label);}catch(RuntimeException){iwCheck($state()===$before,$label);}};
$manage=InternalCapabilityCatalog::MANAGE_ADVISORS;$read='media_review_read';$correct='media_review_corrected_upload';
$run('director','register','advisor');$run('director','register','master');$run('director','appoint_master','master');$run('director','appoint_master','master2');
$deny(fn()=>$run('master','register','new'),'master_without_manage_denied');$run('director','grant','master',$manage);$run('master','register','new');iwCheck(true,'authorized_master_register');
foreach(['suspend','reactivate'] as $op){$run('master',$op,'new');$run('director',$op,'advisor');$run('director',$op,'master2');}iwCheck(true,'staff_lifecycle_director_master');
foreach([['advisor','grant','advisor',$read],['advisor','grant','new',$read],['master','grant','master',$read],['master','delegate','master',$read],['master','appoint_master','new',null],['master','appoint_director','master',null],['master','remove_master','master2',null],['master','suspend','director',null],['master','suspend','master2',null],['master','grant','advisor',$correct]] as [$a,$op,$t,$cap])$deny(fn()=>$run($a,$op,$t,$cap),'negative_'.$a.'_'.$op.'_'.$t);
$run('director','grant','master',$read);$deny(fn()=>$run('master','grant','advisor',$read),'use_does_not_delegate');$run('director','revoke','master',$read);
$run('director','delegate','master',$read);iwCheck(!$grants->activeCapabilities('iw01_master')->contains($read),'delegation_does_not_grant_use');$run('master','grant','advisor',$read);$run('master','revoke','advisor',$read);$run('master','grant','advisor',$read);
$run('director','revoke_delegation','master',$read);$deny(fn()=>$run('master','revoke','advisor',$read),'revoked_delegation_effective');$run('director','delegate','master',$read);
$run('director','grant','advisor',$correct);$run('director','revoke','advisor',$correct);
// Existing canonical memberships remain intact during internal suspension.
$p->exec(file_get_contents(__DIR__.'/../../agenda/db/medical_groups_schema.sql'));$p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_07_19_03_create_auth_account_memberships.sql'));
$p->exec("INSERT INTO profiles_doctors(doctor_id,display_name) VALUES('iw01_profile','Synthetic IW01')");$p->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES('iw01_membership','iw01_advisor','iw01_profile','owner','profile','active')");$memberships=$p->query('SELECT * FROM auth_account_memberships')->fetchAll();
$run('director','suspend','advisor');iwCheck($grants->activeCapabilities('iw01_advisor')->isEmpty(),'suspended_advisor_denied');iwCheck($resolver->resolve($cookies['advisor'])!==null,'global_session_preserved');iwCheck($memberships===$p->query('SELECT * FROM auth_account_memberships')->fetchAll(),'memberships_preserved');
$authority=new InternalOperatorAuthority($resolver,$grants);iwCheck($authority->resolve($cookies['advisor'],'read','media_review_submission','R0')===null,'generic_authority_suspension');
$p->exec($migration);iwCheck($grants->activeCapabilities('iw01_advisor')->isEmpty(),'rerun_never_reactivates_staff');$run('director','reactivate','advisor');iwCheck($grants->activeCapabilities('iw01_advisor')->contains($read)&&!$grants->activeCapabilities('iw01_advisor')->contains($correct),'reactivation_only_active_grants');
$run('director','suspend','master');$deny(fn()=>$run('master','register','new2'),'suspended_master_denied');$run('director','reactivate','master');$run('master','register','new2');
foreach(['*','all','media_review_correct','call_center','invented_capability'] as $cap)$deny(fn()=>$run('director','grant','advisor',$cap),'unknown_catalog_'.$cap);
$run('director','remove_master','master');iwCheck(!$grants->activeCapabilities('iw01_master')->contains($manage),'demotion_revokes_management');$run('director','appoint_master','master');$run('director','grant','master',$manage);$deny(fn()=>$run('master','grant','new2',$read),'demotion_revokes_delegations');
$deny(fn()=>$run('director','suspend','director'),'director_protected');iwCheck($authority->resolve($cookies['director'],'read','media_review_submission','R0')===null,'director_no_product_bypass');$run('director','grant','director',$read);iwCheck($authority->resolve($cookies['director'],'read','media_review_submission','R0')!==null,'director_explicit_use_only');
$events=$p->query("SELECT * FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED'")->fetchAll();iwCheck(count($events)>20,'canonical_audit_events');foreach($events as $e){$m=json_decode($e['metadata_json'],true)['producer_metadata'];iwCheck(str_starts_with($e['real_actor_reference'],'account:iw01_')&&$e['real_actor_reference']===$e['effective_actor_reference']&&$e['resource_reference']===$m['target_account_id'],'audit_actor_target');}
$p->exec("CREATE TRIGGER iw01_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'");try{$deny(fn()=>$run('director','grant','advisor',$correct),'audit_failure_atomic_rollback');}finally{$p->exec('DROP TRIGGER iw01_audit_failure');}
foreach(array_keys(InternalCapabilityCatalog::all()) as $cap)iwCheck(!str_contains($cap,'*'),'catalog_no_wildcards');
echo "IW01_GOVERNANCE_E2E=PASS\n";
