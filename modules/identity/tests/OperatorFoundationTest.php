<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../http/CanonicalClinicalIdentityResolver.php';
require_once __DIR__.'/../http/PrivateApplicationAccessResolver.php';
require_once __DIR__.'/support/Op01Harness.php';

use Identity\Operators\DisposableOperatorFoundation;
use Identity\Services\{FailClosedAuthorizationService,ProfileOperatorOwnershipGuard};
use Identity\Repositories\AccountMembershipRepository;
use Identity\Adapters\ExistingCapabilityAuthorityAdapter;
use Identity\Http\{CanonicalDoctorMembershipPolicy,CanonicalClinicalIdentityResolver,PrivateApplicationAccessResolver,IdentityHttpComposition};

// Independent PHP processes, no inherited PDO connections. Workers communicate
// only synthetic fixtures over stdin; stdout contains no cookies, email or tokens.
if (($argv[1]??'')==='--worker') {
    try {
        $db=$argv[2]??'';$p=op01pdo($db);$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
        $clock=new Op01Clock(new DateTimeImmutable($input['now'],new DateTimeZone('UTC')));$s=op01stack($p,$clock);$a=op01login($s,$input['actor']);
        echo "READY\n";flush();
        if ($input['action']==='recovery') op01governance($p,$s)->executeOwnerRecovery($a['context'],$input['case']);
        else op01command($s,$a,$input['profile'],$input['action'],$input['input'],$input['command']);
        echo "OK\n";exit(0);
    } catch (Throwable $e) {echo "DENIED\n";exit(2);}
}

function concurrent(PDO $p,string $db,string $profile,array $s,array $tasks): array {
    $workers=[];$p->beginTransaction();$p->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE')->execute([$profile]);
    try {
        foreach ($tasks as $i=>$task) {
            $process=proc_open([PHP_BINARY,__FILE__,'--worker',$db],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if (!is_resource($process)) throw new RuntimeException('worker_start_failed');
            fwrite($pipes[0],json_encode($task+['profile'=>$profile,'command'=>'race_command_'.$i,'now'=>$s['clock']->now()->format('Y-m-d\TH:i:s.uP')],JSON_THROW_ON_ERROR));fclose($pipes[0]);
            $workers[]=[$process,$pipes];
        }
        foreach ($workers as [$process,$pipes]) {
            stream_set_timeout($pipes[1],20);
            if (trim((string)fgets($pipes[1]))!=='READY') throw new RuntimeException('worker_not_ready');
        }
        $p->commit();$out=[];
        foreach ($workers as [$process,$pipes]) {
            $out[]=trim(stream_get_contents($pipes[1]));$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);
            if ($error!=='') throw new RuntimeException('worker_runtime_error');
        }
        return $out;
    } finally {
        if($p->inTransaction())$p->rollBack();
        foreach ($workers as [$process,$pipes]) if(is_resource($process))proc_terminate($process);
    }
}
function liveFingerprint(PDO $root,bool $qa): string {
    $schemas=$root->query("SELECT schema_name FROM information_schema.schemata WHERE ".($qa?"schema_name LIKE '%qa05%'":"schema_name='mxmed'")." ORDER BY schema_name")->fetchAll(PDO::FETCH_COLUMN);
    $checks=[];
    foreach ($schemas as $schema) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$schema))throw new RuntimeException('unsafe_snapshot_schema');
        $stmt=$root->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_type='BASE TABLE' ORDER BY table_name");$stmt->execute([$schema]);
        $tables=$stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            if(!preg_match('/^[A-Za-z0-9_]+$/D',$t))throw new RuntimeException('unsafe_snapshot_table');
            $checks[$schema.'.'.$t]=$root->query("CHECKSUM TABLE `$schema`.`$t`")->fetchAll();
        }
    }
    return hash('sha256',json_encode($checks,JSON_THROW_ON_ERROR));
}
function testIdentity(PDO $p,array $s): IdentityHttpComposition {
    // Only the already synthetic adapters are assembled; no process environment,
    // shared Valkey, credentials, cookies or real database is imported.
    $c=(new ReflectionClass(IdentityHttpComposition::class))->newInstanceWithoutConstructor();
    foreach (['pdo'=>$p,'sessions'=>$s['sessions'],'memberships'=>new AccountMembershipRepository($p),
        'authorization'=>new FailClosedAuthorizationService(new AccountMembershipRepository($p),new ExistingCapabilityAuthorityAdapter(new \Subscriptions\Services\ExistingCapabilityAuthorityService()))] as $key=>$v)
        (new ReflectionProperty($c,$key))->setValue($c,$v);
    return $c;
}

