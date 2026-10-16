<?php
declare(strict_types=1);
namespace Identity\Operators;
use PDO;
/** A private, hash-pinned deployment receipt is an operational gate, never actor authority. */
final class OperatorDeploymentConfiguration
{
    private function __construct(public readonly array $receipt){}
    public static function requested():bool{return getenv('MXMED_OPERATORS_RUNTIME_ENABLED')==='1';}
    public static function fromProcess(PDO $pdo):self
    {
        if(!self::requested()||(PHP_SAPI!=='cli'&&(!defined('MXMED_OPERATOR_BOUNDARY_ENFORCED')||MXMED_OPERATOR_BOUNDARY_ENFORCED!==true)))throw new \RuntimeException('operator_activation_pending');
        $file=(string)getenv('MXMED_OPERATOR_DEPLOYMENT_RECEIPT');$root=realpath(dirname(__DIR__,3));$parent=realpath(dirname($file));
        if($file===''||$parent===false||!is_file($file)||is_link($file)||is_link(dirname($file))||(fileperms($file)&0077)!==0||($root&&str_starts_with($parent,$root)))throw new \RuntimeException('operator_private_deployment_receipt_required');
        $bytes=file_get_contents($file);$pin=(string)getenv('MXMED_OPERATOR_DEPLOYMENT_RECEIPT_SHA256');
        if(!preg_match('/^[a-f0-9]{64}$/D',$pin)||!hash_equals($pin,hash('sha256',$bytes)))throw new \RuntimeException('operator_deployment_receipt_drift');
        $r=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($r)||($r['state']??'')!=='ready_for_runtime'||($r['http_boundary_approved']??false)!==true||($r['mutations_approved']??false)!==true||!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$r['authorization_reference']??''))throw new \RuntimeException('operator_deployment_not_approved');
        $self=new self($r);$self->assertConnection($pdo);return $self;
    }
    public function assertConnection(PDO $pdo):void
    {
        $actual=OperatorDeploymentSchema::identity($pdo);
        foreach(['db','server_uuid','server_version'] as $k)if(($this->receipt[$k]??null)!==$actual[$k])throw new \RuntimeException('operator_deployment_target_mismatch');
        if(($this->receipt['runtime_db_user']??null)!==$actual['db_user']||!preg_match('/^mxmed(?:_operator_deploy_[a-f0-9]{10})?$/D',$actual['db']))throw new \RuntimeException('operator_deployment_database_identity_denied');
        if(($this->receipt['package_hash']??null)!==OperatorDeploymentSchema::packageHash()||($this->receipt['source_hash']??null)!==OperatorDeploymentSchema::sourceHash()||($this->receipt['schema_fingerprint']??null)!==OperatorDeploymentSchema::fingerprint($pdo))throw new \RuntimeException('operator_deployment_schema_or_source_drift');
        OperatorInvitationOrigin::assertPublic((string)($this->receipt['public_invitation_origin']??''));
        OperatorDeploymentSchema::assertRequired($pdo);
        foreach(['auth_profile_ownership_approval_endorsements','auth_governance_cases','auth_governance_case_endorsements','auth_staff_mfa_factors','auth_staff_mfa_challenges','auth_staff_mfa_proofs','auth_external_trust_anchor_evidence','auth_governance_events'] as $table){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$table]);if((int)$s->fetchColumn()!==1)throw new \RuntimeException('operator_governance_schema_required');}
        $target=\Platform\Repositories\CanonicalAuditDatabaseTarget::fromConnection($pdo);foreach(['audit_mp01c_lock_stream_head_v1','audit_mp01c_advance_stream_head_cas_v1'] as $routine)$target->routine($pdo,$routine);
    }
    public function deliveryKey():string
    {$key=base64_decode((string)getenv('MXMED_OPERATOR_DELIVERY_KEY'),true);if(!is_string($key)||strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new \RuntimeException('operator_delivery_key_required');return $key;}
    public function deliveryKeyId():string{return (string)($this->receipt['delivery_key_id']??'');}
}
