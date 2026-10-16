<?php
declare(strict_types=1);
namespace Identity\Http;
use Identity\Operators\OperatorDeploymentSchema;
use PDO;

/** A deployment approval is not an actor, a grant, or an external trust root. */
final class GovernanceDeploymentAuthorization
{
    public static function require(PDO $pdo,string $action,?string $target=null,string $environment='local'):array
    {
        $file=(string)getenv('MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION');
        $root=realpath(dirname(__DIR__,3));$parent=realpath(dirname($file));
        if($file===''||$parent===false||!is_file($file)||is_link($file)||is_link(dirname($file))
            ||(fileperms($file)&0077)!==0||(fileperms($parent)&0077)!==0||str_starts_with($parent,$root))throw new \RuntimeException('governance_deployment_authorization_required');
        $bytes=file_get_contents($file);$pin=(string)getenv('MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION_SHA256');
        if(!preg_match('/^[a-f0-9]{64}$/D',$pin)||!hash_equals($pin,hash('sha256',$bytes)))throw new \RuntimeException('governance_deployment_authorization_drift');
        $r=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
        if(($r['contract_version']??null)!==2||($r['state']??null)!=='approved_for_governance'
            ||!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$r['authorization_reference']??'')
            ||!in_array($action,$r['allowed_actions']??[],true)
            ||($target!==null&&!in_array($target,$r['target_account_ids']??[],true)))throw new \RuntimeException('governance_operation_not_approved');
        $expires=new \DateTimeImmutable($r['expires_at']??'1970-01-01T00:00:00Z');$now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
        if($expires<=$now||$expires>$now->modify('+24 hours'))throw new \RuntimeException('governance_deployment_authorization_expired');
        foreach(OperatorDeploymentSchema::identity($pdo) as $k=>$v)if(($r[$k]??null)!==$v)throw new \RuntimeException('governance_deployment_target_mismatch');
        if(($r['schema_fingerprint']??null)!==OperatorDeploymentSchema::fingerprint($pdo)||($r['source_hash']??null)!==self::sourceHash())throw new \RuntimeException('governance_deployment_source_or_schema_drift');
        if(($r['organization_id']??null)!==getenv('MXMED_GOVERNANCE_ORGANIZATION_ID')||($r['environment']??null)!==$environment
            ||($r['trust_key_sha256']??null)!==hash('sha256',(string)getenv('MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY'))
            ||($r['mfa_key_id']??null)!==getenv('MXMED_STAFF_MFA_KEY_ID'))throw new \RuntimeException('governance_deployment_crypto_binding_mismatch');
        if(($r['mfa_key_sha256']??null)!==hash('sha256',(string)getenv('MXMED_STAFF_MFA_ENCRYPTION_KEY')))throw new \RuntimeException('governance_mfa_key_rotation_not_approved');
        return $r;
    }
    public static function sourceHash():string
    {
        $root=dirname(__DIR__,3);$files=[];
        foreach(['services','adapters','contracts','http','repositories','Accreditation'] as $dir)$files=array_merge($files,glob($root.'/modules/identity/'.$dir.'/*.php'));
        foreach(['api/identity/governance.php','scripts/governance-ceremony.php','scripts/normal-local-https-proxy.py',
            'modules/identity/db/migrations/2026_10_16_01_governance_security_bindings.sql',
            'modules/identity/db/migrations/2026_10_16_02_physician_account_binding.sql',
            'modules/identity/db/migrations/2026_10_16_03_physician_evidence_foundation.sql',
            'modules/identity/db/migrations/2026_10_16_04_physician_authority_issuer.sql',
            'api/identity/physician-onboarding.php','public/identity/perfil-medico.php',
            'partials/identity/physician-onboarding.php','assets/js/identity/physician-onboarding.js',
            'assets/css/physician-onboarding.css','public/identity/identity-access.js','internal/media-review/index.php',
            'modules/media/storage/LocalPersistentPrivateMediaStorage.php','modules/media/contracts/PrivateMediaStoragePort.php',
            'modules/platform/services/CanonicalAuditPolicyRegistry.php','modules/platform/contracts/CanonicalAuditEventType.php',
            'modules/platform/services/SourceModuleCatalog.php','modules/platform/services/CorrelatableOperationCatalog.php'] as $path)$files[]=$root.'/'.$path;
        sort($files);$hash=[];foreach($files as $f)$hash[substr($f,strlen($root)+1)]=hash_file('sha256',$f);
        return hash('sha256',json_encode($hash,JSON_THROW_ON_ERROR));
    }
    public static function assertSchema(PDO $pdo):void
    {
        foreach(['auth_governance_cases'=>'security_context','auth_staff_mfa_factors'=>'enrollment_context',
            'auth_staff_mfa_challenges'=>'reauthentication_context','auth_external_trust_anchor_evidence'=>'contract_version'] as $table=>$column){
            $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);
            if((int)$s->fetchColumn()!==1)throw new \RuntimeException('governance_v2_schema_not_installed');
        }
        foreach(['operation','profile_doctor_id','membership_id','membership_version','requester_account_id'] as $column){$s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='auth_external_trust_anchor_evidence' AND COLUMN_NAME=?");$s->execute([$column]);if((int)$s->fetchColumn()!==1)throw new \RuntimeException('governance_v2_schema_not_installed');}
    }
}