$root=op01pdo();$normalBefore=liveFingerprint($root,false);$qaBefore=liveFingerprint($root,true);
$db='mxmed_op01_'.bin2hex(random_bytes(5));$root->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$results=[];
function passed(int $n,string $detail): void {global $results;$results[$n]=$detail;op01check(true,sprintf('OP01_%02d_%s',$n,$detail));}
try {
    $p=op01pdo($db);$base=dirname(__DIR__);
    foreach (['2026_07_19_01_create_auth_accounts.sql','2026_07_20_04_create_auth_account_credentials.sql','2026_07_20_06_create_auth_rate_limit_buckets.sql','2026_09_08_07_create_internal_operator_grants.sql','2026_09_10_01_internal_governance.sql'] as $f)op01migrate($p,$base.'/db/migrations/'.$f);
    op01migrate($p,dirname($base).'/profiles/db/profiles_doctors_schema.sql');$p->exec('CREATE TABLE medical_groups(group_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');
    foreach (['2026_07_19_03_create_auth_account_memberships.sql','2026_10_14_01_create_profile_ownership_governance.sql','2026_10_14_02_profile_ownership_r3_endorsements.sql','2026_10_15_01_identity_governance_r3_runtime.sql','2026_10_15_02_profile_operator_foundation.sql'] as $f)op01migrate($p,$base.'/db/migrations/'.$f);
    op01migrate($p,$base.'/db/migrations/2026_10_15_02_profile_operator_foundation.sql');
    op01migrate($p,$base.'/db/migrations/2026_10_15_04_operator_presentations.sql');
    op01migrate($p,dirname($base).'/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql');
    // Physical audit.v1 shape: same joined canonical writer, local SQL transaction
    // port, no calls to the production routines qualified to the mxmed schema.
    $p->exec('CREATE TABLE platform_audit_stream_heads(stream_key VARCHAR(191) PRIMARY KEY,last_sequence_number BIGINT NOT NULL,last_event_hash CHAR(64) NOT NULL,hash_version VARCHAR(32),updated_at DATETIME(6)) ENGINE=InnoDB');
    $p->exec('CREATE TABLE platform_audit_events(stream_key VARCHAR(191),sequence_number BIGINT,event_id CHAR(64),schema_version VARCHAR(32),occurred_at_utc DATETIME(6),action VARCHAR(128),risk_level VARCHAR(2),outcome VARCHAR(16),reason_code VARCHAR(128),real_actor_reference VARCHAR(191),effective_actor_reference VARCHAR(191),affected_subject_reference VARCHAR(191),correlation_id VARCHAR(191),request_id VARCHAR(191),case_reference VARCHAR(191),resource_type VARCHAR(128),resource_reference VARCHAR(191),metadata_json JSON,previous_hash CHAR(64),event_hash CHAR(64),created_at_utc DATETIME(6),PRIMARY KEY(stream_key,sequence_number),UNIQUE(event_id),UNIQUE(event_hash)) ENGINE=InnoDB');
    $passwordHash=password_hash('Only-Synthetic-Op01!',PASSWORD_ARGON2ID);
    foreach (['owner_a','owner_b','invitee_a','invitee_b','invitee_c','outsider','shell_operator','approver_a','approver_b','requester'] as $id) {
        $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
        $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,$passwordHash]);
    }
    foreach (['approver_a','approver_b','requester'] as $id) {
        $p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,'MASTER_ADMIN','ACTIVE')")->execute([$id]);
        $p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,'profile_initial_ownership_approve','ACTIVE')")->execute([$id]);
    }
    $s=op01stack($p);$owner=op01login($s,'owner_a');$a=op01login($s,'invitee_a');$b=op01login($s,'invitee_b');$c=op01login($s,'invitee_c');$other=op01login($s,'outsider');
    $credentialsBefore=op01credentialFingerprint($p);
    $accountsBefore=(int)op01scalar($p,'SELECT COUNT(*) FROM auth_accounts');
    require_once __DIR__.'/support/Op07PresentationAssertions.php';
    op07PresentationAssertions($p,$root,$db,$s,$owner,$a,$b,$c);
    // All role combinations, with identical and differing closed grants.
    foreach ([['collaborator','collaborator'],['administrator','collaborator'],['collaborator','administrator'],['administrator','administrator']] as $n=>[$r1,$r2]) {
        $pr=op01fresh($p,'roles'.$n);$i1=op01invite($s,$owner,$pr,'invitee_a',['agenda.appointments.read'],$r1);$m1=op01accept($s,$a,$pr,$i1);
        $i2=op01invite($s,$owner,$pr,'invitee_b',['agenda.appointments.read'],$r2);$m2=op01accept($s,$b,$pr,$i2);
        op01check($s['service']->permits($a['token'],$pr,$pr,'agenda.appointments.read')&&$s['service']->permits($b['token'],$pr,$pr,'agenda.appointments.read'),'role_combination_'.$n);
        op01check(op01denied(fn()=>op01invite($s,$owner,$pr,'invitee_c')),'third_denied_role_combination_'.$n);
    }
    passed(1,'owner_plus_two_all_role_combinations');passed(2,'third_denied');passed(3,'administrator_counts');passed(4,'identical_collaborator_grants');
    $pr=op01fresh($p,'different');$ia=op01invite($s,$owner,$pr,'invitee_a',['agenda.appointments.create']);$ma=op01accept($s,$a,$pr,$ia);$ib=op01invite($s,$owner,$pr,'invitee_b',['agenda.appointments.cancel']);$mb=op01accept($s,$b,$pr,$ib);
    $view=$s['service']->overview($owner['token'],$pr);
    foreach($view['members'] as $member){
        op01check($member['contact_verified']===true&&$member['account_status']==='active','ux06_current_contact_facts');
        op01check($member['invited_at']!==null&&$member['accepted_at']!==null,'ux06_persisted_invitation_acceptance_dates');
        op01check(!array_key_exists('email_normalized',$member)&&!array_key_exists('email_verified_at',$member)&&!array_key_exists('last_access',$member),'ux06_minimal_projection_no_guessed_access');
    }
    op01check(op01denied(fn()=>$s['service']->overview($a['token'],$pr)),'ux06_profile_reader_owner_only');
    $p->exec("UPDATE auth_accounts SET status='disabled' WHERE account_id='invitee_a'");
    $view=$s['service']->overview($owner['token'],$pr);
    $disabled=array_values(array_filter($view['members'],fn($m)=>$m['account_id']==='invitee_a'))[0];
    op01check($disabled['account_status']==='disabled'&&$disabled['effective_capabilities']===[],'ux06_disabled_account_not_displayed_as_effective');
    $p->exec("UPDATE auth_accounts SET status='active' WHERE account_id='invitee_a'");
    op01check($s['service']->permits($a['token'],$pr,$pr,'agenda.appointments.create')&&!$s['service']->permits($a['token'],$pr,$pr,'agenda.appointments.cancel')&&$s['service']->permits($b['token'],$pr,$pr,'agenda.appointments.cancel')&&!$s['service']->permits($b['token'],$pr,$pr,'agenda.appointments.create'),'different_permissions');passed(5,'different_grants');
    $pending=op01fresh($p,'pending');$ip=op01invite($s,$owner,$pending,'invitee_a');op01invite($s,$owner,$pending,'invitee_b');op01check(op01denied(fn()=>op01invite($s,$owner,$pending,'invitee_c')),'pending_reservation');passed(6,'pending_reserves');
    $view=$s['service']->overview($owner['token'],$pending);
    op01check($view['occupied']===0&&$view['reserved']===2&&$view['available']===0,'ux06_pending_seats_are_reserved_not_accepted');
    foreach($view['invitations'] as $invitation)op01check($invitation['status']==='pending'&&$invitation['created_at']!==null&&$invitation['expires_at']!==null,'ux06_pending_dates_from_server');
    op01command($s,$owner,$pr,'suspend',['membership_id'=>$ma['membership_id'],'expected_revision'=>1]);
    op01check(!$s['service']->permits($a['token'],$pr,$pr,'agenda.appointments.create')&&op01denied(fn()=>op01invite($s,$owner,$pr,'invitee_c')),'suspended_keeps_seat');passed(7,'suspension_retains');
    op01command($s,$owner,$pr,'reactivate',['membership_id'=>$ma['membership_id'],'expected_revision'=>1]);op01check($s['service']->permits($a['token'],$pr,$pr,'agenda.appointments.create'),'reactivation_current_owner');
    op01command($s,$owner,$pr,'revoke',['membership_id'=>$ma['membership_id'],'expected_revision'=>1]);op01invite($s,$owner,$pr,'invitee_c');op01check(!$s['service']->permits($a['token'],$pr,$pr,'agenda.appointments.create'),'revocation_removes_authority');passed(8,'revocation_frees');
    $expired=op01fresh($p,'expired');$ie=op01invite($s,$owner,$expired,'invitee_a');op01invite($s,$owner,$expired,'invitee_b');
    $future=op01stack($p,new Op01Clock($s['clock']->now()->modify('+7 days')));$fo=op01login($future,'owner_a');$fa=op01login($future,'invitee_a');
    op01check(op01denied(fn()=>op01accept($future,$fa,$expired,$ie)),'expired_cannot_accept');op01invite($future,$fo,$expired,'invitee_c');
    op01check((int)op01scalar($p,"SELECT COUNT(*) FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='expired'",[$expired])===2,'expiry_materialized');passed(9,'expired_frees');
    op01command($s,$owner,$pending,'cancel',['invitation_id'=>$ip['invitation_id']]);op01invite($s,$owner,$pending,'invitee_c');passed(10,'cancelled_frees');
    $race=op01fresh($p,'raceinvites');$tasks=[];foreach (['invitee_a','invitee_b','invitee_c'] as $id)$tasks[]=['actor'=>'owner_a','action'=>'invite','input'=>['email'=>$id.'@example.invalid','capabilities'=>['agenda.appointments.read']]];
    $out=concurrent($p,$db,$race,$s,$tasks);op01check(count(array_filter($out,fn($x)=>$x==='OK'))===2&&(int)op01scalar($p,"SELECT COUNT(*) FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='pending'",[$race])===2,'physical_concurrent_invites');passed(11,'independent_process_invite_race');
    $race=op01fresh($p,'raceaccept');$ra=op01invite($s,$owner,$race,'invitee_a');$rb=op01invite($s,$owner,$race,'invitee_b');$tasks=[];
    foreach ([['invitee_a',$ra],['invitee_a',$ra],['invitee_b',$rb],['invitee_b',$rb]] as [$id,$i])$tasks[]=['actor'=>$id,'action'=>'accept','input'=>['invitation_id'=>$i['invitation_id'],'token'=>$i['token'],'offer_revision'=>1]];
    $out=concurrent($p,$db,$race,$s,$tasks);op01check(count(array_filter($out,fn($x)=>$x==='OK'))===2&&(int)op01scalar($p,"SELECT COUNT(DISTINCT account_id) FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code<>'owner' AND status='active'",[$race])===2,'physical_concurrent_accept');passed(12,'independent_process_acceptance_race');
    op01check(op01denied(fn()=>op01accept($s,$a,$race,$ra)),'single_use');passed(13,'single_use');
    op01check((int)op01scalar($p,'SELECT TIMESTAMPDIFF(SECOND,created_at,expires_at) FROM auth_profile_operator_invitations WHERE invitation_id=?',[$ra['invitation_id']])===604800,'seven_days');passed(14,'server_seven_days');
    $offer=op01fresh($p,'offer');$old=op01invite($s,$owner,$offer,'invitee_a');
    $new=op01command($s,$owner,$offer,'reissue',['invitation_id'=>$old['invitation_id'],'email'=>'invitee_a@example.invalid','capabilities'=>['agenda.appointments.read','agenda.appointments.create']]);
    op01check(op01denied(fn()=>op01accept($s,$a,$offer,$old)),'old_offer_invalidated');$raw=explode('.',$s['notifications']->lastMessage()->token(),2)[1];
    $new+=['token'=>$raw];op01accept($s,$a,$offer,$new);op01check($s['service']->permits($a['token'],$offer,$offer,'agenda.appointments.create'),'new_offer_explicit_acceptance');passed(15,'reissued_immutable_offer');
    $verify=op01fresh($p,'verified');$iv=op01invite($s,$owner,$verify,'invitee_a');
    op01check(op01denied(fn()=>op01accept($s,$other,$verify,$iv)),'other_email_denied');$p->exec("UPDATE auth_accounts SET email_verified_at=NULL WHERE account_id='invitee_a'");
    op01check(op01denied(fn()=>op01accept($s,$a,$verify,$iv)),'unverified_email_denied');$p->exec("UPDATE auth_accounts SET email_verified_at=UTC_TIMESTAMP() WHERE account_id='invitee_a'");op01accept($s,$a,$verify,$iv);passed(16,'verified_exact_account_reused');
    foreach ([$a,$b,$other] as $actor)op01check(op01denied(fn()=>op01invite($s,$actor,$verify,'invitee_c')),'owner_exclusive');passed(17,'owner_only');
    op01check(op01denied(fn()=>op01command($s,$b,$pr,'grants',['membership_id'=>$mb['membership_id'],'expected_revision'=>1,'capabilities'=>['agenda.appointments.create']])),'selfgrant_denied');passed(18,'selfgrant_denied');
    op01check(op01denied(fn()=>op01invite($s,$b,$pr,'invitee_a')),'operator_cannot_invite');passed(19,'operator_cannot_invite');
    // The actual GOV03A transaction consumes two independent endorsements.
    $go=op01fresh($p,'ownerchange');$replace=op01accept($s,$a,$go,op01invite($s,$owner,$go,'invitee_a'));$pendingOld=op01invite($s,$owner,$go,'invitee_b');$requester=op01login($s,'requester');
    $case=op01approvedRecovery($p,$s,$requester,$go,$replace['membership_id']);
    op01check(op01denied(fn()=>op01governance($p,$s,false)->executeOwnerRecovery($requester['context'],$case)),'missing_hook_fails_closed');
    $s['tx']->failInsert=true;op01check(op01denied(fn()=>op01governance($p,$s)->executeOwnerRecovery($requester['context'],$case)),'owner_audit_failure_rolls_back');$s['tx']->failInsert=false;
    op01check((string)op01scalar($p,"SELECT status FROM auth_profile_operator_invitations WHERE invitation_id=?",[$pendingOld['invitation_id']])==='pending'&&(string)op01scalar($p,"SELECT role_code FROM auth_account_memberships WHERE membership_id=?",[$replace['membership_id']])==='collaborator','owner_failure_preserves_all');
    // Race: acceptance either commits before owner change and requires review,
    // or follows it and is denied. It can never retain effective delegation.
    $tasks=[['actor'=>'invitee_b','action'=>'accept','input'=>['invitation_id'=>$pendingOld['invitation_id'],'token'=>$pendingOld['token'],'offer_revision'=>1]],['actor'=>'requester','action'=>'recovery','case'=>$case]];
    $out=concurrent($p,$db,$go,$s,$tasks);op01check($out[1]==='OK','governed_owner_commit_race');
    op01check((int)op01scalar($p,"SELECT COUNT(*) FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='pending'",[$go])===0,'former_pending_cancelled_or_already_accepted');passed(20,'governed_owner_commit_and_race');
    op01check((int)op01scalar($p,"SELECT COUNT(*) FROM auth_profile_operator_grant_revisions r JOIN auth_account_memberships m ON m.membership_id=r.membership_id WHERE m.profile_doctor_id=? AND r.status='active'",[$go])===0,'owner_grants_review_required');passed(21,'new_owner_review_required');
    op01check(!$s['service']->permits($b['token'],$go,$go,'agenda.appointments.read')&&!$s['service']->permits($a['token'],$go,$go,'agenda.appointments.read'),'owner_changed_no_inheritance');passed(22,'no_inherited_effective_grants');
    // Independent deterministic pending cancellation (not dependent on which race won).
    $go2=op01fresh($p,'ownerpending');$replacement2=op01accept($s,$a,$go2,op01invite($s,$owner,$go2,'invitee_a'));$unused=op01invite($s,$owner,$go2,'invitee_c');
    $case2=op01approvedRecovery($p,$s,$requester,$go2,$replacement2['membership_id']);op01governance($p,$s)->executeOwnerRecovery($requester['context'],$case2);
    op01check((string)op01scalar($p,'SELECT status FROM auth_profile_operator_invitations WHERE invitation_id=?',[$unused['invitation_id']])==='cancelled','pending_cancelled_on_owner_commit');
    $former=op01scalar($p,"SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND account_id='owner_a'",[$go2]);
    op01check(op01denied(fn()=>op01invite($s,$owner,$go2,'invitee_b')),'former_owner_no_appointment');
    // A reviewed operator remains suspended until an explicit reactivation.
    $plan=op01fresh($p,'plan');$mp=op01accept($s,$b,$plan,op01invite($s,$owner,$plan,'invitee_b'));
    foreach (['free'=>false,'basic'=>false,'standard'=>true,'optimum'=>true,'professional'=>true] as $code=>$expected) {
        $p->prepare('UPDATE profile_subscriptions SET plan_code=?,contracted_plan_code=? WHERE entity_id=?')->execute([$code,$code,$plan]);
        op01check($s['service']->permits($b['token'],$plan,$plan,'agenda.appointments.read')===$expected,'entitlement_'.$code);
    }
    op01invite($s,$owner,$plan,'invitee_a');op01check(op01denied(fn()=>op01invite($s,$owner,$plan,'invitee_c')),'professional_no_extra_seat');passed(23,'entitlement_not_seats');
    foreach (['free','professional'] as $code) {
        $p->prepare('UPDATE profile_subscriptions SET plan_code=?,contracted_plan_code=? WHERE entity_id=?')->execute([$code,$code,$pr]);
        op01check(!$s['service']->permits($a['token'],$pr,$pr,'agenda.appointments.create'),'entitlement_does_not_revive_revoked_membership_'.$code);
    }

    // Legacy rows/flags do not grant access or enter the canonical quota source.
    op01migrate($p,dirname($base).'/agenda/db/operators_phase1.sql');
    $p->prepare("INSERT INTO agenda_operators(operator_id,doctor_id,alias,alias_normalized,full_name,login,login_normalized,status) VALUES('legacy_operator',?,'legacy','legacy','Synthetic legacy','legacy','legacy','active')")->execute([$plan]);
    $p->prepare("INSERT INTO agenda_operator_permissions(operator_id,doctor_id,permission_key) VALUES('legacy_operator',?,'patients')")->execute([$plan]);
    op01check(op01denied(fn()=>op01invite($s,$owner,$plan,'invitee_c'))&&!$s['service']->permits($other['token'],$plan,$plan,'agenda.appointments.read'),'legacy_max3_ignored');
    // A verified contact change must not merge an occupied seat with a reservation.
    $emailQuota=op01fresh($p,'emailquota');op01accept($s,$a,$emailQuota,op01invite($s,$owner,$emailQuota,'invitee_a'));
    op01invite($s,$owner,$emailQuota,'not_registered');
    $p->exec("UPDATE auth_accounts SET email_normalized='not_registered@example.invalid',email_address='not_registered@example.invalid' WHERE account_id='invitee_a'");
    op01check(op01denied(fn()=>op01invite($s,$owner,$emailQuota,'invitee_b')),'email_change_cannot_merge_two_seats');
    $p->exec("UPDATE auth_accounts SET email_normalized='invitee_a@example.invalid',email_address='invitee_a@example.invalid' WHERE account_id='invitee_a'");
    // Two rows for the same human count once and cannot gain effective authority.
    $dupe=op01fresh($p,'dupe');$md=op01accept($s,$a,$dupe,op01invite($s,$owner,$dupe,'invitee_a'));
    $p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES('duplicate_role','invitee_a',?,'administrator','profile','active')")->execute([$dupe]);
    op01invite($s,$owner,$dupe,'invitee_b');op01check(op01denied(fn()=>op01invite($s,$owner,$dupe,'invitee_c'))&&!$s['service']->permits($a['token'],$dupe,$dupe,'agenda.appointments.read'),'duplicate_roles_no_bypass');passed(24,'legacy_and_duplicate_role_no_bypass');
    op01check($accountsBefore===(int)op01scalar($p,'SELECT COUNT(*) FROM auth_accounts')&&$credentialsBefore===op01credentialFingerprint($p)&&(int)op01scalar($p,'SELECT COUNT(*) FROM agenda_operators WHERE temp_password_hash IS NOT NULL')===0,'no_duplicate_credentials');passed(25,'canonical_credentials_unchanged');
    op01command($s,$owner,$plan,'grants',['membership_id'=>$mp['membership_id'],'expected_revision'=>1,'capabilities'=>[]]);op01check(!$s['service']->permits($b['token'],$plan,$plan,'agenda.appointments.read'),'revoked_grant_next_request');passed(26,'revoked_grants_deny');
    op01check(!$s['service']->permits($b['token'],$pr,$verify,'agenda.appointments.cancel')&&op01denied(fn()=>op01command($s,$owner,$verify,'suspend',['membership_id'=>$mb['membership_id'],'expected_revision'=>1])),'cross_profile_denied');passed(27,'cross_profile_denied');
    $repo=new AccountMembershipRepository($p);$authz=new FailClosedAuthorizationService($repo,new ExistingCapabilityAuthorityAdapter(new \Subscriptions\Services\ExistingCapabilityAuthorityService()));$rows=$repo->activeForAccount('invitee_b');
    foreach (['patients','clinical_record','prescriptions','billing','staff','profile_identity','agenda_appointments'] as $cap)
        op01check(!$authz->authorize($b['context'],'profile_doctor',$pr,$cap,['plan_code'=>'professional','status'=>'active','is_active'=>true])->allowed(),'broad_authority_denied_'.$cap);
    foreach (['patients','clinical_record','prescriptions','prescriptions.sign','prescriptions.emit','credentials.edit','staff.manage','billing.issuance','subscriptions.renew','agenda.settings','agenda.*','*'] as $cap)
        op01check(!$s['service']->permits($b['token'],$pr,$pr,$cap),'closed_catalog_denies_'.$cap);
    $shellProfile=op01fresh($p,'shell');$shellActor=op01login($s,'shell_operator');
    op01accept($s,$shellActor,$shellProfile,op01invite($s,$owner,$shellProfile,'shell_operator',['agenda.appointments.read'],'administrator'));
    $identity=testIdentity($p,$s);$cookies=['__Host-mxmed_session'=>$shellActor['token']];
    foreach (['patients','clinical_record','prescriptions'] as $cap)op01check((new CanonicalClinicalIdentityResolver($identity))->resolve($cookies,$cap)->reason==='nonmedical_operator_requires_granular_consumer','actual_clinical_resolver_denial_'.$cap);
    passed(28,'canonical_clinical_denied_no_real_activation');
    $shell=(new PrivateApplicationAccessResolver($identity))->resolve($cookies);op01check(!$shell->allowed&&$shell->httpStatus===403&&$shell->context===null,'actual_private_shell_denied');
    // Single-member provenance denial too (multi-profile ambiguity alone is insufficient).
    $only=$rows[0];foreach (['owner','administrator','collaborator'] as $role) {
        $only['role_code']=$role;
        op01check((new CanonicalDoctorMembershipPolicy())->select([$only])['reason']==='nonmedical_operator_requires_granular_consumer','provenance_denies_role_'.$role);
    }passed(29,'canonical_shell_no_hydration');
    $audit=$p->query("SELECT real_actor_reference,correlation_id,metadata_json,event_hash,previous_hash,sequence_number,stream_key FROM platform_audit_events ORDER BY stream_key,sequence_number")->fetchAll();$last=[];
    foreach($audit as $event){$meta=json_decode($event['metadata_json'],true,512,JSON_THROW_ON_ERROR);$producer=$meta['producer_metadata'];if(!isset($producer['operator_action'],$producer['profile_doctor_id'],$producer['owner_membership_id'],$producer['grant_revision'],$producer['operation_reason'])||$meta['_audit_v1']['session_id']===null)throw new RuntimeException('audit_attribution_failed');
        if(str_contains($event['metadata_json'],'@example.invalid')||str_contains($event['metadata_json'],$ra['token']))throw new RuntimeException('audit_sensitive_metadata');
        if(isset($last[$event['stream_key']])&&$event['previous_hash']!==$last[$event['stream_key']])throw new RuntimeException('audit_chain_failed');$last[$event['stream_key']]=$event['event_hash'];}
    op01check((int)op01scalar($p,"SELECT COUNT(*) FROM platform_audit_events WHERE real_actor_reference='account:invitee_a' AND action='INVITATION_ACCEPTED'")>0,'invitee_real_actor');passed(30,'canonical_writer_attribution_chain');
    $failure=op01fresh($p,'auditfail');$before=(int)op01scalar($p,'SELECT COUNT(*) FROM auth_profile_operator_invitations');$notes=count($s['notifications']->messages());$s['tx']->failInsert=true;
    op01check(op01denied(fn()=>op01invite($s,$owner,$failure,'invitee_a')),'audit_insert_failure_denies');$s['tx']->failInsert=false;
    op01check($before===(int)op01scalar($p,'SELECT COUNT(*) FROM auth_profile_operator_invitations')&&$notes===count($s['notifications']->messages()),'audit_failure_rollback_no_notification');passed(31,'critical_audit_rolls_back');
    $s['tx']->failInsert=true;op01check(op01denied(fn()=>op01command($s,$owner,$plan,'revoke',['membership_id'=>$mp['membership_id'],'expected_revision'=>2])),'revoke_audit_failure');$s['tx']->failInsert=false;
    op01check((string)op01scalar($p,'SELECT status FROM auth_account_memberships WHERE membership_id=?',[$mp['membership_id']])==='active'&&op01denied(fn()=>op01invite($s,$owner,$plan,'invitee_c')),'failed_revocation_does_not_free_seat');

    $input=['email'=>'invitee_a@example.invalid','capabilities'=>[],'reason'=>'OWNER_REQUEST'];
    foreach ([['', $owner['csrf'],'https://op01.example.invalid'],[$owner['token'],'bad','https://op01.example.invalid'],[$owner['token'],$owner['csrf'],'https://other.example.invalid'],[$owner['token'],$b['csrf'],'https://op01.example.invalid']] as [$token,$csrf,$origin])
        op01check(op01denied(fn()=>$s['service']->command($token,$csrf,$origin,$failure,'csrf_check_'.bin2hex(random_bytes(4)),'invite',$input)),'session_csrf_origin_denial');
    $revoked=op01login($s,'owner_a');$s['sessions']->revokeOwnSession($revoked['token'],(string)$revoked['context']->session()->sessionId());op01check(op01denied(fn()=>op01command($s,$revoked,$failure,'invite',$input)),'revoked_session');
    $expiredStack=op01stack($p);$expiredOwner=op01login($expiredStack,'owner_a');$expiredStack['clock']->value=$expiredStack['clock']->value->modify('+61 minutes');op01check(op01denied(fn()=>op01command($expiredStack,$expiredOwner,$failure,'invite',$input)),'expired_session');
    $p->exec("UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='outsider'");op01check(op01denied(fn()=>op01command($s,$other,$failure,'invite',$input)),'version_mismatch');$p->exec("UPDATE auth_account_credentials SET credential_version=1 WHERE account_id='outsider'");passed(32,'canonical_session_csrf_origin_expiry');
    $idempotent=op01fresh($p,'idempotent');$request=['email'=>'invitee_a@example.invalid','capabilities'=>['agenda.appointments.read']];$receipt=op01command($s,$owner,$idempotent,'invite',$request,'idempotency_invite');$note=$s['notifications']->lastMessage();$n=count($s['notifications']->messages());
    $replay=op01command($s,$owner,$idempotent,'invite',$request,'idempotency_invite');op01check($receipt['invitation_id']===$replay['invitation_id']&&$replay['replayed']&&count($s['notifications']->messages())===$n,'idempotent_invitation');
    op01check(op01denied(fn()=>op01command($s,$owner,$idempotent,'invite',['email'=>'invitee_b@example.invalid','capabilities'=>[]],'idempotency_invite')),'idempotent_payload_conflict');
    $accept=['invitation_id'=>$receipt['invitation_id'],'token'=>explode('.',$note->token(),2)[1],'offer_revision'=>1];$first=op01command($s,$a,$idempotent,'accept',$accept,'idempotency_accept');$second=op01command($s,$a,$idempotent,'accept',$accept,'idempotency_accept');
    op01check($first['membership_id']===$second['membership_id']&&$second['replayed']&&op01denied(fn()=>op01command($s,$a,$idempotent,'accept',$accept,'different_accept_command')),'idempotent_acceptance_receipt_only');passed(33,'replay_and_idempotency');
    op01command($s,$owner,$idempotent,'revoke',['membership_id'=>$first['membership_id'],'expected_revision'=>1]);
    $receiptOnly=op01command($s,$a,$idempotent,'accept',$accept,'idempotency_accept');
    op01check($receiptOnly['replayed']&&!$s['service']->permits($a['token'],$idempotent,$idempotent,'agenda.appointments.read'),'historical_receipt_does_not_restore_authority');

    op01check(count($s['notifications']->messages())>0&&op01denied(fn()=>op01command($s,$owner,$failure,'invite',['email'=>'someone@real-domain.test','capabilities'=>[]])),'memory_notifications_synthetic_only');
    op01check(op01denied(fn()=>DisposableOperatorFoundation::compose($root,$s['sessions'],$s['csrf'],op01writer($p,$s['tx']),$s['notifications'],$s['clock'])),'no_runtime_composition');
    passed(36,'memory_only_no_external_effects');
    $ambiguousOwner=op01fresh($p,'ambiguousowner');
    $p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES('conflicting_owner','outsider',?,'owner','profile','active')")->execute([$ambiguousOwner]);
    $p->exec("UPDATE auth_accounts SET status='disabled' WHERE account_id='outsider'");
    op01check(op01denied(fn()=>op01invite($s,$owner,$ambiguousOwner,'invitee_a')),'disabled_conflicting_owner_still_ambiguous');
    $p->exec("UPDATE auth_accounts SET status='active' WHERE account_id='outsider'");
    op01check(op01denied(fn()=>op01invite($s,$owner,$failure,'invitee_a',[],'owner')),'cannot_invite_owner_role');

    // Deterministic existing active operator review + suspended seat after owner change.
    $review=op01fresh($p,'review');$newOwner=op01accept($s,$a,$review,op01invite($s,$owner,$review,'invitee_a'));
    $reviewed=op01accept($s,$b,$review,op01invite($s,$owner,$review,'invitee_b'));
    op01command($s,$owner,$review,'suspend',['membership_id'=>$reviewed['membership_id'],'expected_revision'=>1]);
    $reviewCase=op01approvedRecovery($p,$s,$requester,$review,$newOwner['membership_id']);op01governance($p,$s)->executeOwnerRecovery($requester['context'],$reviewCase);
    op01check(!$s['service']->permits($b['token'],$review,$review,'agenda.appointments.read'),'active_operator_needs_owner_review');
    op01check(op01denied(fn()=>op01command($s,$a,$review,'reactivate',['membership_id'=>$reviewed['membership_id'],'expected_revision'=>1])),'reactivation_not_review');
    op01command($s,$a,$review,'review',['membership_id'=>$reviewed['membership_id'],'expected_revision'=>1,'capabilities'=>['agenda.appointments.read']]);
    op01check(!$s['service']->permits($b['token'],$review,$review,'agenda.appointments.read'),'review_does_not_unsuspend');
    op01command($s,$a,$review,'reactivate',['membership_id'=>$reviewed['membership_id'],'expected_revision'=>2]);
    op01check($s['service']->permits($b['token'],$review,$review,'agenda.appointments.read'),'explicit_review_then_reactivation');
    op01command($s,$a,$review,'grants',['membership_id'=>$reviewed['membership_id'],'expected_revision'=>2,'capabilities'=>['agenda.appointments.read'],'valid_until'=>$s['clock']->now()->modify('+1 second')->format('Y-m-d H:i:s.u')]);
    $s['clock']->value=$s['clock']->value->modify('+2 seconds');op01check(!$s['service']->permits($b['token'],$review,$review,'agenda.appointments.read'),'grant_expiration_enforced');
    // Rejection, catalog and exact-offer revision also fail closed.
    $reject=op01fresh($p,'reject');$rejectI=op01invite($s,$owner,$reject,'invitee_c');
    op01check(op01denied(fn()=>op01command($s,$c,$reject,'accept',['invitation_id'=>$rejectI['invitation_id'],'token'=>$rejectI['token'],'offer_revision'=>2])),'offer_revision_conflict');
    op01command($s,$c,$reject,'reject',['invitation_id'=>$rejectI['invitation_id'],'token'=>$rejectI['token'],'offer_revision'=>1]);
    op01check(op01denied(fn()=>op01accept($s,$c,$reject,$rejectI)),'rejected_token_denied');
    op01check(op01denied(fn()=>op01invite($s,$owner,$reject,'invitee_c',['agenda.*'])),'wildcard_grant_denied');
    op01check(op01denied(fn()=>$p->exec("INSERT INTO auth_profile_operator_grants(revision_id,capability) VALUES('missing_revision','clinical_record')")),'physical_grant_integrity');
    op01check(op01denied(fn()=>op01migrate($p,$base.'/db/migrations/2026_10_15_02_rollback_profile_operator_foundation.sql')),'populated_recovery_refused');
    op01check((int)op01scalar($p,'SELECT COUNT(*) FROM auth_profile_operator_invitations')>0,'history_preserved_after_recovery_refusal');
    // Rehearse forward/recovery against an independently empty disposable DB.
    $emptyDb='mxmed_op01_'.bin2hex(random_bytes(5));$root->exec("CREATE DATABASE `$emptyDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    try {
        $ep=op01pdo($emptyDb);op01migrate($ep,$base.'/db/migrations/2026_07_19_01_create_auth_accounts.sql');op01migrate($ep,dirname($base).'/profiles/db/profiles_doctors_schema.sql');
        $ep->exec('CREATE TABLE medical_groups(group_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');op01migrate($ep,$base.'/db/migrations/2026_07_19_03_create_auth_account_memberships.sql');
        op01migrate($ep,$base.'/db/migrations/2026_10_15_02_profile_operator_foundation.sql');op01migrate($ep,$base.'/db/migrations/2026_10_15_02_rollback_profile_operator_foundation.sql');
        op01check((int)op01scalar($ep,"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'auth_profile_operator_%'")===0,'empty_recovery_pass');
    } finally {if(!preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',$emptyDb))throw new RuntimeException('unsafe_recovery_cleanup');$root->exec("DROP DATABASE IF EXISTS `$emptyDb`");}
    foreach (['CanonicalClinicalIdentityResolverTest.php','C3ProductiveSessionContractTest.php','PrivateSession02ContractTest.php','Qa05AdminEnvironmentContractTest.php','GovernanceGov03ATest.php','GovernanceHttpContractTest.php','ProfileMembershipRoleChangeServiceTest.php','ProfileOwnershipPersistenceTest.php','GovernedAuthorityPreparationTest.php'] as $test) {
        $proc=proc_open([PHP_BINARY,__DIR__.'/'.$test],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$log=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);
        if($code!==0)throw new RuntimeException('regression_failed:'.$test.' '.substr($errors,-1500));
        op01check(true,'regression_'.$test);
    }
    passed(38,'nine_existing_identity_governance_regressions');
} finally {
    if(isset($p)&&$p->inTransaction())$p->rollBack();
    if(!preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',$db))throw new RuntimeException('unsafe_cleanup');
    $root->exec("DROP DATABASE IF EXISTS `$db`");
}

op01check($normalBefore===liveFingerprint($root,false),'normal_local_all_table_checksums_unchanged');passed(34,'normal_local_unchanged');
op01check($qaBefore===liveFingerprint($root,true),'persistent_qa05_all_table_checksums_unchanged');passed(35,'qa05_unchanged');
op01check((int)op01scalar($root,"SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name REGEXP '^mxmed_op01_[a-f0-9]{10}$'")===0,'all_op01_databases_deleted');passed(37,'all_disposable_databases_deleted');
ksort($results);op01check(count($results)===38,'OP01_01_THROUGH_38');
echo "OP_IMP01_DISPOSABLE_SECURITY_QA=PASS\n";
