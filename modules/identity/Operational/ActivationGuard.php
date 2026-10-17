<?php
declare(strict_types=1);
namespace Identity\Operational;
use PDO;
use Identity\Http\IdentityHttpComposition;
use Identity\Operators\OperatorDeploymentSchema;
use Identity\Services\StaffRoutineOperationPolicy;

/** Signed, private, short-lived authorization. No approval creation or env selector. */
final class ActivationGuard
{
    public const WORKFLOWS=['public_enrollment','enrollment_review','c4_evidence','c4_issuer','correction_intake','correction_review','correction_apply','professional_source_provisioning'];
    public static function privateFile(string $path):string
    {
        $real=realpath($path);$root=DeploymentPackage::root();$parent=realpath(dirname($path));
        if($real===false||$parent===false||!is_file($path)||is_link($path)||is_link(dirname($path))
            ||str_starts_with($real,$root.'/')||($real!==$path)||((fileperms($path)|fileperms($parent))&0077)!==0
            ||fileowner($path)!==posix_geteuid()||filesize($path)>65536)throw new \RuntimeException('act02_private_file_required');
        return (string)file_get_contents($path);
    }
    public static function json(string $path):array
    {$v=json_decode(self::privateFile($path),true,32,JSON_THROW_ON_ERROR);if(!is_array($v)||array_is_list($v))throw new \RuntimeException('act02_private_object_required');return $v;}
    public static function verify(IdentityHttpComposition $identity,string $authorization,string $trust,array $secrets):array
    {
        $p=$identity->pdo();if($p->inTransaction())throw new \RuntimeException('act02_guard_requires_idle_connection');
        $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();
        try{return self::verifyReadOnly($identity,$authorization,$trust,$secrets);}
        finally{if($p->inTransaction())$p->rollBack();}
    }
    private static function verifyReadOnly(IdentityHttpComposition $identity,string $authorization,string $trust,array $secrets):array
    {
        $t=self::json($trust);$envelope=self::json($authorization);
        $key=base64_decode($t['public_key']??'',true);$bytes=base64_decode($envelope['payload']??'',true);$signature=base64_decode($envelope['signature']??'',true);
        if(!is_string($key)||strlen($key)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES||!is_string($bytes)||!is_string($signature)||strlen($signature)!==SODIUM_CRYPTO_SIGN_BYTES
            ||!sodium_crypto_sign_verify_detached($signature,$bytes,$key))throw new \RuntimeException('act02_signature_required');
        $a=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);$now=time();
        if(($a['contract']??null)!=='MXMED-ACT02.1'||($a['state']??null)!=='approved_pilot'
            ||!is_int($a['issued_at']??null)||!is_int($a['expires_at']??null)||$a['issued_at']>$now||$a['expires_at']<=$now
            ||$a['expires_at']-$a['issued_at']>86400||($a['origin']??null)!==$identity->allowedOrigin())throw new \RuntimeException('act02_approval_invalid_or_expired');
        $pdo=$identity->pdo();$actual=OperatorDeploymentSchema::identity($pdo);
        if(($a['identity']??null)!==$actual||str_starts_with(strtolower($actual['db_user']),'root@'))throw new \RuntimeException('act02_restricted_target_required');
        $synthetic=($a['mode']??null)==='synthetic';
        if($synthetic?!preg_match('/^mxmed_gate4d_preview_(?:act02|cor02|c4ui)_[a-f0-9]{10}$/D',$actual['db']):($actual['db']!=='mxmed'||($a['mode']??null)!=='pilot'||$identity->allowedOrigin()!=='https://localhost:8443'))throw new \RuntimeException('act02_environment_denied');
        if(!$synthetic){$path=$secrets['_connection_path']??'';if(!is_string($path)||($a['connection_source']['path']??null)!==$path
            ||($a['connection_source']['sha256']??null)!==hash('sha256',self::privateFile($path)))throw new \RuntimeException('act02_connection_source_not_approved');}
        if(!is_string($a['deployment_principal']??null)||strlen($a['deployment_principal'])<3)throw new \RuntimeException('act02_deployment_principal_required');
        if(($a['source_hash']??null)!==DeploymentPackage::sourceHash()||($a['schema_fingerprint']??null)!==DeploymentPackage::schemaFingerprint($pdo))throw new \RuntimeException('act02_source_or_schema_drift');
        DeploymentPackage::assertSchema($pdo);
        if(in_array('professional_source_provisioning',$a['workflows']??[],true))DeploymentPackage::assertProfessionalSources($pdo);
        foreach(['privacy','custody','recovery','retention','legal_hold','disposal','staff','pilot','abuse'] as $p)
            if(!is_string($a['policies'][$p]??null)||!preg_match('/^[A-Za-z0-9_.:-]{8,128}$/D',$a['policies'][$p]))throw new \RuntimeException('act02_policy_required');
        foreach(['accounts','profiles','workflows'] as $list){if(!is_array($a[$list]??null)||!array_is_list($a[$list])||count($a[$list])<1||count($a[$list])>100)throw new \RuntimeException('act02_bounded_cohort_required');foreach($a[$list] as $v)if(!is_string($v)||!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D',$v))throw new \RuntimeException('act02_cohort_invalid');}
        if(array_diff($a['workflows'],self::WORKFLOWS)!==[])throw new \RuntimeException('act02_unknown_workflow');
        if(in_array('professional_source_provisioning',$a['workflows'],true)&&array_diff(['public_enrollment','enrollment_review'],$a['workflows'])!==[])throw new \RuntimeException('act03_source_enrollment_workflows_required');
        foreach(['browser'=>3,'peer'=>100,'profile'=>20] as $key=>$max)if(!is_int($a['rate_limits'][$key]??null)||$a['rate_limits'][$key]<1||$a['rate_limits'][$key]>$max)throw new \RuntimeException('act02_rate_policy_required');
        foreach(['evidence_key','report_key','proof_key','mfa_key','transport_key','audit_key'] as $name){
            $value=$secrets[$name]??null;if(!is_string($value)||strlen($value)<32||($a['secret_hashes'][$name]??null)!==hash('sha256',$value))throw new \RuntimeException('act02_secret_required');
        }
        $secretSource=$secrets['_source_path']??'';
        if(!is_string($secretSource)||($a['secret_source']['path']??null)!==$secretSource
            ||($a['secret_source']['sha256']??null)!==hash('sha256',self::privateFile($secretSource)))throw new \RuntimeException('act02_secret_source_not_approved');
        foreach(['evidence_key_id','report_key_id','mfa_key_id'] as $name)if(($a[$name]??null)!==($secrets[$name]??null)||!preg_match('/^[A-Za-z0-9_.:-]{3,64}$/D',$secrets[$name]??''))throw new \RuntimeException('act02_key_id_required');
        if(!hash_equals($secrets['transport_key'],(string)getenv('MXMED_GOVERNANCE_PROXY_KEY'))||!hash_equals($secrets['audit_key'],(string)getenv('MXMED_AUDIT_IP_HMAC_SECRET')))throw new \RuntimeException('act02_existing_transport_audit_binding_required');
        if(!is_array($a['staff']??null)||$a['staff']===[])throw new \RuntimeException('act02_staff_required');
        $required=[];
        if(in_array('professional_source_provisioning',$a['workflows'],true))$required[]='professional_source_provision';
        if(array_intersect($a['workflows'],['enrollment_review','c4_evidence','c4_issuer']))$required[]='physician_accreditation_review';
        if(in_array('correction_review',$a['workflows'],true))$required[]='public_profile_correction_review';
        if(in_array('correction_apply',$a['workflows'],true))$required[]='public_profile_correction_apply';
        $advisorOperations=['enrollment_review'=>'PHYSICIAN_ENROLLMENT_REVIEW','c4_evidence'=>'PHYSICIAN_EVIDENCE_REVIEW',
            'professional_source_provisioning'=>'PROFESSIONAL_SOURCE_PROVISION'];
        $issuerStaff=false;
        foreach($a['staff'] as $account=>$caps){
            if(!in_array($account,$a['accounts'],true)||!is_array($caps))throw new \RuntimeException('act02_staff_scope_required');
            foreach($caps as $cap){if(!in_array($cap,$required,true))throw new \RuntimeException('act02_unrelated_staff_capability');
                $q=$pdo->prepare("SELECT s.governance_class FROM internal_staff s JOIN auth_accounts a ON a.account_id=s.account_id JOIN auth_account_credentials c ON c.account_id=s.account_id JOIN internal_operator_grants g ON g.account_id=s.account_id WHERE s.account_id=? AND s.status='ACTIVE' AND a.status='active' AND a.email_verified_at IS NOT NULL AND c.credential_version>0 AND g.capability=? AND g.status='ACTIVE' AND g.revoked_at IS NULL AND EXISTS(SELECT 1 FROM auth_staff_mfa_factors f WHERE f.account_id=s.account_id AND f.status='ACTIVE')");$q->execute([$account,$cap]);$classes=$q->fetchAll(PDO::FETCH_COLUMN);
                if(count($classes)!==1)throw new \RuntimeException('act02_staff_capability_or_MFA_missing');
                $governance=in_array($classes[0],['DIRECTOR','MASTER_ADMIN'],true);$routine=false;
                if($classes[0]==='ADVISOR')foreach($advisorOperations as $workflow=>$operation)
                    if(in_array($workflow,$a['workflows'],true)&&StaffRoutineOperationPolicy::capability($operation)===$cap)$routine=true;
                if(!$governance&&!$routine)throw new \RuntimeException('act02_staff_capability_or_MFA_missing');
                if($governance&&$cap==='physician_accreditation_review')$issuerStaff=true;
            }
        }
        foreach($required as $cap){if(!array_filter($a['staff'],fn($caps)=>in_array($cap,$caps,true)))throw new \RuntimeException('act02_staff_coverage_missing');}
        // Routine review delegation does not authorize professional authority lifecycle changes.
        if(in_array('c4_issuer',$a['workflows'],true)&&!$issuerStaff)throw new \RuntimeException('act02_staff_coverage_missing');
        $grants=$pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
        foreach($grants as $g){
            $privileges=explode(' ON ',$g,2)[0];
            $sourceInsert=in_array('professional_source_provisioning',$a['workflows'],true)&&preg_match('/^GRANT (?:SELECT, INSERT|INSERT) ON `'.preg_quote($actual['db'],'/').'`\.`(?:profiles_verified_identities|profiles_doctor_credentials)` TO /',$g);
            if(!str_contains($g,' ON ')||preg_match('/ALL PRIVILEGES|GRANT OPTION|CREATE |ALTER|DROP|SUPER|SYSTEM_USER|ROLE |TRIGGER|PROXY/i',$privileges)||str_contains($g,'WITH GRANT OPTION')||(!str_contains($g,'GRANT USAGE ON *.*')&&str_contains($g,' ON *.*'))
                ||(!$sourceInsert&&preg_match('/(?:INSERT|UPDATE|DELETE).* ON `[^`]+`\.\*/i',$g))
                ||(!$sourceInsert&&preg_match('/(?:INSERT|UPDATE|DELETE).* ON `[^`]+`\.`(?:internal_staff|internal_operator_grants|clinical_[^`]+|auth_account_memberships|profiles_verified_identities|profiles_doctor_credentials|profiles_doctors|profile_subscriptions)`/i',$g)))throw new \RuntimeException('act02_runtime_privileges_too_broad');
        }
        return $a;
    }
}
