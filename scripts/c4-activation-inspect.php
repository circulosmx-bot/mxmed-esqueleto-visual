<?php
declare(strict_types=1);
/** C4-ACT01 preparation only. No apply mode, DDL, CALL, sessions, files or runtime writes. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();

use Identity\Operators\OperatorDeploymentSchema;
use Identity\Http\GovernanceDeploymentAuthorization;

function c4PrivateInput(string $path): array
{
    $file=realpath($path); $parent=realpath(dirname($path)); $root=dirname(__DIR__);
    if ($file===false || $parent===false || !is_file($file) || is_link($path) || is_link(dirname($path))
        || str_starts_with($file,$root.'/') || (fileperms($file)&0077)!==0 || (fileperms($parent)&0077)!==0
        || fileowner($file)!==posix_getuid()) throw new RuntimeException('private_input_required');
    $data=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
    if (!is_array($data) || array_is_list($data)) throw new RuntimeException('private_input_invalid');
    return $data;
}
function c4Digest(mixed $data): string { return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR)); }

try {
    $args=[];
    for ($i=1;$i<$argc;$i+=2) {
        if (!in_array($argv[$i],['--connection','--baseline'],true) || !isset($argv[$i+1]) || isset($args[$argv[$i]]))
            throw new RuntimeException('inspect_arguments_required');
        $args[$argv[$i]]=$argv[$i+1];
    }
    $cfg=c4PrivateInput($args['--connection']??'');
    if (($cfg['MXMED_DB_HOST']??null)!=='127.0.0.1' || (string)($cfg['MXMED_DB_PORT']??'')!=='3306'
        || ($cfg['MXMED_DB_NAME']??null)!=='mxmed') throw new RuntimeException('normal_local_target_required');
    $pdo=new PDO('mysql:host=127.0.0.1;port=3306;dbname=mxmed;charset=utf8mb4',$cfg['MXMED_DB_USER'],$cfg['MXMED_DB_PASS'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    // All inspection reads share a read-only consistent transaction. No source SQL is executed.
    $pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
    $identity=OperatorDeploymentSchema::identity($pdo);
    $rows=$pdo->query('SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll();
    $tables=array_column($rows,'TABLE_NAME');
    $installed=['auth_profile_ownership_approval_endorsements','auth_governance_cases','auth_staff_mfa_factors',
        'auth_staff_mfa_challenges','auth_staff_mfa_proofs','auth_governance_case_endorsements','auth_external_trust_anchor_evidence',
        'auth_governance_events',...OperatorDeploymentSchema::TABLES];
    $protected=[];
    foreach ($installed as $table) {
        if (!in_array($table,$tables,true)) throw new RuntimeException('op_act02a_object_missing');
        $protected[$table]=hash('sha256',$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1]);
    }
    OperatorDeploymentSchema::assertRequired($pdo);
    $columns=$pdo->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION')->fetchAll();
    $present=array_fill_keys(array_map(static fn($r)=>$r['TABLE_NAME'].'.'.$r['COLUMN_NAME'],$columns),true);
    $newColumns=['auth_governance_cases.security_context','auth_staff_mfa_factors.enrollment_context',
        'auth_staff_mfa_challenges.reauthentication_context','auth_external_trust_anchor_evidence.contract_version',
        'auth_external_trust_anchor_evidence.operation','auth_external_trust_anchor_evidence.profile_doctor_id',
        'auth_external_trust_anchor_evidence.membership_id','auth_external_trust_anchor_evidence.membership_version',
        'auth_external_trust_anchor_evidence.requester_account_id'];
    $newTables=['profiles_physician_admissions','auth_account_professional_bindings','auth_account_phone_verifications',
        'auth_physician_evidence_requests','auth_physician_evidence_files','auth_physician_issuance_workflows','auth_physician_authority_history'];
    $shape=['migration01_columns'=>array_combine($newColumns,array_map(static fn($n)=>isset($present[$n]),$newColumns)),
        'c4_tables'=>array_combine($newTables,array_map(static fn($n)=>in_array($n,$tables,true),$newTables))];
    $allAbsent=!in_array(true,array_merge(array_values($shape['migration01_columns']),array_values($shape['c4_tables'])),true);
    $counts=[];
    foreach (['auth_accounts','internal_staff','internal_operator_grants','auth_staff_mfa_factors','auth_account_memberships',...$newTables] as $table)
        $counts[$table]=in_array($table,$tables,true)?(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn():null;
    $counts['active_owners']=(int)$pdo->query("SELECT COUNT(*) FROM auth_account_memberships WHERE role_code='owner' AND status='active'")->fetchColumn();
    $counts['active_accreditation_reviewers']=(int)$pdo->query("SELECT COUNT(DISTINCT s.account_id) FROM internal_staff s JOIN internal_operator_grants g ON g.account_id=s.account_id JOIN auth_accounts a ON a.account_id=s.account_id WHERE s.status='ACTIVE' AND a.status='active' AND a.email_verified_at IS NOT NULL AND g.capability='physician_accreditation_review' AND g.status='ACTIVE' AND g.revoked_at IS NULL")->fetchColumn();
    $foreignKeys=$pdo->query('SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll();
    $checks=$pdo->query("SELECT t.TABLE_NAME,t.CONSTRAINT_NAME,t.ENFORCED,c.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS t JOIN information_schema.CHECK_CONSTRAINTS c ON c.CONSTRAINT_SCHEMA=t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME=t.CONSTRAINT_NAME WHERE t.CONSTRAINT_SCHEMA=DATABASE() ORDER BY t.TABLE_NAME,t.CONSTRAINT_NAME")->fetchAll();
    $triggers=$pdo->query('SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING,ACTION_STATEMENT,DEFINER,SQL_MODE FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME')->fetchAll();
    $routines=$pdo->query('SELECT ROUTINE_NAME,ROUTINE_TYPE,ROUTINE_DEFINITION,SECURITY_TYPE,DEFINER,SQL_MODE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_NAME')->fetchAll();
    // The existing canonical fingerprint includes generated columns, indexes, CHECK clauses and audit routines.
    $schemaHash=OperatorDeploymentSchema::fingerprint($pdo);
    $ddl=[];
    foreach ($tables as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) throw new RuntimeException('metadata_identifier_denied');
        // AUTO_INCREMENT counters are data state, not schema drift; data is preserved separately.
        $ddl[$table]=preg_replace('/\sAUTO_INCREMENT=\d+/','',$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1]);
    }
    $pdo->rollBack();
    $migration=[];
    foreach (['01_governance_security_bindings','02_physician_account_binding','03_physician_evidence_foundation','04_physician_authority_issuer'] as $name) {
        $file='2026_10_16_'.$name.'.sql'; $migration[$file]=hash_file('sha256',dirname(__DIR__).'/modules/identity/db/migrations/'.$file);
    }
    $report=['inspection_contract'=>'C4-ACT01-READ-ONLY-v1','activation_authorized'=>false,'ddl_executed'=>false,
        'identity'=>$identity,'target_identity_sha256'=>c4Digest($identity),'table_count'=>count($tables),
        'schema_fingerprint_sha256'=>$schemaHash,'full_ddl_sha256'=>c4Digest([$ddl,$foreignKeys,$checks,$triggers,$routines]),
        'op_act02a_table_ddl_sha256'=>$protected,'op_act02a_fingerprint_sha256'=>c4Digest($protected),
        'foreign_key_count'=>count(array_unique(array_map(static fn($r)=>$r['TABLE_NAME'].'.'.$r['CONSTRAINT_NAME'],$foreignKeys))),
        'unenforced_checks'=>count(array_filter($checks,static fn($r)=>$r['ENFORCED']!=='YES')),
        'cross_schema_foreign_keys'=>count(array_filter($foreignKeys,static fn($r)=>$r['REFERENCED_TABLE_SCHEMA']!=='mxmed')),
        'trigger_count'=>count($triggers),'routine_count'=>count($routines),'migration_sha256'=>$migration,
        'governance_source_sha256'=>GovernanceDeploymentAuthorization::sourceHash(),'authority_counts'=>$counts,
        'c4_prestate'=>$shape,'pristine_pre_c4'=>$allAbsent,
        'ddl_permission_required'=>true,'runtime_implementation_ready'=>false,'functional_test_ready'=>false];
    if (isset($args['--baseline'])) {
        $baseline=c4PrivateInput($args['--baseline']);
        foreach (['inspection_contract','target_identity_sha256','schema_fingerprint_sha256','full_ddl_sha256',
            'op_act02a_fingerprint_sha256','migration_sha256','governance_source_sha256','authority_counts','c4_prestate'] as $field)
            if (($baseline[$field]??null)!==$report[$field]) throw new RuntimeException('inspection_baseline_drift');
        $report['baseline_matches']=true;
    }
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,"C4_INSPECTION_REFUSED: verify private inputs, read privileges, target and baseline; no activation or DDL performed.\n");
    exit(78);
}
