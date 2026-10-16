<?php
declare(strict_types=1);
// Synthetic fixtures only, invoked inside OperatorFoundationTest's guarded disposable DB.
function op07PresentationAssertions(PDO $p,PDO $root,string $db,array $s,array $owner,array $a,array $b,array $c): void
{
    $profile=op01fresh($p,'op07');
    $invite=function(string $account,?string $alias)use($s,$owner,$profile):array{
        $r=op01command($s,$owner,$profile,'invite',['email'=>$account.'@example.invalid','alias'=>$alias,'role'=>'collaborator','capabilities'=>\Identity\Services\ProfileOperatorCapabilityCatalog::KEYS]);
        [, $token]=explode('.',$s['notifications']->lastMessage()->token(),2);return $r+['token'=>$token];
    };
    $empty=$s['service']->overview($owner['token'],$profile);
    op01check($empty['members']===[]&&$empty['invitations']===[]&&$empty['available']===2,'OP07_01_empty_no_invented_alias');
    $i=$invite('invitee_a','Mary');$j=$invite('invitee_b',null);
    op01check($i['seat_position']===1&&$i['personal_alias']==='Mary','OP07_02_06_11_owner_alias_case_position1');
    op01check($j['seat_position']===2&&$j['personal_alias']===null,'OP07_03_position2_no_email_alias');
    op01check(op01denied(fn()=>$invite('invitee_c','Tere')),'OP07_04_third_denied');
    $view=$s['service']->overview($owner['token'],$profile);
    op01check($view['reserved']===2&&$view['available']===0,'OP07_05_reserved_positions');
    $ma=op01accept($s,$a,$profile,$i);
    $mb=op01command($s,$b,$profile,'accept',['invitation_id'=>$j['invitation_id'],'token'=>$j['token'],'offer_revision'=>1,'alias'=>'Lupita']);
    op01check($ma['seat_position']===1&&$mb['personal_alias']==='Lupita'&&$mb['seat_position']===2,'OP07_07_invitee_alias_position_retained');
    $rename=fn(array $actor,array $member,string $alias,?string $command=null)=>op01command($s,$actor,$profile,'rename',['membership_id'=>$member['membership_id'],'expected_presentation_revision'=>$member['presentation_revision'],'alias'=>$alias],$command);
    $renamed=$rename($owner,$ma,'Tere','rename_op07_once');
    $replay=$rename($owner,$ma,'Tere','rename_op07_once');
    op01check($renamed['personal_alias']==='Tere'&&($replay['replayed']??false)&&$replay['presentation_revision']===$renamed['presentation_revision'],'OP07_08_owner_rename_version_idempotency');
    op01check(op01denied(fn()=>$rename($owner,$mb,'TERE'))&&op01denied(fn()=>$rename($owner,$ma,'Old')),'OP07_09_collision_and_stale_revision');
    op01check(op01denied(fn()=>$rename($a,$renamed,'Other')),'OP07_owner_only_rename');
    $n=\Identity\Operators\OperatorAlias::normalize("Jose\u{0301}");$upper=\Identity\Operators\OperatorAlias::normalize('JOSÉ');
    op01check($n['display']==='José'&&$n['key']===$upper['key']&&\Identity\Operators\OperatorAlias::normalize('Jose')['key']!==$n['key'],'OP07_10_unicode_NFC_casefold_accent_policy');
    foreach(['Mary Jane',"Ma\u{200D}ry",'123','<svg>',str_repeat('a',25),"\xFF"] as $invalid)
        op01check(op01denied(fn()=>\Identity\Operators\OperatorAlias::normalize($invalid)),'OP07_12_invalid_alias_'.hash('crc32b',$invalid));
    $out=concurrent($p,$db,$profile,$s,[['actor'=>'owner_a','action'=>'rename','input'=>['membership_id'=>$ma['membership_id'],'expected_presentation_revision'=>$renamed['presentation_revision'],'alias'=>'Ana']],['actor'=>'owner_a','action'=>'rename','input'=>['membership_id'=>$mb['membership_id'],'expected_presentation_revision'=>$mb['presentation_revision'],'alias'=>'ANA']]]);
    op01check(count(array_filter($out,fn($r)=>$r==='OK'))===1,'OP07_13_physical_concurrent_alias_collision');
    op01command($s,$owner,$profile,'suspend',['membership_id'=>$mb['membership_id'],'expected_revision'=>1]);
    op01check(op01denied(fn()=>$invite('invitee_c','Tere'))&&(int)op01scalar($p,'SELECT seat_position FROM auth_profile_operator_presentations WHERE membership_id=?',[$mb['membership_id']])===2,'OP07_15_suspension_keeps_position');
    op01command($s,$owner,$profile,'reactivate',['membership_id'=>$mb['membership_id'],'expected_revision'=>1]);
    $before=(int)op01scalar($p,'SELECT revision FROM auth_profile_operator_presentations WHERE membership_id=?',[$ma['membership_id']]);
    $s['tx']->failInsert=true;
    op01check(op01denied(fn()=>op01command($s,$owner,$profile,'rename',['membership_id'=>$ma['membership_id'],'expected_presentation_revision'=>$before,'alias'=>'Rollback'])),'OP07_32_audit_failure_denied');$s['tx']->failInsert=false;
    op01check($before===(int)op01scalar($p,'SELECT revision FROM auth_profile_operator_presentations WHERE membership_id=?',[$ma['membership_id']]),'OP07_32_alias_revision_rolled_back');
    op01command($s,$owner,$profile,'revoke',['membership_id'=>$ma['membership_id'],'expected_revision'=>1]);$replacement=$invite('invitee_c','Tere');
    op01check($replacement['seat_position']===1&&$replacement['presentation_id']!==$ma['presentation_id']&&op01scalar($p,'SELECT released_at FROM auth_profile_operator_presentations WHERE membership_id=?',[$ma['membership_id']])!==null,'OP07_14_17_new_assignment_after_release');
    $expiry=op01fresh($p,'op07expiry');$pending=op01invite($s,$owner,$expiry,'invitee_a');
    $future=op01stack($p,new Op01Clock($s['clock']->now()->modify('+8 days')));$fo=op01login($future,'owner_a');$expired=$future['service']->overview($fo['token'],$expiry);
    op01check($expired['available']===2&&op01scalar($p,'SELECT released_at FROM auth_profile_operator_presentations WHERE invitation_id=?',[$pending['invitation_id']])!==null,'OP07_16_expiration_releases_position');
    // Binary uniqueness survives MySQL's default accent-insensitive collation.
    $unicode=op01fresh($p,'op07unicode');
    op01command($s,$owner,$unicode,'invite',['email'=>'invitee_a@example.invalid','alias'=>"Jose\u{0301}",'capabilities'=>[]]);
    op01check(op01denied(fn()=>op01command($s,$owner,$unicode,'invite',['email'=>'invitee_b@example.invalid','alias'=>'JOSÉ','capabilities'=>[]])),'OP07_10_physical_unicode_collision');
    op01command($s,$owner,$unicode,'invite',['email'=>'invitee_b@example.invalid','alias'=>'Jose','capabilities'=>[]]);
    op01check(op01denied(fn()=>$p->prepare('UPDATE auth_profile_operator_presentations SET profile_doctor_id=? WHERE presentation_id=?')->execute([$unicode,$replacement['presentation_id']])),'OP07_30_composite_profile_FK');
    op01check(op01denied(fn()=>op01command($s,$owner,$unicode,'rename',['membership_id'=>$mb['membership_id'],'expected_presentation_revision'=>2,'alias'=>'Forged'])),'OP07_30_cross_profile_rename');
    op01check(!$s['service']->permits($b['token'],$profile,$profile,'clinical_record')&&!$s['service']->permits($b['token'],$profile,$profile,'agenda.*'),'OP07_31_33_no_clinical_or_wildcard');
    $audit=(string)op01scalar($p,"SELECT metadata_json FROM platform_audit_events WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operator_action'))='rename' ORDER BY occurred_at_utc DESC LIMIT 1");
    op01check(str_contains($audit,'presentation_revision')&&!str_contains($audit,'@example.invalid'),'OP07_alias_audit_revision_no_contact');
    $base=dirname(__DIR__,2);$agenda=dirname($base).'/agenda';
    op01migrate($p,$base.'/db/migrations/2026_10_15_04_operator_presentations.sql');
    op01check(op01denied(fn()=>op01migrate($p,$base.'/db/migrations/2026_10_15_04_rollback_operator_presentations.sql')),'OP07_populated_recovery_refused');
    $p->exec('DROP TEMPORARY TABLE IF EXISTS op07_empty_guard');
    $emptyDb='mxmed_op01_'.bin2hex(random_bytes(5));$root->exec("CREATE DATABASE `$emptyDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    try{
        $ep=op01pdo($emptyDb);op01migrate($ep,$base.'/db/migrations/2026_07_19_01_create_auth_accounts.sql');op01migrate($ep,dirname($base).'/profiles/db/profiles_doctors_schema.sql');$ep->exec('CREATE TABLE medical_groups(group_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');
        foreach(['2026_07_19_03_create_auth_account_memberships.sql','2026_10_15_02_profile_operator_foundation.sql','2026_10_15_04_operator_presentations.sql'] as $f)op01migrate($ep,$base.'/db/migrations/'.$f);
        op01migrate($ep,$agenda.'/db/ready_schema.sql');op01migrate($ep,$agenda.'/db/migrations/2026_10_15_12_appointment_creator_snapshots.sql');
        op01migrate($ep,$agenda.'/db/migrations/2026_10_15_12_rollback_appointment_creator_snapshots.sql');op01migrate($ep,$base.'/db/migrations/2026_10_15_04_rollback_operator_presentations.sql');
        op01check((int)op01scalar($ep,"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('auth_profile_operator_presentations','agenda_appointment_creator_snapshots')")===0,'OP07_empty_forward_recovery_pass');
    }finally{$root->exec("DROP DATABASE `$emptyDb`");}
}
