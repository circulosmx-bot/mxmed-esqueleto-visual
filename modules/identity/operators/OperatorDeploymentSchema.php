<?php
declare(strict_types=1);
namespace Identity\Operators;
use PDO;
/** Metadata only, no migrations or lazy schema creation at runtime. */
final class OperatorDeploymentSchema
{
    public const TABLES=['auth_profile_operator_invitations','auth_profile_operator_grant_revisions','auth_profile_operator_grants','auth_profile_operator_commands','auth_profile_operator_presentations','agenda_appointment_creator_snapshots','auth_identity_notification_deliveries'];
    public static function fingerprint(PDO $pdo):string
    {
        $queries=[
            "SELECT TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION",
            "SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX",
            "SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION",
            "SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME",
            "SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME",
            "SELECT SPECIFIC_NAME,ORDINAL_POSITION,PARAMETER_MODE,PARAMETER_NAME,DTD_IDENTIFIER,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME IN ('audit_mp01c_lock_stream_head_v1','audit_mp01c_advance_stream_head_cas_v1') ORDER BY SPECIFIC_NAME,ORDINAL_POSITION",
            "SELECT ROUTINE_NAME,ROUTINE_TYPE,ROUTINE_DEFINITION,SECURITY_TYPE,DEFINER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME IN ('audit_mp01c_lock_stream_head_v1','audit_mp01c_advance_stream_head_cas_v1') ORDER BY ROUTINE_NAME",
        ];$data=[];foreach($queries as $q)$data[]=$pdo->query($q)->fetchAll(PDO::FETCH_ASSOC);
        return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
    }
    public static function assertRequired(PDO $pdo):void
    {
        $s=$pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');$s->execute();
        if(array_diff(self::TABLES,$s->fetchAll(PDO::FETCH_COLUMN)))throw new \RuntimeException('operator_deployment_schema_missing');
        foreach(['auth_accounts'=>'account_id','auth_account_memberships'=>'membership_id','profiles_doctors'=>'doctor_id','agenda_appointments'=>'appointment_id'] as $table=>$column){
            $s=$pdo->prepare('SELECT COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);$r=$s->fetch(PDO::FETCH_ASSOC);
            if(!$r||$r['COLUMN_TYPE']!=='varchar(64)'||$r['COLLATION_NAME']!=='utf8mb4_unicode_ci')throw new \RuntimeException('operator_deployment_fk_drift');
        }
        foreach(['op07_assignment_identity_guard','op07_snapshot_no_update','op07_snapshot_no_delete','op07_original_creator_guard','op07_snapshot_insert_binding'] as $name){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?');$s->execute([$name]);if((int)$s->fetchColumn()!==1)throw new \RuntimeException('operator_deployment_trigger_missing');}
    }
    public static function identity(PDO $pdo):array
    {return $pdo->query('SELECT DATABASE() db,@@server_uuid server_uuid,VERSION() server_version,CURRENT_USER() db_user')->fetch(PDO::FETCH_ASSOC);}
    public static function sourceHash():string
    {
        $root=dirname(__DIR__,3);$paths=array_merge(glob(__DIR__.'/*.php'),glob($root.'/modules/identity/deployment/op-act01/*.sql'));
        foreach(['private-app.php','api/profile-operators/index.php','scripts/canonical-private-router.php','scripts/normal-local-identity.py','scripts/operator-invitation-dispatch.php','modules/identity/http/IdentityHttpComposition.php','modules/identity/http/GovernanceHttpComposition.php','modules/identity/adapters/SesIdentityNotificationAdapter.php','modules/subscriptions/services/CurrentSubscriptionReadModelService.php','modules/platform/repositories/JoinedPdoCanonicalAuditTransactionAdapter.php','assets/js/agenda/operators-panel.js','assets/js/agenda/agenda-workspace.js','partials/private-app/operators.php','partials/private-app/operator-inbox.php','modules/platform/repositories/CanonicalAuditDatabaseTarget.php','modules/platform/repositories/PdoCanonicalAuditTransactionAdapter.php','assets/js/agenda/canonical-operators.js','public/identity/identity-access.js','assets/js/core/private-session.js'] as $p)$paths[]=$root.'/'.$p;
        sort($paths);$hash=[];foreach($paths as $p){if(!is_file($p))throw new \RuntimeException('operator_deployment_source_missing');$hash[substr($p,strlen($root)+1)]=hash_file('sha256',$p);}return hash('sha256',json_encode($hash,JSON_THROW_ON_ERROR));
    }
    public static function packageHash():string
    {$files=glob(dirname(__DIR__).'/deployment/op-act01/*.sql');sort($files);$v=[];foreach($files as $f)$v[basename($f)]=hash_file('sha256',$f);return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
}
