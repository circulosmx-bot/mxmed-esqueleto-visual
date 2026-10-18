<?php
declare(strict_types=1);
namespace Identity\MasterGovernance;
use Identity\Services\InternalCapabilityCatalog;
use PDO;
/** Closed staff case variant; no physician memberships, endorsements or root bootstrap. */
final class Policy
{
    public const OPERATIONS=[
        'INTERNAL_MASTER_APPOINT'=>'appoint_master',
        'INTERNAL_MASTER_REMOVE'=>'remove_master',
        'INTERNAL_ADVISOR_MANAGEMENT_GRANT'=>'grant',
        'INTERNAL_ADVISOR_MANAGEMENT_REVOKE'=>'revoke',
        'INTERNAL_CAPABILITY_DELEGATE'=>'delegate',
        'INTERNAL_CAPABILITY_DELEGATION_REVOKE'=>'revoke_delegation',
    ];
    public static function known(string $operation):bool{return isset(self::OPERATIONS[$operation]);}
    public static function command(string $operation,?string $capability):string
    {
        $command=self::OPERATIONS[$operation]??throw new \RuntimeException('master_invalid_request');
        if(in_array($command,['appoint_master','remove_master'],true)){
            if($capability!==null)throw new \RuntimeException('master_invalid_request');
        }elseif(in_array($command,['grant','revoke'],true)){
            if($capability!==InternalCapabilityCatalog::MANAGE_ADVISORS)throw new \RuntimeException('master_invalid_request');
        }else{
            $d=InternalCapabilityCatalog::all()[$capability??'']??null;
            if(!$d||$d['kind']!=='operational'||!$d['delegable'])throw new \RuntimeException('master_invalid_request');
        }
        return $command;
    }
    public static function digest(array $intent):string{return hash('sha256',json_encode(self::canonical($intent),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));}
    private static function canonical(array $value):array
    {if(!array_is_list($value))ksort($value,SORT_STRING);foreach($value as $k=>$v)if(is_array($v))$value[$k]=self::canonical($v);return $value;}
    public static function row(PDO $p,string $sql,array $args=[]):array
    {$q=$p->prepare($sql);$q->execute($args);return $q->fetch(PDO::FETCH_ASSOC)?:throw new \RuntimeException('master_denied');}
    public static function lockAccounts(PDO $p,string $actor,string $target):void
    {
        if(!$p->inTransaction()||$actor===$target)throw new \RuntimeException('master_denied');
        $ids=[$actor,$target];sort($ids,SORT_STRING);
        foreach($ids as $id){self::row($p,'SELECT account_id FROM auth_accounts WHERE account_id=? FOR UPDATE',[$id]);
            $q=$p->prepare('SELECT account_id FROM internal_staff WHERE account_id=? FOR UPDATE');$q->execute([$id]);$q->fetchAll();}
        foreach($ids as $id)self::row($p,'SELECT credential_version FROM auth_account_credentials WHERE account_id=? FOR UPDATE',[$id]);
    }
    public static function director(PDO $p,string $actor,int $version,bool $lock=false):void
    {
        $a=self::row($p,"SELECT a.status,a.email_verified_at,s.governance_class,s.status staff_status,c.credential_version FROM auth_accounts a JOIN internal_staff s ON s.account_id=a.account_id JOIN auth_account_credentials c ON c.account_id=a.account_id WHERE a.account_id=?".($lock?' FOR UPDATE':''),[$actor]);
        if($a['status']!=='active'||$a['email_verified_at']===null||$a['governance_class']!=='DIRECTOR'||$a['staff_status']!=='ACTIVE'||(int)$a['credential_version']!==$version)throw new \RuntimeException('master_denied');
    }
    public static function context(array $case):array
    {
        $c=json_decode((string)$case['security_context'],true,32,JSON_THROW_ON_ERROR);$i=$c['intent']??null;
        if(($c['kind']??null)!=='internal_master_governance'||($c['version']??null)!==1||!is_array($i)||!is_string($c['digest']??null)
            ||!hash_equals(self::digest($i),$c['digest'])||!self::known($case['operation'])
            ||($i['case_id']??null)!==$case['case_id']||($i['operation_id']??null)!==$case['operation_id']||($i['operation']??null)!==$case['operation']
            ||($i['target']??null)!==$case['beneficiary_account_id']||($i['actor']['account_id']??null)!==$case['requester_account_id']
            ||$case['requester_account_id']===$case['beneficiary_account_id']||($i['expires_at']??null)!==$case['expires_at']
            ||$case['target_membership_id']!==null||$case['replacement_membership_id']!==null||$case['profile_doctor_id']!==null)
            throw new \RuntimeException('master_context_denied');
        self::command($i['operation'],$i['capability']);return $c;
    }
    /** Lock accounts before case/challenge/proof for all new MFA paths. */
    public static function mfaCase(PDO $p,array $binding,\DateTimeImmutable $now,?array $reauth=null):array
    {
        self::lockAccounts($p,$binding['actor_account_id'],$binding['target_account_id']);
        $case=self::row($p,'SELECT * FROM auth_governance_cases WHERE case_id=? FOR UPDATE',[$binding['case_id']]);$c=self::context($case);$i=$c['intent'];
        if($case['status']!=='OPEN'||new \DateTimeImmutable($case['expires_at'],new \DateTimeZone('UTC'))<=$now
            ||$case['operation']!==$binding['operation']||$case['requester_account_id']!==$binding['actor_account_id']
            ||$case['beneficiary_account_id']!==$binding['target_account_id']||$binding['beneficiary_account_id']!==$binding['target_account_id']||$binding['profile_doctor_id']!==null)
            throw new \RuntimeException('master_context_denied');
        if($reauth!==null){
            if(($reauth['master_intent_digest']??null)!==$c['digest'])throw new \RuntimeException('master_context_denied');
            foreach(['account_id','session_id','credential_version','token_digest'] as $k)if(($reauth[$k]??null)!==$i['actor'][$k])throw new \RuntimeException('master_context_denied');
        }
        self::director($p,$case['requester_account_id'],$i['actor']['credential_version'],true);return $c;
    }
}
