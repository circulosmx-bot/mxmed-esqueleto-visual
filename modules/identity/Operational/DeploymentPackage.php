<?php
declare(strict_types=1);
namespace Identity\Operational;
use PDO;
use Identity\Operators\OperatorDeploymentSchema;

final class DeploymentPackage
{
    public static function root():string{return dirname(__DIR__,3);}
    public static function migrations():array
    {
        $files=[];
        foreach(['01_governance_security_bindings','02_physician_account_binding','03_physician_evidence_foundation','04_physician_authority_issuer','05_public_physician_enrollment','06_reviewed_physician_enrollment'] as $f)
            $files[]='modules/identity/db/migrations/2026_10_16_'.$f.'.sql';
        return [...$files,'modules/profiles/db/2026_10_16_01_public_correction_reports.sql','modules/profiles/db/2026_10_16_02_correction_applications.sql','modules/subscriptions/db/2026_10_16_01_preloaded_public_free_source.sql','modules/identity/deployment/act02/metadata.sql'];
    }
    public static function sourceHash():string
    {
        $root=self::root();$hash=[];
        foreach(['modules/identity','modules/profiles','modules/subscriptions','modules/platform','modules/media','public/identity','api/identity','api/profiles','profiles','internal/media-review','partials/identity','partials/profiles','assets/js/identity'] as $dir){
            $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir,\FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $f){$path=substr($f->getPathname(),strlen($root)+1);if($f->isFile()&&!preg_match('~/(tests|qa)/~',$path)&&in_array($f->getExtension(),['php','js','css','sql','json'],true))$hash[$path]=hash_file('sha256',$f->getPathname());}
        }
        foreach(['scripts/normal-local-https-proxy.py','scripts/canonical-private-router.php','scripts/act02-deployment.php','scripts/act02-rehearsal.py','scripts/act02-principal.py','modules/agenda/contracts/OtpProviderPort.php','assets/js/profile-correction-review.js','assets/js/profile-correction-application.js','assets/css/physician-onboarding.css','assets/css/profile-correction-review.css','assets/css/media-review-inbox.css'] as $p)$hash[$p]=hash_file('sha256',$root.'/'.$p);
        ksort($hash);return hash('sha256',json_encode($hash,JSON_THROW_ON_ERROR));
    }
    public static function assertSchema(PDO $pdo):void
    {
        if((int)$pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn()!==1
            ||(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK' AND ENFORCED<>'YES'")->fetchColumn()!==0)
            throw new \RuntimeException('act02_native_integrity_not_enforced');
        foreach(OperatorDeploymentSchema::TABLES as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);if((int)$q->fetchColumn()!==1)throw new \RuntimeException('act02_OP_ACT02A_table_missing');}
        $triggers=array_column(self::triggerMetadata($pdo)[0],'TRIGGER_NAME');
        foreach(['op07_assignment_identity_guard','op07_snapshot_no_update','op07_snapshot_no_delete','op07_original_creator_guard','op07_snapshot_insert_binding','correction_application_result_guard','correction_contact_version_update'] as $name)
            if(!in_array($name,$triggers,true))throw new \RuntimeException('act02_required_trigger_missing:'.$name);
        \Identity\Http\GovernanceDeploymentAuthorization::assertSchema($pdo);
        foreach(['profiles_physician_admissions','auth_account_professional_bindings','auth_account_phone_verifications','auth_physician_evidence_requests','auth_physician_evidence_files','auth_physician_issuance_workflows','auth_physician_authority_history','auth_physician_enrollment_origins','auth_physician_enrollment_intake_revisions','auth_physician_enrollment_resolutions','auth_physician_enrollment_handoffs','profiles_correction_reports','profiles_correction_decisions','profiles_correction_rate_buckets','profiles_correction_review_intents','profiles_contact_correction_versions','profiles_correction_application_commands','profiles_correction_application_authorizations','profiles_correction_application_journal','profiles_correction_application_results'] as $table)$pdo->query('SELECT 1 FROM `'.$table.'` LIMIT 0');
        $pdo->query('SELECT preloaded_free_plan,preloaded_free_reference,preloaded_free_recorded_at FROM profiles_doctors LIMIT 0');
        $target=\Platform\Repositories\CanonicalAuditDatabaseTarget::fromConnection($pdo);
        foreach(['audit_mp01c_lock_stream_head_v1','audit_mp01c_advance_stream_head_cas_v1'] as $r)$target->routine($pdo,$r);
    }
    public static function professionalSourceMigrations():array
    // Fresh-install order only. Installed/partial targets require the COMPAT01 runbook preflight; never replay this list.
    {return [...self::migrations(),'modules/identity/db/migrations/2026_10_18_02_professional_source_check_compat01.sql',
        'modules/identity/db/migrations/2026_10_18_03_professional_source_objects_compat01.sql'];}
    public static function assertProfessionalSources(PDO $p):void
    {
        foreach(['auth_professional_source_requests','auth_professional_source_files','auth_professional_source_reads','auth_professional_source_operations','auth_professional_source_journal'] as $table)$p->query('SELECT 1 FROM `'.$table.'` LIMIT 0');
        $p->query('SELECT result_identity_id,result_credential_id,frozen_hash,payload_ciphertext,authorization_case_id,reviewer_account_id,result_json FROM auth_professional_source_operations LIMIT 0');
        $p->query('SELECT professional_source_file_id FROM auth_physician_evidence_files LIMIT 0');
        $names=array_column(self::triggerMetadata($p)[0],'TRIGGER_NAME');
        foreach(['source_files_no_update','source_files_no_delete','source_journal_no_update','source_journal_no_delete','source_request_binding_guard','source_operation_frozen_guard','source_c4_reference_guard'] as $name)if(!in_array($name,$names,true))throw new \RuntimeException('act03_source_trigger_missing');
    }
    public static function inspect(PDO $pdo):array
    {
        $hash=[];foreach(self::migrations() as $f)$hash[$f]=hash_file('sha256',self::root().'/'.$f);
        return ['contract'=>'MXMED-ACT02.1','identity'=>OperatorDeploymentSchema::identity($pdo),'source_hash'=>self::sourceHash(),
            'schema_fingerprint'=>self::schemaFingerprint($pdo),'ordered_migrations'=>$hash,'activation_authorized'=>false];
    }
    public static function schemaFingerprint(PDO $pdo):string
    {
        $q=$pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='act02_trigger_metadata'");
        $metadata=(int)$q->fetchColumn()===1?self::triggerMetadata($pdo):[];
        $checks=$pdo->query("SELECT TABLE_NAME,CONSTRAINT_NAME,ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK' ORDER BY TABLE_NAME,CONSTRAINT_NAME")->fetchAll(PDO::FETCH_ASSOC);
        $engines=$pdo->query('SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_ASSOC);
        $fkSchemas=$pdo->query('SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC);
        $fkRules=$pdo->query('SELECT TABLE_NAME,CONSTRAINT_NAME,UNIQUE_CONSTRAINT_SCHEMA,UNIQUE_CONSTRAINT_NAME,MATCH_OPTION,UPDATE_RULE,DELETE_RULE,REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_NAME')->fetchAll(PDO::FETCH_ASSOC);
        return hash('sha256',json_encode([OperatorDeploymentSchema::fingerprint($pdo),$metadata,$checks,$engines,$fkSchemas,$fkRules],JSON_THROW_ON_ERROR));
    }
    private static function triggerMetadata(PDO $pdo):array
    {
        $q=$pdo->query('CALL act02_trigger_metadata()');$rows=$q->fetchAll(PDO::FETCH_ASSOC);$q->nextRowset();$routine=$q->fetch(PDO::FETCH_ASSOC);while($q->nextRowset()){}$q->closeCursor();
        $db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $template=(string)file_get_contents(self::root().'/modules/identity/deployment/act02/metadata.sql');
        $start=strpos($template,"\nBEGIN");$body=substr($template,$start+1,strpos($template,'END$$')-$start+2);
        $expected=str_replace('{{TARGET_SCHEMA_NAME}}',$db,$body);$normalize=static fn(string $v):string=>preg_replace('/\s+/',' ',trim($v));
        if(!is_array($routine)||$routine['SECURITY_TYPE']!=='DEFINER'||$routine['SQL_DATA_ACCESS']!=='READS SQL DATA'
            ||!is_string($routine['ROUTINE_DEFINITION'])||$normalize($routine['ROUTINE_DEFINITION'])!==$normalize($expected))throw new \RuntimeException('act02_metadata_reader_drift');
        return [$rows,$routine];
    }
}
